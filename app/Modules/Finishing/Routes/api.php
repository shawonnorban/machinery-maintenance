<?php

declare(strict_types=1);

use App\Modules\Finishing\Http\Controllers\Api\FinInspectionApiController;
use App\Modules\Finishing\Http\Controllers\Api\FinishingApiController;
use App\Modules\Finishing\Http\Controllers\Api\FinProductionApiController;
use App\Modules\Finishing\Http\Controllers\Api\FinReportApiController;
use App\Modules\Finishing\Http\Controllers\Api\FinScProcessApiController;
use App\Modules\Finishing\Http\Controllers\Api\FinStoreApiController;
use Illuminate\Support\Facades\Route;

/*
 * Finishing Line (AnnotechERP 18). The plain lists are screen-kit resources
 * under /erp/{resource}; the process groups and sub-groups and a machine's key
 * image live here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/finishing/lookups', [FinishingApiController::class, 'lookups'])->name('finishing.lookups');
    Route::get('/finishing/groups', [FinishingApiController::class, 'groups'])->name('finishing.groups');
    Route::post('/finishing/groups', [FinishingApiController::class, 'saveGroup'])->name('finishing.groups.store');
    Route::get('/finishing/groups/{group}', [FinishingApiController::class, 'subgroups'])->name('finishing.subgroups');
    Route::put('/finishing/groups/{group}', [FinishingApiController::class, 'saveSubgroups'])->name('finishing.subgroups.save');
    Route::post('/finishing/key-images', [FinishingApiController::class, 'keyImage'])->name('finishing.key-images');

    // DF Production (18/02): the production sheets, posting, and the Process-Wise Delivery board.
    Route::get('/finishing/production/options', [FinProductionApiController::class, 'options'])->name('finishing.production.options');
    Route::get('/finishing/production-sheets', [FinProductionApiController::class, 'sheets'])->name('finishing.production-sheets.index');
    Route::post('/finishing/production-sheets', [FinProductionApiController::class, 'store'])->name('finishing.production-sheets.store');
    Route::get('/finishing/production-sheets/{no}', [FinProductionApiController::class, 'sheet'])->name('finishing.production-sheets.show');
    Route::put('/finishing/production-sheets/{no}', [FinProductionApiController::class, 'update'])->name('finishing.production-sheets.update');
    Route::delete('/finishing/production-sheets/{no}', [FinProductionApiController::class, 'destroy'])->name('finishing.production-sheets.destroy');
    Route::post('/finishing/production-sheets/{no}/post', [FinProductionApiController::class, 'post'])->name('finishing.production-sheets.post');
    Route::get('/finishing/delivery-board', [FinProductionApiController::class, 'board'])->name('finishing.delivery-board');
    Route::get('/finishing/delivery-board/export/{format}', [FinProductionApiController::class, 'export'])->name('finishing.delivery-board.export');
    Route::post('/finishing/delivery-board/{no}/deliver', [FinProductionApiController::class, 'deliver'])->name('finishing.delivery-board.deliver');

    // S/C Process (18/03): the delivery challan, the finished receipt, the process bill and the party balance.
    Route::get('/finishing/sc/options', [FinScProcessApiController::class, 'options'])->name('finishing.sc.options');
    Route::get('/finishing/sc-challans', [FinScProcessApiController::class, 'challans'])->name('finishing.sc-challans.index');
    Route::post('/finishing/sc-challans', [FinScProcessApiController::class, 'saveChallan'])->name('finishing.sc-challans.store');
    Route::get('/finishing/sc-challans/{no}', [FinScProcessApiController::class, 'challan'])->name('finishing.sc-challans.show');
    Route::put('/finishing/sc-challans/{no}', [FinScProcessApiController::class, 'saveChallan'])->name('finishing.sc-challans.update');
    Route::post('/finishing/sc-challans/{no}/post', [FinScProcessApiController::class, 'postChallan'])->name('finishing.sc-challans.post');
    Route::post('/finishing/sc-challans/{no}/cancel', [FinScProcessApiController::class, 'cancelChallan'])->name('finishing.sc-challans.cancel');
    Route::get('/finishing/sc-receipts', [FinScProcessApiController::class, 'receipts'])->name('finishing.sc-receipts.index');
    Route::post('/finishing/sc-receipts', [FinScProcessApiController::class, 'saveReceipt'])->name('finishing.sc-receipts.store');
    Route::get('/finishing/sc-receipts/{no}', [FinScProcessApiController::class, 'receipt'])->name('finishing.sc-receipts.show');
    Route::put('/finishing/sc-receipts/{no}', [FinScProcessApiController::class, 'saveReceipt'])->name('finishing.sc-receipts.update');
    Route::post('/finishing/sc-receipts/{no}/post', [FinScProcessApiController::class, 'postReceipt'])->name('finishing.sc-receipts.post');
    Route::get('/finishing/sc-bills', [FinScProcessApiController::class, 'bills'])->name('finishing.sc-bills.index');
    Route::post('/finishing/sc-bills', [FinScProcessApiController::class, 'saveBill'])->name('finishing.sc-bills.store');
    Route::get('/finishing/sc-bills/{no}', [FinScProcessApiController::class, 'bill'])->name('finishing.sc-bills.show');
    Route::put('/finishing/sc-bills/{no}', [FinScProcessApiController::class, 'saveBill'])->name('finishing.sc-bills.update');
    Route::post('/finishing/sc-bills/{no}/status', [FinScProcessApiController::class, 'billStatus'])->name('finishing.sc-bills.status');
    Route::get('/finishing/sc-balance', [FinScProcessApiController::class, 'balance'])->name('finishing.sc-balance');
    Route::get('/finishing/sc-balance/export', [FinScProcessApiController::class, 'balanceExport'])->name('finishing.sc-balance.export');

    // QC Process (18/04): the four-point fabric inspection, and what the QC lists pick from.
    Route::get('/finishing/qc/options', [FinInspectionApiController::class, 'options'])->name('finishing.qc.options');
    Route::get('/finishing/inspections', [FinInspectionApiController::class, 'index'])->name('finishing.inspections.index');
    Route::post('/finishing/inspections', [FinInspectionApiController::class, 'save'])->name('finishing.inspections.store');
    Route::get('/finishing/inspections/{ref}', [FinInspectionApiController::class, 'show'])->name('finishing.inspections.show');
    Route::put('/finishing/inspections/{ref}', [FinInspectionApiController::class, 'save'])->name('finishing.inspections.update');
    Route::post('/finishing/inspections/{ref}/post', [FinInspectionApiController::class, 'post'])->name('finishing.inspections.post');

    // Fabric Store (18/05): the delivery to the store and its receipt, the issue to cutting, and the lots on hand.
    Route::get('/finishing/store/options', [FinStoreApiController::class, 'options'])->name('finishing.store.options');
    Route::get('/finishing/store/lots', [FinStoreApiController::class, 'lots'])->name('finishing.store.lots');
    Route::get('/finishing/store-deliveries', [FinStoreApiController::class, 'deliveries'])->name('finishing.store-deliveries.index');
    Route::post('/finishing/store-deliveries', [FinStoreApiController::class, 'saveDelivery'])->name('finishing.store-deliveries.store');
    Route::get('/finishing/store-deliveries/{no}', [FinStoreApiController::class, 'delivery'])->name('finishing.store-deliveries.show');
    Route::put('/finishing/store-deliveries/{no}', [FinStoreApiController::class, 'saveDelivery'])->name('finishing.store-deliveries.update');
    Route::post('/finishing/store-deliveries/{no}/send', [FinStoreApiController::class, 'sendDelivery'])->name('finishing.store-deliveries.send');
    Route::post('/finishing/store-deliveries/{no}/receive', [FinStoreApiController::class, 'receiveDelivery'])->name('finishing.store-deliveries.receive');
    Route::get('/finishing/cut-requisitions', [FinStoreApiController::class, 'requisitions'])->name('finishing.cut-requisitions.index');
    Route::post('/finishing/cut-requisitions', [FinStoreApiController::class, 'saveRequisition'])->name('finishing.cut-requisitions.store');
    Route::get('/finishing/cut-requisitions/{no}', [FinStoreApiController::class, 'requisition'])->name('finishing.cut-requisitions.show');
    Route::put('/finishing/cut-requisitions/{no}', [FinStoreApiController::class, 'saveRequisition'])->name('finishing.cut-requisitions.update');

    // Report (18/06): Fabric Finishing Reports.
    Route::get('/finishing/reports/options', [FinReportApiController::class, 'options'])->name('finishing.reports.options');
    Route::get('/finishing/reports/{report}', [FinReportApiController::class, 'show'])->name('finishing.reports.show');
});
