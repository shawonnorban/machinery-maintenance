<?php

declare(strict_types=1);

namespace App\Modules\Api\Providers;

use App\Modules\Api\Support\ApiCaller;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring for the API (API 35.1).
 */
class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound so that a controller type-hinting ApiCaller outside an
        // authenticated route fails with a plain 401 rather than a container
        // resolution error nobody can read.
        $this->app->bind(ApiCaller::class, function (): never {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        });
    }

    public function boot(): void
    {
        // Per token where there is one, per IP where there is not. Keying an
        // authenticated limit by IP would make one busy factory's outbound
        // address throttle every integration behind it.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)
            ->by($request->bearerToken() !== null
                ? sha1((string) $request->bearerToken())
                : (string) $request->ip()));

        // The two doors are much tighter, and by IP on purpose: an attacker
        // trying credentials has no token yet, so a token-keyed limit would
        // count nothing.
        RateLimiter::for('api-auth', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));

        // Readings arrive from controllers and PLCs in bursts — a dye house
        // posting a batch at shift change is normal traffic, not abuse.
        RateLimiter::for('api-ingest', fn (Request $request) => Limit::perMinute(1000)
            ->by($request->bearerToken() !== null
                ? sha1((string) $request->bearerToken())
                : (string) $request->ip()));
    }
}
