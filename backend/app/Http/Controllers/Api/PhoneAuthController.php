<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PhoneAuthController extends Controller
{
    /**
     * Gửi mã OTP giả lập đến số điện thoại.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^(0[3|5|7|8|9])+([0-9]{8})$/'],
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex'    => 'Số điện thoại không đúng định dạng Việt Nam.',
        ]);

        $phone = $request->phone;
        
        // Sinh mã OTP 6 chữ số ngẫu nhiên
        $otp = (string) rand(100000, 999999);
        
        // Lưu OTP vào Cache trong 5 phút
        Cache::put('otp_' . $phone, $otp, now()->addMinutes(5));

        // Trả về kết quả (ở môi trường local/debug sẽ trả về kèm mã OTP để dễ test)
        $data = [];
        if (config('app.env') === 'local' || config('app.debug')) {
            $data['otp'] = $otp;
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Mã OTP đã được gửi thành công.',
            'data'    => $data
        ]);
    }

    /**
     * Xác thực mã OTP số điện thoại.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'size:6'],
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'otp.required'   => 'Vui lòng nhập mã OTP.',
            'otp.size'       => 'Mã OTP phải có đúng 6 chữ số.',
        ]);

        $phone = $request->phone;
        $otp = $request->otp;

        $cachedOtp = Cache::get('otp_' . $phone);

        // Chấp nhận OTP từ Cache hoặc mã mặc định '123456' để dễ test
        if ($otp !== $cachedOtp && $otp !== '123456') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Mã OTP không chính xác hoặc đã hết hạn.',
                'errors'  => [
                    'otp' => ['Mã OTP không chính xác hoặc đã hết hạn.']
                ]
            ], 422);
        }

        // Dọn dẹp Cache sau khi verify thành công
        Cache::forget('otp_' . $phone);

        $user = User::where('phone', $phone)->first();

        if ($user) {
            // Đăng nhập trực tiếp cho tài khoản đã có sẵn
            $user->tokens()->delete();
            $user->load(['vendor', 'membershipTier', 'author']);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status'  => 'success',
                'message' => 'Xác thực số điện thoại thành công.',
                'data'    => [
                    'user'         => new UserResource($user),
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                ],
            ]);
        }

        // Số điện thoại chưa liên kết tài khoản nào -> Trả về để frontend mở form hoàn thiện đăng ký
        return response()->json([
            'status'  => 'needs_registration',
            'message' => 'Số điện thoại hợp lệ. Vui lòng hoàn tất thông tin đăng ký.',
            'data'    => [
                'phone' => $phone,
            ]
        ]);
    }
}
