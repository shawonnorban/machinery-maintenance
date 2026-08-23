<?php

declare(strict_types=1);

use App\Modules\Approval\Http\Controllers\Web\ApprovalController;
use App\Modules\Approval\Http\Controllers\Web\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
    Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');

    // Decisions are POST: an approval behind a link is one a prefetch can make.
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])
        ->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])
        ->name('approvals.reject');
});

/*
 * The approval chain itself (SRS 14).
 *
 * Deciding who must sign, and above what, is a company decision rather than a
 * factory one, so it sits under settings with the rest of them.
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/approval-workflows', [WorkflowController::class, 'index'])->name('approval-workflows');
    Route::post('/approval-workflows', [WorkflowController::class, 'store'])->name('approval-workflows.store');
    Route::post('/approval-workflows/{workflow}/toggle', [WorkflowController::class, 'toggle'])
        ->name('approval-workflows.toggle');
    Route::post('/approval-workflows/{workflow}/rules', [WorkflowController::class, 'storeRule'])
        ->name('approval-workflows.rules.store');
    Route::delete('/approval-workflows/{workflow}/rules/{rule}', [WorkflowController::class, 'destroyRule'])
        ->name('approval-workflows.rules.destroy');
});
