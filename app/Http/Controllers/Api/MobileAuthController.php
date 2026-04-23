<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['male', 'female', 'non_binary', 'prefer_not_to_say'])],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'birth_date' => $validated['birth_date'],
            'gender' => $validated['gender'],
            'address_line' => $validated['address_line'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'postal_code' => $validated['postal_code'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        return response()->json([
            'message' => "Account created successfully. Welcome to McDonald's Reservations.",
            'token' => $this->issueToken($user),
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        return response()->json([
            'message' => 'Signed in successfully.',
            'token' => $this->issueToken($user),
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }

    protected function issueToken(User $user): string
    {
        $user->tokens()->where('name', 'expo-mobile')->delete();

        return $user->createToken('expo-mobile')->plainTextToken;
    }

    protected function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'birth_date' => $user->birth_date?->toDateString(),
            'gender' => $user->gender,
            'address_line' => $user->address_line,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
            'full_address' => collect([
                $user->address_line,
                $user->city,
                $user->province,
                $user->postal_code,
            ])->filter()->implode(', '),
        ];
    }
}
