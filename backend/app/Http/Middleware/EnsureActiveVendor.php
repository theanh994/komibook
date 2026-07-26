<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user()?->vendor()->withoutGlobalScopes()->first();
        if (! $vendor || ! $vendor->isActive()) {
            abort(403, 'Hồ sơ nhà bán chưa được phê duyệt hoặc đã bị đình chỉ.');
        }

        return $next($request);
    }
}
