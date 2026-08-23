<?php

declare(strict_types=1);

use App\Modules\Api\Http\Middleware\AuthenticateApiToken;
use App\Modules\Api\Http\Middleware\EnforceIdempotency;
use App\Modules\Billing\Http\Middleware\EnforceSubscriptionState;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Shared\Exceptions\TenantContextMissingException;
use App\Shared\Http\Api\ApiExceptionRenderer;
use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Http\Middleware\SetLocale;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\AuthenticateSession;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Order matters. The request id is assigned first so every later
        // failure can be traced; the tenant is resolved after authentication,
        // because it derives from membership.
        $middleware->append(AssignRequestId::class);

        $middleware->web(append: [
            SetLocale::class,
            ResolveTenantContext::class,
            // After the tenant is known, because it is the tenant's contract
            // that decides this. Reads and exports pass through it untouched
            // (ADR-029).
            EnforceSubscriptionState::class,
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        // The API resolves its tenant from the bearer token rather than from
        // membership and a header, because a token is minted for exactly one
        // company. That check lives in `api.auth`, and the subscription state
        // has to be enforced after it — a middleware that runs before the
        // tenant is known has no company whose contract it could read.
        $middleware->group('api.auth', [
            AuthenticateApiToken::class,
            EnforceSubscriptionState::class,
        ]);

        $middleware->alias([
            'company' => ResolveTenantContext::class,
            'idempotent' => EnforceIdempotency::class,
        ]);

        // Route model binding MUST run after the tenant is resolved.
        //
        // Otherwise the binding query runs unscoped and resolves another
        // company's record, which the policy then rejects with 403. That
        // leaks existence: a 403 tells an attacker the id is real, while a
        // 404 does not (API 2). Authentication has to come first in turn,
        // because tenant context derives from membership.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            Authenticate::class,
            AuthenticateSession::class,
            SetLocale::class,
            ResolveTenantContext::class,
            // The API's own tenant resolution belongs in the same slot for the
            // same reason: without it, `{asset}` in a route is looked up
            // before there is a company to scope the lookup to.
            AuthenticateApiToken::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every way the application can fail, turned into the one error
        // envelope a client can branch on. Scoped to api/* inside the
        // renderer itself: the web UI's failures are redirects with flash
        // messages and must stay that way.
        $exceptions->render(
            fn (Throwable $e, Request $request) => (new ApiExceptionRenderer)($e, $request),
        );

        // A query that escaped tenant scoping is a defect, not a user error.
        // It must never degrade into an unscoped result set.
        $exceptions->render(function (TenantContextMissingException $e, Request $request) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.tenant_context_required'),
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'meta' => ['request_id' => $request->attributes->get('request_id')],
                ], 403);
            }

            abort(403, __('auth.tenant_context_required'));
        });
    })->create();
