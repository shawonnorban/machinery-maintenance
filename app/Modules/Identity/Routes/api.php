<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Api\PermissionApiController;
use App\Modules\Identity\Http\Controllers\Api\RoleApiController;
use App\Modules\Identity\Http\Controllers\Api\TeamApiController;
use App\Modules\Identity\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

/*
 * User, role, permission and team administration (API 4.1).
 *
 * Absent from v1.0, which specified a full RBAC model with no way to
 * administer it (Gap Analysis 3.4 #33).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/users', [UserApiController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserApiController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}', [UserApiController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/deactivate', [UserApiController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/activate', [UserApiController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/reset-password', [UserApiController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('/users/{user}', [UserApiController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}/roles', [UserApiController::class, 'roles'])->name('users.roles.index');
    Route::delete('/users/{user}/roles/{assignment}', [UserApiController::class, 'removeRoleAssignment'])
        ->name('users.roles.destroy');

    Route::get('/roles', [RoleApiController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}', [RoleApiController::class, 'show'])->name('roles.show');
    Route::patch('/roles/{role}', [RoleApiController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleApiController::class, 'destroy'])->name('roles.destroy');

    Route::get('/permissions', [PermissionApiController::class, 'index'])->name('permissions.index');

    Route::get('/teams', [TeamApiController::class, 'index'])->name('teams.index');
    Route::get('/teams/form-options', [TeamApiController::class, 'formOptions'])->name('teams.form-options');
    Route::get('/teams/{team}', [TeamApiController::class, 'show'])->name('teams.show');
    Route::patch('/teams/{team}', [TeamApiController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamApiController::class, 'destroy'])->name('teams.destroy');
});

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    // Each of these creates a row a retried request must not duplicate: an
    // account and membership, a role assignment, a role, a team.
    Route::post('/users', [UserApiController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/roles', [UserApiController::class, 'assignRole'])->name('users.roles.store');
    Route::post('/roles', [RoleApiController::class, 'store'])->name('roles.store');
    Route::post('/teams', [TeamApiController::class, 'store'])->name('teams.store');
});
