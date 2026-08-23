<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Illuminate\Support\Str;

/**
 * One nonce per request, for the handful of inline scripts the shell needs.
 *
 * There are exactly two: `window.App`, which carries per-request context that
 * cannot be compiled into a bundle because it differs per user and per
 * company, and the small confirm handler on one printable page.
 *
 * A nonce rather than `'unsafe-inline'` because the difference is the whole
 * value of the policy. `'unsafe-inline'` permits every inline script including
 * one an attacker injected; a nonce permits only the two this application
 * wrote, and an injected script cannot guess a value generated per request.
 */
class Nonce
{
    private static ?string $value = null;

    /**
     * The nonce for this request, generated on first use.
     *
     * Generated lazily rather than in a middleware, so a request that renders
     * no HTML never pays for one, and so the value is identical whether the
     * header or the template asks first.
     */
    public static function current(): string
    {
        return self::$value ??= Str::random(24);
    }

    /**
     * Between requests in a long-lived worker, the nonce has to be forgotten
     * or every response in that worker shares one — which is the same as not
     * having one.
     */
    public static function forget(): void
    {
        self::$value = null;
    }
}
