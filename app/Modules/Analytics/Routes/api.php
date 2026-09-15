<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\Api\DashboardApiController;
use Illuminate\Support\Facades\Route;

/*
 * The three dashboards (API 21).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/dashboard/management', [DashboardApiController::class, 'management'])
        ->name('dashboard.management');
    Route::get('/dashboard/maintenance', [DashboardApiController::class, 'maintenance'])
        ->name('dashboard.maintenance');
    Route::get('/dashboard/store', [DashboardApiController::class, 'store'])->name('dashboard.store');
    Route::get('/dashboard/kpis', [DashboardApiController::class, 'kpis'])->name('dashboard.kpis');
    Route::get('/dashboard/trend', [DashboardApiController::class, 'trend'])->name('dashboard.trend');
});
