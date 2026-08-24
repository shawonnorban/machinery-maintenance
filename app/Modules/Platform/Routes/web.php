<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\Web\SupportSessionController;
use Illuminate\Support\Facades\Route;

/*
 * The one route the platform area needs inside the tenant application
 * (SRS 5.4): the way back out of a support session.
 *
 * The rest of the platform lives outside /app, in routes/web.php, because
 * /app resolves a tenant context and platform staff have no tenant.
 */

Route::middleware('auth')->group(function (): void {
    Route::post('/support/leave', [SupportSessionController::class, 'leave'])->name('support.leave');
});
