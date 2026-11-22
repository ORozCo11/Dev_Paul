<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Each limit has its own counter. A plain "throttle:10,1" shares ONE counter per user (or IP) across every route that
        // uses it, so typing in the address box could use up the login or Support-form allowance.
        $limit = fn (string $name, int $perMinute) => RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)
            ->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$name));
        $limit('login', 10);
        $limit('address-suggestions', 20);
        $limit('concern-reports', 10);
        $limit('impersonate', 10);
        $limit('change-password', 10);
        $limit('resend-credentials', 10);
        $limit('create-admin', 20);
        $limit('send-email', 10);
    }
}
