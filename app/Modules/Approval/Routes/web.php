<?php

declare(strict_types=1);

use App\Modules\Approval\Http\Controllers\Web\ApprovalController;
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
