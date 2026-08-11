<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->hasSession()
            ? (int) $request->session()->get('auth.password_confirmed_at', 0)
            : 0;
        if ($confirmedAt < now()->subMinutes(15)->timestamp) {
            return response()->json([
                'status' => 'error',
                'code' => 'RECENT_AUTHENTICATION_REQUIRED',
                'message' => 'Vui lòng đăng nhập lại để thực hiện thao tác nhạy cảm này.',
            ], 423);
        }

        return $next($request);
    }
}
