<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Otp\OtpSenderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PhoneAuthController extends Controller
{
    /**
     * Chuẩn hóa số điện thoại về định dạng 10 chữ số chuẩn.
     */
    protected function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '84') && strlen($clean) === 11) {
            $clean = '0'.substr($clean, 2);
        }

        return $clean;
    }

    /**
     * Gửi mã OTP an toàn đến số điện thoại.
     */
    public function sendOtp(Request $request, OtpSenderInterface $otpSender): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
        ]);

        $phone = $this->normalizePhone($request->phone);
        $ip = $request->ip();

        if (! preg_match('/^0[35789]\d{8}$/', $phone)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Số điện thoại không đúng định dạng 10 chữ số tại Việt Nam.',
            ], 422);
        }

        // Rate Limiting riêng theo SĐT và theo IP Address (60 giây)
        $phoneRateKey = 'otp_rate_phone_'.$phone;
        $ipRateKey = 'otp_rate_ip_'.$ip;

        if (Cache::has($phoneRateKey) || Cache::has($ipRateKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng chờ 60 giây trước khi yêu cầu mã OTP mới.',
            ], 429);
        }

        // Sinh OTP ngẫu nhiên mật mã (6 chữ số)
        $otp = (string) random_int(100000, 999999);

        // Gửi OTP qua dịch vụ OtpSender (Fail-closed ở production nếu thiếu provider, không ghi log)
        try {
            $otpSender->send($phone, $otp);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Lưu OTP dạng hash bảo mật vào Cache trong 5 phút
        Cache::put('otp_'.$phone, Hash::make($otp), now()->addMinutes(5));
        Cache::put($phoneRateKey, true, now()->addSeconds(60));
        Cache::put($ipRateKey, true, now()->addSeconds(60));
        Cache::forget('otp_attempts_phone_'.$phone);
        Cache::forget('otp_attempts_ip_'.$ip);

        return response()->json([
            'status' => 'success',
            'message' => 'Mã OTP đã được gửi thành công.',
        ]);
    }

    /**
     * Xác thực mã OTP số điện thoại.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'otp.required' => 'Vui lòng nhập mã OTP.',
        ]);

        $phone = $this->normalizePhone($request->phone);
        $otp = trim($request->otp);
        $ip = $request->ip();

        if (! preg_match('/^0[35789]\d{8}$/', $phone)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Số điện thoại không hợp lệ.',
            ], 422);
        }

        if (! preg_match('/^\d{6}$/', $otp)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP phải có đúng 6 chữ số.',
            ], 422);
        }

        $phoneAttemptKey = 'otp_attempts_phone_'.$phone;
        $ipAttemptKey = 'otp_attempts_ip_'.$ip;

        $phoneAttempts = (int) Cache::get($phoneAttemptKey, 0);
        $ipAttempts = (int) Cache::get($ipAttemptKey, 0);

        if ($phoneAttempts >= 5 || $ipAttempts >= 10) {
            Cache::forget('otp_'.$phone);

            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP đã bị khóa do thử sai quá số lần cho phép. Vui lòng gửi lại OTP mới.',
            ], 429);
        }

        $hashedOtp = Cache::get('otp_'.$phone);
        $isValid = false;

        if ($hashedOtp && Hash::check($otp, $hashedOtp)) {
            $isValid = true;
        }

        if (! $isValid) {
            Cache::put($phoneAttemptKey, $phoneAttempts + 1, now()->addMinutes(5));
            Cache::put($ipAttemptKey, $ipAttempts + 1, now()->addMinutes(5));

            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP không chính xác hoặc đã hết hạn.',
                'errors' => [
                    'otp' => ['Mã OTP không chính xác hoặc đã hết hạn.'],
                ],
            ], 422);
        }

        // Dọn dẹp OTP và số lần thử sau khi verify thành công.
        // Giữ nguyên rate limit cooldown flag (không xóa otp_rate_) để tránh spam resend.
        Cache::forget('otp_'.$phone);
        Cache::forget('test_otp_'.$phone);
        Cache::forget($phoneAttemptKey);
        Cache::forget($ipAttemptKey);

        $user = User::where('phone', $phone)->first();

        if ($user) {
            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $user->load(['vendor', 'membershipTier', 'author']);

            return response()->json([
                'status' => 'success',
                'message' => 'Xác thực số điện thoại thành công.',
                'data' => [
                    'user' => new UserResource($user),
                ],
            ]);
        }

        // Số điện thoại chưa liên kết tài khoản nào -> Trả về để hoàn thiện đăng ký
        return response()->json([
            'status' => 'needs_registration',
            'message' => 'Số điện thoại hợp lệ. Vui lòng hoàn tất thông tin đăng ký.',
            'data' => [
                'phone' => $phone,
            ],
        ]);
    }
}
