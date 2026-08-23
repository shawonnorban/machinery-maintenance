<?php

declare(strict_types=1);

use App\Modules\Metering\Http\Controllers\Api\MeterApiController;
use Illuminate\Support\Facades\Route;

/*
 * Meters and readings (API 10).
 *
 * Readings carry the higher rate limit: a dye house posting a batch at shift
 * change is normal traffic, not abuse.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/assets/{asset}/meters', [MeterApiController::class, 'index'])->name('assets.meters');
    Route::get('/meters/{meter}/readings', [MeterApiController::class, 'readings'])->name('meters.readings');
});

Route::middleware(['api.auth', 'throttle:api-ingest', 'idempotent'])->group(function (): void {
    // A reading counted twice does not merely inflate a number: it brings the
    // next service forward and can raise a job that is not due.
    Route::post('/meters/{meter}/readings', [MeterApiController::class, 'store'])->name('meters.readings.store');
});
