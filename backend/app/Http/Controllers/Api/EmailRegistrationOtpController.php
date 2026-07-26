<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\RegistrationEmailOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class EmailRegistrationOtpController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $emailHash = hash('sha256', $email);
        $ipHash = hash('sha256', (string) $request->ip());
        $emailRateKey = 'registration_email_otp_rate_email_'.$emailHash;
        $ipRateKey = 'registration_email_otp_rate_ip_'.$ipHash;

        if (Cache::has($emailRateKey) || Cache::has($ipRateKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng chờ 60 giây trước khi yêu cầu mã OTP mới.',
            ], 429);
        }

        $otp = (string) random_int(10000000, 99999999);

        try {
            Notification::route('mail', $email)->notify(new RegistrationEmailOtp($otp));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gửi email OTP lúc này. Vui lòng thử lại sau.',
            ], 500);
        }

        Cache::put('registration_email_otp_'.$emailHash, Hash::make($otp), now()->addMinutes(5));
        Cache::put($emailRateKey, true, now()->addSeconds(60));
        Cache::put($ipRateKey, true, now()->addSeconds(60));
        Cache::forget('registration_email_otp_attempts_email_'.$emailHash);
        Cache::forget('registration_email_otp_attempts_ip_'.$ipHash);

        return response()->json([
            'status' => 'success',
            'message' => 'Mã OTP đã được gửi đến email của bạn.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp' => ['required', 'string', 'regex:/^\d{8}$/'],
        ], [
            'otp.regex' => 'Mã OTP phải có đúng 8 chữ số.',
        ]);

        $email = Str::lower(trim($validated['email']));
        $emailHash = hash('sha256', $email);
        $ipHash = hash('sha256', (string) $request->ip());
        $emailAttemptKey = 'registration_email_otp_attempts_email_'.$emailHash;
        $ipAttemptKey = 'registration_email_otp_attempts_ip_'.$ipHash;
        $emailAttempts = (int) Cache::get($emailAttemptKey, 0);
        $ipAttempts = (int) Cache::get($ipAttemptKey, 0);

        if ($emailAttempts >= 5 || $ipAttempts >= 10) {
            Cache::forget('registration_email_otp_'.$emailHash);

            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP đã bị khóa do nhập sai quá số lần cho phép. Vui lòng gửi mã mới.',
            ], 429);
        }

        $hashedOtp = Cache::get('registration_email_otp_'.$emailHash);
        if (! $hashedOtp || ! Hash::check($validated['otp'], $hashedOtp)) {
            Cache::put($emailAttemptKey, $emailAttempts + 1, now()->addMinutes(5));
            Cache::put($ipAttemptKey, $ipAttempts + 1, now()->addMinutes(5));

            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP không chính xác hoặc đã hết hạn.',
                'errors' => ['otp' => ['Mã OTP không chính xác hoặc đã hết hạn.']],
            ], 422);
        }

        Cache::forget('registration_email_otp_'.$emailHash);
        Cache::forget($emailAttemptKey);
        Cache::forget($ipAttemptKey);

        $verificationToken = (string) Str::uuid();
        Cache::put(
            'registration_email_verified_'.$verificationToken,
            $email,
            now()->addMinutes(10)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Email đã được xác thực.',
            'data' => ['verification_token' => $verificationToken],
        ]);
    }
}
