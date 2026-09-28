<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The front door for the platform API (Platform API §1).
 *
 * Deliberately not `AuthenticateApiToken`: that middleware's whole job is
 * resolving *which company* a bearer token reads as, and a platform token
 * reads as none — `EnsurePlatformAdmin`'s docblock is exactly as true here
 * as it is for the web platform area: "every role in this system is scoped
 * to a company or a factory, and platform staff belong to neither." So this
 * never touches `TenantContext`, never binds an `ApiCaller`, and accepts
 * only a token `IssueApiToken::forPlatformAdmin()` minted — one with no
 * `company_id` at all, which the ordinary tenant guard would refuse as
 * malformed rather than treat as platform-scoped.
 *
 * The 404-not-403 stance is `EnsurePlatformAdmin`'s too: whether the
 * platform area exists is not information a non-admin token is told,
 * whether that token is well-formed but ordinary, or missing entirely.
 */
class AuthenticatePlatformToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $token = PersonalAccessToken::findToken($bearer);

        if ($token === null
            || ! $token->tokenable instanceof User
            || ($token->expires_at !== null && $token->expires_at->isPast())
            // A tenant token is well-formed but simply not a platform one —
            // never treated as an authentication failure, only as "not
            // this door" (checked below, alongside is_platform_admin).
        ) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $user = $token->tokenable;

        if ($token->company_id !== null || ! $user->is_platform_admin) {
            app(AuditRecorder::class)->event(
                'SECURITY_EVENT',
                ['reason' => 'PLATFORM_ACCESS_DENIED', 'path' => $request->path()],
                userId: $user->id,
                label: 'PLATFORM_ACCESS_DENIED',
            );

            throw ApiException::of(ErrorCode::RESOURCE_NOT_FOUND);
        }

        $request->setUserResolver(fn (): User => $user);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
