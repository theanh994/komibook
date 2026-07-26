<?php

use App\Http\Middleware\EnsureActiveVendor;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: ['127.0.0.1']);
        $middleware->statefulApi();
        $middleware->append(SecurityHeadersMiddleware::class);

        // Đăng ký middleware kiểm tra role (admin/vendor)
        $middleware->alias([
            'role' => EnsureRole::class,
            'active-vendor' => EnsureActiveVendor::class,
            'recent-auth' => EnsureRecentAuthentication::class,
            'verified-email' => EnsureVerifiedEmail::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return $request->expectsJson() ? null : '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
