<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\Web\DashboardController;
use App\Modules\Tenancy\Http\Controllers\Web\FactoryController;
use App\Modules\Tenancy\Http\Controllers\Web\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/factory-scope', [PreferenceController::class, 'factoryScope'])->name('factory-scope');
    Route::post('/locale', [PreferenceController::class, 'locale'])->name('locale');
});

/*
 * Factory administration (SRS 4). Under /settings with the rest of the
 * configuration screens, and behind settings.factory.manage.
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/factories', [FactoryController::class, 'index'])->name('factories');
    Route::get('/factories/create', [FactoryController::class, 'create'])->name('factories.create');
    Route::post('/factories', [FactoryController::class, 'store'])->name('factories.store');
    Route::get('/factories/{factory}/edit', [FactoryController::class, 'edit'])->name('factories.edit');
    Route::patch('/factories/{factory}', [FactoryController::class, 'update'])->name('factories.update');
    Route::post('/factories/{factory}/toggle', [FactoryController::class, 'toggle'])->name('factories.toggle');
    // Only a factory nothing is filed against. A factory that has run is
    // closed, because it still owns everything that ever happened in it.
    Route::delete('/factories/{factory}', [FactoryController::class, 'destroy'])->name('factories.destroy');
});
