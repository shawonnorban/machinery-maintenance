<?php

declare(strict_types=1);

use App\Modules\Settings\Http\Controllers\Api\MasterDataApiController;
use App\Modules\Settings\Http\Controllers\Api\NumberingApiController;
use App\Modules\Settings\Http\Controllers\Api\SettingsApiController;
use Illuminate\Support\Facades\Route;

/*
 * Reference data: asset types, categories, manufacturers, models,
 * maintenance types, failure codes, root causes, warehouses, stores, bins,
 * and the rest of MasterDataRegistry (API 5.2, Gap Analysis 3.4 #34).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/settings', [SettingsApiController::class, 'index'])->name('settings.index');
    Route::get('/settings/definitions', [SettingsApiController::class, 'definitions'])->name('settings.definitions');
    Route::post('/settings/company/logo', [SettingsApiController::class, 'updateLogo'])->name('settings.company.logo.update');
    Route::delete('/settings/company/logo', [SettingsApiController::class, 'destroyLogo'])->name('settings.company.logo.destroy');
    Route::put('/settings/{key}', [SettingsApiController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{key}', [SettingsApiController::class, 'destroy'])->name('settings.destroy');

    Route::get('/numbering', [NumberingApiController::class, 'index'])->name('numbering.index');
    Route::patch('/numbering/{documentType}', [NumberingApiController::class, 'update'])->name('numbering.update');
    Route::delete('/numbering/{documentType}', [NumberingApiController::class, 'reset'])->name('numbering.reset');

    Route::get('/master-data', [MasterDataApiController::class, 'index'])->name('master-data.index');
    Route::get('/master-data/{type}', [MasterDataApiController::class, 'show'])->name('master-data.show');
    Route::patch('/master-data/{type}/{row}', [MasterDataApiController::class, 'update'])
        ->name('master-data.update');
    Route::patch('/master-data/{type}/{row}/active', [MasterDataApiController::class, 'setActive'])
        ->name('master-data.set-active');
    Route::delete('/master-data/{type}/{row}', [MasterDataApiController::class, 'destroy'])
        ->name('master-data.destroy');
});

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    Route::post('/master-data/{type}', [MasterDataApiController::class, 'store'])->name('master-data.store');
});
