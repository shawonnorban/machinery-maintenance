<?php

declare(strict_types=1);

use App\Modules\Vendor\Http\Controllers\Web\CoverageController;
use App\Modules\Vendor\Http\Controllers\Web\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/create', [VendorController::class, 'create'])->name('vendors.create');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::get('/vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    // Archive rather than delete: history has to stay resolvable (ADR-057).
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.archive');

    Route::get('/warranties', [CoverageController::class, 'warranties'])->name('warranties.index');
    Route::get('/warranties/create', [CoverageController::class, 'createWarranty'])->name('warranties.create');
    Route::post('/warranties', [CoverageController::class, 'storeWarranty'])->name('warranties.store');
    Route::get('/warranties/{warranty}', [CoverageController::class, 'showWarranty'])->name('warranties.show');
    Route::post('/warranties/{warranty}/claims', [CoverageController::class, 'storeClaim'])->name('warranties.claims.store');
    Route::post('/warranty-claims/{claim}/decide', [CoverageController::class, 'decideClaim'])->name('warranty-claims.decide');

    Route::get('/service-contracts', [CoverageController::class, 'contracts'])->name('service-contracts.index');
    Route::get('/service-contracts/create', [CoverageController::class, 'createContract'])->name('service-contracts.create');
    Route::post('/service-contracts', [CoverageController::class, 'storeContract'])->name('service-contracts.store');
    Route::get('/service-contracts/{contract}', [CoverageController::class, 'showContract'])->name('service-contracts.show');
    Route::post('/service-contracts/{contract}/renew', [CoverageController::class, 'renewContract'])->name('service-contracts.renew');
    Route::post('/service-contracts/{contract}/cancel', [CoverageController::class, 'cancelContract'])->name('service-contracts.cancel');
});
