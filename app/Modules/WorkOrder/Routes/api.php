<?php

declare(strict_types=1);

use App\Modules\WorkOrder\Http\Controllers\Api\TechnicianApiController;
use App\Modules\WorkOrder\Http\Controllers\Api\WorkOrderApiController;
use App\Modules\WorkOrder\Http\Controllers\Api\WorkOrderAssignmentApiController;
use App\Modules\WorkOrder\Http\Controllers\Api\WorkOrderAttachmentApiController;
use App\Modules\WorkOrder\Http\Controllers\Api\WorkOrderChecklistApiController;
use App\Modules\WorkOrder\Http\Controllers\Api\WorkOrderLaborApiController;
use App\Shared\Files\Http\Controllers\Api\FileApiController;
use Illuminate\Support\Facades\Route;

/*
 * Work orders (API 11).
 *
 * The lifecycle is named endpoints rather than a status field, because each
 * step has rules behind it and a refusal has to be able to say which rule.
 */

// The generic file endpoints (API 19.1) live here rather than in a Shared
// module route file, because Shared isn't a module the loader scans (only
// app/Modules/*/Routes is auto-discovered) — the same reason the web
// download route (`attachments.show`) already lives in this file rather than
// in App\Shared\Files.
//
// Unsigned and outside `api.auth` on purpose: a signed URL exists precisely
// to be handed to something with no bearer token, like a browser tab.
Route::middleware('signed')->get('/files/{file}/signed', [FileApiController::class, 'signed'])
    ->name('files.signed');

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    Route::get('/files/{file}', [FileApiController::class, 'show'])->name('files.show');
    Route::get('/files/{file}/download', [FileApiController::class, 'download'])->name('files.download');
    Route::delete('/files/{file}', [FileApiController::class, 'destroy'])->name('files.destroy');

    Route::get('/work-orders/{workOrder}/attachments', [WorkOrderAttachmentApiController::class, 'index'])
        ->name('work-orders.attachments.index');
    Route::post('/work-orders/{workOrder}/attachments', [WorkOrderAttachmentApiController::class, 'store'])
        ->name('work-orders.attachments.store');

    Route::get('/work-orders', [WorkOrderApiController::class, 'index'])->name('work-orders.index');
    Route::post('/work-orders', [WorkOrderApiController::class, 'store'])->name('work-orders.store');

    // Must be registered before GET /work-orders/{workOrder}: a wildcard
    // segment would otherwise swallow "form-options"/"counts" as an
    // attempted id.
    Route::get('/work-orders/form-options', [WorkOrderApiController::class, 'formOptions'])
        ->name('work-orders.form-options');
    Route::get('/work-orders/counts', [WorkOrderApiController::class, 'counts'])
        ->name('work-orders.counts');

    Route::get('/work-orders/{workOrder}', [WorkOrderApiController::class, 'show'])->name('work-orders.show');
    Route::get('/work-orders/{workOrder}/assignable-technicians', [WorkOrderApiController::class, 'assignableTechnicians'])
        ->name('work-orders.assignable-technicians');

    Route::post('/work-orders/{workOrder}/submit-for-approval', [WorkOrderApiController::class, 'submitForApproval'])
        ->name('work-orders.submit-for-approval');
    Route::post('/work-orders/{workOrder}/assign', [WorkOrderAssignmentApiController::class, 'store'])
        ->name('work-orders.assign');
    Route::post('/work-orders/{workOrder}/unassign', [WorkOrderAssignmentApiController::class, 'destroy'])
        ->name('work-orders.unassign');

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
    Route::post('/work-orders/{workOrder}/reopen', [WorkOrderApiController::class, 'reopen'])
        ->name('work-orders.reopen');

    Route::get('/work-orders/{workOrder}/costs', [WorkOrderApiController::class, 'costs'])
        ->name('work-orders.costs');
    Route::get('/work-orders/{workOrder}/history', [WorkOrderApiController::class, 'history'])
        ->name('work-orders.history');

    Route::get('/work-orders/{workOrder}/checklist', [WorkOrderChecklistApiController::class, 'index'])
        ->name('work-orders.checklist.index');
    Route::post('/work-orders/{workOrder}/checklist/results', [WorkOrderChecklistApiController::class, 'store'])
        ->name('work-orders.checklist.results.store');

    Route::get('/work-orders/{workOrder}/labor', [WorkOrderLaborApiController::class, 'index'])
        ->name('work-orders.labor.index');
    Route::post('/work-orders/{workOrder}/labor', [WorkOrderLaborApiController::class, 'store'])
        ->name('work-orders.labor.store');
    Route::delete('/work-orders/{workOrder}/labor/{entry}', [WorkOrderLaborApiController::class, 'destroy'])
        ->name('work-orders.labor.destroy');

    Route::get('/technicians', [TechnicianApiController::class, 'index'])->name('technicians.index');
    Route::post('/technicians', [TechnicianApiController::class, 'store'])->name('technicians.store');
    // Ahead of the {technician} show route below, which would otherwise
    // swallow "form-options" as an attempted technician id.
    Route::get('/technicians/form-options', [TechnicianApiController::class, 'formOptions'])
        ->name('technicians.form-options');
    Route::get('/technicians/{technician}', [TechnicianApiController::class, 'show'])->name('technicians.show');
    Route::patch('/technicians/{technician}', [TechnicianApiController::class, 'update'])
        ->name('technicians.update');
    Route::patch('/technicians/{technician}/active', [TechnicianApiController::class, 'setActive'])
        ->name('technicians.active');
    Route::delete('/technicians/{technician}', [TechnicianApiController::class, 'destroy'])
        ->name('technicians.destroy');
    Route::get('/technicians/{technician}/workload', [TechnicianApiController::class, 'workload'])
        ->name('technicians.workload');
    Route::post('/technicians/{technician}/skills', [TechnicianApiController::class, 'storeSkill'])
        ->name('technicians.skills.store');
    Route::delete('/technicians/{technician}/skills/{skill}', [TechnicianApiController::class, 'destroySkill'])
        ->name('technicians.skills.destroy');
});
