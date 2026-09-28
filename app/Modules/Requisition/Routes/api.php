<?php

declare(strict_types=1);

use App\Modules\Requisition\Http\Controllers\Api\FloorRequisitionApiController;
use Illuminate\Support\Facades\Route;

/*
 * Floor requisitions (Floor/Store Requisition spec §3) and the store's
 * fulfilment of them (§4–§7).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/floor-requisitions', [FloorRequisitionApiController::class, 'index'])->name('floor-requisitions.index');
    Route::post('/floor-requisitions', [FloorRequisitionApiController::class, 'store'])->name('floor-requisitions.store');
    Route::get('/floor-requisitions/form-options', [FloorRequisitionApiController::class, 'formOptions'])->name('floor-requisitions.form-options');
    Route::get('/floor-requisitions/{requisition}', [FloorRequisitionApiController::class, 'show'])->name('floor-requisitions.show');
    Route::patch('/floor-requisitions/{requisition}', [FloorRequisitionApiController::class, 'update'])->name('floor-requisitions.update');
    Route::post('/floor-requisitions/{requisition}/submit', [FloorRequisitionApiController::class, 'submit'])->name('floor-requisitions.submit');
    Route::post('/floor-requisitions/{requisition}/cancel', [FloorRequisitionApiController::class, 'cancel'])->name('floor-requisitions.cancel');

    Route::get('/floor-requisitions/{requisition}/availability', [FloorRequisitionApiController::class, 'availability'])->name('floor-requisitions.availability');
    Route::post('/floor-requisitions/{requisition}/review', [FloorRequisitionApiController::class, 'review'])->name('floor-requisitions.review');
    Route::post('/floor-requisitions/{requisition}/issue', [FloorRequisitionApiController::class, 'issue'])->name('floor-requisitions.issue');
    Route::post('/floor-requisitions/{requisition}/reject', [FloorRequisitionApiController::class, 'reject'])->name('floor-requisitions.reject');
    Route::post('/floor-requisitions/{requisition}/close', [FloorRequisitionApiController::class, 'close'])->name('floor-requisitions.close');
    Route::post('/floor-requisitions/{requisition}/returns', [FloorRequisitionApiController::class, 'returnStock'])->name('floor-requisitions.returns');
});
