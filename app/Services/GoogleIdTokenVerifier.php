<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Validation\ValidationException;

class GoogleIdTokenVerifier
{
    /**
     * Verify a Google ID token and return its trusted claims.
     *
     * @return array<string, mixed>
     */
    public function verify(string $idToken): array
    {
        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in is not configured on the server yet.',
            ]);
        }

        $client = new GoogleClient(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($idToken);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'google' => 'Google could not verify this sign-in. Please choose your account again.',
            ]);
        }

        return $payload;
    }
}
