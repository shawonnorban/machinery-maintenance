<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\Api\AuthController;
use App\Modules\Api\Http\Controllers\Api\HealthController;
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
});
