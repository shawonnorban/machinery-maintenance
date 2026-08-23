<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Api\AssetApiController;
use Illuminate\Support\Facades\Route;

/*
 * Machines (API 6).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/assets', [AssetApiController::class, 'index'])->name('assets.index');
    Route::get('/assets/{asset}', [AssetApiController::class, 'show'])->name('assets.show');
});
