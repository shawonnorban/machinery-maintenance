<?php

declare(strict_types=1);

use App\Modules\Accounting\Http\Controllers\Api\AccountingApiController;
use App\Modules\Accounting\Http\Controllers\Api\ChequeApiController;
use App\Modules\Accounting\Http\Controllers\Api\FinanceReportApiController;
use App\Modules\Accounting\Http\Controllers\Api\MrrBillApiController;
use App\Modules\Accounting\Http\Controllers\Api\PeriodCloseApiController;
use App\Modules\Accounting\Http\Controllers\Api\VoucherApiController;
use App\Modules\Accounting\Http\Controllers\Api\VoucherAuditApiController;
use Illuminate\Support\Facades\Route;

/*
 * Finance & Ledger (AnnotechERP 23). The master lists are screen-kit
 * resources under /erp/{resource}; the chart's tree, maker-checker grants, a
 * bank's branches and a cheque book's leaves live here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/accounting/lookups', [AccountingApiController::class, 'lookups'])->name('accounting.lookups');
    Route::get('/accounting/tree', [AccountingApiController::class, 'tree'])->name('accounting.tree');
    Route::get('/accounting/tree/{account}', [AccountingApiController::class, 'childrenOf'])->name('accounting.tree.children');
    Route::get('/accounting/voucher-types', [AccountingApiController::class, 'voucherTypes'])->name('accounting.voucher-types');
    Route::get('/accounting/voucher-types/{type}', [AccountingApiController::class, 'grantsOf'])->name('accounting.grants');
    Route::put('/accounting/voucher-types/{type}', [AccountingApiController::class, 'saveGrants'])->name('accounting.grants.save');
    Route::get('/accounting/banks', [AccountingApiController::class, 'banks'])->name('accounting.banks');
    Route::get('/accounting/banks/{bank}', [AccountingApiController::class, 'branchesOf'])->name('accounting.branches');
    Route::put('/accounting/banks/{bank}', [AccountingApiController::class, 'saveBranchesOf'])->name('accounting.branches.save');
    Route::get('/accounting/cheque-books', [AccountingApiController::class, 'chequeBooks'])->name('accounting.cheque-books');
    Route::get('/accounting/cheque-books/{book}', [AccountingApiController::class, 'leavesOf'])->name('accounting.leaves');
    Route::put('/accounting/cheque-books/{book}', [AccountingApiController::class, 'saveLeavesOf'])->name('accounting.leaves.save');

    // Voucher Entry (AnnotechERP 23/02): drafts, posting control, the statutory register, combined MRR bills.
    Route::get('/accounting/vouchers/options', [VoucherApiController::class, 'options'])->name('accounting.vouchers.options');
    Route::get('/accounting/vouchers/last-no', [VoucherApiController::class, 'lastNo'])->name('accounting.vouchers.last');
    Route::get('/accounting/vouchers/posting', [VoucherApiController::class, 'posting'])->name('accounting.vouchers.posting');
    Route::get('/accounting/vouchers/statutory', [VoucherApiController::class, 'statutory'])->name('accounting.vouchers.statutory');
    Route::get('/accounting/vouchers', [VoucherApiController::class, 'index'])->name('accounting.vouchers');
    Route::post('/accounting/vouchers', [VoucherApiController::class, 'save'])->name('accounting.vouchers.create');
    Route::get('/accounting/vouchers/{id}', [VoucherApiController::class, 'show'])->name('accounting.vouchers.show');
    Route::put('/accounting/vouchers/{id}', [VoucherApiController::class, 'save'])->name('accounting.vouchers.update');
    Route::delete('/accounting/vouchers/{id}', [VoucherApiController::class, 'destroy'])->name('accounting.vouchers.delete');
    Route::post('/accounting/vouchers/{id}/post', [VoucherApiController::class, 'post'])->name('accounting.vouchers.post');
    Route::post('/accounting/vouchers/{id}/unpost', [VoucherApiController::class, 'unpost'])->name('accounting.vouchers.unpost');
    Route::get('/accounting/mrr-bills/options', [MrrBillApiController::class, 'options'])->name('accounting.mrr-bills.options');
    Route::get('/accounting/mrr-bills/mrrs', [MrrBillApiController::class, 'mrrs'])->name('accounting.mrr-bills.mrrs');
    Route::get('/accounting/mrr-bills', [MrrBillApiController::class, 'index'])->name('accounting.mrr-bills');
    Route::post('/accounting/mrr-bills', [MrrBillApiController::class, 'save'])->name('accounting.mrr-bills.create');
    Route::get('/accounting/mrr-bills/{id}', [MrrBillApiController::class, 'show'])->name('accounting.mrr-bills.show');
    Route::put('/accounting/mrr-bills/{id}', [MrrBillApiController::class, 'save'])->name('accounting.mrr-bills.update');
    Route::delete('/accounting/mrr-bills/{id}', [MrrBillApiController::class, 'destroy'])->name('accounting.mrr-bills.delete');
    Route::post('/accounting/mrr-bills/{id}/final', [MrrBillApiController::class, 'final'])->name('accounting.mrr-bills.final');
    Route::post('/accounting/mrr-bills/{id}/fixed-asset', [MrrBillApiController::class, 'fixedAsset'])->name('accounting.mrr-bills.fixed-asset');

    // Period Close (AnnotechERP 23/04): stock value closing, bank reconciliation, fiscal year closing.
    Route::get('/accounting/period/stock/options', [PeriodCloseApiController::class, 'stockOptions'])->name('accounting.period.stock.options');
    Route::get('/accounting/period/stock/worksheet', [PeriodCloseApiController::class, 'worksheet'])->name('accounting.period.stock.worksheet');
    Route::put('/accounting/period/stock/worksheet', [PeriodCloseApiController::class, 'saveWorksheet'])->name('accounting.period.stock.worksheet.save');
    Route::get('/accounting/period/stock', [PeriodCloseApiController::class, 'stockIndex'])->name('accounting.period.stock');
    Route::post('/accounting/period/stock', [PeriodCloseApiController::class, 'stockStore'])->name('accounting.period.stock.create');
    Route::put('/accounting/period/stock/{id}', [PeriodCloseApiController::class, 'stockUpdate'])->name('accounting.period.stock.update');
    Route::delete('/accounting/period/stock/{id}', [PeriodCloseApiController::class, 'stockDestroy'])->name('accounting.period.stock.delete');
    Route::get('/accounting/period/bank/options', [PeriodCloseApiController::class, 'bankOptions'])->name('accounting.period.bank.options');
    Route::get('/accounting/period/bank/board', [PeriodCloseApiController::class, 'bankBoard'])->name('accounting.period.bank.board');
    Route::get('/accounting/period/bank/candidates', [PeriodCloseApiController::class, 'candidates'])->name('accounting.period.bank.candidates');
    Route::get('/accounting/period/bank', [PeriodCloseApiController::class, 'bankIndex'])->name('accounting.period.bank');
    Route::post('/accounting/period/bank', [PeriodCloseApiController::class, 'addBankLine'])->name('accounting.period.bank.create');
    Route::put('/accounting/period/bank/accounts/{account}', [PeriodCloseApiController::class, 'saveBank'])->name('accounting.period.bank.save');
    Route::delete('/accounting/period/bank/{line}', [PeriodCloseApiController::class, 'removeBankLine'])->name('accounting.period.bank.delete');
    Route::get('/accounting/period/fiscal-years/options', [PeriodCloseApiController::class, 'fyOptions'])->name('accounting.period.fy.options');
    Route::get('/accounting/period/fiscal-years', [PeriodCloseApiController::class, 'fyIndex'])->name('accounting.period.fy');
    Route::post('/accounting/period/fiscal-years', [PeriodCloseApiController::class, 'closeYear'])->name('accounting.period.fy.close');
    Route::delete('/accounting/period/fiscal-years/{id}', [PeriodCloseApiController::class, 'reopenYear'])->name('accounting.period.fy.reopen');

    // Cheque Management (AnnotechERP 23/05): cheque issue and cheque cancellation.
    Route::get('/accounting/cheques/options', [ChequeApiController::class, 'options'])->name('accounting.cheques.options');
    Route::get('/accounting/cheques/leaves', [ChequeApiController::class, 'leaves'])->name('accounting.cheques.leaves');
    Route::get('/accounting/cheques/used', [ChequeApiController::class, 'used'])->name('accounting.cheques.used');
    Route::get('/accounting/cheques/cancellations', [ChequeApiController::class, 'cancellations'])->name('accounting.cheques.cancellations');
    Route::get('/accounting/cheques/cancellations/leaves', [ChequeApiController::class, 'cancellable'])->name('accounting.cheques.cancellable');
    Route::post('/accounting/cheques/cancellations', [ChequeApiController::class, 'cancel'])->name('accounting.cheques.cancel');
    Route::delete('/accounting/cheques/cancellations/{id}', [ChequeApiController::class, 'uncancel'])->name('accounting.cheques.uncancel');
    Route::get('/accounting/cheques', [ChequeApiController::class, 'index'])->name('accounting.cheques');
    Route::post('/accounting/cheques', [ChequeApiController::class, 'save'])->name('accounting.cheques.create');
    Route::get('/accounting/cheques/{id}', [ChequeApiController::class, 'show'])->name('accounting.cheques.show');
    Route::put('/accounting/cheques/{id}', [ChequeApiController::class, 'save'])->name('accounting.cheques.update');
    Route::delete('/accounting/cheques/{id}', [ChequeApiController::class, 'destroy'])->name('accounting.cheques.delete');
    Route::post('/accounting/cheques/{id}/print', [ChequeApiController::class, 'print'])->name('accounting.cheques.print');

    // Voucher Audit (AnnotechERP 23/06): read-only registers over the voucher log.
    Route::get('/accounting/voucher-audit', [VoucherAuditApiController::class, 'index'])->name('accounting.voucher-audit');

    // Reports (AnnotechERP 23/07): the five report screens — run a report off the posted ledger; the runs kept.
    Route::get('/accounting/reports/options', [FinanceReportApiController::class, 'options'])->name('accounting.reports.options');
    Route::post('/accounting/reports/run', [FinanceReportApiController::class, 'run'])->name('accounting.reports.run');
    Route::get('/accounting/reports/runs', [FinanceReportApiController::class, 'runs'])->name('accounting.reports.runs');
    Route::delete('/accounting/reports/runs/{id}', [FinanceReportApiController::class, 'destroyRun'])->name('accounting.reports.runs.delete');
});
