<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\Api\SparePartApiController;
use Illuminate\Support\Facades\Route;

/*
 * Spare parts and stock (API 13).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/spare-parts', [SparePartApiController::class, 'index'])->name('spare-parts.index');
    Route::get('/spare-parts/{part}', [SparePartApiController::class, 'show'])->name('spare-parts.show');
    Route::get('/spare-parts/{part}/stock', [SparePartApiController::class, 'stock'])->name('spare-parts.stock');
    Route::get('/spare-parts/{part}/transactions', [SparePartApiController::class, 'transactions'])
        ->name('spare-parts.transactions');
});
