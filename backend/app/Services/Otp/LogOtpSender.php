<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class LogOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $otp): bool
    {
        // Chỉ ghi OTP vào log trong môi trường local
        if (app()->environment('local')) {
            Log::info("OTP sent to {$phone}: {$otp}");

            return true;
        }

        throw new RuntimeException('LogOtpSender chỉ được phép hoạt động ở môi trường local.');
    }
}
