<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\Api\ApiClientApiController;
use App\Modules\Api\Http\Controllers\Api\AuthController;
use App\Modules\Api\Http\Controllers\Api\HealthController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

/*
 * The front door (API 3, 19.3).
 *
 * Everything here is prefixed /api/v1 by the module loader. The two token
 * endpoints and the two health endpoints are the only unauthenticated routes
 * in the whole API; every other route in every module sits behind `api.auth`.
 */

// Unauthenticated, and rate limited hard: these are the endpoints an attacker
// would use to try credentials.
Route::middleware('throttle:api-auth')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/token', [AuthController::class, 'token'])->name('auth.token');
});

// No authentication and no tenant: a load balancer has neither.
Route::get('/health', [HealthController::class, 'alive'])->name('health');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('/auth/permissions', [AuthController::class, 'permissions'])->name('auth.permissions');
    Route::get('/auth/companies', [AuthController::class, 'companies'])->name('auth.companies');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/switch-company', [AuthController::class, 'switchCompany'])->name('auth.switch-company');
    Route::patch('/auth/locale', [AuthController::class, 'setLocale'])->name('auth.locale');
    Route::get('/auth/sessions', [AuthController::class, 'sessions'])->name('auth.sessions.index');
    Route::delete('/auth/sessions/{session}', [AuthController::class, 'revokeSession'])->name('auth.sessions.revoke');
    Route::post('/auth/sessions/revoke-all', [AuthController::class, 'revokeAllSessions'])
        ->name('auth.sessions.revoke-all');
    Route::get('/auth/tokens', [AuthController::class, 'tokens'])->name('auth.tokens.index');
    Route::delete('/auth/tokens/{token}', [AuthController::class, 'revokeToken'])->name('auth.tokens.revoke');
    Route::post('/auth/password', [AuthController::class, 'changePassword'])->name('auth.password');

    // Private/presence channel authorization (routes/channels.php), reached
    // over the bearer-token guard rather than the framework's own
    // `broadcasting/auth` (which only registers under the `web` session
    // guard — see docs/12-Stack-Migration-Implementation-Plan.md Phase F).
    // `AuthenticateApiToken` sets the request's user resolver, and
    // `BroadcastController::authenticate()` resolves via `$request->user()`
    // under the hood, so this needs no bespoke controller of its own.
    Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');

    // Machine credentials (API 4.2, SRS 43). form-options ahead of
    // {client}: a wildcard segment would otherwise swallow it as an
    // attempted client id.
    Route::get('/api-clients/form-options', [ApiClientApiController::class, 'formOptions'])
        ->name('api-clients.form-options');
    Route::get('/api-clients', [ApiClientApiController::class, 'index'])->name('api-clients.index');
    Route::post('/api-clients', [ApiClientApiController::class, 'store'])->name('api-clients.store');
    Route::patch('/api-clients/{client}', [ApiClientApiController::class, 'update'])->name('api-clients.update');
    Route::post('/api-clients/{client}/rotate', [ApiClientApiController::class, 'rotate'])->name('api-clients.rotate');
    Route::delete('/api-clients/{client}', [ApiClientApiController::class, 'revoke'])->name('api-clients.revoke');
    Route::delete('/api-tokens/{token}', [ApiClientApiController::class, 'revokeToken'])->name('api-tokens.revoke');
});
