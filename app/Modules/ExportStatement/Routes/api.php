<?php

declare(strict_types=1);

use App\Modules\ExportStatement\Http\Controllers\Api\ColumnApiController;
use App\Modules\ExportStatement\Http\Controllers\Api\StatementApiController;
use Illuminate\Support\Facades\Route;

/*
 * Export Statement (AnnotechERP 35, from Norban's "MD Sir Export Status"):
 * the sheet's make-up, the statements (list, header-filter values, new and
 * changed rows, Delete), Excel both ways, the Dashboard's KPI cards, the
 * edits waiting for approval, and Column Setup.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('export-statement')->name('export-statement.')->group(function (): void {
    Route::get('/meta', [StatementApiController::class, 'meta'])->name('meta');
    Route::get('/job-numbers', [StatementApiController::class, 'jobNumbers'])->name('job-numbers');
    Route::get('/dashboard', [StatementApiController::class, 'dashboard'])->name('dashboard');
    Route::get('/statements/distinct', [StatementApiController::class, 'distinct'])->name('statements.distinct');
    Route::get('/statements/export', [StatementApiController::class, 'export'])->name('statements.export');
    Route::post('/statements/import', [StatementApiController::class, 'import'])->name('statements.import');
    Route::get('/statements', [StatementApiController::class, 'index'])->name('statements.index');
    Route::post('/statements', [StatementApiController::class, 'store'])->name('statements.store');
    Route::patch('/statements', [StatementApiController::class, 'update'])->name('statements.update');
    Route::delete('/statements', [StatementApiController::class, 'destroy'])->name('statements.destroy');

    Route::get('/approvals', [StatementApiController::class, 'approvals'])->name('approvals');
    Route::post('/edits/{edit}/decision', [StatementApiController::class, 'decide'])->name('edits.decision');
    Route::post('/edits/{edit}/withdraw', [StatementApiController::class, 'withdraw'])->name('edits.withdraw');

    Route::get('/columns', [ColumnApiController::class, 'index'])->name('columns.index');
    Route::get('/columns/history', [ColumnApiController::class, 'history'])->name('columns.history');
    Route::post('/columns/check', [ColumnApiController::class, 'check'])->name('columns.check');
    Route::post('/columns', [ColumnApiController::class, 'store'])->name('columns.store');
    Route::put('/columns/freeze', [ColumnApiController::class, 'freeze'])->name('columns.freeze');
    Route::patch('/columns/{key}', [ColumnApiController::class, 'update'])->name('columns.update');
    Route::patch('/columns/{key}/visibility', [ColumnApiController::class, 'visibility'])->name('columns.visibility');
    Route::delete('/columns/{key}', [ColumnApiController::class, 'destroy'])->name('columns.destroy');
    Route::post('/groups', [ColumnApiController::class, 'storeGroup'])->name('groups.store');
    Route::patch('/groups/{group}', [ColumnApiController::class, 'updateGroup'])->name('groups.update');
});
