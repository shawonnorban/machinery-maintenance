<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Web\CompanySwitchController;
use Illuminate\Support\Facades\Route;

/*
 * Every screen this file used to register (account, teams, settings/users,
 * settings/roles) is gone (Phase D/F, docs/12-Stack-Migration-
 * Implementation-Plan.md) — fully replaced by the Next.js app.
 *
 * `/switch-company` survives on purpose: it has no screen of its own, and
 * `Tenancy/Resources/views/{suspended,closed}.blade.php` — rendered by
 * `ResolveTenantContext` middleware, which runs on every web request
 * regardless of which module's routes are still registered — post to it
 * directly. Decommissioning this file wholesale would have taken a
 * suspended or closed tenant's error page down with it (a
 * RouteNotFoundException instead of the page explaining why they're
 * locked out) for a route that costs nothing to leave live.
 */
Route::middleware(['auth'])->group(function (): void {
    Route::post('/switch-company', [CompanySwitchController::class, 'store'])
        ->name('switch-company');
});
