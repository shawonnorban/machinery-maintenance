<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Web\ScanController;
use App\Modules\Identity\Http\Controllers\Web\LoginController;
use App\Modules\Identity\Http\Controllers\Web\MfaChallengeController;
use App\Modules\Identity\Http\Controllers\Web\PasswordResetController;
use App\Modules\Platform\Http\Controllers\Web\TenantController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    /*
     * The second half of signing in (SRS 50.3). Under `guest` because that is
     * exactly what somebody at this screen still is: the password was accepted
     * and nothing was logged in.
     */
    Route::get('/mfa/challenge', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'store']);

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
        Route::get('/tenants/new', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{company}', [TenantController::class, 'show'])->name('tenants.show');

        Route::post('/tenants/{company}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{company}/contract', [TenantController::class, 'storeContract'])->name('tenants.contract');

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
