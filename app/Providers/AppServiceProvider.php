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
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('hover', fn (Request $request) => Limit::perMinute(180)->by($request->ip()));
        RateLimiter::for('vote', function (Request $request) {
            $max = $request->user()?->risk_status === 'normal' ? 20 : 6;
            return Limit::perMinute($max)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('reaction', function (Request $request) {
            $max = $request->user()?->risk_status === 'normal' ? 40 : 10;
            return Limit::perMinute($max)->by($request->user()?->id ?: $request->ip());
        });
    }
}
