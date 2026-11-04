<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless($this->isConfigured(), 503, 'Google sign-in is not configured yet.');

        return Socialite::driver('google')->redirect();
    }

    public function callback(GoogleAccountService $accounts): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured yet.',
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $raw = $googleUser->getRaw();

            $user = $accounts->resolve([
                'sub' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'email_verified' => $raw['email_verified'] ?? $raw['verified_email'] ?? false,
                'name' => $googleUser->getName(),
                'picture' => $googleUser->getAvatar(),
            ]);

            Auth::login($user, true);
            request()->session()->regenerate();

            if ($accounts->needsProfileCompletion($user)) {
                return redirect()->route('profile.edit')->with(
                    'success',
                    'Google sign-in succeeded. Add your phone number and birth date before booking.'
                );
            }

            return redirect()->intended(route('dashboard'));
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        } catch (Throwable $exception) {
            Log::warning('Google web sign-in failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in could not be completed. Please try again.',
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
