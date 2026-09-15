<?php

declare(strict_types=1);

use App\Modules\Costing\Http\Controllers\Api\CostEntryApiController;
use Illuminate\Support\Facades\Route;

/*
 * What a machine has cost (API 15).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/cost-categories', [CostEntryApiController::class, 'categories'])->name('cost-categories.index');
    Route::get('/costs', [CostEntryApiController::class, 'index'])->name('costs.index');
    Route::post('/costs', [CostEntryApiController::class, 'store'])->name('costs.store');
    Route::post('/costs/{entry}/reverse', [CostEntryApiController::class, 'reverse'])->name('costs.reverse');

    Route::get('/assets/{asset}/lifecycle-cost', [CostEntryApiController::class, 'lifecycleCost'])
        ->name('assets.lifecycle-cost');
});
