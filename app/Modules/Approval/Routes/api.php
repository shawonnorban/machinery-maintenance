<?php

declare(strict_types=1);

use App\Modules\Approval\Http\Controllers\Api\ApprovalCenterApiController;
use App\Modules\Approval\Http\Controllers\Api\ApprovalRequestApiController;
use App\Modules\Approval\Http\Controllers\Api\ApprovalTicketApiController;
use App\Modules\Approval\Http\Controllers\Api\ApprovalWorkflowApiController;
use App\Modules\Approval\Http\Controllers\Api\WorkflowDesignerApiController;
use Illuminate\Support\Facades\Route;

/*
 * Approvals (API 27).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    // Approval Center (AnnotechERP 04) and the Approval Workflow Designer (02/03).
    Route::get('/approval-center/requests', [ApprovalCenterApiController::class, 'index'])->name('approval-center.requests.index');
    Route::get('/approval-center/requests/{approval}', [ApprovalCenterApiController::class, 'show'])->name('approval-center.requests.show');
    Route::post('/approval-center/requests/{approval}/approve', [ApprovalCenterApiController::class, 'approve'])->name('approval-center.requests.approve');
    Route::post('/approval-center/requests/{approval}/reject', [ApprovalCenterApiController::class, 'reject'])->name('approval-center.requests.reject');
    Route::post('/approval-center/requests/{approval}/return', [ApprovalCenterApiController::class, 'sendBack'])->name('approval-center.requests.return');
    Route::get('/approval-center/history', [ApprovalCenterApiController::class, 'history'])->name('approval-center.history');
    Route::get('/approval-center/dashboard', [ApprovalCenterApiController::class, 'dashboard'])->name('approval-center.dashboard');
    Route::get('/approval-center/form-options', [ApprovalCenterApiController::class, 'formOptions'])->name('approval-center.form-options');
    Route::post('/approval-center/tickets', [ApprovalTicketApiController::class, 'store'])->name('approval-center.tickets.store');
    Route::get('/approval-center/tickets/{ticket}', [ApprovalTicketApiController::class, 'show'])->name('approval-center.tickets.show');
    Route::patch('/approval-center/tickets/{ticket}', [ApprovalTicketApiController::class, 'update'])->name('approval-center.tickets.update');
    Route::delete('/approval-center/tickets/{ticket}', [ApprovalTicketApiController::class, 'destroy'])->name('approval-center.tickets.destroy');
    Route::post('/approval-center/tickets/{ticket}/submit', [ApprovalTicketApiController::class, 'submit'])->name('approval-center.tickets.submit');
    Route::post('/approval-center/tickets/{ticket}/cancel', [ApprovalTicketApiController::class, 'cancel'])->name('approval-center.tickets.cancel');
    Route::get('/approval-center/workflows', [WorkflowDesignerApiController::class, 'index'])->name('approval-center.workflows.index');
    Route::post('/approval-center/workflows', [WorkflowDesignerApiController::class, 'store'])->name('approval-center.workflows.store');
    Route::patch('/approval-center/workflows/{workflow}', [WorkflowDesignerApiController::class, 'update'])->name('approval-center.workflows.update');
    Route::get('/approval-center/workflows/{workflow}/steps', [WorkflowDesignerApiController::class, 'steps'])->name('approval-center.workflows.steps');
    Route::put('/approval-center/workflows/{workflow}/steps', [WorkflowDesignerApiController::class, 'saveSteps'])->name('approval-center.workflows.steps.save');
    Route::get('/approval-center/steps/{rule}/users', [WorkflowDesignerApiController::class, 'stepUsers'])->name('approval-center.steps.users');
    Route::put('/approval-center/steps/{rule}/users', [WorkflowDesignerApiController::class, 'saveStepUsers'])->name('approval-center.steps.users.save');

    Route::get('/approval-requests', [ApprovalRequestApiController::class, 'index'])
        ->name('approval-requests.index');
    Route::get('/approval-requests/{approval}', [ApprovalRequestApiController::class, 'show'])
        ->name('approval-requests.show');
    Route::post('/approval-requests/{approval}/approve', [ApprovalRequestApiController::class, 'approve'])
        ->name('approval-requests.approve');
    Route::post('/approval-requests/{approval}/reject', [ApprovalRequestApiController::class, 'reject'])
        ->name('approval-requests.reject');

    Route::get('/approval-workflows', [ApprovalWorkflowApiController::class, 'index'])
        ->name('approval-workflows.index');
    Route::post('/approval-workflows', [ApprovalWorkflowApiController::class, 'store'])
        ->name('approval-workflows.store');
    Route::post('/approval-workflows/{workflow}/toggle', [ApprovalWorkflowApiController::class, 'toggle'])
        ->name('approval-workflows.toggle');
    Route::post('/approval-workflows/{workflow}/rules', [ApprovalWorkflowApiController::class, 'storeRule'])
        ->name('approval-workflows.rules.store');
    Route::delete('/approval-workflows/{workflow}/rules/{rule}', [ApprovalWorkflowApiController::class, 'destroyRule'])
        ->name('approval-workflows.rules.destroy');
});
