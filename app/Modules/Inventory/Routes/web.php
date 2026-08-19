<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\Web\SparePartController;
use App\Modules\Inventory\Http\Controllers\Web\StockController;
use App\Modules\Inventory\Http\Controllers\Web\WorkOrderPartsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/inventory/parts', [SparePartController::class, 'index'])->name('inventory.parts');
    Route::get('/inventory/parts/create', [SparePartController::class, 'create'])->name('inventory.parts.create');
    Route::post('/inventory/parts', [SparePartController::class, 'store'])->name('inventory.parts.store');
    Route::get('/inventory/parts/{part}', [SparePartController::class, 'show'])->name('inventory.parts.show');

    Route::get('/inventory/stock', [StockController::class, 'index'])->name('inventory.stock');
    Route::get('/inventory/low-stock', [StockController::class, 'lowStock'])->name('inventory.low-stock');
    Route::post('/inventory/stock/receive', [StockController::class, 'store'])->name('inventory.stock.receive');
    Route::post('/inventory/stock/adjust', [StockController::class, 'adjust'])->name('inventory.stock.adjust');
    // A correction is a new opposing row, never an edit of the original, so
    // this posts rather than patches.
    Route::post('/inventory/transactions/{transaction}/reverse', [StockController::class, 'reverse'])
        ->name('inventory.transactions.reverse');

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
