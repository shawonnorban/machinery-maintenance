<?php

declare(strict_types=1);

use App\Modules\WorkOrder\Http\Controllers\Web\ChecklistExecutionController;
use App\Modules\WorkOrder\Http\Controllers\Web\LaborEntryController;
use App\Modules\WorkOrder\Http\Controllers\Web\MyWorkController;
use App\Modules\WorkOrder\Http\Controllers\Web\TechnicianController;
use App\Modules\WorkOrder\Http\Controllers\Web\WorkOrderController;
use App\Modules\WorkOrder\Http\Controllers\Web\WorkOrderTransitionController;
use App\Shared\Files\Http\Controllers\FileAttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/my-work', [MyWorkController::class, 'index'])->name('my-work');

    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');

    /*
     * Transitions are POST, never GET. A state change behind a link is a state
     * change a crawler, a prefetch or a back button can make.
     */
    Route::post('/work-orders/{workOrder}/schedule', [WorkOrderTransitionController::class, 'schedule'])
        ->name('work-orders.schedule');
    Route::post('/work-orders/{workOrder}/assign', [WorkOrderTransitionController::class, 'assign'])
        ->name('work-orders.assign');
    Route::post('/work-orders/{workOrder}/unassign', [WorkOrderTransitionController::class, 'unassign'])
        ->name('work-orders.unassign');
    Route::post('/work-orders/{workOrder}/start', [WorkOrderTransitionController::class, 'start'])
        ->name('work-orders.start');
    Route::post('/work-orders/{workOrder}/hold', [WorkOrderTransitionController::class, 'hold'])
        ->name('work-orders.hold');
    Route::post('/work-orders/{workOrder}/resume', [WorkOrderTransitionController::class, 'resume'])
        ->name('work-orders.resume');
    Route::post('/work-orders/{workOrder}/complete', [WorkOrderTransitionController::class, 'complete'])
        ->name('work-orders.complete');
    Route::post('/work-orders/{workOrder}/verify', [WorkOrderTransitionController::class, 'verify'])
        ->name('work-orders.verify');
    Route::post('/work-orders/{workOrder}/close', [WorkOrderTransitionController::class, 'close'])
        ->name('work-orders.close');
    Route::post('/work-orders/{workOrder}/cancel', [WorkOrderTransitionController::class, 'cancel'])
        ->name('work-orders.cancel');
    Route::post('/work-orders/{workOrder}/reopen', [WorkOrderTransitionController::class, 'reopen'])
        ->name('work-orders.reopen');

    Route::post('/work-orders/{workOrder}/checklist', [ChecklistExecutionController::class, 'store'])
        ->name('work-orders.checklist.store');

    Route::post('/work-orders/{workOrder}/labor', [LaborEntryController::class, 'store'])
        ->name('work-orders.labor.store');
    Route::delete('/work-orders/{workOrder}/labor/{entry}', [LaborEntryController::class, 'destroy'])
        ->name('work-orders.labor.destroy');

    Route::get('/attachments/{attachment}', [FileAttachmentController::class, 'show'])
        ->name('attachments.show');
});

/*
 * The maintenance roster (SRS 25). No money on any of these screens:
 * technicians are salaried, so the record says who to send, not what an hour
 * costs.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/technicians', [TechnicianController::class, 'index'])->name('technicians.index');
    Route::get('/technicians/create', [TechnicianController::class, 'create'])->name('technicians.create');
    Route::post('/technicians', [TechnicianController::class, 'store'])->name('technicians.store');
    Route::get('/technicians/{technician}/edit', [TechnicianController::class, 'edit'])->name('technicians.edit');
    Route::patch('/technicians/{technician}', [TechnicianController::class, 'update'])->name('technicians.update');
    Route::post('/technicians/{technician}/toggle', [TechnicianController::class, 'toggle'])->name('technicians.toggle');
    // Only somebody with no work behind them: a repair whose technician cannot
    // be named is a repair nobody can ask about.
    Route::delete('/technicians/{technician}', [TechnicianController::class, 'destroy'])->name('technicians.destroy');
});
