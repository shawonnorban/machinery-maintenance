<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\Web\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing');
    Route::get('/billing/invoices/{invoice}', [BillingController::class, 'show'])->name('billing.invoice');

    // Left reachable while a subscription is read-only: locking a customer out
    // of the page where they would settle the account would be a remarkable way
    // to not get paid.
    Route::post('/billing/invoices/{invoice}/payments', [BillingController::class, 'pay'])
        ->name('billing.invoice.pay');
});
