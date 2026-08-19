<?php

declare(strict_types=1);

use App\Modules\Costing\Http\Controllers\Web\AssetCostController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/assets/{asset}/costs', [AssetCostController::class, 'show'])->name('assets.costs');

    // Append-only: a correction is a reversal row, never an edit, so there is
    // no update or delete route to reach for.
    Route::post('/costs', [AssetCostController::class, 'store'])->name('costs.store');
    Route::post('/costs/{entry}/reverse', [AssetCostController::class, 'reverse'])->name('costs.reverse');
});
