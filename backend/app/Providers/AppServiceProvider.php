<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        }

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')) . '/reset-password?token=' . $token . '&email=' . $user->email;
        });

        // Cấu hình Rate Limiting
        \Illuminate\Support\Facades\RateLimiter::for('login', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('forgot-password', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('checkout', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
