<?php

namespace App\Services;

use InvalidArgumentException;

class FakeFacebookTokenVerifier implements FacebookTokenVerifierInterface
{
    public function verify(string $accessToken): array
    {
        if (
            empty(config('services.facebook.app_id'))
            || empty(config('services.facebook.app_secret'))
            || empty(config('services.facebook.graph_version'))
        ) {
            throw new InvalidArgumentException('Cấu hình Facebook Login chưa đầy đủ.');
        }

        if (str_starts_with($accessToken, 'invalid_')) {
            throw new InvalidArgumentException('Token Facebook không hợp lệ.');
        }

        return [
            'id' => 'facebook_123456789',
            'email' => 'facebookuser@example.com',
            'name' => 'Facebook User',
        ];
    }
}
