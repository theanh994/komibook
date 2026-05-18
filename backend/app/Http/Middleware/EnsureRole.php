<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra role của user đang đăng nhập.
 *
 * Sử dụng:
 *   Route::middleware('role:admin')  → chỉ cho admin
 *   Route::middleware('role:vendor') → chỉ cho vendor
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== $role) {
            abort(403, 'Bạn không có quyền truy cập tài nguyên này.');
        }

        return $next($request);
    }
}
