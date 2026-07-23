<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Cache;

class FakeOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $otp): bool
    {
        // Lưu OTP vào Cache để automated test đọc trực tiếp
        Cache::put('test_otp_'.$phone, $otp, now()->addMinutes(5));

        return true;
    }
}
