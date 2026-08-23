<?php

declare(strict_types=1);

use App\Modules\Metering\Http\Controllers\Web\MeterController;
use Illuminate\Support\Facades\Route;

/*
 * Meters and their readings (SRS 11).
 *
 * The half of usage-based maintenance that was missing: a plan can say
 * "service every 500 running hours", and until somebody records the hours it
 * can never come due.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/meters', [MeterController::class, 'index'])->name('meters.index');
    Route::get('/meters/{meter}', [MeterController::class, 'show'])->name('meters.show');

    // Recording is the technician's job and needs only their permission;
    // fitting and resetting a meter are configuration.
    Route::post('/meters/{meter}/readings', [MeterController::class, 'record'])->name('meters.readings');
    Route::post('/meters/{meter}/reset', [MeterController::class, 'reset'])->name('meters.reset');
    Route::post('/meters/{meter}/toggle', [MeterController::class, 'toggle'])->name('meters.toggle');

    Route::post('/assets/{asset}/meters', [MeterController::class, 'attach'])->name('assets.meters.attach');
});
