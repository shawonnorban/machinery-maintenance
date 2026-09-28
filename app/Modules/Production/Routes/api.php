<?php

declare(strict_types=1);

use App\Modules\Production\Http\Controllers\Api\CuttingProgramApiController;
use App\Modules\Production\Http\Controllers\Api\MaterialIssueApiController;
use App\Modules\Production\Http\Controllers\Api\ProductionOrderApiController;
use Illuminate\Support\Facades\Route;

/*
 * The minimal production master (Master Data spec §6): orders, styles,
 * bills of materials and cutting programmes.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/production-orders', [ProductionOrderApiController::class, 'index'])->name('production-orders.index');
    Route::post('/production-orders', [ProductionOrderApiController::class, 'store'])->name('production-orders.store');
    Route::get('/production-orders/{order}', [ProductionOrderApiController::class, 'show'])->name('production-orders.show');
    Route::patch('/production-orders/{order}', [ProductionOrderApiController::class, 'update'])->name('production-orders.update');
    Route::post('/production-orders/{order}/status', [ProductionOrderApiController::class, 'setStatus'])->name('production-orders.status');
    Route::post('/production-orders/{order}/styles', [ProductionOrderApiController::class, 'storeStyle'])->name('production-orders.styles.store');
    Route::put('/production-styles/{style}/bom', [ProductionOrderApiController::class, 'saveBom'])->name('production-styles.bom');

    Route::get('/cutting-programs', [CuttingProgramApiController::class, 'index'])->name('cutting-programs.index');
    Route::get('/cutting-programs/{program}', [CuttingProgramApiController::class, 'show'])->name('cutting-programs.show');
    Route::patch('/cutting-programs/{program}', [CuttingProgramApiController::class, 'update'])->name('cutting-programs.update');
    Route::post('/cutting-programs/{program}/status', [CuttingProgramApiController::class, 'setStatus'])->name('cutting-programs.status');
    Route::post('/production-styles/{style}/cutting-programs', [CuttingProgramApiController::class, 'store'])->name('production-styles.cutting-programs.store');

    // Material issued to a cut, and the BOM variance it produced.
    Route::get('/cutting-programs/{program}/material-issues', [MaterialIssueApiController::class, 'index'])->name('material-issues.index');
    Route::post('/cutting-programs/{program}/material-issues', [MaterialIssueApiController::class, 'store'])->name('material-issues.store');
});
