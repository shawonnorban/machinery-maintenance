<?php

declare(strict_types=1);

use App\Modules\TradeExport\Http\Controllers\Api\CashIncentiveApiController;
use App\Modules\TradeExport\Http\Controllers\Api\CmPaymentApiController;
use App\Modules\TradeExport\Http\Controllers\Api\ExportBillApiController;
use App\Modules\TradeExport\Http\Controllers\Api\ExportCreditApiController;
use App\Modules\TradeExport\Http\Controllers\Api\ImportBillApiController;
use App\Modules\TradeExport\Http\Controllers\Api\ImportCreditApiController;
use App\Modules\TradeExport\Http\Controllers\Api\TradeExportApiController;
use App\Modules\TradeExport\Http\Controllers\Api\TradeReportApiController;
use Illuminate\Support\Facades\Route;

/*
 * Trade & Export (AnnotechERP 22). The master lists are screen-kit resources
 * under /erp/{resource}; what their forms pick from lives here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/tradeexport/lookups', [TradeExportApiController::class, 'lookups'])->name('tradeexport.lookups');

    // Operations (AnnotechERP 22/02): the export paper chain.
    Route::prefix('tradeexport/ops')->group(function (): void {
        Route::get('/options', [ExportCreditApiController::class, 'options'])->name('tradeop.options');
        Route::get('/lcs/{id}/b2b', [ImportCreditApiController::class, 'b2bSummary'])->name('tradeop.lc.b2b');

        // Import (AnnotechERP 22/03), and the two declarations of Operations that read it.
        Route::get('/import-options', [ImportCreditApiController::class, 'options'])->name('tradeop.imp.options');
        Route::get('/imp-pis', [ImportCreditApiController::class, 'pis'])->name('tradeop.ipi');
        Route::post('/imp-pis', [ImportCreditApiController::class, 'savePi'])->name('tradeop.ipi.create');
        Route::get('/imp-pis/pos', [ImportCreditApiController::class, 'supplierPos'])->name('tradeop.ipi.pos');
        Route::get('/imp-pis/po-lines/{kind}/{po}', [ImportCreditApiController::class, 'poLines'])->name('tradeop.ipi.lines');
        Route::get('/imp-pis/{id}', [ImportCreditApiController::class, 'pi'])->name('tradeop.ipi.show');
        Route::get('/imp-pis/{id}/row', [ImportCreditApiController::class, 'piForLc'])->name('tradeop.ipi.row');
        Route::put('/imp-pis/{id}', [ImportCreditApiController::class, 'savePi'])->name('tradeop.ipi.update');
        Route::post('/imp-pis/{id}/approve', [ImportCreditApiController::class, 'piApprove'])->name('tradeop.ipi.approve');
        Route::delete('/imp-pis/{id}', [ImportCreditApiController::class, 'piDestroy'])->name('tradeop.ipi.delete');
        Route::get('/imp-lcs', [ImportCreditApiController::class, 'lcs'])->name('tradeop.ilc');
        Route::post('/imp-lcs', [ImportCreditApiController::class, 'saveLc'])->name('tradeop.ilc.create');
        Route::get('/imp-lcs/{id}', [ImportCreditApiController::class, 'lc'])->name('tradeop.ilc.show');
        Route::put('/imp-lcs/{id}', [ImportCreditApiController::class, 'saveLc'])->name('tradeop.ilc.update');
        Route::delete('/imp-lcs/{id}', [ImportCreditApiController::class, 'lcDestroy'])->name('tradeop.ilc.delete');
        Route::get('/entries', [ImportBillApiController::class, 'entries'])->name('tradeop.be');
        Route::post('/entries', [ImportBillApiController::class, 'saveEntry'])->name('tradeop.be.create');
        Route::get('/entries/source/{lc}', [ImportBillApiController::class, 'entrySource'])->name('tradeop.be.source');
        Route::get('/entries/{id}', [ImportBillApiController::class, 'entry'])->name('tradeop.be.show');
        Route::put('/entries/{id}', [ImportBillApiController::class, 'saveEntry'])->name('tradeop.be.update');
        Route::post('/entries/{id}/attachment', [ImportBillApiController::class, 'entryAttach'])->name('tradeop.be.attach');
        Route::post('/entries/{id}/final', [ImportBillApiController::class, 'entryFinal'])->name('tradeop.be.final');
        Route::delete('/entries/{id}', [ImportBillApiController::class, 'entryDestroy'])->name('tradeop.be.delete');
        Route::get('/acceptances', [ImportBillApiController::class, 'acceptances'])->name('tradeop.ac');
        Route::post('/acceptances', [ImportBillApiController::class, 'saveAcceptance'])->name('tradeop.ac.create');
        Route::get('/acceptances/source/{lc}', [ImportBillApiController::class, 'acceptanceSource'])->name('tradeop.ac.source');
        Route::get('/acceptances/{id}', [ImportBillApiController::class, 'acceptance'])->name('tradeop.ac.show');
        Route::put('/acceptances/{id}', [ImportBillApiController::class, 'saveAcceptance'])->name('tradeop.ac.update');
        Route::delete('/acceptances/{id}', [ImportBillApiController::class, 'acceptanceDestroy'])->name('tradeop.ac.delete');
        Route::get('/uds', [ImportBillApiController::class, 'uds'])->name('tradeop.ud');
        Route::get('/uds/lcs', [ImportBillApiController::class, 'udLcs'])->name('tradeop.ud.lcs');
        Route::post('/uds', [ImportBillApiController::class, 'saveUd'])->name('tradeop.ud.create');
        Route::get('/uds/{id}', [ImportBillApiController::class, 'ud'])->name('tradeop.ud.show');
        Route::put('/uds/{id}', [ImportBillApiController::class, 'saveUd'])->name('tradeop.ud.update');
        Route::post('/uds/{id}/final', [ImportBillApiController::class, 'udFinal'])->name('tradeop.ud.final');
        Route::delete('/uds/{id}', [ImportBillApiController::class, 'udDestroy'])->name('tradeop.ud.delete');
        Route::get('/ud-transfers', [ImportBillApiController::class, 'transfers'])->name('tradeop.udt');
        Route::get('/ud-transfers/source', [ImportBillApiController::class, 'transferSource'])->name('tradeop.udt.source');
        Route::post('/ud-transfers', [ImportBillApiController::class, 'saveTransfer'])->name('tradeop.udt.create');
        Route::get('/ud-transfers/{id}', [ImportBillApiController::class, 'transfer'])->name('tradeop.udt.show');
        Route::put('/ud-transfers/{id}', [ImportBillApiController::class, 'saveTransfer'])->name('tradeop.udt.update');
        Route::delete('/ud-transfers/{id}', [ImportBillApiController::class, 'transferDestroy'])->name('tradeop.udt.delete');

        Route::get('/pis', [ExportCreditApiController::class, 'pis'])->name('tradeop.pi');
        Route::post('/pis', [ExportCreditApiController::class, 'savePi'])->name('tradeop.pi.create');
        Route::get('/pis/{id}', [ExportCreditApiController::class, 'pi'])->name('tradeop.pi.show');
        Route::get('/pis/{id}/lines', [ExportCreditApiController::class, 'piLines'])->name('tradeop.pi.lines');
        Route::put('/pis/{id}', [ExportCreditApiController::class, 'savePi'])->name('tradeop.pi.update');
        Route::delete('/pis/{id}', [ExportCreditApiController::class, 'piDestroy'])->name('tradeop.pi.delete');
        Route::get('/lcs', [ExportCreditApiController::class, 'lcs'])->name('tradeop.lc');
        Route::post('/lcs', [ExportCreditApiController::class, 'saveLc'])->name('tradeop.lc.create');
        Route::get('/lcs/{id}', [ExportCreditApiController::class, 'lc'])->name('tradeop.lc.show');
        Route::put('/lcs/{id}', [ExportCreditApiController::class, 'saveLc'])->name('tradeop.lc.update');
        Route::delete('/lcs/{id}', [ExportCreditApiController::class, 'lcDestroy'])->name('tradeop.lc.delete');

        Route::get('/invoices', [ExportBillApiController::class, 'invoices'])->name('tradeop.inv');
        Route::post('/invoices', [ExportBillApiController::class, 'saveInvoice'])->name('tradeop.inv.create');
        Route::get('/invoices/source/{lc}', [ExportBillApiController::class, 'invoiceSource'])->name('tradeop.inv.source');
        Route::get('/invoices/order/{order}', [ExportBillApiController::class, 'invoiceOrder'])->name('tradeop.inv.order');
        Route::get('/invoices/{id}', [ExportBillApiController::class, 'invoice'])->name('tradeop.inv.show');
        Route::put('/invoices/{id}', [ExportBillApiController::class, 'saveInvoice'])->name('tradeop.inv.update');
        Route::post('/invoices/{id}/final', [ExportBillApiController::class, 'invoiceFinal'])->name('tradeop.inv.final');
        Route::delete('/invoices/{id}', [ExportBillApiController::class, 'invoiceDestroy'])->name('tradeop.inv.delete');

        Route::get('/lodgements', [ExportBillApiController::class, 'lodgements'])->name('tradeop.lodge');
        Route::get('/lodgements/invoices', [ExportBillApiController::class, 'lodgeable'])->name('tradeop.lodge.invoices');
        Route::post('/lodgements', [ExportBillApiController::class, 'saveLodgement'])->name('tradeop.lodge.create');
        Route::get('/lodgements/{id}', [ExportBillApiController::class, 'lodgement'])->name('tradeop.lodge.show');
        Route::put('/lodgements/{id}', [ExportBillApiController::class, 'saveLodgement'])->name('tradeop.lodge.update');
        Route::post('/lodgements/{id}/attachment', [ExportBillApiController::class, 'lodgementAttach'])->name('tradeop.lodge.attach');
        Route::post('/lodgements/{id}/final', [ExportBillApiController::class, 'lodgementFinal'])->name('tradeop.lodge.final');
        Route::delete('/lodgements/{id}', [ExportBillApiController::class, 'lodgementDestroy'])->name('tradeop.lodge.delete');

        Route::get('/boes', [ExportBillApiController::class, 'boes'])->name('tradeop.boe');
        Route::post('/boes', [ExportBillApiController::class, 'saveBoe'])->name('tradeop.boe.create');
        Route::get('/boes/source/{lc}', [ExportBillApiController::class, 'boeSource'])->name('tradeop.boe.source');
        Route::get('/boes/dates/{lc}', [CmPaymentApiController::class, 'billDates'])->name('tradeop.boe.dates');
        Route::put('/boes/dates', [CmPaymentApiController::class, 'saveBillDates'])->name('tradeop.boe.dates.save');

        // CM Payment Receive (AnnotechERP 22/04).
        Route::get('/cm-receipts', [CmPaymentApiController::class, 'receipts'])->name('tradeop.cm');
        Route::get('/cm-receipts/bills', [CmPaymentApiController::class, 'bills'])->name('tradeop.cm.bills');
        Route::get('/cm-receipts/source/{boe}', [CmPaymentApiController::class, 'source'])->name('tradeop.cm.source');
        Route::post('/cm-receipts', [CmPaymentApiController::class, 'save'])->name('tradeop.cm.create');
        Route::get('/cm-receipts/{id}', [CmPaymentApiController::class, 'receipt'])->name('tradeop.cm.show');
        Route::put('/cm-receipts/{id}', [CmPaymentApiController::class, 'save'])->name('tradeop.cm.update');
        Route::post('/cm-receipts/{id}/final', [CmPaymentApiController::class, 'final'])->name('tradeop.cm.final');
        Route::delete('/cm-receipts/{id}', [CmPaymentApiController::class, 'destroy'])->name('tradeop.cm.delete');
        Route::get('/margin-closings', [CmPaymentApiController::class, 'margins'])->name('tradeop.mc');

        // Cash Incentive (AnnotechERP 22/05), and the PRC it reads off the L/C.
        Route::get('/cash-incentives', [CashIncentiveApiController::class, 'index'])->name('tradeop.ci');
        Route::get('/cash-incentives/credit', [CashIncentiveApiController::class, 'credit'])->name('tradeop.ci.credit');
        Route::post('/cash-incentives', [CashIncentiveApiController::class, 'save'])->name('tradeop.ci.create');
        Route::put('/cash-incentives/{id}', [CashIncentiveApiController::class, 'save'])->name('tradeop.ci.update');
        Route::put('/lcs/{lc}/prc', [CashIncentiveApiController::class, 'savePrc'])->name('tradeop.lc.prc');
        Route::post('/lcs/{lc}/prc/attachment', [CashIncentiveApiController::class, 'prcAttach'])->name('tradeop.lc.prc.attach');

        // Report (AnnotechERP 22/06).
        Route::get('/reports/options', [TradeReportApiController::class, 'options'])->name('tradeop.rp.options');
        Route::post('/reports/run', [TradeReportApiController::class, 'run'])->name('tradeop.rp.run');
        Route::get('/boes/{id}', [ExportBillApiController::class, 'boe'])->name('tradeop.boe.show');
        Route::put('/boes/{id}', [ExportBillApiController::class, 'saveBoe'])->name('tradeop.boe.update');
        Route::post('/boes/{id}/final', [ExportBillApiController::class, 'boeFinal'])->name('tradeop.boe.final');
        Route::delete('/boes/{id}', [ExportBillApiController::class, 'boeDestroy'])->name('tradeop.boe.delete');

        Route::get('/realisations', [ExportBillApiController::class, 'realisations'])->name('tradeop.real');
        Route::get('/realisations/bills', [ExportBillApiController::class, 'realisable'])->name('tradeop.real.bills');
        Route::post('/realisations', [ExportBillApiController::class, 'saveRealisation'])->name('tradeop.real.create');
        Route::get('/realisations/{id}', [ExportBillApiController::class, 'realisation'])->name('tradeop.real.show');
        Route::put('/realisations/{id}', [ExportBillApiController::class, 'saveRealisation'])->name('tradeop.real.update');
        Route::post('/realisations/{id}/final', [ExportBillApiController::class, 'realisationFinal'])->name('tradeop.real.final');
        Route::delete('/realisations/{id}', [ExportBillApiController::class, 'realisationDestroy'])->name('tradeop.real.delete');
    });
});
