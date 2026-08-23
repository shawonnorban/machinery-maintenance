<?php

declare(strict_types=1);

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
