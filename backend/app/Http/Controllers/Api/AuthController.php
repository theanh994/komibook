<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Author;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GoogleTokenVerifierInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ─── Register ─────────────────────────────────────────────────────────────

    /**
     * Đăng ký tài khoản mới.
     *
     * - Nếu có challenge_token từ Google: Lấy email và google_id từ Cache đã xác minh.
     * - Tạo user với role mặc định 'customer'.
     * - Đăng nhập Session cookie cho SPA. Không phát hành Bearer access_token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $email = $request->email;
        $googleId = null;

        // Bảo mật AUTH-01: Không cho phép client gửi google_id trực tiếp mà không qua challenge_token
        if ($request->filled('google_id') && ! $request->filled('challenge_token')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tự khai báo google_id trực tiếp. Vui lòng xác thực qua Google.',
            ], 422);
        }

        // Nếu có challenge_token từ Google authentication
        if ($request->filled('challenge_token')) {
            $googleData = Cache::pull('google_challenge_'.$request->challenge_token);
            if (! $googleData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mã xác thực Google (challenge_token) không hợp lệ hoặc đã hết hạn.',
                ], 422);
            }

            $email = $googleData['email'];
            $googleId = $googleData['google_id'];
        }

        // Thực hiện trong Database Transaction và kiểm tra lại duy nhất email/google_id
        $user = DB::transaction(function () use ($request, $email, $googleId) {
            if ($googleId && User::where('google_id', $googleId)->exists()) {
                throw new \InvalidArgumentException('Tài khoản Google này đã được liên kết với người dùng khác.');
            }
            if ($email && User::where('email', $email)->exists()) {
                throw new \InvalidArgumentException('Email này đã được đăng ký trên hệ thống.');
            }

            $newUser = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => $request->password,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birthday' => $request->birthday,
                'google_id' => $googleId,
                'role' => 'customer',
            ]);

            if ($request->desired_role === 'author') {
                Author::create([
                    'user_id' => $newUser->id,
                    'pen_name' => $newUser->name,
                    'bank_account_number' => 'Pending',
                    'bank_name' => 'Pending',
                    'bank_holder_name' => strtoupper($newUser->name),
                    'identity_document' => 'Pending',
                    'status' => 'pending',
                ]);
            } elseif ($request->desired_role === 'vendor') {
                Vendor::withoutGlobalScopes()->create([
                    'user_id' => $newUser->id,
                    'shop_name' => 'Shop '.$newUser->name,
                    'slug' => Str::slug('Shop '.$newUser->name.'-'.rand(1000, 9999)),
                    'description' => '',
                    'status' => 'pending',
                ]);
            }

            return $newUser;
        });

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký thành công.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    /**
     * Đăng nhập.
     *
     * - Xác thực credentials bằng Auth::attempt().
     * - Đăng nhập Session cookie cho SPA và regenerate session. Không trả Bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $credentials = [
            $loginField => $request->email,
            'password' => $request->password,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản hoặc mật khẩu không chính xác.',
                'data' => null,
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Single-device policy clean up tokens nếu có token cũ
        $user->tokens()->delete();

        $user->load(['vendor', 'membershipTier', 'author', 'favoriteCategories']);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    /**
     * Đăng xuất.
     *
     * - Invalidate Session và regenerate CSRF token cho SPA.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user() && $request->user()->currentAccessToken() && method_exists($request->user()->currentAccessToken(), 'delete')) {
            $request->user()->currentAccessToken()->delete();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng xuất thành công.',
            'data' => null,
        ]);
    }

    // ─── Me ───────────────────────────────────────────────────────────────────

    /**
     * Lấy thông tin user đang đăng nhập.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load(['vendor', 'membershipTier', 'author', 'favoriteCategories']);

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy thông tin người dùng thành công.',
            'data' => [
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

    // ─── Google Login ─────────────────────────────────────────────────────────

    /**
     * Đăng nhập hoặc đăng ký bằng tài khoản Google (Dùng Verifier Abstraction & Registration Challenge).
     */
    public function googleLogin(Request $request, GoogleTokenVerifierInterface $verifier): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string',
        ], [
            'id_token.required' => 'Yêu cầu Token xác minh Google (id_token).',
        ]);

        // Cấu hình Fail Closed ở mọi môi trường nếu chưa cài Client ID
        $clientId = config('services.google.client_id');
        if (empty($clientId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hệ thống chưa được cấu hình Google Client ID.',
            ], 500);
        }

        try {
            $payload = $verifier->verify($request->id_token);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $email = $payload['email'];
        $name = $payload['name'] ?? '';
        $googleId = $payload['sub'];

        // Tìm kiếm user theo google_id hoặc email
        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            // Liên kết google_id nếu chưa có
            if (empty($user->google_id)) {
                $user->google_id = $googleId;
                $user->save();
            }

            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $user->load(['vendor', 'membershipTier', 'author', 'favoriteCategories']);

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công.',
                'data' => [
                    'user' => new UserResource($user),
                ],
            ]);
        }

        // Tạo Registration Challenge Token dùng 1 lần trong 10 phút, chống race/replay
        $challengeToken = (string) Str::uuid();
        Cache::put('google_challenge_'.$challengeToken, [
            'google_id' => $googleId,
            'email' => $email,
            'name' => $name,
        ], now()->addMinutes(10));

        return response()->json([
            'status' => 'needs_registration',
            'message' => 'Tài khoản Google chưa liên kết. Vui lòng hoàn tất thông tin đăng ký.',
            'data' => [
                'challenge_token' => $challengeToken,
                'email' => $email,
                'name' => $name,
            ],
        ]);
    }
}
