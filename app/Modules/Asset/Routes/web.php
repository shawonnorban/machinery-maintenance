<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Web\AssetController;
use App\Modules\Asset\Http\Controllers\Web\AssetLabelController;
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
