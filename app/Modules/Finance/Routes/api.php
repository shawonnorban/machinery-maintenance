<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\Api\GlMappingApiController;
use Illuminate\Support\Facades\Route;

/*
 * GL wiring (Shared Systems spec §2, §5.1). The mapping is data, and this
 * is where a finance team edits it once the chart of accounts is signed
 * off — no code or migration changes with it.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/gl-mappings', [GlMappingApiController::class, 'index'])->name('gl-mappings.index');
    Route::patch('/gl-mappings/{eventType}', [GlMappingApiController::class, 'update'])->name('gl-mappings.update');
    Route::get('/gl-entries', [GlMappingApiController::class, 'entries'])->name('gl-entries.index');
});
