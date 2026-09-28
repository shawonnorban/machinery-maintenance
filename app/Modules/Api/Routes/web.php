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
 * `/session-token` outlived that for a while: it minted a bearer token for
 * the old Blade technician mobile pages' own offline queue
 * (`resources/js/offline/token.js` → `queue.js`, SRS 38). Those pages —
 * the QR scan landing pages, the breakdown report form, "My Work" — all
 * belonged to modules (Asset, Breakdown, WorkOrder) already listed in
 * `ModuleServiceProvider::WEB_DECOMMISSIONED`, so once the last of them
 * was confirmed to have no other caller, this route had none either. `Api`
 * is now in that list too, and this file (like every other decommissioned
 * module's `Routes/web.php`) is simply never loaded — left in place rather
 * than deleted, so un-decommissioning is a one-line revert if a gap turns
 * up. `frontend/src/lib/offline/*` + `frontend/src/app/api/offline-relay/
 * route.js` is the equivalent mechanism in the current stack, and never
 * needed this route at all — the bearer token lives in an httpOnly cookie
 * on the Next.js side, not in page-reachable JS.
 */
Route::middleware('auth')->group(function (): void {
    Route::post('/session-token', [SessionTokenController::class, 'store'])->name('session-token');
});
