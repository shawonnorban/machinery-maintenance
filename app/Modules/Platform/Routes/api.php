<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\Api\PlatformAuthApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformContractApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformDomainApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformFinanceApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformModuleApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformInvoiceApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformNotificationApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformSupportGrantApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformTenantAccountApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformTenantApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformTicketApiController;
use App\Modules\Platform\Http\Controllers\Api\PlatformTicketAttachmentApiController;
use App\Modules\Platform\Http\Controllers\Api\SupportTicketApiController;
use App\Modules\Platform\Http\Controllers\Api\SupportTicketAttachmentApiController;
use App\Shared\Files\Http\Controllers\Api\FileApiController;
use Illuminate\Support\Facades\Route;

/*
 * Everything here is prefixed /api/v1 by the module loader.
 *
 * Two areas share this file but not a middleware group: the tenant's own
 * support-ticket endpoints run under the ordinary `api.auth` a tenant token
 * always carries (Platform API §7's note — a customer asking the platform
 * something needs no admin gate), while every `/platform/*` route below runs
 * `platform.auth` instead, because a valid tenant token must never reach
 * them (Platform API §1).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/support/tickets', [SupportTicketApiController::class, 'index'])->name('support.tickets.index');
    Route::post('/support/tickets', [SupportTicketApiController::class, 'store'])->name('support.tickets.store');
    Route::get('/support/tickets/{ticket}', [SupportTicketApiController::class, 'show'])->name('support.tickets.show');
    Route::patch('/support/tickets/{ticket}', [SupportTicketApiController::class, 'update'])->name('support.tickets.update');
    Route::delete('/support/tickets/{ticket}', [SupportTicketApiController::class, 'destroy'])->name('support.tickets.destroy');
    Route::post('/support/tickets/{ticket}/reply', [SupportTicketApiController::class, 'reply'])
        ->name('support.tickets.reply');
    Route::get('/support/tickets/{ticket}/attachments', [SupportTicketAttachmentApiController::class, 'index'])
        ->name('support.tickets.attachments.index');
    Route::post('/support/tickets/{ticket}/attachments', [SupportTicketAttachmentApiController::class, 'store'])
        ->name('support.tickets.attachments.store');
});

Route::middleware('throttle:api-auth')->group(function (): void {
    Route::post('/platform/auth/login', [PlatformAuthApiController::class, 'login'])->name('platform.auth.login');
});

Route::middleware(['platform.auth', 'throttle:api'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/auth/me', [PlatformAuthApiController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [PlatformAuthApiController::class, 'logout'])->name('auth.logout');

    Route::get('/tenants', [PlatformTenantApiController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [PlatformTenantApiController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{company}', [PlatformTenantApiController::class, 'show'])->name('tenants.show');
    Route::patch('/tenants/{company}', [PlatformTenantApiController::class, 'update'])->name('tenants.update');
    Route::post('/tenants/{company}/logo', [PlatformTenantAccountApiController::class, 'updateLogo'])
        ->name('tenants.logo');
    Route::post('/tenants/{company}/suspend', [PlatformTenantApiController::class, 'suspend'])
        ->name('tenants.suspend');
    Route::post('/tenants/{company}/reactivate', [PlatformTenantApiController::class, 'reactivate'])
        ->name('tenants.reactivate');
    Route::delete('/tenants/{company}', [PlatformTenantApiController::class, 'destroy'])->name('tenants.destroy');
    Route::post('/tenants/{company}/restore', [PlatformTenantApiController::class, 'restore'])
        ->name('tenants.restore');
    Route::delete('/tenants/{company}/purge', [PlatformTenantApiController::class, 'purge'])
        ->name('tenants.purge');

    Route::get('/tenants/{company}/members', [PlatformTenantApiController::class, 'members'])
        ->name('tenants.members.index');
    Route::get('/tenants/{company}/usage', [PlatformTenantApiController::class, 'usage'])
        ->name('tenants.usage');

    Route::get('/tenants/{company}/contracts', [PlatformContractApiController::class, 'index'])
        ->name('tenants.contracts.index');
    Route::post('/tenants/{company}/contracts', [PlatformContractApiController::class, 'store'])
        ->name('tenants.contracts.store');
    Route::patch('/tenants/{company}/entitlements', [PlatformContractApiController::class, 'updateEntitlements'])
        ->name('tenants.entitlements.update');

    // The modules a customer has been given (every module but the core ones).
    Route::get('/tenants/{company}/modules', [PlatformModuleApiController::class, 'index'])
        ->name('tenants.modules.index');
    Route::put('/tenants/{company}/modules', [PlatformModuleApiController::class, 'update'])
        ->name('tenants.modules.update-many');
    Route::put('/tenants/{company}/modules/{code}', [PlatformModuleApiController::class, 'update'])
        ->where('code', '[A-Z_]{2,40}')->name('tenants.modules.update');

    Route::get('/tenants/{company}/invoices', [PlatformInvoiceApiController::class, 'index'])
        ->name('tenants.invoices.index');
    Route::post('/tenants/{company}/invoices', [PlatformInvoiceApiController::class, 'store'])
        ->name('tenants.invoices.store');
    Route::post('/invoices/{invoiceId}/issue', [PlatformInvoiceApiController::class, 'issue'])
        ->name('invoices.issue');
    Route::post('/invoices/{invoiceId}/payments', [PlatformInvoiceApiController::class, 'pay'])
        ->name('invoices.pay');
    Route::post('/invoices/{invoiceId}/void', [PlatformInvoiceApiController::class, 'void'])
        ->name('invoices.void');

    Route::get('/tenants/{company}/domains', [PlatformDomainApiController::class, 'index'])
        ->name('tenants.domains.index');
    Route::post('/tenants/{company}/domains', [PlatformDomainApiController::class, 'store'])
        ->name('tenants.domains.store');
    Route::post('/domains/{domain}/verify', [PlatformDomainApiController::class, 'verify'])
        ->name('domains.verify');
    Route::post('/domains/{domain}/primary', [PlatformDomainApiController::class, 'primary'])
        ->name('domains.primary');
    Route::delete('/domains/{domain}', [PlatformDomainApiController::class, 'destroy'])->name('domains.destroy');

    Route::patch('/tenants/{company}/members/{member}/email', [PlatformTenantAccountApiController::class, 'updateEmail'])
        ->name('tenants.members.email');
    Route::post('/tenants/{company}/members/{member}/reset-password', [PlatformTenantAccountApiController::class, 'resetPassword'])
        ->name('tenants.members.reset-password');

    Route::get('/support-grants', [PlatformSupportGrantApiController::class, 'index'])->name('support-grants.index');
    Route::post('/tenants/{company}/support-grants', [PlatformSupportGrantApiController::class, 'store'])
        ->name('tenants.support-grants.store');
    Route::post('/support-grants/{grant}/enter', [PlatformSupportGrantApiController::class, 'enter'])
        ->name('support-grants.enter');
    Route::post('/support-grants/{grant}/leave', [PlatformSupportGrantApiController::class, 'leave'])
        ->name('support-grants.leave');
    Route::post('/support-grants/{grant}/close', [PlatformSupportGrantApiController::class, 'close'])
        ->name('support-grants.close');

    Route::get('/tickets', [PlatformTicketApiController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [PlatformTicketApiController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [PlatformTicketApiController::class, 'reply'])->name('tickets.reply');
    Route::patch('/tickets/{ticket}/status', [PlatformTicketApiController::class, 'setStatus'])
        ->name('tickets.status');
    Route::patch('/tickets/{ticket}/assign', [PlatformTicketApiController::class, 'assign'])->name('tickets.assign');
    Route::patch('/tickets/{ticket}/classification', [PlatformTicketApiController::class, 'reclassify'])
        ->name('tickets.classification');
    Route::get('/tickets/{ticket}/attachments', [PlatformTicketAttachmentApiController::class, 'index'])
        ->name('tickets.attachments.index');
    Route::post('/tickets/{ticket}/attachments', [PlatformTicketAttachmentApiController::class, 'store'])
        ->name('tickets.attachments.store');

    // Platform staff had no way to view or delete any attachment at all
    // before ticket attachments existed — `FileApiController`'s own routes
    // previously lived only under the tenant's `api.auth` group. Reused
    // here rather than duplicated: the same controller already branches
    // its own permission logic per `attachable_type`.
    Route::get('/files/{file}', [FileApiController::class, 'show'])->name('files.show');
    Route::get('/files/{file}/download', [FileApiController::class, 'download'])->name('files.download');
    Route::delete('/files/{file}', [FileApiController::class, 'destroy'])->name('files.destroy');

    Route::get('/finance/summary', [PlatformFinanceApiController::class, 'summary'])->name('finance.summary');
    Route::get('/finance/payments', [PlatformFinanceApiController::class, 'payments'])->name('finance.payments');
    Route::get('/finance/invoices/overdue', [PlatformFinanceApiController::class, 'overdueInvoices'])
        ->name('finance.invoices.overdue');
    Route::get('/finance/expenses', [PlatformFinanceApiController::class, 'expenses'])->name('finance.expenses.index');
    Route::post('/finance/expenses', [PlatformFinanceApiController::class, 'storeExpense'])
        ->name('finance.expenses.store');
    Route::delete('/finance/expenses/{expense}', [PlatformFinanceApiController::class, 'destroyExpense'])
        ->name('finance.expenses.destroy');

    Route::get('/notifications', [PlatformNotificationApiController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [PlatformNotificationApiController::class, 'readAll'])
        ->name('notifications.read-all');
});
