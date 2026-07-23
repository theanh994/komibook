<?php

namespace App\Services;

use InvalidArgumentException;

class FakeGoogleTokenVerifier implements GoogleTokenVerifierInterface
{
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');

        if (empty($clientId)) {
            throw new InvalidArgumentException('Google Client ID chưa được cấu hình.');
        }

        if (str_starts_with($idToken, 'invalid_')) {
            throw new InvalidArgumentException('Token Google không hợp lệ.');
        }

        return [
            'sub' => 'google_123456789',
            'email' => 'googleuser@gmail.com',
            'name' => 'Google User',
            'aud' => $clientId,
            'iss' => 'https://accounts.google.com',
            'email_verified' => true,
            'exp' => time() + 3600,
        ];
    }
}
