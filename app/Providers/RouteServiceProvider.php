<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    public function configureRateLimiting(): void
    {
        // General API rate limiter (for all authenticated API requests)
        RateLimiter::for('api', function (Request $request){
            return Limit::perMinute(60)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function (Request $request, array $headers){
                    return response()->json([
                        'message' => 'Too many requests. Please try again later.'
                    ], 429, $headers);
                });
        });

        // Stricter rate limiter for authentication endpoints
        RateLimiter::for('auth', function (Request $request){
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers){
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in a minute.'
                    ], 429, $headers);
                });
        });

        // Rate limiter for sensitive operations (deposits, withdrawals, etc.)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(10)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests to sensitive endpoints. Please slow down.'
                    ], 429, $headers);
                });
        });

        // Public API endpoints (if any)
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests. Please try again later.'
                    ], 429, $headers);
                });
        });
    }
}
