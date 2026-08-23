<?php

declare(strict_types=1);

use App\Modules\WorkOrder\Http\Controllers\Api\WorkOrderApiController;
use Illuminate\Support\Facades\Route;

/*
 * Work orders (API 11).
 *
 * The lifecycle is named endpoints rather than a status field, because each
 * step has rules behind it and a refusal has to be able to say which rule.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/work-orders', [WorkOrderApiController::class, 'index'])->name('work-orders.index');
    Route::get('/work-orders/{workOrder}', [WorkOrderApiController::class, 'show'])->name('work-orders.show');

    Route::post('/work-orders/{workOrder}/start', [WorkOrderApiController::class, 'start'])
        ->name('work-orders.start');
    Route::post('/work-orders/{workOrder}/hold', [WorkOrderApiController::class, 'hold'])
        ->name('work-orders.hold');
    Route::post('/work-orders/{workOrder}/resume', [WorkOrderApiController::class, 'resume'])
        ->name('work-orders.resume');
    Route::post('/work-orders/{workOrder}/complete', [WorkOrderApiController::class, 'complete'])
        ->name('work-orders.complete');
    Route::post('/work-orders/{workOrder}/verify', [WorkOrderApiController::class, 'verify'])
        ->name('work-orders.verify');
    Route::post('/work-orders/{workOrder}/close', [WorkOrderApiController::class, 'close'])
        ->name('work-orders.close');
    Route::post('/work-orders/{workOrder}/cancel', [WorkOrderApiController::class, 'cancel'])
        ->name('work-orders.cancel');
});
