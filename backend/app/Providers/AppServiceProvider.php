<?php

namespace App\Providers;

use App\Services\FacebookTokenVerifier;
use App\Services\FacebookTokenVerifierInterface;
use App\Services\FakeFacebookTokenVerifier;
use App\Services\FakeGoogleTokenVerifier;
use App\Services\GoogleTokenVerifier;
use App\Services\GoogleTokenVerifierInterface;
use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSenderInterface;
use App\Services\Otp\ProductionOtpSender;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FacebookTokenVerifierInterface::class, function () {
            if (app()->environment('testing')) {
                return new FakeFacebookTokenVerifier;
            }

            return new FacebookTokenVerifier;
        });

        $this->app->bind(GoogleTokenVerifierInterface::class, function () {
            if (app()->environment('testing')) {
                return new FakeGoogleTokenVerifier;
            }

            return new GoogleTokenVerifier;
        });

        $this->app->bind(OtpSenderInterface::class, function () {
            if (app()->environment('testing')) {
                return new FakeOtpSender;
            }
            if (app()->environment('local')) {
                return new LogOtpSender;
            }

            return new ProductionOtpSender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')).'/reset-password?token='.$token.'&email='.$user->email;
        });

        // Cấu hình Rate Limiting
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
