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
        // A factory's people all reach the API from one address (user, 2026-11-26: email sign-in was 10 a
        // minute for everybody together): ten a minute per account, the address far wider.
        RateLimiter::for('api-auth', fn (Request $request) => [
            Limit::perMinute(10)->by('auth|'.sha1(mb_strtolower(trim((string) $request->input('email', $request->input('login', '')))).'|'.$request->ip())),
            Limit::perMinute(300)->by('auth-ip|'.$request->ip()),
        ]);

        // A QC's PIN sign-in (AnnotechERP 37): by phone and ID, because a floor's phones share one
        // address; the address has a far wider limit of its own. Wrong PINs are locked by the sign-in itself.
        RateLimiter::for('qt-sign-in', fn (Request $request) => [
            Limit::perMinute(10)->by('qt|'.sha1(json_encode([$request->input('phone.install_id'), $request->input('employee_id')]) ?: '')),
            Limit::perMinute(300)->by('qt-ip|'.$request->ip()),
        ]);
        RateLimiter::for('qt-phone', fn (Request $request) => Limit::perMinute(300)->by('qt-phone|'.$request->header('X-Phone-Id', (string) $request->ip())));

        // Readings arrive from controllers and PLCs in bursts — a dye house
        // posting a batch at shift change is normal traffic, not abuse.
        RateLimiter::for('api-ingest', fn (Request $request) => Limit::perMinute(1000)
            ->by($request->bearerToken() !== null
                ? sha1((string) $request->bearerToken())
                : (string) $request->ip()));
    }
}
