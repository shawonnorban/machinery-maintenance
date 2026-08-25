<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\Web\SupportSessionController;
use App\Modules\Platform\Http\Controllers\Web\SupportTicketController;
use Illuminate\Support\Facades\Route;

/*
 * The two things the platform area needs inside the tenant application
 * (SRS 5, 5.4): the way back out of a support session, and the way for a
 * customer to reach the platform in writing.
 *
 * The rest of the platform lives outside /app, in routes/web.php, because
 * /app resolves a tenant context and platform staff have no tenant.
 */

Route::middleware('auth')->group(function (): void {
    Route::post('/support/leave', [SupportSessionController::class, 'leave'])->name('support.leave');

    Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
    Route::get('/support/tickets/new', [SupportTicketController::class, 'create'])
        ->name('support.tickets.create');
    Route::post('/support/tickets', [SupportTicketController::class, 'store'])->name('support.tickets.store');
    Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])
        ->name('support.tickets.show');
    Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])
        ->name('support.tickets.reply');
});
