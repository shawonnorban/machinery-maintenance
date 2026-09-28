<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\Api\FactoryApiController;
use Illuminate\Support\Facades\Route;

/*
 * Factories (API 5) — the unit everything else in the product is scoped to.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/factories', [FactoryApiController::class, 'index'])->name('factories.index');
    Route::post('/factories', [FactoryApiController::class, 'store'])->name('factories.store');
    Route::get('/factories/{factory}', [FactoryApiController::class, 'show'])->name('factories.show');
    Route::patch('/factories/{factory}', [FactoryApiController::class, 'update'])->name('factories.update');
    Route::patch('/factories/{factory}/active', [FactoryApiController::class, 'setActive'])
        ->name('factories.active');
    Route::delete('/factories/{factory}', [FactoryApiController::class, 'destroy'])->name('factories.destroy');
});
