<?php

declare(strict_types=1);

use App\Modules\AiCore\Http\Controllers\Api\AiSettingsApiController;
use Illuminate\Support\Facades\Route;

/*
 * AI services (Settings → Integration Hub → AI Services). The services and
 * the features are ERP resources (`/erp/ai-services`, `/erp/ai-features`);
 * these are the feature list and a service's connection test.
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/ai/features', [AiSettingsApiController::class, 'features'])->name('ai.features');
    Route::post('/ai/services/{id}/test', [AiSettingsApiController::class, 'test'])->name('ai.services.test');
});
