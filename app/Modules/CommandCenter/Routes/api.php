<?php

declare(strict_types=1);

use App\Modules\CommandCenter\Http\Controllers\Api\CommandCenterApiController;
use Illuminate\Support\Facades\Route;

/*
 * Command Center (AnnotechERP 27). Its lists are ERP resources
 * (`/erp/cc-boards`, `cc-drill-paths`, `cc-compositions` …); this is what
 * their pickers offer.
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/command-center/options', [CommandCenterApiController::class, 'options'])->name('command-center.options');
    Route::get('/command-center/deck', [CommandCenterApiController::class, 'deck'])->name('command-center.deck');
});
