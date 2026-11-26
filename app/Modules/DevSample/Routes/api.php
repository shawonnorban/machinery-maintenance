<?php

declare(strict_types=1);

use App\Modules\DevSample\Http\Controllers\Api\ColumnApiController;
use App\Modules\DevSample\Http\Controllers\Api\DevSampleApiController;
use Illuminate\Support\Facades\Route;

/*
 * Dev Sample Status (AnnotechERP 31, from Norban; Column Setup 2026-11-21):
 * the sheet's make-up, the samples (list with summary cards, header-filter
 * values, new / changed rows, Copy, Delete), the sample image, the Pivot
 * Report, and Column Setup.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('dev-sample')->name('dev-sample.')->group(function (): void {
    Route::get('/lookups', [DevSampleApiController::class, 'lookups'])->name('lookups');
    Route::get('/meta', [DevSampleApiController::class, 'meta'])->name('meta');
    Route::get('/job-numbers', [DevSampleApiController::class, 'jobNumbers'])->name('job-numbers');
    Route::get('/report', [DevSampleApiController::class, 'report'])->name('report');
    Route::get('/dashboard', [DevSampleApiController::class, 'dashboard'])->name('dashboard');
    Route::post('/samples/image', [DevSampleApiController::class, 'image'])->name('samples.image');
    Route::get('/samples/distinct', [DevSampleApiController::class, 'distinct'])->name('samples.distinct');
    Route::post('/samples/copy', [DevSampleApiController::class, 'copy'])->name('samples.copy');
    Route::get('/samples', [DevSampleApiController::class, 'index'])->name('samples.index');
    Route::post('/samples', [DevSampleApiController::class, 'store'])->name('samples.store');
    Route::patch('/samples', [DevSampleApiController::class, 'update'])->name('samples.update');
    Route::delete('/samples', [DevSampleApiController::class, 'destroy'])->name('samples.destroy');

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
