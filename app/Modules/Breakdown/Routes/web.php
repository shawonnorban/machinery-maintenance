<?php

declare(strict_types=1);

use App\Modules\Breakdown\Http\Controllers\Web\BreakdownController;
use App\Modules\Breakdown\Http\Controllers\Web\BreakdownTransitionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/breakdowns', [BreakdownController::class, 'index'])->name('breakdowns.index');
    Route::get('/breakdowns/create', [BreakdownController::class, 'create'])->name('breakdowns.create');
    Route::post('/breakdowns', [BreakdownController::class, 'store'])->name('breakdowns.store');
    Route::get('/breakdowns/{breakdown}', [BreakdownController::class, 'show'])->name('breakdowns.show');

    // POST, never GET: a state change behind a link is one a prefetch can make.
    Route::post('/breakdowns/{breakdown}/acknowledge', [BreakdownTransitionController::class, 'acknowledge'])
        ->name('breakdowns.acknowledge');
    Route::post('/breakdowns/{breakdown}/assign', [BreakdownTransitionController::class, 'assign'])
        ->name('breakdowns.assign');
    Route::post('/breakdowns/{breakdown}/arrive', [BreakdownTransitionController::class, 'arrive'])
        ->name('breakdowns.arrive');
    Route::post('/breakdowns/{breakdown}/start-repair', [BreakdownTransitionController::class, 'startRepair'])
        ->name('breakdowns.start-repair');
    Route::post('/breakdowns/{breakdown}/hold', [BreakdownTransitionController::class, 'hold'])
        ->name('breakdowns.hold');
    Route::post('/breakdowns/{breakdown}/resume', [BreakdownTransitionController::class, 'resume'])
        ->name('breakdowns.resume');
    Route::post('/breakdowns/{breakdown}/complete-repair', [BreakdownTransitionController::class, 'completeRepair'])
        ->name('breakdowns.complete-repair');
    Route::post('/breakdowns/{breakdown}/resume-production', [BreakdownTransitionController::class, 'resumeProduction'])
        ->name('breakdowns.resume-production');
    Route::post('/breakdowns/{breakdown}/close', [BreakdownTransitionController::class, 'close'])
        ->name('breakdowns.close');
    Route::post('/breakdowns/{breakdown}/cancel', [BreakdownTransitionController::class, 'cancel'])
        ->name('breakdowns.cancel');

    Route::post('/breakdowns/{breakdown}/work-order', [BreakdownTransitionController::class, 'raiseWorkOrder'])
        ->name('breakdowns.work-order');
    Route::post('/breakdowns/{breakdown}/impact', [BreakdownTransitionController::class, 'recordImpact'])
        ->name('breakdowns.impact');
    Route::post('/breakdowns/{breakdown}/timestamp', [BreakdownTransitionController::class, 'correctTimestamp'])
        ->name('breakdowns.timestamp');
});
