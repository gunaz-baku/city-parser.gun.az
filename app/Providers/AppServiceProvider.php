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
        // phpredis extension yoxdursa (məs. WSL), .env-də phpredis qalsa belə Predis istifadə et.
        if (! extension_loaded('redis') && config('database.redis.client') === 'phpredis') {
            config(['database.redis.client' => 'predis']);
        }

        RateLimiter::for('parser-api', function (Request $request) {
            return Limit::perMinute((int) config('parser_api.rate_limit_per_minute', 180))
                ->by($request->ip());
        });
    }
}
