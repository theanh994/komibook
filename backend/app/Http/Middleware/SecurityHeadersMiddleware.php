<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            "default-src 'self'; frame-ancestors 'self'; img-src 'self' data: https:; "
            ."script-src 'self' 'unsafe-inline' https://accounts.google.com https://connect.facebook.net; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."font-src 'self' data: https://fonts.gstatic.com; "
            ."connect-src 'self' https://accounts.google.com https://graph.facebook.com; "
            ."frame-src 'self' https://accounts.google.com https://www.facebook.com;"
        );

        return $response;
    }
}
