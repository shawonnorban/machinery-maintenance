<?php

declare(strict_types=1);

use App\Modules\GatePass\Http\Controllers\Api\GatePassApiController;
use App\Modules\GatePass\Http\Controllers\Api\GatePassReportApiController;
use Illuminate\Support\Facades\Route;

/*
 * Gate Pass → Dashboard, Reports, Create, Approval, Out, In, Return
 * Reconciliation and List (AnnotechERP 11/02–11/04). A pass is addressed by
 * its number (GP-2026-000001), never its key. The master lists live in the
 * ERP resource registry (`/erp/gatepass-*`).
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/gate-pass/dashboard', [GatePassReportApiController::class, 'dashboard'])->name('gate-pass.dashboard');
    Route::get('/gate-pass/reports/options', [GatePassReportApiController::class, 'options'])->name('gate-pass.reports.options');
    Route::get('/gate-pass/reports/{report}', [GatePassReportApiController::class, 'show'])->name('gate-pass.reports.show');
    Route::get('/gate-pass/reports/{report}/excel', [GatePassReportApiController::class, 'excel'])->name('gate-pass.reports.excel');
    Route::get('/gate-pass/reports/{report}/pdf', [GatePassReportApiController::class, 'pdf'])->name('gate-pass.reports.pdf');

    Route::get('/gate-pass/options', [GatePassApiController::class, 'options'])->name('gate-pass.options');
    Route::get('/gate-pass/employees/{card}', [GatePassApiController::class, 'employee'])->name('gate-pass.employee');
    Route::get('/gate-pass/passes', [GatePassApiController::class, 'index'])->name('gate-pass.passes.index');
    Route::get('/gate-pass/passes/excel', [GatePassApiController::class, 'excel'])->name('gate-pass.passes.excel');
    Route::post('/gate-pass/passes', [GatePassApiController::class, 'store'])->name('gate-pass.passes.store');
    Route::get('/gate-pass/passes/{pass}', [GatePassApiController::class, 'show'])->name('gate-pass.passes.show');
    Route::put('/gate-pass/passes/{pass}', [GatePassApiController::class, 'update'])->name('gate-pass.passes.update');
    Route::post('/gate-pass/passes/{pass}/submit', [GatePassApiController::class, 'submit'])->name('gate-pass.passes.submit');
    Route::post('/gate-pass/passes/{pass}/approve', [GatePassApiController::class, 'approve'])->name('gate-pass.passes.approve');
    Route::post('/gate-pass/passes/{pass}/reject', [GatePassApiController::class, 'reject'])->name('gate-pass.passes.reject');
    Route::post('/gate-pass/passes/{pass}/send-back', [GatePassApiController::class, 'sendBack'])->name('gate-pass.passes.send-back');
    Route::post('/gate-pass/passes/{pass}/cancel', [GatePassApiController::class, 'cancel'])->name('gate-pass.passes.cancel');
    Route::post('/gate-pass/passes/{pass}/out', [GatePassApiController::class, 'out'])->name('gate-pass.passes.out');
    Route::post('/gate-pass/passes/{pass}/in', [GatePassApiController::class, 'in'])->name('gate-pass.passes.in');
    Route::post('/gate-pass/passes/{pass}/close', [GatePassApiController::class, 'close'])->name('gate-pass.passes.close');
});
