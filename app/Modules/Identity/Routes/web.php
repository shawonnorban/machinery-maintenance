<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Web\AccountController;
use App\Modules\Identity\Http\Controllers\Web\CompanySwitchController;
use App\Modules\Identity\Http\Controllers\Web\RoleController;
use App\Modules\Identity\Http\Controllers\Web\TeamController;
use App\Modules\Identity\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Authenticated routes under /app (Frontend 5).
 * Guest auth routes live in routes/web.php, outside the /app prefix.
 */

Route::middleware(['auth'])->group(function (): void {
    Route::post('/switch-company', [CompanySwitchController::class, 'store'])
        ->name('switch-company');

    /*
     * Your own account (SRS 50.2, 50.3). No permission guards any of it:
     * every route here acts on the account of whoever is asking, and a
     * technician who can reach no other screen still has to be able to change
     * their own password.
     */
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::post('/account/password', [AccountController::class, 'changePassword'])->name('account.password');

    Route::post('/account/mfa', [AccountController::class, 'beginMfa'])->name('account.mfa.begin');
    Route::post('/account/mfa/confirm', [AccountController::class, 'confirmMfa'])->name('account.mfa.confirm');
    Route::post('/account/mfa/cancel', [AccountController::class, 'cancelMfa'])->name('account.mfa.cancel');
    Route::delete('/account/mfa', [AccountController::class, 'disableMfa'])->name('account.mfa.disable');
    Route::post('/account/mfa/recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])
        ->name('account.mfa.recovery-codes');

    Route::delete('/account/sessions/{session}', [AccountController::class, 'revokeSession'])
        ->name('account.sessions.revoke');
    Route::delete('/account/tokens/{token}', [AccountController::class, 'revokeToken'])
        ->name('account.tokens.revoke');

    /*
     * Maintenance teams (SRS 25): who a job goes to when it does not go to one
     * person. Work orders, breakdowns, plans, approval steps and escalation
     * rules can all name one.
     */
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::post('/teams/{team}/toggle', [TeamController::class, 'toggle'])->name('teams.toggle');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
});

/*
 * User administration (SRS 5). Membership of this company, not the account:
 * the same person may work for two companies in a group.
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    // Ends the membership. The account and the work signed off under it stay.
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles');
});
