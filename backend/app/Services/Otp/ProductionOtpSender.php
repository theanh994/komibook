<?php

namespace App\Services\Otp;

use RuntimeException;

class ProductionOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $otp): bool
    {
        $provider = config('services.sms.provider');

        if (empty($provider)) {
            // Fail closed: Không cấu hình SMS provider thì báo lỗi trực tiếp, tuyệt đối không ghi OTP ra log
            throw new RuntimeException('Hệ thống chưa được cấu hình dịch vụ SMS Gateway provider.');
        }

        // Hiện chưa có adapter SMS Gateway thực tế nào được tích hợp
        // Phải ném ngoại lệ fail closed chứ tuyệt đối không trả true khống.
        throw new RuntimeException("SMS Provider [{$provider}] chưa được hỗ trợ adapter gửi SMS thực tế.");
    }
}
