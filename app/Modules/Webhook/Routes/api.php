<?php

declare(strict_types=1);

use App\Modules\Webhook\Http\Controllers\Api\WebhookEndpointApiController;
use Illuminate\Support\Facades\Route;

/*
 * Outgoing integrations (API 28).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/webhooks', [WebhookEndpointApiController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookEndpointApiController::class, 'store'])->name('webhooks.store');
    // Ahead of the {endpoint} show route below, which would otherwise
    // swallow "events" as an attempted endpoint id.
    Route::get('/webhooks/events', [WebhookEndpointApiController::class, 'events'])->name('webhooks.events');
    Route::get('/webhooks/{endpoint}', [WebhookEndpointApiController::class, 'show'])->name('webhooks.show');
    Route::patch('/webhooks/{endpoint}', [WebhookEndpointApiController::class, 'update'])->name('webhooks.update');
    Route::delete('/webhooks/{endpoint}', [WebhookEndpointApiController::class, 'destroy'])->name('webhooks.destroy');

    Route::post('/webhooks/{endpoint}/rotate-secret', [WebhookEndpointApiController::class, 'rotateSecret'])
        ->name('webhooks.rotate-secret');
    Route::post('/webhooks/{endpoint}/enable', [WebhookEndpointApiController::class, 'enable'])
        ->name('webhooks.enable');
    Route::post('/webhooks/{endpoint}/pause', [WebhookEndpointApiController::class, 'pause'])
        ->name('webhooks.pause');
    Route::post('/webhook-deliveries/{delivery}/redeliver', [WebhookEndpointApiController::class, 'redeliver'])
        ->name('webhook-deliveries.redeliver');
});
