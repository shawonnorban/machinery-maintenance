<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Web\ScanController;
use App\Modules\Identity\Http\Controllers\Web\LoginController;
use App\Modules\Identity\Http\Controllers\Web\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
 * The platform admin console (SRS 3.1, 5, 40) — decommissioned. Every screen
 * this block used to register (the customer list, a customer's own tabs,
 * finance, the cross-customer ticket inbox, support access) has a Next.js
 * equivalent under `/platform` now, and the two gaps that kept this Blade
 * console alive alongside it are closed: entering a support grant mints a
 * bearer token straight into this app's own tenant session cookie
 * (`PlatformSupportGrantApiController::enter`, wired up from
 * `frontend/src/app/platform/(console)/tenants/[companyId]/actions.js`'s
 * `enterSupportGrant` — no cross-app handoff needed, since the platform
 * console and the tenant app are the same Next.js deployment now), and a
 * ticket can be reassigned to any platform staff member, not only
 * self-assigned (`PlatformTicketApiController::assign` plus a real picker in
 * `frontend/src/components/platform/ticket-thread.jsx`).
 *
 * Running two live admin UIs against the same data was the actual defect —
 * divergent enforcement, and no single place to point an audit at — not a
 * cosmetic duplication, which is why this is a removal rather than a second
 * entry in `ModuleServiceProvider::WEB_DECOMMISSIONED` (that array is for a
 * module's own `Routes/web.php`; this block was hand-registered here
 * instead, for the reasons this comment used to explain). The controllers
 * and Blade views these routes pointed at (`TenantController`,
 * `PlatformFinanceController`, `PlatformTicketController`,
 * `TenantAccountController`, `TenantBillingController`,
 * `TenantDomainController`, `PlatformDeskController`, and everything under
 * `app/Modules/Platform/Resources/views/`) are left on disk rather than
 * deleted alongside this — their business logic (`ManageSupportAccess`,
 * `ManageSupportTicket`, the platform Actions) is shared with the API
 * controllers that are still live, and git history is the record of what
 * this route block itself looked like, not a kept-but-unreachable file.
 */

/*
 * The QR landing routes (Data Dictionary 5.2). Outside the /app prefix so a
 * printed label stays short. No `auth` gate here any more — `ScanController`
 * is a bare redirect into the Next.js scan page now, which does its own
 * auth (and its own "send a guest to login and back") on the other side;
 * gating here too would mean both a Blade and a Next.js session have to be
 * live at once, which is the double-login trap this redirect exists to
 * avoid (see `ScanController`'s own docblock).
 */
Route::get('/s/l/{code}', [ScanController::class, 'location'])->name('scan.location');
Route::get('/s/{code}', [ScanController::class, 'asset'])->name('scan.asset');
