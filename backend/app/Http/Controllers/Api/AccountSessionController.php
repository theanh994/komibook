<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountSessionController extends Controller
{
    public function index(Request $request, AccountSessionService $sessions): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $sessions->list($request)]);
    }

    public function destroy(Request $request, string $sessionId, AccountSessionService $sessions): JsonResponse
    {
        abort_unless($sessions->revoke($request->user(), $sessionId), 404, 'Phiên đăng nhập không tồn tại.');
        $isCurrent = hash_equals((string) $request->session()->getId(), $sessionId);
        if ($isCurrent) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['status' => 'success', 'data' => ['revoked_current_session' => $isCurrent]]);
    }
}
