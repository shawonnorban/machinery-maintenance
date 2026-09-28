<?php

declare(strict_types=1);

use App\Modules\ItemMaster\Http\Controllers\Api\FabricInspectionApiController;
use App\Modules\ItemMaster\Http\Controllers\Api\FabricRollApiController;
use App\Modules\ItemMaster\Http\Controllers\Api\ItemApiController;
use App\Modules\ItemMaster\Http\Controllers\Api\ItemReservationApiController;
use App\Modules\ItemMaster\Http\Controllers\Api\StockCountApiController;
use App\Modules\ItemMaster\Http\Controllers\Api\SubcontractChallanApiController;
use Illuminate\Support\Facades\Route;

/*
 * The item library (Master Data spec §2) and fabric roll inspection
 * (Inventory spec §3, §4).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/items', [ItemApiController::class, 'index'])->name('items.index');
    Route::post('/items', [ItemApiController::class, 'store'])->name('items.store');
    // Static segment ahead of the {item} wildcard.
    Route::get('/items/form-options', [ItemApiController::class, 'formOptions'])->name('items.form-options');
    Route::get('/items/low-stock', [ItemApiController::class, 'lowStock'])->name('items.low-stock');
    Route::get('/items/{item}', [ItemApiController::class, 'show'])->name('items.show');
    Route::patch('/items/{item}', [ItemApiController::class, 'update'])->name('items.update');
    Route::patch('/items/{item}/active', [ItemApiController::class, 'setActive'])->name('items.active');
    Route::delete('/items/{item}', [ItemApiController::class, 'destroy'])->name('items.destroy');

    Route::get('/fabric-rolls', [FabricRollApiController::class, 'index'])->name('fabric-rolls.index');
    Route::get('/fabric-rolls/form-options', [FabricRollApiController::class, 'formOptions'])->name('fabric-rolls.form-options');
    Route::post('/fabric-rolls/merge', [FabricRollApiController::class, 'merge'])->name('fabric-rolls.merge');
    Route::get('/fabric-rolls/{roll}', [FabricRollApiController::class, 'show'])->name('fabric-rolls.show');
    Route::post('/fabric-rolls/{roll}/inspect', [FabricRollApiController::class, 'inspect'])->name('fabric-rolls.inspect');
    Route::post('/fabric-rolls/{roll}/split', [FabricRollApiController::class, 'split'])->name('fabric-rolls.split');

    // Four-point inspection (Inventory spec §4.3). The sheet is the lot,
    // and rolls are tallied onto it — the per-roll `fabric-rolls/{roll}/inspect`
    // above stays for deliveries nobody four-points.
    Route::get('/fabric-inspections', [FabricInspectionApiController::class, 'index'])->name('fabric-inspections.index');
    Route::post('/fabric-inspections', [FabricInspectionApiController::class, 'store'])->name('fabric-inspections.store');
    Route::get('/fabric-inspections/form-options', [FabricInspectionApiController::class, 'formOptions'])->name('fabric-inspections.form-options');
    Route::get('/fabric-inspections/{inspection}', [FabricInspectionApiController::class, 'show'])->name('fabric-inspections.show');
    Route::post('/fabric-inspections/{inspection}/rolls', [FabricInspectionApiController::class, 'recordRoll'])->name('fabric-inspections.rolls');
    Route::post('/fabric-inspections/{inspection}/submit', [FabricInspectionApiController::class, 'submit'])->name('fabric-inspections.submit');
    Route::post('/fabric-inspections/{inspection}/approve', [FabricInspectionApiController::class, 'approve'])->name('fabric-inspections.approve');
    Route::post('/fabric-inspections/{inspection}/reject', [FabricInspectionApiController::class, 'reject'])->name('fabric-inspections.reject');
    Route::post('/fabric-inspections/{inspection}/escalate', [FabricInspectionApiController::class, 'escalate'])->name('fabric-inspections.escalate');
    Route::post('/fabric-inspections/{inspection}/cancel', [FabricInspectionApiController::class, 'cancel'])->name('fabric-inspections.cancel');

    Route::get('/item-reservations', [ItemReservationApiController::class, 'index'])->name('item-reservations.index');
    Route::post('/item-reservations', [ItemReservationApiController::class, 'store'])->name('item-reservations.store');
    Route::post('/item-reservations/{reservation}/release', [ItemReservationApiController::class, 'release'])->name('item-reservations.release');

    // Fabric out for dyeing/printing/embroidery (Inventory spec §8).
    Route::get('/subcontract-challans', [SubcontractChallanApiController::class, 'index'])->name('subcontract-challans.index');
    Route::post('/subcontract-challans', [SubcontractChallanApiController::class, 'store'])->name('subcontract-challans.store');
    Route::get('/subcontract-challans/{challan}', [SubcontractChallanApiController::class, 'show'])->name('subcontract-challans.show');
    Route::post('/subcontract-challans/{challan}/send', [SubcontractChallanApiController::class, 'send'])->name('subcontract-challans.send');
    Route::post('/subcontract-challans/{challan}/returns', [SubcontractChallanApiController::class, 'receiveReturn'])->name('subcontract-challans.returns');
    Route::post('/subcontract-challans/{challan}/close', [SubcontractChallanApiController::class, 'close'])->name('subcontract-challans.close');
    Route::post('/subcontract-challans/{challan}/cancel', [SubcontractChallanApiController::class, 'cancel'])->name('subcontract-challans.cancel');

    // Physical inventory (Inventory spec §12). Static segment ahead of the
    // {count} wildcard.
    Route::get('/stock-counts', [StockCountApiController::class, 'index'])->name('stock-counts.index');
    Route::post('/stock-counts', [StockCountApiController::class, 'store'])->name('stock-counts.store');
    Route::get('/stock-counts/form-options', [StockCountApiController::class, 'formOptions'])->name('stock-counts.form-options');
    Route::get('/stock-counts/{count}', [StockCountApiController::class, 'show'])->name('stock-counts.show');
    Route::post('/stock-counts/{count}/lines', [StockCountApiController::class, 'record'])->name('stock-counts.record');
    Route::post('/stock-counts/{count}/found', [StockCountApiController::class, 'recordFound'])->name('stock-counts.found');
    Route::post('/stock-counts/{count}/submit', [StockCountApiController::class, 'submit'])->name('stock-counts.submit');
    Route::post('/stock-counts/{count}/post', [StockCountApiController::class, 'post'])->name('stock-counts.post');
    Route::post('/stock-counts/{count}/cancel', [StockCountApiController::class, 'cancel'])->name('stock-counts.cancel');
    Route::post('/stock-count-lines/{line}/recount', [StockCountApiController::class, 'recount'])->name('stock-count-lines.recount');
});
