<?php

declare(strict_types=1);

use App\Modules\GeneralStore\Http\Controllers\Api\GsAdminApiController;
use App\Modules\GeneralStore\Http\Controllers\Api\GsDocumentApiController;
use App\Modules\GeneralStore\Http\Controllers\Api\GsReportApiController;
use App\Modules\GeneralStore\Http\Controllers\Api\GsRequisitionApiController;
use App\Modules\GeneralStore\Http\Controllers\Api\GsStockApiController;
use Illuminate\Support\Facades\Route;

/*
 * General Store → Dashboard and Inventory (AnnotechERP 10/02–10/03). The six
 * documents share one set of routes by kind — opening, receive, issue,
 * return, adjustment, transfer — and a document is addressed by its number
 * (RC-2026-000001), never its key. The master lists live in the ERP
 * resource registry (`/erp/store-*`).
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/general-store/dashboard', [GsStockApiController::class, 'dashboard'])->name('general-store.dashboard');
    Route::get('/general-store/current-stock', [GsStockApiController::class, 'current'])->name('general-store.current-stock');
    Route::get('/general-store/current-stock/excel', [GsStockApiController::class, 'currentExcel'])->name('general-store.current-stock.excel');
    Route::get('/general-store/ledger', [GsStockApiController::class, 'ledger'])->name('general-store.ledger');
    Route::get('/general-store/ledger/excel', [GsStockApiController::class, 'ledgerExcel'])->name('general-store.ledger.excel');

    $kinds = 'opening|receive|issue|return|adjustment|transfer';
    Route::get('/general-store/{kind}/options', [GsDocumentApiController::class, 'options'])->where('kind', $kinds)->name('general-store.documents.options');
    Route::get('/general-store/{kind}/documents', [GsDocumentApiController::class, 'index'])->where('kind', $kinds)->name('general-store.documents.index');
    Route::get('/general-store/{kind}/documents/excel', [GsDocumentApiController::class, 'excel'])->where('kind', $kinds)->name('general-store.documents.excel');
    Route::post('/general-store/{kind}/documents', [GsDocumentApiController::class, 'store'])->where('kind', $kinds)->name('general-store.documents.store');
    Route::get('/general-store/documents/{document}', [GsDocumentApiController::class, 'show'])->name('general-store.documents.show');
    Route::get('/general-store/documents/{document}/returnable', [GsDocumentApiController::class, 'returnable'])->name('general-store.documents.returnable');
    Route::post('/general-store/documents/{document}/cancel', [GsDocumentApiController::class, 'cancel'])->name('general-store.documents.cancel');

    // Requisition (10/04–10/05): addressed by its number (REQ-2026-000001).
    Route::get('/general-store/requisitions/options', [GsRequisitionApiController::class, 'options'])->name('general-store.requisitions.options');
    Route::get('/general-store/requisitions', [GsRequisitionApiController::class, 'index'])->name('general-store.requisitions.index');
    Route::post('/general-store/requisitions', [GsRequisitionApiController::class, 'store'])->name('general-store.requisitions.store');
    Route::get('/general-store/requisitions/{requisition}', [GsRequisitionApiController::class, 'show'])->name('general-store.requisitions.show');
    Route::put('/general-store/requisitions/{requisition}', [GsRequisitionApiController::class, 'update'])->name('general-store.requisitions.update');
    Route::post('/general-store/requisitions/{requisition}/submit', [GsRequisitionApiController::class, 'submit'])->name('general-store.requisitions.submit');
    Route::post('/general-store/requisitions/{requisition}/approve', [GsRequisitionApiController::class, 'approve'])->name('general-store.requisitions.approve');
    Route::post('/general-store/requisitions/{requisition}/reject', [GsRequisitionApiController::class, 'reject'])->name('general-store.requisitions.reject');
    Route::post('/general-store/requisitions/{requisition}/cancel', [GsRequisitionApiController::class, 'cancel'])->name('general-store.requisitions.cancel');
    Route::post('/general-store/requisitions/{requisition}/printed', [GsRequisitionApiController::class, 'printed'])->name('general-store.requisitions.printed');
    Route::get('/general-store/requisitions/{requisition}/issuable', [GsRequisitionApiController::class, 'issuable'])->name('general-store.requisitions.issuable');

    // Reports (10/06): one route set for the thirteen.
    Route::get('/general-store/reports/options', [GsReportApiController::class, 'options'])->name('general-store.reports.options');
    Route::get('/general-store/reports/{report}', [GsReportApiController::class, 'show'])->name('general-store.reports.show');
    Route::get('/general-store/reports/{report}/excel', [GsReportApiController::class, 'excel'])->name('general-store.reports.excel');
    Route::get('/general-store/reports/{report}/pdf', [GsReportApiController::class, 'pdf'])->name('general-store.reports.pdf');

    // Administration (10/07).
    Route::get('/general-store/audit-log', [GsAdminApiController::class, 'audit'])->name('general-store.audit-log');
    Route::get('/general-store/audit-log/excel', [GsAdminApiController::class, 'auditExcel'])->name('general-store.audit-log.excel');
    Route::get('/general-store/settings', [GsAdminApiController::class, 'settings'])->name('general-store.settings');
    Route::put('/general-store/settings', [GsAdminApiController::class, 'saveSettings'])->name('general-store.settings.save');
});
