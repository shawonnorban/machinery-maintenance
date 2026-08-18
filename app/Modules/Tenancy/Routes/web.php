<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\Web\DashboardController;
use App\Modules\Tenancy\Http\Controllers\Web\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/factory-scope', [PreferenceController::class, 'factoryScope'])->name('factory-scope');
    Route::post('/locale', [PreferenceController::class, 'locale'])->name('locale');
});
