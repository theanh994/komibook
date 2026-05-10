<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ─── Register ─────────────────────────────────────────────────────────────

    /**
     * Đăng ký tài khoản mới.
     *
     * - Tạo user với role mặc định 'customer'.
     * - Tạo Sanctum API token và trả về kèm UserResource.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password, // Model tự hash qua cast 'hashed'
            'role'     => 'customer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Đăng ký thành công.',
            'data'    => [
                'user'         => new UserResource($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 201);
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    /**
     * Đăng nhập.
     *
     * - Xác thực credentials bằng Auth::attempt().
     * - Xóa tất cả token cũ (single-device policy) trước khi cấp token mới.
     * - Load thêm vendor relation nếu user là vendor.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email hoặc mật khẩu không chính xác.',
                'data'    => null,
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Single-device policy: xóa tất cả token cũ trước khi cấp mới
        $user->tokens()->delete();

        // Eager load vendor nếu cần để UserResource không tạo thêm query
        if ($user->isVendor()) {
            $user->load('vendor');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Đăng nhập thành công.',
            'data'    => [
                'user'         => new UserResource($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    /**
     * Đăng xuất.
     *
     * - Thu hồi (delete) token hiện tại của user — không ảnh hưởng token khác.
     */
    public function logout(Request $request): JsonResponse
    {
        // Support either token or session based authentication
        if ($request->user()->currentAccessToken() && method_exists($request->user()->currentAccessToken(), 'delete')) {
            $request->user()->currentAccessToken()->delete();
        }

        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status'  => 'success',
            'message' => 'Đăng xuất thành công.',
            'data'    => null,
        ]);
    }

    // ─── Me ───────────────────────────────────────────────────────────────────

    /**
     * Lấy thông tin user đang đăng nhập.
     *
     * - Load thêm vendor relation nếu user là vendor.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isVendor()) {
            $user->load('vendor');
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Lấy thông tin người dùng thành công.',
            'data'    => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    /**
     * Gửi liên kết đặt lại mật khẩu.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['status' => 'success', 'message' => 'Liên kết đặt lại mật khẩu đã được gửi.'])
            : response()->json(['status' => 'error', 'message' => __($status)], 400);
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    /**
     * Đặt lại mật khẩu mới.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['status' => 'success', 'message' => __($status)])
            : response()->json(['status' => 'error', 'message' => __($status)], 400);
    }
}
