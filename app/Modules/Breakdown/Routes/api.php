<?php

declare(strict_types=1);

use App\Modules\Breakdown\Http\Controllers\Api\BreakdownApiController;
use Illuminate\Support\Facades\Route;

/*
 * Breakdowns (API 12).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/breakdowns', [BreakdownApiController::class, 'index'])->name('breakdowns.index');
    // Ahead of GET /breakdowns/{breakdown}: a wildcard segment would
    // otherwise swallow "counts"/"create-form-options" as an attempted id.
    Route::get('/breakdowns/counts', [BreakdownApiController::class, 'counts'])->name('breakdowns.counts');
    Route::get('/breakdowns/create-form-options', [BreakdownApiController::class, 'createFormOptions'])
        ->name('breakdowns.create-form-options');
    Route::get('/breakdowns/{breakdown}', [BreakdownApiController::class, 'show'])->name('breakdowns.show');

    Route::post('/breakdowns/{breakdown}/acknowledge', [BreakdownApiController::class, 'acknowledge'])
        ->name('breakdowns.acknowledge');
    Route::post('/breakdowns/{breakdown}/assign', [BreakdownApiController::class, 'assign'])
        ->name('breakdowns.assign');
    Route::post('/breakdowns/{breakdown}/arrive', [BreakdownApiController::class, 'arrive'])
        ->name('breakdowns.arrive');
    Route::post('/breakdowns/{breakdown}/start-repair', [BreakdownApiController::class, 'startRepair'])
        ->name('breakdowns.start-repair');
    Route::post('/breakdowns/{breakdown}/hold', [BreakdownApiController::class, 'hold'])
        ->name('breakdowns.hold');
    Route::post('/breakdowns/{breakdown}/resume', [BreakdownApiController::class, 'resume'])
        ->name('breakdowns.resume');
    Route::post('/breakdowns/{breakdown}/complete-repair', [BreakdownApiController::class, 'completeRepair'])
        ->name('breakdowns.complete-repair');
    Route::post('/breakdowns/{breakdown}/resume-production', [BreakdownApiController::class, 'resumeProduction'])
        ->name('breakdowns.resume-production');
    Route::post('/breakdowns/{breakdown}/close', [BreakdownApiController::class, 'close'])
        ->name('breakdowns.close');
    Route::post('/breakdowns/{breakdown}/cancel', [BreakdownApiController::class, 'cancel'])
        ->name('breakdowns.cancel');
    Route::post('/breakdowns/{breakdown}/work-order', [BreakdownApiController::class, 'raiseWorkOrder'])
        ->name('breakdowns.work-order');
    Route::patch('/breakdowns/{breakdown}', [BreakdownApiController::class, 'update'])->name('breakdowns.update');
    Route::get('/breakdowns/{breakdown}/downtime', [BreakdownApiController::class, 'downtime'])
        ->name('breakdowns.downtime');
    Route::get('/breakdowns/{breakdown}/root-cause', [BreakdownApiController::class, 'rootCause'])
        ->name('breakdowns.root-cause');
    Route::get('/breakdowns/{breakdown}/form-options', [BreakdownApiController::class, 'formOptions'])
        ->name('breakdowns.form-options');
});

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    // Two breakdown numbers for one stoppage halve the MTBF of a machine that
    // broke once, and a tablet on factory wifi will be pressed twice.
    Route::post('/breakdowns', [BreakdownApiController::class, 'store'])->name('breakdowns.store');
});
