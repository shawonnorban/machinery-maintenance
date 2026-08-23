<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\Web\PartRequestController;
use App\Modules\Inventory\Http\Controllers\Web\SparePartController;
use App\Modules\Inventory\Http\Controllers\Web\StockController;
use App\Modules\Inventory\Http\Controllers\Web\TransferController;
use App\Modules\Inventory\Http\Controllers\Web\WorkOrderPartsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/inventory/parts', [SparePartController::class, 'index'])->name('inventory.parts');
    Route::get('/inventory/parts/create', [SparePartController::class, 'create'])->name('inventory.parts.create');
    Route::post('/inventory/parts', [SparePartController::class, 'store'])->name('inventory.parts.store');
    Route::get('/inventory/parts/{part}', [SparePartController::class, 'show'])->name('inventory.parts.show');
    Route::get('/inventory/parts/{part}/edit', [SparePartController::class, 'edit'])->name('inventory.parts.edit');
    Route::patch('/inventory/parts/{part}', [SparePartController::class, 'update'])->name('inventory.parts.update');
    Route::post('/inventory/parts/{part}/toggle', [SparePartController::class, 'toggle'])->name('inventory.parts.toggle');
    // Only reaches a part nothing points at — the row typed in twice. Anything
    // the ledger or a work order names is retired instead.
    Route::delete('/inventory/parts/{part}', [SparePartController::class, 'destroy'])->name('inventory.parts.destroy');

    Route::get('/inventory/stock', [StockController::class, 'index'])->name('inventory.stock');
    Route::get('/inventory/low-stock', [StockController::class, 'lowStock'])->name('inventory.low-stock');
    /*
     * Moving stock between factories (SRS 23.4). Four steps with four
     * different people behind them, and the stock sits in an in-transit bin
     * in between so a valuation taken while a van is on the road balances.
     */
    Route::get('/inventory/transfers', [TransferController::class, 'index'])->name('inventory.transfers');
    Route::post('/inventory/transfers', [TransferController::class, 'store'])->name('inventory.transfers.store');
    Route::post('/inventory/transfers/{transfer}/approve', [TransferController::class, 'approve'])->name('inventory.transfers.approve');
    Route::post('/inventory/transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('inventory.transfers.reject');
    Route::post('/inventory/transfers/{transfer}/dispatch', [TransferController::class, 'dispatch'])->name('inventory.transfers.dispatch');
    Route::post('/inventory/transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('inventory.transfers.receive');

    // What the floor is waiting for. A request nobody in the store can see
    // is a request that does not exist.
    Route::get('/inventory/requests', [PartRequestController::class, 'index'])->name('inventory.requests');
    Route::post('/inventory/stock/receive', [StockController::class, 'store'])->name('inventory.stock.receive');
    Route::post('/inventory/stock/adjust', [StockController::class, 'adjust'])->name('inventory.stock.adjust');
    // A correction is a new opposing row, never an edit of the original, so
    // this posts rather than patches.
    Route::post('/inventory/transactions/{transaction}/reverse', [StockController::class, 'reverse'])
        ->name('inventory.transactions.reverse');

    // The step between "this machine needs a hook" and "the store handed one
    // over". A technician may ask; only the store may issue.
    Route::post('/work-orders/{workOrder}/parts/request', [WorkOrderPartsController::class, 'request'])
        ->name('work-orders.parts.request');
    Route::post('/work-orders/{workOrder}/parts/{line}/cancel-request', [WorkOrderPartsController::class, 'cancelRequest'])
        ->name('work-orders.parts.cancel-request');

    Route::post('/work-orders/{workOrder}/parts/reserve', [WorkOrderPartsController::class, 'reserve'])
        ->name('work-orders.parts.reserve');
    Route::post('/work-orders/{workOrder}/parts/issue', [WorkOrderPartsController::class, 'issue'])
        ->name('work-orders.parts.issue');
    Route::post('/work-orders/{workOrder}/parts/{line}/consume', [WorkOrderPartsController::class, 'consume'])
        ->name('work-orders.parts.consume');
    Route::post('/work-orders/{workOrder}/parts/{line}/return', [WorkOrderPartsController::class, 'returnToStore'])
        ->name('work-orders.parts.return');
    Route::post('/work-orders/{workOrder}/reservations/{reservation}/release', [WorkOrderPartsController::class, 'release'])
        ->name('work-orders.reservations.release');
});
