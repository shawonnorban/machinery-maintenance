<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Shared\Exceptions\TenantContextMissingException;
use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
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
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->api(append: [
            ResolveTenantContext::class,
        ]);

        $middleware->alias([
            'company' => ResolveTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
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
