<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAccountService
{
    /**
     * Find, link, or create a user from a verified Google identity.
     * Privileged accounts may only sign in when this exact Google subject was linked before promotion.
     *
     * @param  array<string, mixed>  $identity
     */
    public function resolve(array $identity): User
    {
        $googleId = trim((string) ($identity['sub'] ?? $identity['id'] ?? ''));
        $email = Str::lower(trim((string) ($identity['email'] ?? '')));
        $emailVerified = filter_var($identity['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if ($googleId === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'google' => 'Google did not return a valid account identity. Please try again.',
            ]);
        }

        if (! $emailVerified) {
            throw ValidationException::withMessages([
                'google' => 'Only Google accounts with a verified email address can sign in.',
            ]);
        }

        return DB::transaction(function () use ($identity, $googleId, $email): User {
            $user = User::query()->where('google_id', $googleId)->first();
            $wasAlreadyLinked = $user !== null;

            if (! $user) {
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();
            }

            if ($user && $user->role !== 'customer' && ! $wasAlreadyLinked && ! $user->google_link_pending) {
                throw ValidationException::withMessages([
                    'google' => 'This privileged account is not linked to this Google identity. Use the account password or ask a Master Admin for help.',
                ]);
            }

            if ($user && $user->google_id && ! hash_equals((string) $user->google_id, $googleId)) {
                throw ValidationException::withMessages([
                    'google' => 'This email is already linked to a different Google account.',
                ]);
            }

            $name = trim((string) ($identity['name'] ?? '')) ?: Str::before($email, '@');
            $avatar = trim((string) ($identity['picture'] ?? $identity['avatar'] ?? '')) ?: null;

            if (! $user) {
                return User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'google_avatar_url' => $avatar,
                    'email_verified_at' => now(),
                    'gender' => 'prefer_not_to_say',
                    'role' => 'customer',
                    'password' => Hash::make(Str::random(64)),
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'google_link_pending' => false,
                'google_avatar_url' => $avatar ?: $user->google_avatar_url,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            return $user->refresh();
        });
    }

    public function needsProfileCompletion(User $user): bool
    {
        return blank($user->phone) || blank($user->birth_date);
    }
}
