<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Api\AssetApiController;
use App\Modules\Asset\Http\Controllers\Api\AssetDocumentApiController;
use App\Modules\Asset\Http\Controllers\Api\AssetLabelApiController;
use App\Modules\Asset\Http\Controllers\Api\AssetLocationApiController;
use App\Modules\Asset\Http\Controllers\Api\AssetStatusApiController;
use App\Modules\Asset\Http\Controllers\Api\AssetTransferApiController;
use App\Modules\Asset\Http\Controllers\Api\ScanApiController;
use Illuminate\Support\Facades\Route;

/*
 * Machines (API 6). `idempotent` at the group level, not per-route: POST
 * /assets is the one write here a client is likely to retry blind (API 32),
 * and the middleware is a no-op for every request that carries no key.
 */

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    Route::get('/assets', [AssetApiController::class, 'index'])->name('assets.index');
    Route::post('/assets', [AssetApiController::class, 'store'])->name('assets.store');

    // Must be registered before GET /assets/{asset}: a wildcard segment
    // would otherwise swallow "form-options"/"labels" as an attempted
    // asset id.
    Route::get('/assets/form-options', [AssetApiController::class, 'formOptions'])->name('assets.form-options');
    Route::get('/assets/counts', [AssetApiController::class, 'counts'])->name('assets.counts');
    Route::get('/assets/labels', [AssetLabelApiController::class, 'bulk'])->name('assets.labels.bulk');

    Route::get('/assets/{asset}', [AssetApiController::class, 'show'])->name('assets.show');
    Route::patch('/assets/{asset}', [AssetApiController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetApiController::class, 'destroy'])->name('assets.destroy');

    Route::post('/assets/{asset}/status', [AssetStatusApiController::class, 'store'])->name('assets.status');

    Route::get('/transfers', [AssetTransferApiController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/{transfer}', [AssetTransferApiController::class, 'show'])->name('transfers.show');
    Route::post('/assets/{asset}/transfer', [AssetTransferApiController::class, 'store'])
        ->name('assets.transfer.store');
    Route::get('/assets/{asset}/transfer-history', [AssetTransferApiController::class, 'history'])
        ->name('assets.transfer.history');
    Route::post('/transfers/{transfer}/approve', [AssetTransferApiController::class, 'approve'])
        ->name('transfers.approve');
    Route::post('/transfers/{transfer}/receive', [AssetTransferApiController::class, 'receive'])
        ->name('transfers.receive');
    Route::post('/transfers/{transfer}/reject', [AssetTransferApiController::class, 'reject'])
        ->name('transfers.reject');

    Route::get('/assets/{asset}/status-history', [AssetApiController::class, 'statusHistory'])
        ->name('assets.status-history');
    Route::get('/assets/{asset}/maintenance-history', [AssetApiController::class, 'maintenanceHistory'])
        ->name('assets.maintenance-history');

    Route::get('/assets/{asset}/qr', [AssetLabelApiController::class, 'qr'])->name('assets.qr');
    Route::post('/assets/{asset}/qr/regenerate', [AssetLabelApiController::class, 'regenerate'])
        ->name('assets.qr.regenerate');
    Route::get('/assets/{asset}/barcode', [AssetLabelApiController::class, 'barcode'])->name('assets.barcode');

    Route::get('/assets/{asset}/documents', [AssetDocumentApiController::class, 'index'])
        ->name('assets.documents.index');
    Route::post('/assets/{asset}/documents', [AssetDocumentApiController::class, 'store'])
        ->name('assets.documents.store');

    // Locations (API 5, ADR-052). Hosted here rather than in a Tenancy route
    // file because AssetLocation is an Asset-module model — the same reason
    // the generic file routes live in whichever module owns their controller.
    Route::get('/factories/{factory}/locations', [AssetLocationApiController::class, 'forFactory'])
        ->name('locations.for-factory');
    Route::get('/locations', [AssetLocationApiController::class, 'index'])->name('locations.index');
    Route::post('/locations', [AssetLocationApiController::class, 'store'])->name('locations.store');
    Route::get('/locations/{location}', [AssetLocationApiController::class, 'show'])->name('locations.show');
    Route::patch('/locations/{location}', [AssetLocationApiController::class, 'update'])->name('locations.update');
    Route::patch('/locations/{location}/active', [AssetLocationApiController::class, 'setActive'])
        ->name('locations.active');
    Route::delete('/locations/{location}', [AssetLocationApiController::class, 'destroy'])
        ->name('locations.destroy');

    // A QR-scan landing (SRS 8, Data Dictionary 5.2) — a distinct `/scan`
    // prefix rather than `/assets/{code}`, since a scanned token is not an
    // asset id and would otherwise be swallowed by `{asset}` route-model
    // binding above.
    Route::get('/scan/assets/{code}', [ScanApiController::class, 'asset'])->name('scan.assets');
    Route::get('/scan/locations/{code}', [ScanApiController::class, 'location'])->name('scan.locations');
});
