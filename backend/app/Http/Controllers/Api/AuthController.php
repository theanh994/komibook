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
            'phone'    => $request->phone,
            'gender'   => $request->gender,
            'birthday' => $request->birthday,
            'google_id' => $request->google_id,
            'role'     => 'customer',
        ]);

        if ($request->desired_role === 'author') {
            \App\Models\Author::create([
                'user_id' => $user->id,
                'pen_name' => $user->name,
                'bank_account_number' => 'Pending',
                'bank_name' => 'Pending',
                'bank_holder_name' => strtoupper($user->name),
                'identity_document' => 'Pending',
                'status' => 'pending',
            ]);
        } elseif ($request->desired_role === 'vendor') {
            \App\Models\Vendor::withoutGlobalScopes()->create([
                'user_id'     => $user->id,
                'shop_name'   => 'Shop ' . $user->name,
                'slug'        => \Illuminate\Support\Str::slug('Shop ' . $user->name . '-' . rand(1000, 9999)),
                'description' => '',
                'status'      => 'pending',
            ]);
        }

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
        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $credentials = [
            $loginField => $request->email,
            'password' => $request->password,
        ];

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tài khoản hoặc mật khẩu không chính xác.',
                'data'    => null,
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Single-device policy: xóa tất cả token cũ trước khi cấp mới
        $user->tokens()->delete();

        // Eager load relationships
        $user->load(['vendor', 'membershipTier', 'author']);

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

        $user->load(['vendor', 'membershipTier', 'author']);

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

    /**
     * Đăng nhập hoặc đăng ký bằng tài khoản Google.
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $email = $request->email;
        $name = $request->name;
        $googleId = $request->google_id;

        // Nếu client gửi lên id_token (từ Google SDK thực tế trên frontend)
        if ($request->has('id_token')) {
            $idToken = $request->id_token;
            $verifyResponse = \Illuminate\Support\Facades\Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$idToken}");

            if ($verifyResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token xác minh tài khoản Google không hợp lệ hoặc đã hết hạn.',
                    'errors' => [
                        'id_token' => ['Token Google không hợp lệ.']
                    ]
                ], 422);
            }

            $payload = $verifyResponse->json();
            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? null;
            $googleId = $payload['sub'] ?? null;

            if (empty($email) || empty($googleId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể trích xuất thông tin định danh từ Google Token.',
                ], 422);
            }
        } else {
            // Nếu không gửi id_token, chỉ cho phép mock bằng google_id + email khi ở môi trường phát triển (debug/local)
            if (config('app.env') !== 'local' && !config('app.debug')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Yêu cầu Token xác minh Google (id_token) ở môi trường production.',
                ], 422);
            }

            // Môi trường dev: validate các trường mock gửi lên
            $request->validate([
                'email' => 'required|string|email',
                'name' => 'required|string',
                'google_id' => 'required|string',
            ]);
        }

        // Tiến hành đăng nhập/đăng ký
        $user = User::where('google_id', $googleId)
                    ->orWhere('email', $email)
                    ->first();

        if ($user) {
            // Nếu chưa có google_id thì gán
            if (empty($user->google_id)) {
                $user->google_id = $googleId;
                $user->save();
            }

            $user->tokens()->delete();
            $user->load(['vendor', 'membershipTier', 'author']);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công.',
                'data' => [
                    'user' => new UserResource($user),
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        }

        // Tài khoản chưa tồn tại -> Yêu cầu thiết lập/hoàn tất thông tin đăng ký
        return response()->json([
            'status' => 'needs_registration',
            'message' => 'Tài khoản Google chưa liên kết. Vui lòng hoàn tất thông tin đăng ký.',
            'data' => [
                'email' => $email,
                'name' => $name,
                'google_id' => $googleId,
            ]
        ]);
    }
}
