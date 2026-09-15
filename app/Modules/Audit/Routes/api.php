<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\Api\AuditLogApiController;
use Illuminate\Support\Facades\Route;

/*
 * The audit trail (API 26). Read-only.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/audit-logs', [AuditLogApiController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{log}', [AuditLogApiController::class, 'show'])->name('audit-logs.show');
});
