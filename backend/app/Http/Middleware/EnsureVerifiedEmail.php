<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->email_verified_at) {
            return response()->json([
                'status' => 'error',
                'code' => 'EMAIL_VERIFICATION_REQUIRED',
                'message' => 'Vui lòng xác thực email trước khi thực hiện thao tác nhạy cảm này.',
            ], 403);
        }

        return $next($request);
    }
}
