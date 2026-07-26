<?php

namespace App\Services;

interface FacebookTokenVerifierInterface
{
    /**
     * Xác minh Facebook access token và trả về hồ sơ thuộc đúng Meta App.
     *
     * @return array{id: string, email: ?string, name: string}
     *
     * @throws \InvalidArgumentException
     */
    public function verify(string $accessToken): array;
}
