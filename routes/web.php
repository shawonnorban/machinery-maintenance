<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Web\ScanController;
use App\Modules\Identity\Http\Controllers\Web\LoginController;
use App\Modules\Identity\Http\Controllers\Web\PasswordResetController;
use App\Modules\Platform\Http\Controllers\Web\PlatformDeskController;
use App\Modules\Platform\Http\Controllers\Web\PlatformFinanceController;
use App\Modules\Platform\Http\Controllers\Web\PlatformTicketController;
use App\Modules\Platform\Http\Controllers\Web\TenantAccountController;
use App\Modules\Platform\Http\Controllers\Web\TenantBillingController;
use App\Modules\Platform\Http\Controllers\Web\TenantController;
use App\Modules\Platform\Http\Controllers\Web\TenantDomainController;
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
 * The platform area (SRS 3.1, 5, 40).
 *
 * Outside /app on purpose. Every route under /app resolves a tenant context
 * from membership, and platform staff are members of nothing — putting these
 * screens there would mean either giving platform staff a company or teaching
 * the tenant middleware about an exception. Both are worse than a prefix.
 */
Route::middleware(['auth', 'platform'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function (): void {
        Route::get('/', [TenantController::class, 'index'])->name('tenants');

        // The screens that are about the platform rather than about one
        // customer, and the destinations in the sidebar beside Customers.
        Route::get('/support', [PlatformDeskController::class, 'support'])->name('support');
        Route::get('/tickets', [PlatformDeskController::class, 'tickets'])->name('tickets');
        // What the business took, is owed, and spent.
        Route::get('/finance', [PlatformFinanceController::class, 'index'])->name('finance');
        Route::post('/finance/expenses', [PlatformFinanceController::class, 'storeExpense'])
            ->name('finance.expenses.store');
        Route::delete('/finance/expenses/{expense}', [PlatformFinanceController::class, 'destroyExpense'])
            ->name('finance.expenses.destroy');

        Route::get('/notifications', [PlatformDeskController::class, 'notifications'])
            ->name('notifications');
        Route::post('/notifications/read', [PlatformDeskController::class, 'markRead'])
            ->name('notifications.read');
        Route::get('/tenants/new', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

        // Two segments after the id, so it cannot be swallowed by the
        // single-segment tab route below — and declared before it regardless,
        // because the first matching route wins.
        Route::get('/tenants/{company}/company/edit', [TenantController::class, 'edit'])
            ->name('tenants.edit');

        // One controller method, one tab of it shown at a time — each still
        // its own URL, its own back-button entry, its own page load, because
        // a company's billing and its support tickets are different enough
        // errands that cramming both onto one page just to save a route was
        // what made the page unreadable in the first place.
        Route::get('/tenants/{company}/{tab?}', [TenantController::class, 'show'])
            ->name('tenants.show')
            ->where('tab', 'company|billing|domains|support|tickets|analytics|danger');

        // A ticket thread, reached the same way whether the click came from a
        // customer's own Tickets tab or the cross-customer inbox above.
        Route::get('/tickets/{ticket}', [PlatformTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [PlatformTicketController::class, 'reply'])
            ->name('tickets.reply');
        Route::post('/tickets/{ticket}/status', [PlatformTicketController::class, 'setStatus'])
            ->name('tickets.status');
        Route::post('/tickets/{ticket}/assign', [PlatformTicketController::class, 'assign'])
            ->name('tickets.assign');

        Route::post('/tenants/{company}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');

        // Closing is reversible and erasing is not, so they are separate verbs
        // on separate URLs rather than one route with a flag. Restore and purge
        // take a raw id: their target is soft-deleted, and model binding would
        // 404 on it.
        Route::delete('/tenants/{company}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('/tenants/{company}/restore', [TenantController::class, 'restore'])->name('tenants.restore');
        Route::delete('/tenants/{company}/erase', [TenantController::class, 'purge'])->name('tenants.purge');
        Route::post('/tenants/{company}/contract', [TenantController::class, 'storeContract'])->name('tenants.contract');

        // Entitlements are amended in place; price and term still need a new
        // contract, which is why this is not part of the contract form.
        Route::patch('/tenants/{company}/limits', [TenantController::class, 'updateLimits'])
            ->name('tenants.limits');

        // The addresses a customer reaches their system on. Domain ids are
        // passed raw: CompanyDomain is tenant-scoped and model binding finds
        // nothing for platform staff, who belong to no company.
        Route::post('/tenants/{company}/domains', [TenantDomainController::class, 'store'])
            ->name('tenants.domains.store');
        Route::post('/domains/{domain}/verify', [TenantDomainController::class, 'verify'])
            ->name('domains.verify');
        Route::post('/domains/{domain}/primary', [TenantDomainController::class, 'primary'])
            ->name('domains.primary');
        Route::delete('/domains/{domain}', [TenantDomainController::class, 'destroy'])
            ->name('domains.destroy');

        // The customer's own details, and their sign-ins. Housekeeping and a
        // credential change sit side by side in the routes and could not be
        // further apart in weight — see TenantAccountController.
        Route::patch('/tenants/{company}/details', [TenantAccountController::class, 'update'])
            ->name('tenants.details');
        Route::post('/tenants/{company}/logo', [TenantAccountController::class, 'updateLogo'])
            ->name('tenants.logo');
        Route::patch('/tenants/{company}/members/{member}/email', [TenantAccountController::class, 'updateEmail'])
            ->name('tenants.members.email');
        Route::post('/tenants/{company}/members/{member}/password', [TenantAccountController::class, 'resetPassword'])
            ->name('tenants.members.password');

        // Invoicing, from the side that sends the invoice (SRS 40).
        Route::post('/tenants/{company}/invoices', [TenantBillingController::class, 'store'])
            ->name('tenants.invoices.store');
        Route::post('/invoices/{invoice}/issue', [TenantBillingController::class, 'issue'])
            ->name('invoices.issue');
        Route::post('/invoices/{invoice}/payments', [TenantBillingController::class, 'pay'])
            ->name('invoices.pay');
        Route::post('/invoices/{invoice}/void', [TenantBillingController::class, 'void'])
            ->name('invoices.void');

        Route::post('/tenants/{company}/support', [TenantController::class, 'openSupport'])->name('support.open');
        Route::post('/support/{grant}/enter', [TenantController::class, 'enterSupport'])->name('support.enter');
        Route::post('/support/{grant}/close', [TenantController::class, 'closeSupport'])->name('support.close');
    });

/*
 * The QR landing routes (Data Dictionary 5.2). Outside the /app prefix so a
 * printed label stays short, and behind auth so a scanned token alone grants
 * nothing: a guest is sent to login and returned here afterwards.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/s/l/{code}', [ScanController::class, 'location'])->name('scan.location');
    Route::get('/s/{code}', [ScanController::class, 'asset'])->name('scan.asset');
});
