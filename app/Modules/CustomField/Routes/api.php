<?php

declare(strict_types=1);

use App\Modules\CustomField\Http\Controllers\Api\CustomFieldDefinitionApiController;
use App\Modules\CustomField\Http\Controllers\Api\CustomFieldValueApiController;
use Illuminate\Support\Facades\Route;

/*
 * The generic custom-fields engine (definitions + values). No web routes —
 * this module's own admin screen and every entity's own form are Next.js
 * only, matching the rest of this app's direction.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/custom-fields/definitions', [CustomFieldDefinitionApiController::class, 'index'])
        ->name('custom-fields.definitions.index');
    Route::post('/custom-fields/definitions', [CustomFieldDefinitionApiController::class, 'store'])
        ->name('custom-fields.definitions.store');
    Route::patch('/custom-fields/definitions/{definition}', [CustomFieldDefinitionApiController::class, 'update'])
        ->name('custom-fields.definitions.update');
    Route::patch('/custom-fields/definitions/{definition}/active', [CustomFieldDefinitionApiController::class, 'setActive'])
        ->name('custom-fields.definitions.set-active');
    Route::delete('/custom-fields/definitions/{definition}', [CustomFieldDefinitionApiController::class, 'destroy'])
        ->name('custom-fields.definitions.destroy');

    Route::get('/custom-fields/values', [CustomFieldValueApiController::class, 'show'])
        ->name('custom-fields.values.show');
    Route::post('/custom-fields/values', [CustomFieldValueApiController::class, 'store'])
        ->name('custom-fields.values.store');
});
