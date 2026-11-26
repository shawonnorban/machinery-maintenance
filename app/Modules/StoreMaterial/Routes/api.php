<?php

declare(strict_types=1);

use App\Modules\StoreMaterial\Http\Controllers\Api\GrnApiController;
use App\Modules\StoreMaterial\Http\Controllers\Api\IssueApiController;
use App\Modules\StoreMaterial\Http\Controllers\Api\StockApiController;
use App\Modules\StoreMaterial\Http\Controllers\Api\TraceApiController;
use Illuminate\Support\Facades\Route;

/*
 * Inventory & Stores → Store & Material (AnnotechERP 09): In-House / GRN
 * against an approved PR or direct, the Fabric and the Trims & Accessories
 * warehouses, Material Out and Return, Stock Adjustment, the Stock Ledger,
 * Traceability, Reports and the Dashboard. A GRN is addressed by its
 * number (GRN-2026-00001), a requisition by its own (PR-2026-00001).
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/store-material/filters', [StockApiController::class, 'filters'])->name('store-material.filters');
    Route::get('/store-material/dashboard', [StockApiController::class, 'dashboard'])->name('store-material.dashboard');
    Route::get('/store-material/fabric-warehouse', [StockApiController::class, 'fabric'])->name('store-material.fabric-warehouse');
    Route::get('/store-material/trims-warehouse', [StockApiController::class, 'trims'])->name('store-material.trims-warehouse');
    Route::get('/store-material/stock-ledger', [StockApiController::class, 'ledger'])->name('store-material.stock-ledger');
    Route::get('/store-material/employee', [StockApiController::class, 'employee'])->name('store-material.employee');

    Route::get('/store-material/grns/options', [GrnApiController::class, 'options'])->name('store-material.grns.options');
    Route::get('/store-material/requisitions/{pr}', [GrnApiController::class, 'requisition'])->name('store-material.requisitions.show');
    Route::post('/store-material/grns/against-pr', [GrnApiController::class, 'storeAgainstPr'])->name('store-material.grns.against-pr');
    Route::post('/store-material/grns/direct', [GrnApiController::class, 'storeDirect'])->name('store-material.grns.direct');
    Route::get('/store-material/grns/{grn}', [GrnApiController::class, 'show'])->name('store-material.grns.show');
    Route::post('/store-material/grns/{grn}/reverse', [GrnApiController::class, 'reverse'])->name('store-material.grns.reverse');
    Route::post('/store-material/grns/{grn}/attachments', [GrnApiController::class, 'attach'])->name('store-material.grns.attach');

    Route::get('/store-material/issue-options', [IssueApiController::class, 'options'])->name('store-material.issue-options');
    Route::post('/store-material/material-outs', [IssueApiController::class, 'storeOut'])->name('store-material.material-outs.store');
    Route::get('/store-material/material-outs/{mo}', [IssueApiController::class, 'showOut'])->name('store-material.material-outs.show');
    Route::post('/store-material/material-returns', [IssueApiController::class, 'storeReturn'])->name('store-material.material-returns.store');
    Route::post('/store-material/adjustments', [IssueApiController::class, 'storeAdjustment'])->name('store-material.adjustments.store');
    Route::post('/store-material/adjustments/{adjustment}/decide', [IssueApiController::class, 'decideAdjustment'])->name('store-material.adjustments.decide');
    Route::put('/store-material/min-levels', [IssueApiController::class, 'saveMinLevel'])->name('store-material.min-levels.save');

    Route::get('/store-material/traceability', [TraceApiController::class, 'trace'])->name('store-material.traceability');
    Route::get('/store-material/reports/{report}', [TraceApiController::class, 'report'])->name('store-material.reports.show');
    Route::get('/store-material/reports/{report}/excel', [TraceApiController::class, 'excel'])->name('store-material.reports.excel');
    Route::get('/store-material/reports/{report}/pdf', [TraceApiController::class, 'pdf'])->name('store-material.reports.pdf');
});
