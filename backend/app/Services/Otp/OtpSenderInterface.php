<?php

namespace App\Services\Otp;

interface OtpSenderInterface
{
    /**
     * Gửi mã OTP đến số điện thoại người dùng.
     *
     * @throws \RuntimeException
     */
    public function send(string $phone, string $otp): bool;
}
