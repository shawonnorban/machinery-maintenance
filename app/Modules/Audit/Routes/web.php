<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\Web\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    // Read-only by construction: there is no store, update or destroy route,
    // because an audit row that can be written through the UI is not evidence.
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
    Route::get('/audit-logs/{log}', [AuditLogController::class, 'show'])->name('audit-logs.show');
});
