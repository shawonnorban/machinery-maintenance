<?php

declare(strict_types=1);

use App\Modules\Settings\Http\Controllers\Web\CompanySettingsController;
use App\Modules\Settings\Http\Controllers\Web\MasterDataController;
use App\Modules\Settings\Http\Controllers\Web\NumberingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    // How this company wants the product to behave. Several of these change
    // what a number means, not how a screen looks.
    Route::get('/company', [CompanySettingsController::class, 'index'])->name('company');
    Route::post('/company', [CompanySettingsController::class, 'update'])->name('company.update');
    // Drops a factory own answer so it follows the company again.
    Route::delete('/company', [CompanySettingsController::class, 'reset'])->name('company.reset');

    // What this company's documents are called (SRS 52). A format change is
    // date-effective by construction: it takes hold when the counter next
    // restarts, never in the middle of a period.
    Route::get('/numbering', [NumberingController::class, 'index'])->name('numbering');
    Route::patch('/numbering/{documentType}', [NumberingController::class, 'update'])->name('numbering.update');
    Route::delete('/numbering/{documentType}', [NumberingController::class, 'reset'])->name('numbering.reset');

    // The name the sidebar has been pointing at since the shell was built.
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data');

    Route::get('/master-data/{type}', [MasterDataController::class, 'show'])->name('master-data.show');
    Route::post('/master-data/{type}', [MasterDataController::class, 'store'])->name('master-data.store');
    Route::put('/master-data/{type}/{row}', [MasterDataController::class, 'update'])->name('master-data.update');
    Route::post('/master-data/{type}/{row}/toggle', [MasterDataController::class, 'toggle'])->name('master-data.toggle');
    Route::delete('/master-data/{type}/{row}', [MasterDataController::class, 'destroy'])->name('master-data.destroy');
});
