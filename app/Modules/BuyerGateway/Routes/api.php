<?php

declare(strict_types=1);

use App\Modules\BuyerGateway\Http\Controllers\Api\BuyerGatewayApiController;
use Illuminate\Support\Facades\Route;

/*
 * Buyer Gateway (AnnotechERP 25). The Access & Onboarding lists are ERP
 * resources (`/erp/bg-portal-users`, `bg-permission-sets`, `bg-onboardings`,
 * `bg-notification-prefs`); this is what their pickers offer.
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/buyer-gateway/options', [BuyerGatewayApiController::class, 'options'])->name('buyer-gateway.options');
    Route::post('/buyer-gateway/document-files', [BuyerGatewayApiController::class, 'documentFile'])->name('buyer-gateway.document-files');
    Route::post('/buyer-gateway/logo-files', [BuyerGatewayApiController::class, 'logoFile'])->name('buyer-gateway.logo-files');
});
