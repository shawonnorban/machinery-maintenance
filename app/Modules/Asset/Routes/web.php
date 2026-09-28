<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Web\AssetController;
use App\Modules\Asset\Http\Controllers\Web\AssetDocumentController;
use App\Modules\Asset\Http\Controllers\Web\AssetLabelController;
use App\Modules\Asset\Http\Controllers\Web\AssetLocationController;
use App\Modules\Asset\Http\Controllers\Web\AssetStatusController;
use App\Modules\Asset\Http\Controllers\Web\AssetTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');

    Route::get('/assets/transfers', [AssetTransferController::class, 'index'])->name('assets.transfers');

    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::patch('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');

    // Only for a machine with no history behind it. Disposal is RETIRED then
    // SCRAPPED, which keeps the record; this is for the row typed twice.
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    // A machine papers: manual, wiring diagram, calibration certificate.
    // Read by everybody who works on it, changed by very few.
    Route::post('/assets/{asset}/documents', [AssetDocumentController::class, 'store'])
        ->name('assets.documents.store');
    Route::delete('/assets/{asset}/documents/{attachment}', [AssetDocumentController::class, 'destroy'])
        ->name('assets.documents.destroy');

    Route::post('/assets/{asset}/status', [AssetStatusController::class, 'store'])->name('assets.status');

    Route::get('/assets/{asset}/transfer', [AssetTransferController::class, 'create'])->name('assets.transfer.create');
    Route::post('/assets/{asset}/transfer', [AssetTransferController::class, 'store'])->name('assets.transfer.store');

    Route::post('/transfers/{transfer}/approve', [AssetTransferController::class, 'approve'])->name('transfers.approve');
    Route::post('/transfers/{transfer}/receive', [AssetTransferController::class, 'receive'])->name('transfers.receive');
    Route::post('/transfers/{transfer}/reject', [AssetTransferController::class, 'reject'])->name('transfers.reject');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/assets-labels', [AssetLabelController::class, 'index'])->name('assets.labels');
    Route::post('/assets/{asset}/qr/regenerate', [AssetLabelController::class, 'regenerate'])
        ->name('assets.qr.regenerate');
});

/*
 * Locations (ADR-052). Under /settings because they are configuration, not
 * day-to-day work, and behind masterdata.manage — the same permission the
 * location import already uses.
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/locations', [AssetLocationController::class, 'index'])->name('locations');
    Route::get('/locations/create', [AssetLocationController::class, 'create'])->name('locations.create');
    Route::post('/locations', [AssetLocationController::class, 'store'])->name('locations.store');
    Route::get('/locations/{location}/edit', [AssetLocationController::class, 'edit'])->name('locations.edit');
    Route::patch('/locations/{location}', [AssetLocationController::class, 'update'])->name('locations.update');
    Route::post('/locations/{location}/toggle', [AssetLocationController::class, 'toggle'])->name('locations.toggle');
    Route::delete('/locations/{location}', [AssetLocationController::class, 'destroy'])->name('locations.destroy');
});
