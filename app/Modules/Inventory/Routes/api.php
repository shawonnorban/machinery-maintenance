<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\Api\InventoryTransferApiController;
use App\Modules\Inventory\Http\Controllers\Api\SparePartApiController;
use App\Modules\Inventory\Http\Controllers\Api\SparePartCompatibilityApiController;
use App\Modules\Inventory\Http\Controllers\Api\WorkOrderPartApiController;
use Illuminate\Support\Facades\Route;

/*
 * Spare parts and stock (API 13), and transfers between factories (API 14).
 */

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    // Ahead of GET /spare-parts/{part}: a static segment registered after a
    // wildcard route sharing its prefix is swallowed by it.
    Route::get('/spare-parts/low-stock', [SparePartApiController::class, 'lowStock'])
        ->name('spare-parts.low-stock');
    Route::get('/spare-parts/bins', [SparePartApiController::class, 'bins'])->name('spare-parts.bins');
    Route::get('/spare-parts/form-options', [SparePartApiController::class, 'formOptions'])
        ->name('spare-parts.form-options');

    Route::get('/spare-parts', [SparePartApiController::class, 'index'])->name('spare-parts.index');
    Route::post('/spare-parts', [SparePartApiController::class, 'store'])->name('spare-parts.store');
    Route::get('/spare-parts/{part}', [SparePartApiController::class, 'show'])->name('spare-parts.show');
    Route::patch('/spare-parts/{part}', [SparePartApiController::class, 'update'])->name('spare-parts.update');
    Route::patch('/spare-parts/{part}/active', [SparePartApiController::class, 'setActive'])
        ->name('spare-parts.active');
    Route::delete('/spare-parts/{part}', [SparePartApiController::class, 'destroy'])->name('spare-parts.destroy');

    Route::get('/spare-parts/{part}/stock', [SparePartApiController::class, 'stock'])->name('spare-parts.stock');
    Route::get('/spare-parts/{part}/verify', [SparePartApiController::class, 'verify'])->name('spare-parts.verify');
    Route::get('/spare-parts/{part}/transactions', [SparePartApiController::class, 'transactions'])
        ->name('spare-parts.transactions');
    Route::post('/spare-parts/{part}/reserve', [SparePartApiController::class, 'reserve'])
        ->name('spare-parts.reserve');
    Route::post('/spare-parts/{part}/release', [SparePartApiController::class, 'release'])
        ->name('spare-parts.release');
    Route::post('/spare-parts/{part}/issue', [SparePartApiController::class, 'issue'])
        ->name('spare-parts.issue');
    Route::post('/spare-parts/{part}/return', [SparePartApiController::class, 'returnToStore'])
        ->name('spare-parts.return');
    Route::post('/spare-parts/{part}/adjust', [SparePartApiController::class, 'adjust'])
        ->name('spare-parts.adjust');
    Route::post('/spare-parts/{part}/receive', [SparePartApiController::class, 'receive'])
        ->name('spare-parts.receive');

    Route::get('/spare-parts/{part}/compatibility', [SparePartCompatibilityApiController::class, 'index'])
        ->name('spare-parts.compatibility.index');
    Route::post('/spare-parts/{part}/compatibility', [SparePartCompatibilityApiController::class, 'store'])
        ->name('spare-parts.compatibility.store');
    Route::delete('/spare-parts/{part}/compatibility/{compatibility}', [SparePartCompatibilityApiController::class, 'destroy'])
        ->name('spare-parts.compatibility.destroy');

    Route::post('/inventory-transactions/{transaction}/reverse', [SparePartApiController::class, 'reverseTransaction'])
        ->name('inventory-transactions.reverse');

    Route::get('/inventory-balances', [SparePartApiController::class, 'balances'])->name('inventory-balances.index');

    // Ahead of GET /inventory-transfers/{transfer}: a static segment
    // registered after a wildcard sharing its prefix is swallowed by it.
    Route::get('/inventory-transfers/form-options', [InventoryTransferApiController::class, 'formOptions'])
        ->name('inventory-transfers.form-options');
    Route::get('/inventory-transfers', [InventoryTransferApiController::class, 'index'])
        ->name('inventory-transfers.index');
    Route::post('/inventory-transfers', [InventoryTransferApiController::class, 'store'])
        ->name('inventory-transfers.store');
    Route::get('/inventory-transfers/{transfer}', [InventoryTransferApiController::class, 'show'])
        ->name('inventory-transfers.show');
    Route::post('/inventory-transfers/{transfer}/approve', [InventoryTransferApiController::class, 'approve'])
        ->name('inventory-transfers.approve');
    Route::post('/inventory-transfers/{transfer}/reject', [InventoryTransferApiController::class, 'reject'])
        ->name('inventory-transfers.reject');
    Route::post('/inventory-transfers/{transfer}/dispatch', [InventoryTransferApiController::class, 'dispatch'])
        ->name('inventory-transfers.dispatch');
    Route::post('/inventory-transfers/{transfer}/receive', [InventoryTransferApiController::class, 'receive'])
        ->name('inventory-transfers.receive');

    // What the floor is waiting for, across every open work order — mirrors
    // `PartRequestController::index()` (SRS 22).
    Route::get('/part-requests', [WorkOrderPartApiController::class, 'requests'])->name('part-requests.index');

    // Parts on a work order (API 11) — hosted here, not WorkOrder, matching
    // exactly where the web controller and the models it moves already
    // live (WorkOrderPart, SparePart, Bin).
    Route::get('/work-orders/{workOrder}/parts', [WorkOrderPartApiController::class, 'index'])
        ->name('work-orders.parts.index');
    Route::post('/work-orders/{workOrder}/parts', [WorkOrderPartApiController::class, 'store'])
        ->name('work-orders.parts.store');
    Route::post('/work-orders/{workOrder}/parts/issue', [WorkOrderPartApiController::class, 'issueDirect'])
        ->name('work-orders.parts.issue-direct');
    Route::patch('/work-orders/{workOrder}/parts/{line}', [WorkOrderPartApiController::class, 'update'])
        ->name('work-orders.parts.update');
    Route::post('/work-orders/{workOrder}/parts/{line}/issue', [WorkOrderPartApiController::class, 'issue'])
        ->name('work-orders.parts.issue');
    Route::post('/work-orders/{workOrder}/parts/{line}/consume', [WorkOrderPartApiController::class, 'consume'])
        ->name('work-orders.parts.consume');
    Route::post('/work-orders/{workOrder}/parts/{line}/return', [WorkOrderPartApiController::class, 'returnToStore'])
        ->name('work-orders.parts.return');
});
