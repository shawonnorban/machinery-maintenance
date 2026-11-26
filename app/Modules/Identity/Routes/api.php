<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Api\AccessMappingApiController;
use App\Modules\Identity\Http\Controllers\Api\CategoryPermissionApiController;
use App\Modules\Identity\Http\Controllers\Api\LookupApiController;
use App\Modules\Identity\Http\Controllers\Api\MenuAccessApiController;
use App\Modules\Identity\Http\Controllers\Api\PermissionApiController;
use App\Modules\Identity\Http\Controllers\Api\RoleApiController;
use App\Modules\Identity\Http\Controllers\Api\TeamApiController;
use App\Modules\Identity\Http\Controllers\Api\TnaEventApiController;
use App\Modules\Identity\Http\Controllers\Api\UserApiController;
use App\Modules\Identity\Http\Controllers\Api\UserCompanyMappingApiController;
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
    Route::post('/users/{user}/password', [UserApiController::class, 'setPassword'])->name('users.password');
    Route::get('/access/password-changes', [UserApiController::class, 'passwordChanges'])->name('access.password-changes');
    Route::delete('/users/{user}', [UserApiController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}/roles', [UserApiController::class, 'roles'])->name('users.roles.index');
    Route::delete('/users/{user}/roles/{assignment}', [UserApiController::class, 'removeRoleAssignment'])
        ->name('users.roles.destroy');

    Route::get('/roles', [RoleApiController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}', [RoleApiController::class, 'show'])->name('roles.show');
    Route::patch('/roles/{role}', [RoleApiController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleApiController::class, 'destroy'])->name('roles.destroy');

    Route::get('/permissions', [PermissionApiController::class, 'index'])->name('permissions.index');

    // Access Control's menu tree and V/C/E/D/A/P/X grants (AnnotechERP 02/01).
    Route::get('/access/navigation', [MenuAccessApiController::class, 'navigation'])->name('access.navigation');
    Route::get('/access/restricted-screens', [MenuAccessApiController::class, 'restricted'])->name('access.restricted');
    Route::get('/access/locked-modules', [MenuAccessApiController::class, 'locked'])->name('access.locked');
    Route::get('/access/menus', [MenuAccessApiController::class, 'menus'])->name('access.menus.index');
    Route::patch('/access/menus/{menu}', [MenuAccessApiController::class, 'updateMenu'])->name('access.menus.update');
    Route::get('/access/modules', [MenuAccessApiController::class, 'modules'])->name('access.modules.index');
    Route::patch('/access/modules/{module}', [MenuAccessApiController::class, 'updateModule'])->name('access.modules.update');
    Route::get('/access/roles/{role}/menu-grants', [MenuAccessApiController::class, 'grants'])->name('access.roles.grants');
    Route::put('/access/roles/{role}/menu-grants', [MenuAccessApiController::class, 'saveGrants'])->name('access.roles.grants.save');

    // Lookup Table Master and Category Access Permission (AnnotechERP 02/01, step C).
    Route::get('/access/lookups', [LookupApiController::class, 'index'])->name('access.lookups.index');
    Route::patch('/access/lookups/{table}', [LookupApiController::class, 'update'])->name('access.lookups.update');
    Route::delete('/access/lookups/{table}', [LookupApiController::class, 'destroy'])->name('access.lookups.destroy');
    Route::get('/access/lookups/{table}/values', [LookupApiController::class, 'values'])->name('access.lookups.values.index');
    Route::patch('/access/lookups/{table}/values/{value}', [LookupApiController::class, 'updateValue'])->name('access.lookups.values.update');
    Route::delete('/access/lookups/{table}/values/{value}', [LookupApiController::class, 'destroyValue'])->name('access.lookups.values.destroy');
    // Mappings (AnnotechERP 02/02). The user-company kind lives on memberships.
    Route::get('/access/user-companies', [UserCompanyMappingApiController::class, 'index'])->name('access.user-companies.index');
    Route::get('/access/user-companies/options', [UserCompanyMappingApiController::class, 'options'])->name('access.user-companies.options');
    Route::patch('/access/user-companies/{membership}', [UserCompanyMappingApiController::class, 'update'])->name('access.user-companies.update');
    Route::delete('/access/user-companies/{membership}', [UserCompanyMappingApiController::class, 'destroy'])->name('access.user-companies.destroy');
    Route::get('/access/mappings/{kind}', [AccessMappingApiController::class, 'index'])->name('access.mappings.index');
    Route::get('/access/mappings/{kind}/options', [AccessMappingApiController::class, 'options'])->name('access.mappings.options');
    Route::get('/access/mappings/{kind}/subjects/{subject}', [AccessMappingApiController::class, 'matrix'])->name('access.mappings.matrix');
    Route::put('/access/mappings/{kind}/subjects/{subject}', [AccessMappingApiController::class, 'saveMatrix'])->name('access.mappings.matrix.save');
    Route::patch('/access/mappings/{kind}/{mapping}', [AccessMappingApiController::class, 'update'])->name('access.mappings.update');
    Route::delete('/access/mappings/{kind}/{mapping}', [AccessMappingApiController::class, 'destroy'])->name('access.mappings.destroy');
    // TNA Task Status Setup (AnnotechERP 02/04): the calendar events, their raised instances and who is notified.
    Route::get('/access/tna-events', [TnaEventApiController::class, 'index'])->name('access.tna-events.index');
    Route::get('/access/tna-events/options', [TnaEventApiController::class, 'options'])->name('access.tna-events.options');
    Route::patch('/access/tna-events/{task}', [TnaEventApiController::class, 'update'])->name('access.tna-events.update');
    Route::get('/access/tna-events/{task}/instances', [TnaEventApiController::class, 'instances'])->name('access.tna-events.instances');
    Route::put('/access/tna-events/{task}/instances', [TnaEventApiController::class, 'saveInstances'])->name('access.tna-events.instances.save');
    Route::get('/access/tna-events/{task}/users', [TnaEventApiController::class, 'users'])->name('access.tna-events.users');
    Route::put('/access/tna-events/{task}/users', [TnaEventApiController::class, 'saveUsers'])->name('access.tna-events.users.save');
    Route::get('/access/menu-grants', [MenuAccessApiController::class, 'grantRecords'])->name('access.menu-grants.index');
    Route::put('/access/roles/{role}/menu-grants/{menu}', [MenuAccessApiController::class, 'saveMenuGrant'])->name('access.roles.grants.menu');
    Route::get('/access/category-permissions', [CategoryPermissionApiController::class, 'index'])->name('access.categories.index');
    Route::put('/access/roles/{role}/category-permissions/{category}', [CategoryPermissionApiController::class, 'saveOne'])->name('access.roles.categories.one');
    Route::get('/access/roles/{role}/category-permissions', [CategoryPermissionApiController::class, 'show'])->name('access.roles.categories');
    Route::put('/access/roles/{role}/category-permissions', [CategoryPermissionApiController::class, 'save'])->name('access.roles.categories.save');

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
    Route::post('/access/lookups', [LookupApiController::class, 'store'])->name('access.lookups.store');
    Route::post('/access/mappings/{kind}', [AccessMappingApiController::class, 'store'])->name('access.mappings.store');
    Route::post('/access/user-companies', [UserCompanyMappingApiController::class, 'store'])->name('access.user-companies.store');
    Route::post('/access/tna-events', [TnaEventApiController::class, 'store'])->name('access.tna-events.store');
    Route::post('/access/lookups/{table}/values', [LookupApiController::class, 'storeValue'])->name('access.lookups.values.store');
    Route::post('/teams', [TeamApiController::class, 'store'])->name('teams.store');
});
