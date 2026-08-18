<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale: user profile, then company default, then the
 * application fallback (SRS 48.1 rule 2).
 *
 * Accept-Language is honoured only for values the application actually
 * supports, so a browser default never silently switches a Bengali user to
 * English mid-session.
 */
class SetLocale
{
    private const SUPPORTED = ['en', 'bn'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        $user = $request->user();

        if ($user !== null && in_array($user->locale, self::SUPPORTED, true)) {
            $locale = $user->locale;
        }

        if ($locale === null) {
            $header = $request->header('Accept-Language');

            if (is_string($header)) {
                $preferred = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));

                if (in_array($preferred, self::SUPPORTED, true)) {
                    $locale = $preferred;
                }
            }
        }

        app()->setLocale($locale ?? config('app.locale'));

        return $next($request);
    }
}
