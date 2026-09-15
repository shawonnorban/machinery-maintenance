<?php

declare(strict_types=1);

use App\Modules\Approval\Http\Controllers\Api\ApprovalRequestApiController;
use App\Modules\Approval\Http\Controllers\Api\ApprovalWorkflowApiController;
use Illuminate\Support\Facades\Route;

/*
 * Approvals (API 27).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
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
