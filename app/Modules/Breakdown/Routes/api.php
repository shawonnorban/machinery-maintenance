<?php

declare(strict_types=1);

use App\Modules\Breakdown\Http\Controllers\Api\BreakdownApiController;
use Illuminate\Support\Facades\Route;

/*
 * Breakdowns (API 12).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/breakdowns', [BreakdownApiController::class, 'index'])->name('breakdowns.index');
    Route::get('/breakdowns/{breakdown}', [BreakdownApiController::class, 'show'])->name('breakdowns.show');

    Route::post('/breakdowns/{breakdown}/acknowledge', [BreakdownApiController::class, 'acknowledge'])
        ->name('breakdowns.acknowledge');
    Route::post('/breakdowns/{breakdown}/assign', [BreakdownApiController::class, 'assign'])
        ->name('breakdowns.assign');
    Route::post('/breakdowns/{breakdown}/start-repair', [BreakdownApiController::class, 'startRepair'])
        ->name('breakdowns.start-repair');
    Route::post('/breakdowns/{breakdown}/complete-repair', [BreakdownApiController::class, 'completeRepair'])
        ->name('breakdowns.complete-repair');
    Route::post('/breakdowns/{breakdown}/resume-production', [BreakdownApiController::class, 'resumeProduction'])
        ->name('breakdowns.resume-production');
});

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    // Two breakdown numbers for one stoppage halve the MTBF of a machine that
    // broke once, and a tablet on factory wifi will be pressed twice.
    Route::post('/breakdowns', [BreakdownApiController::class, 'store'])->name('breakdowns.store');
});
