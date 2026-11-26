<?php

declare(strict_types=1);

use App\Modules\DyeHouse\Http\Controllers\Api\DyeBatchApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeChemIssueApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeChemReceiptApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeChemStoreApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeHouseApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeLabDipApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeProductionApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeProductionBoardApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeQualityApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeRequisitionApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeScOutHouseApiController;
use App\Modules\DyeHouse\Http\Controllers\Api\DyeScQuotationApiController;
use Illuminate\Support\Facades\Route;

/*
 * Dye House (AnnotechERP 17). The master lists are screen-kit resources under
 * /erp/{resource}; the screens with a shape of their own live here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/dyehouse/lookups', [DyeHouseApiController::class, 'lookups'])->name('dyehouse.lookups');

    // Colour Group Rate Update: the grid, saved as a whole.
    Route::put('/dyehouse/colour-groups', [DyeHouseApiController::class, 'saveColourGroups'])->name('dyehouse.colour-groups.save');

    // Dye Chemical Audit Recipe.
    Route::get('/dyehouse/audit-buyers', [DyeHouseApiController::class, 'auditBuyers'])->name('dyehouse.audit.buyers');
    Route::get('/dyehouse/buyers/{buyer}/audit-checks', [DyeHouseApiController::class, 'auditChecks'])->name('dyehouse.audit.show');
    Route::put('/dyehouse/buyers/{buyer}/audit-checks', [DyeHouseApiController::class, 'saveAuditChecks'])->name('dyehouse.audit.save');

    // Dye Sub-Contract Party Rate.
    Route::get('/dyehouse/sc-quotations', [DyeScQuotationApiController::class, 'index'])->name('dyehouse.sc.index');
    Route::get('/dyehouse/sc-quotations/heads', [DyeScQuotationApiController::class, 'heads'])->name('dyehouse.sc.heads');
    Route::post('/dyehouse/sc-quotations', [DyeScQuotationApiController::class, 'store'])->name('dyehouse.sc.store');
    Route::get('/dyehouse/sc-quotations/{quotation}', [DyeScQuotationApiController::class, 'show'])->name('dyehouse.sc.show');
    Route::put('/dyehouse/sc-quotations/{quotation}', [DyeScQuotationApiController::class, 'update'])->name('dyehouse.sc.update');

    // Lab-Dip (AnnotechERP 17/02): dosing templates by code; recipes are the dye-lab-dip-recipes resource.
    Route::get('/dyehouse/lab-dip/options', [DyeLabDipApiController::class, 'options'])->name('dyehouse.labdip.options');
    Route::get('/dyehouse/dosing-templates', [DyeLabDipApiController::class, 'index'])->name('dyehouse.templates.index');
    Route::post('/dyehouse/dosing-templates', [DyeLabDipApiController::class, 'save'])->name('dyehouse.templates.store');
    Route::get('/dyehouse/dosing-templates/{code}', [DyeLabDipApiController::class, 'show'])->name('dyehouse.templates.show');
    Route::put('/dyehouse/dosing-templates/{code}', [DyeLabDipApiController::class, 'save'])->name('dyehouse.templates.update');
    Route::delete('/dyehouse/dosing-templates/{code}', [DyeLabDipApiController::class, 'destroy'])->name('dyehouse.templates.destroy');

    // Batch (Bulk) (AnnotechERP 17/03): the board by plan code, cards by batch number, requisitions by their number.
    Route::get('/dyehouse/batch/options', [DyeBatchApiController::class, 'options'])->name('dyehouse.batch.options');
    Route::get('/dyehouse/batch-plans/{code}/board', [DyeBatchApiController::class, 'board'])->name('dyehouse.batch.board');
    Route::post('/dyehouse/batch-plans/{code}/pull', [DyeBatchApiController::class, 'pull'])->name('dyehouse.batch.pull');
    Route::post('/dyehouse/batch-plans/{code}/bars', [DyeBatchApiController::class, 'place'])->name('dyehouse.batch.place');
    Route::delete('/dyehouse/batch-plans/{code}/bars/{batch}', [DyeBatchApiController::class, 'clear'])->name('dyehouse.batch.clear');
    Route::put('/dyehouse/batch-plans/{code}/view', [DyeBatchApiController::class, 'saveView'])->name('dyehouse.batch.view');
    Route::get('/dyehouse/batches', [DyeBatchApiController::class, 'batches'])->name('dyehouse.batches.index');
    Route::get('/dyehouse/batches/{no}', [DyeBatchApiController::class, 'showBatch'])->name('dyehouse.batches.show');
    Route::post('/dyehouse/batches/{no}/split', [DyeBatchApiController::class, 'split'])->name('dyehouse.batches.split');
    Route::get('/dyehouse/grey-indents/{no}/issue', [DyeBatchApiController::class, 'issueSheet'])->name('dyehouse.indent.issue-sheet');
    Route::post('/dyehouse/grey-indents/{no}/issue', [DyeBatchApiController::class, 'issue'])->name('dyehouse.indent.issue');
    Route::get('/dyehouse/batch-leftover', [DyeBatchApiController::class, 'leftoverLots'])->name('dyehouse.batch.leftover');
    Route::post('/dyehouse/batch-leftover', [DyeBatchApiController::class, 'makeLeftover'])->name('dyehouse.batch.leftover.make');

    // Batch (Sample) (AnnotechERP 17/04): a card's production by its batch number, sample lists by kind.
    Route::get('/dyehouse/production/batches', [DyeProductionApiController::class, 'batches'])->name('dyehouse.production.batches');
    Route::get('/dyehouse/batches/{no}/production', [DyeProductionApiController::class, 'sheet'])->name('dyehouse.production.sheet');
    Route::post('/dyehouse/batches/{no}/production', [DyeProductionApiController::class, 'save'])->name('dyehouse.production.save');
    Route::get('/dyehouse/sample/lists/{kind}', [DyeProductionApiController::class, 'lists'])->name('dyehouse.sample.lists');

    // Requisition (AnnotechERP 17/05): dye and chemical requisitions by their number (IR-DC-…), check-roll returns by theirs (CRR-…).
    Route::get('/dyehouse/chem-requisitions', [DyeRequisitionApiController::class, 'index'])->name('dyehouse.req.index');
    Route::post('/dyehouse/chem-requisitions', [DyeRequisitionApiController::class, 'save'])->name('dyehouse.req.store');
    Route::get('/dyehouse/chem-requisitions/{no}', [DyeRequisitionApiController::class, 'show'])->name('dyehouse.req.show');
    Route::put('/dyehouse/chem-requisitions/{no}', [DyeRequisitionApiController::class, 'save'])->name('dyehouse.req.update');
    Route::delete('/dyehouse/chem-requisitions/{no}', [DyeRequisitionApiController::class, 'destroy'])->name('dyehouse.req.destroy');
    Route::post('/dyehouse/chem-requisitions/{no}/issue', [DyeRequisitionApiController::class, 'issue'])->name('dyehouse.req.issue');
    Route::post('/dyehouse/chem-requisitions/{no}/{decision}', [DyeRequisitionApiController::class, 'decide'])->whereIn('decision', ['approve', 'reject'])->name('dyehouse.req.decide');
    Route::post('/dyehouse/check-roll-returns/{no}/back', [DyeRequisitionApiController::class, 'sendBack'])->name('dyehouse.crr.back');

    // Chemical Store (AnnotechERP 17/06): receipts by MRR/RTN-LP number, returns by RTN-, issues by their REQ-ST-/IS-… number, statements by CS-, closings by period.
    Route::get('/dyehouse/chem-store/options', [DyeChemStoreApiController::class, 'options'])->name('dyehouse.chem.options');
    Route::get('/dyehouse/chem-store/stock', [DyeChemStoreApiController::class, 'stock'])->name('dyehouse.chem.stock');
    Route::get('/dyehouse/chem-receipts', [DyeChemReceiptApiController::class, 'index'])->name('dyehouse.chem.receipts.index');
    Route::post('/dyehouse/chem-receipts', [DyeChemReceiptApiController::class, 'save'])->name('dyehouse.chem.receipts.store');
    Route::get('/dyehouse/chem-receipts/{no}', [DyeChemReceiptApiController::class, 'show'])->name('dyehouse.chem.receipts.show');
    Route::put('/dyehouse/chem-receipts/{no}', [DyeChemReceiptApiController::class, 'save'])->name('dyehouse.chem.receipts.update');
    Route::get('/dyehouse/chem-receipts/{no}/return-source', [DyeChemReceiptApiController::class, 'returnSource'])->name('dyehouse.chem.receipts.return-source');
    Route::post('/dyehouse/chem-receipts/{no}/{action}', [DyeChemReceiptApiController::class, 'transition'])->whereIn('action', ['finalise', 'verify', 'cancel'])->name('dyehouse.chem.receipts.transition');
    Route::get('/dyehouse/chem-returns', [DyeChemReceiptApiController::class, 'returns'])->name('dyehouse.chem.returns.index');
    Route::post('/dyehouse/chem-returns', [DyeChemReceiptApiController::class, 'saveReturn'])->name('dyehouse.chem.returns.store');
    Route::get('/dyehouse/chem-returns/{no}', [DyeChemReceiptApiController::class, 'showReturn'])->name('dyehouse.chem.returns.show');
    Route::put('/dyehouse/chem-returns/{no}', [DyeChemReceiptApiController::class, 'saveReturn'])->name('dyehouse.chem.returns.update');
    Route::post('/dyehouse/chem-returns/{no}/{action}', [DyeChemReceiptApiController::class, 'returnTransition'])->whereIn('action', ['finalise', 'cancel'])->name('dyehouse.chem.returns.transition');
    Route::get('/dyehouse/chem-issues', [DyeChemIssueApiController::class, 'index'])->name('dyehouse.chem.issues.index');
    Route::post('/dyehouse/chem-issues', [DyeChemIssueApiController::class, 'save'])->name('dyehouse.chem.issues.store');
    Route::get('/dyehouse/chem-issues/{no}', [DyeChemIssueApiController::class, 'show'])->name('dyehouse.chem.issues.show');
    Route::put('/dyehouse/chem-issues/{no}', [DyeChemIssueApiController::class, 'save'])->name('dyehouse.chem.issues.update');
    Route::post('/dyehouse/chem-issues/{no}/verify', [DyeChemIssueApiController::class, 'verify'])->name('dyehouse.chem.issues.verify');
    Route::post('/dyehouse/chem-issues/{no}/issue', [DyeChemIssueApiController::class, 'issueStock'])->name('dyehouse.chem.issues.issue');
    Route::post('/dyehouse/chem-issues/{no}/cancel', [DyeChemIssueApiController::class, 'cancel'])->name('dyehouse.chem.issues.cancel');
    Route::get('/dyehouse/chem-cs', [DyeChemStoreApiController::class, 'statements'])->name('dyehouse.chem.cs.index');
    Route::post('/dyehouse/chem-cs', [DyeChemStoreApiController::class, 'saveStatement'])->name('dyehouse.chem.cs.store');
    Route::get('/dyehouse/chem-cs/{no}', [DyeChemStoreApiController::class, 'showStatement'])->name('dyehouse.chem.cs.show');
    Route::put('/dyehouse/chem-cs/{no}', [DyeChemStoreApiController::class, 'saveStatement'])->name('dyehouse.chem.cs.update');
    Route::post('/dyehouse/chem-cs/{no}/{action}', [DyeChemStoreApiController::class, 'statementTransition'])->whereIn('action', ['submit', 'approve', 'reject', 'close'])->name('dyehouse.chem.cs.transition');
    // Production Board (AnnotechERP 17/07): productions and loads by batch, wash productions by their requisition's number, bookings and programmes by theirs.
    Route::get('/dyehouse/productions', [DyeProductionApiController::class, 'index'])->name('dyehouse.production.index');
    Route::get('/dyehouse/production-loads', [DyeProductionApiController::class, 'loads'])->name('dyehouse.production.loads');
    Route::put('/dyehouse/production-loads', [DyeProductionApiController::class, 'saveLoads'])->name('dyehouse.production.loads.save');
    Route::get('/dyehouse/production-board/options', [DyeProductionBoardApiController::class, 'options'])->name('dyehouse.board.options');
    Route::get('/dyehouse/wash-productions', [DyeProductionBoardApiController::class, 'washes'])->name('dyehouse.wash.index');
    Route::get('/dyehouse/wash-productions/{reqNo}', [DyeProductionBoardApiController::class, 'wash'])->name('dyehouse.wash.show');
    Route::put('/dyehouse/wash-productions/{reqNo}', [DyeProductionBoardApiController::class, 'saveWash'])->name('dyehouse.wash.save');
    Route::get('/dyehouse/fabric-bookings', [DyeProductionBoardApiController::class, 'bookings'])->name('dyehouse.booking.index');
    Route::post('/dyehouse/fabric-bookings', [DyeProductionBoardApiController::class, 'saveBooking'])->name('dyehouse.booking.store');
    Route::get('/dyehouse/fabric-bookings/{no}', [DyeProductionBoardApiController::class, 'booking'])->name('dyehouse.booking.show');
    Route::put('/dyehouse/fabric-bookings/{no}', [DyeProductionBoardApiController::class, 'saveBooking'])->name('dyehouse.booking.update');
    Route::get('/dyehouse/trim-programmes', [DyeProductionBoardApiController::class, 'trims'])->name('dyehouse.trim.index');
    Route::post('/dyehouse/trim-programmes', [DyeProductionBoardApiController::class, 'saveTrim'])->name('dyehouse.trim.store');
    Route::get('/dyehouse/trim-programmes/{no}', [DyeProductionBoardApiController::class, 'trim'])->name('dyehouse.trim.show');
    Route::put('/dyehouse/trim-programmes/{no}', [DyeProductionBoardApiController::class, 'saveTrim'])->name('dyehouse.trim.update');
    Route::get('/dyehouse/df-closings', [DyeProductionBoardApiController::class, 'closings'])->name('dyehouse.dfclose.index');
    Route::post('/dyehouse/df-closings', [DyeProductionBoardApiController::class, 'close'])->name('dyehouse.dfclose.close');

    // Dyeing Q.C (AnnotechERP 17/08): inspections by their number (CRI-…), a card's sheet by its batch number, completions by the requisition's.
    Route::get('/dyehouse/qc/options', [DyeQualityApiController::class, 'options'])->name('dyehouse.qc.options');
    Route::get('/dyehouse/check-roll-inspections', [DyeQualityApiController::class, 'inspections'])->name('dyehouse.qc.inspections.index');
    Route::post('/dyehouse/check-roll-inspections', [DyeQualityApiController::class, 'save'])->name('dyehouse.qc.inspections.store');
    Route::put('/dyehouse/check-roll-inspections/{no}', [DyeQualityApiController::class, 'save'])->name('dyehouse.qc.inspections.update');
    Route::get('/dyehouse/batches/{no}/check-roll', [DyeQualityApiController::class, 'sheet'])->name('dyehouse.qc.sheet');
    Route::get('/dyehouse/batch-completions', [DyeQualityApiController::class, 'completions'])->name('dyehouse.qc.completions.index');
    Route::post('/dyehouse/batch-completions/{reqNo}', [DyeQualityApiController::class, 'complete'])->name('dyehouse.qc.completions.complete');

    // S/C Dyeing (AnnotechERP 17/09): programmes (DF-SC-OH-), requisitions (DF-SC-RQ-), challans (DF-SC-CH-) and receipts (DF-SC-FR-/MRR-) by their numbers.
    Route::get('/dyehouse/sc/options', [DyeScOutHouseApiController::class, 'options'])->name('dyehouse.sc.options');
    Route::get('/dyehouse/sc-programmes', [DyeScOutHouseApiController::class, 'programmes'])->name('dyehouse.sc.programmes.index');
    Route::post('/dyehouse/sc-programmes', [DyeScOutHouseApiController::class, 'saveProgramme'])->name('dyehouse.sc.programmes.store');
    Route::get('/dyehouse/sc-programmes/{no}', [DyeScOutHouseApiController::class, 'programme'])->name('dyehouse.sc.programmes.show');
    Route::put('/dyehouse/sc-programmes/{no}', [DyeScOutHouseApiController::class, 'saveProgramme'])->name('dyehouse.sc.programmes.update');
    Route::get('/dyehouse/sc-requisitions', [DyeScOutHouseApiController::class, 'requisitions'])->name('dyehouse.sc.requisitions.index');
    Route::post('/dyehouse/sc-requisitions', [DyeScOutHouseApiController::class, 'saveRequisition'])->name('dyehouse.sc.requisitions.store');
    Route::get('/dyehouse/sc-requisitions/{no}', [DyeScOutHouseApiController::class, 'requisition'])->name('dyehouse.sc.requisitions.show');
    Route::put('/dyehouse/sc-requisitions/{no}', [DyeScOutHouseApiController::class, 'saveRequisition'])->name('dyehouse.sc.requisitions.update');
    Route::post('/dyehouse/sc-requisitions/{no}/issue', [DyeScOutHouseApiController::class, 'issue'])->name('dyehouse.sc.requisitions.issue');
    Route::get('/dyehouse/sc-challans', [DyeScOutHouseApiController::class, 'challans'])->name('dyehouse.sc.challans.index');
    Route::post('/dyehouse/sc-challans', [DyeScOutHouseApiController::class, 'saveChallan'])->name('dyehouse.sc.challans.store');
    Route::get('/dyehouse/sc-challans/{no}', [DyeScOutHouseApiController::class, 'challan'])->name('dyehouse.sc.challans.show');
    Route::put('/dyehouse/sc-challans/{no}', [DyeScOutHouseApiController::class, 'saveChallan'])->name('dyehouse.sc.challans.update');
    Route::get('/dyehouse/sc-receipts', [DyeScOutHouseApiController::class, 'receipts'])->name('dyehouse.sc.receipts.index');
    Route::post('/dyehouse/sc-receipts', [DyeScOutHouseApiController::class, 'saveReceipt'])->name('dyehouse.sc.receipts.store');
    Route::get('/dyehouse/sc-receipts/{no}', [DyeScOutHouseApiController::class, 'receipt'])->name('dyehouse.sc.receipts.show');
    Route::put('/dyehouse/sc-receipts/{no}', [DyeScOutHouseApiController::class, 'saveReceipt'])->name('dyehouse.sc.receipts.update');

    Route::get('/dyehouse/chem-closings', [DyeChemStoreApiController::class, 'closings'])->name('dyehouse.chem.closings.index');
    Route::post('/dyehouse/chem-closings', [DyeChemStoreApiController::class, 'close'])->name('dyehouse.chem.closings.close');
});
