<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountSessionService;
use App\Services\FacebookTokenVerifierInterface;
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
        $facebookId = null;
        $emailVerified = false;

        // Không cho phép client tự khai báo mã định danh của nhà cung cấp mạng xã hội.
        if (
            ($request->filled('google_id') || $request->filled('facebook_id'))
            && ! $request->filled('challenge_token')
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tự khai báo mã tài khoản mạng xã hội. Vui lòng xác thực qua nhà cung cấp.',
            ], 422);
        }

        // Challenge dùng một lần, được tạo sau khi backend xác minh Google/Facebook.
        if ($request->filled('challenge_token')) {
            $googleData = Cache::pull('google_challenge_'.$request->challenge_token);
            $facebookData = $googleData
                ? null
                : Cache::pull('facebook_challenge_'.$request->challenge_token);

            if (! $googleData && ! $facebookData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mã xác thực mạng xã hội không hợp lệ hoặc đã hết hạn.',
                ], 422);
            }

            if ($googleData) {
                $email = $googleData['email'];
                $googleId = $googleData['google_id'];
                $emailVerified = true;
            } else {
                $email = $facebookData['email'] ?: $request->email;
                $facebookId = $facebookData['facebook_id'];
                $emailVerified = ! empty($facebookData['email']);
            }
        } elseif ($email) {
            $verificationToken = $request->string('email_verification_token')->toString();
            $verifiedEmail = $verificationToken
                ? Cache::pull('registration_email_verified_'.$verificationToken)
                : null;

            if (! $verifiedEmail || ! hash_equals(Str::lower(trim($email)), $verifiedEmail)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vui lòng xác thực email bằng mã OTP trước khi đăng ký.',
                    'errors' => [
                        'email_verification_token' => ['Mã xác thực email không hợp lệ hoặc đã hết hạn.'],
                    ],
                ], 422);
            }

            $emailVerified = true;
        }

        // Thực hiện trong transaction và kiểm tra lại các khóa duy nhất.
        $user = DB::transaction(function () use ($request, $email, $googleId, $facebookId, $emailVerified) {
            if ($googleId && User::where('google_id', $googleId)->exists()) {
                throw new \InvalidArgumentException('Tài khoản Google này đã được liên kết với người dùng khác.');
            }
            if ($facebookId && User::where('facebook_id', $facebookId)->exists()) {
                throw new \InvalidArgumentException('Tài khoản Facebook này đã được liên kết với người dùng khác.');
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
                'facebook_id' => $facebookId,
                'role' => 'customer',
            ]);

            if ($emailVerified) {
                $newUser->forceFill(['email_verified_at' => now()])->save();
            }

            if ($request->desired_role === 'vendor') {
                Vendor::withoutGlobalScopes()->create([
                    'user_id' => $newUser->id,
                    'shop_name' => null,
                    'slug' => null,
                    'description' => null,
                    'status' => 'inactive',
                    'onboarding_status' => 'draft',
                ]);
            }

            return $newUser;
        });

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put('auth.password_confirmed_at', time());
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
            $request->session()->put('auth.password_confirmed_at', time());
        }

        // Single-device policy clean up tokens nếu có token cũ
        $user->tokens()->delete();

        $user->load([
            'vendor', 'membershipTier', 'usedBookSellerProfile', 'favoriteCategories',
            'warehouseManagerAssignments', 'organizationMemberships',
        ]);

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

        $user->load(['vendor', 'membershipTier', 'usedBookSellerProfile', 'favoriteCategories', 'warehouseManagerAssignments']);

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy thông tin người dùng thành công.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function confirmRecentAuthentication(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        abort_unless($request->hasSession(), 422, 'Yêu cầu xác nhận lại cần phiên đăng nhập trình duyệt.');
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xác nhận lại danh tính trong 15 phút.',
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

                app(AccountSessionService::class)->revokeOtherSessions($user);

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['status' => 'success', 'message' => __($status)])
            : response()->json(['status' => 'error', 'message' => __($status)], 400);
    }

    // ─── Google Login ─────────────────────────────────────────────────────────

    /**
     * Trả về duy nhất cấu hình công khai cần để khởi tạo SDK đăng nhập xã hội.
     * App secret của Facebook tuyệt đối không được gửi xuống trình duyệt.
     */
    public function socialLoginConfig(): JsonResponse
    {
        $googleClientId = trim((string) config('services.google.client_id'));
        $facebookAppId = trim((string) config('services.facebook.app_id'));
        $facebookAppSecret = trim((string) config('services.facebook.app_secret'));
        $facebookGraphVersion = trim((string) config('services.facebook.graph_version'));

        $googleEnabled = $googleClientId !== '';
        $facebookEnabled = $facebookAppId !== ''
            && $facebookAppSecret !== ''
            && $facebookGraphVersion !== '';

        return response()->json([
            'data' => [
                'google' => [
                    'enabled' => $googleEnabled,
                    'client_id' => $googleEnabled ? $googleClientId : null,
                ],
                'facebook' => [
                    'enabled' => $facebookEnabled,
                    'app_id' => $facebookEnabled ? $facebookAppId : null,
                    'graph_version' => $facebookEnabled ? $facebookGraphVersion : null,
                ],
            ],
        ]);
    }

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
            }
            if (($payload['email_verified'] ?? false) && ! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();

            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->put('auth.password_confirmed_at', time());
            }

            $user->load(['vendor', 'membershipTier', 'usedBookSellerProfile', 'favoriteCategories']);

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

    /**
     * Đăng nhập hoặc bắt đầu đăng ký bằng Facebook.
     *
     * Access token được kiểm tra lại ở backend bằng debug_token và phải thuộc
     * đúng Meta App trước khi hệ thống tin bất kỳ thông tin hồ sơ nào.
     */
    public function facebookLogin(Request $request, FacebookTokenVerifierInterface $verifier): JsonResponse
    {
        $request->validate([
            'access_token' => ['required', 'string'],
        ], [
            'access_token.required' => 'Yêu cầu token xác minh Facebook.',
        ]);

        if (
            empty(config('services.facebook.app_id'))
            || empty(config('services.facebook.app_secret'))
            || empty(config('services.facebook.graph_version'))
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hệ thống chưa được cấu hình Facebook Login.',
            ], 500);
        }

        try {
            $profile = $verifier->verify($request->string('access_token')->toString());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $facebookId = $profile['id'];
        $email = $profile['email'];
        $name = $profile['name'];

        $user = User::where('facebook_id', $facebookId)
            ->when($email, fn ($query) => $query->orWhere('email', $email))
            ->first();

        if ($user) {
            if (empty($user->facebook_id)) {
                $user->facebook_id = $facebookId;
            }
            if ($email && ! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();

            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->put('auth.password_confirmed_at', time());
            }

            $user->load(['vendor', 'membershipTier', 'usedBookSellerProfile', 'favoriteCategories']);

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập Facebook thành công.',
                'data' => [
                    'user' => new UserResource($user),
                ],
            ]);
        }

        $challengeToken = (string) Str::uuid();
        Cache::put('facebook_challenge_'.$challengeToken, [
            'facebook_id' => $facebookId,
            'email' => $email,
            'name' => $name,
        ], now()->addMinutes(10));

        return response()->json([
            'status' => 'needs_registration',
            'message' => 'Tài khoản Facebook chưa liên kết. Vui lòng hoàn tất thông tin đăng ký.',
            'data' => [
                'challenge_token' => $challengeToken,
                'email' => $email,
                'name' => $name,
            ],
        ]);
    }
}
