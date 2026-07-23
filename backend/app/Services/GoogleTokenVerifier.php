<?php

namespace App\Services;

use Google\Client as GoogleClient;
use InvalidArgumentException;

class GoogleTokenVerifier implements GoogleTokenVerifierInterface
{
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');

        if (empty($clientId)) {
            throw new InvalidArgumentException('Google Client ID chưa được cấu hình.');
        }

        $client = new GoogleClient(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($idToken);

        if (! $payload || ! is_array($payload)) {
            throw new InvalidArgumentException('Token xác minh tài khoản Google không hợp lệ hoặc đã hết hạn.');
        }

        // 1. Kiểm tra sub
        if (empty($payload['sub'])) {
            throw new InvalidArgumentException('Token Google thiếu thông tin định danh sub.');
        }

        // 2. Kiểm tra email
        if (empty($payload['email'])) {
            throw new InvalidArgumentException('Token Google thiếu thông tin email.');
        }

        // 3. Kiểm tra audience
        if (! isset($payload['aud']) || $payload['aud'] !== $clientId) {
            throw new InvalidArgumentException('Token Google không đúng Audience ứng dụng.');
        }

        // 4. Kiểm tra issuer
        $iss = $payload['iss'] ?? '';
        if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
            throw new InvalidArgumentException('Nguồn phát hành Token (Issuer) không phải từ Google.');
        }

        // 5. Kiểm tra email_verified
        $emailVerified = $payload['email_verified'] ?? false;
        if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== 1) {
            throw new InvalidArgumentException('Tài khoản Google chưa được xác minh email.');
        }

        // 6. Kiểm tra expiry
        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < time()) {
            throw new InvalidArgumentException('Token xác minh Google đã hết hạn.');
        }

        return [
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'name' => $payload['name'] ?? '',
            'aud' => $payload['aud'],
            'iss' => $payload['iss'],
            'email_verified' => true,
            'exp' => $exp,
        ];
    }
}
