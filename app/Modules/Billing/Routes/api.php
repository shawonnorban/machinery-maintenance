<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\Api\SubscriptionApiController;
use Illuminate\Support\Facades\Route;

/*
 * What the customer owes and what they are using (API 25).
 *
 * See SubscriptionApiController's docblock for what v1.0's spec asked for
 * here that is deliberately not built: contract creation, editing,
 * cancellation, renewal, and refunds are platform-staff operations with no
 * tenant self-service equivalent anywhere in this product.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/subscription', [SubscriptionApiController::class, 'show'])->name('subscription.show');
    Route::get('/subscription/invoices', [SubscriptionApiController::class, 'invoices'])
        ->name('subscription.invoices.index');
    Route::get('/subscription/invoices/{invoice}', [SubscriptionApiController::class, 'showInvoice'])
        ->name('subscription.invoices.show');
    Route::post('/subscription/invoices/{invoice}/pay', [SubscriptionApiController::class, 'storePayment'])
        ->name('subscription.invoices.pay');
    Route::get('/subscription/payments', [SubscriptionApiController::class, 'payments'])
        ->name('subscription.payments.index');
});
