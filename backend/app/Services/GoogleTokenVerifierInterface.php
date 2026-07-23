<?php

namespace App\Services;

interface GoogleTokenVerifierInterface
{
    /**
     * Xác minh Google ID Token và trả về payload đã kiểm tra đầy đủ.
     *
     * @return array{sub: string, email: string, name: ?string, aud: string, iss: string, email_verified: bool, exp: int}
     *
     * @throws \InvalidArgumentException
     */
    public function verify(string $idToken): array;
}
