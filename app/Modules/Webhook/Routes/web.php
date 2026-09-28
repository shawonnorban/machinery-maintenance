<?php

declare(strict_types=1);

use App\Modules\Webhook\Http\Controllers\Web\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');

    // Before the wildcard, or "deliveries" would be read as an endpoint id.
    Route::post('/webhooks/deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver'])
        ->name('webhooks.redeliver');

    Route::get('/webhooks/{endpoint}', [WebhookController::class, 'show'])->name('webhooks.show');
    Route::put('/webhooks/{endpoint}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::post('/webhooks/{endpoint}/rotate', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
    Route::post('/webhooks/{endpoint}/enable', [WebhookController::class, 'enable'])->name('webhooks.enable');
    Route::post('/webhooks/{endpoint}/pause', [WebhookController::class, 'pause'])->name('webhooks.pause');
});
