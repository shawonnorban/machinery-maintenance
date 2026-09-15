<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\Web\SessionTokenController;
use Illuminate\Support\Facades\Route;

/*
 * The machine-credential *screen* this file used to also register
 * (`/app/settings/api-clients*`) is gone (Phase D/F, docs/12-Stack-
 * Migration-Implementation-Plan.md) — fully replaced by the Next.js app's
 * own `/settings/api-clients`.
 *
 * `/session-token` survives on purpose, and is not a screen at all: it's
 * what the *still-Blade* technician mobile pages (`layouts.mobile`, the QR
 * scan landing pages — no Next.js port of those exists yet) use to get a
 * bearer token for their own offline queue (`resources/js/offline/token.js`
 * → `queue.js`, SRS 38) — session-authenticated and CSRF-protected like any
 * other web route, which is what makes it safe to hand a bearer token to a
 * browser at all. Decommissioning this file wholesale would have taken
 * that down along with the screen, breaking offline breakdown reporting
 * from a scanned machine on the factory floor for no reason.
 */
Route::middleware('auth')->group(function (): void {
    Route::post('/session-token', [SessionTokenController::class, 'store'])->name('session-token');
});
