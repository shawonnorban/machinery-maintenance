<?php

declare(strict_types=1);

use App\Modules\Vendor\Http\Controllers\Api\ServiceContractApiController;
use App\Modules\Vendor\Http\Controllers\Api\VendorApiController;
use App\Modules\Vendor\Http\Controllers\Api\WarrantyApiController;
use Illuminate\Support\Facades\Route;

/*
 * Vendors (API 17), warranties and service contracts (API 18).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/vendors', [VendorApiController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorApiController::class, 'store'])->name('vendors.store');
    Route::get('/vendors/{vendor}', [VendorApiController::class, 'show'])->name('vendors.show');
    Route::patch('/vendors/{vendor}', [VendorApiController::class, 'update'])->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorApiController::class, 'destroy'])->name('vendors.destroy');

    Route::get('/warranties', [WarrantyApiController::class, 'index'])->name('warranties.index');
    Route::post('/warranties', [WarrantyApiController::class, 'store'])->name('warranties.store');
    Route::get('/warranties/{warranty}', [WarrantyApiController::class, 'show'])->name('warranties.show');
    Route::post('/warranties/{warranty}/claims', [WarrantyApiController::class, 'storeClaim'])
        ->name('warranties.claims.store');
    Route::patch('/warranty-claims/{claim}', [WarrantyApiController::class, 'decideClaim'])
        ->name('warranty-claims.decide');

    Route::get('/service-contracts', [ServiceContractApiController::class, 'index'])->name('service-contracts.index');
    Route::post('/service-contracts', [ServiceContractApiController::class, 'store'])
        ->name('service-contracts.store');
    Route::get('/service-contracts/{contract}', [ServiceContractApiController::class, 'show'])
        ->name('service-contracts.show');
    Route::post('/service-contracts/{contract}/renew', [ServiceContractApiController::class, 'renew'])
        ->name('service-contracts.renew');
    Route::post('/service-contracts/{contract}/cancel', [ServiceContractApiController::class, 'cancel'])
        ->name('service-contracts.cancel');
});
