<?php

declare(strict_types=1);

use App\Modules\OrderSummary\Http\Controllers\Api\ColumnApiController;
use App\Modules\OrderSummary\Http\Controllers\Api\MailSettingApiController;
use App\Modules\OrderSummary\Http\Controllers\Api\OrderApiController;
use App\Modules\OrderSummary\Http\Controllers\Api\ReportApiController;
use App\Modules\OrderSummary\Http\Controllers\Api\TeamApiController;
use Illuminate\Support\Facades\Route;

/*
 * Order Summary (AnnotechERP 32, from Norban): the sheet (meta, rows, header
 * filter values, writes), Column Setup, Merchandising Team, Mail Schedule,
 * the dashboard and reports.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('order-summary')->name('order-summary.')->group(function (): void {
    Route::get('/meta', [OrderApiController::class, 'meta'])->name('meta');
    Route::get('/orders', [OrderApiController::class, 'index'])->name('orders.index');
    Route::get('/orders/distinct', [OrderApiController::class, 'distinct'])->name('orders.distinct');
    Route::get('/orders/export', [OrderApiController::class, 'export'])->name('orders.export');
    Route::get('/job-numbers', [OrderApiController::class, 'jobNumbers'])->name('job-numbers');
    Route::post('/orders', [OrderApiController::class, 'store'])->name('orders.store');
    Route::patch('/orders', [OrderApiController::class, 'update'])->name('orders.update');
    Route::post('/orders/bulk-update', [OrderApiController::class, 'bulkUpdate'])->name('orders.bulk-update');
    Route::post('/orders/copy', [OrderApiController::class, 'copy'])->name('orders.copy');
    Route::delete('/orders', [OrderApiController::class, 'destroy'])->name('orders.destroy');
    Route::post('/orders/artwork', [OrderApiController::class, 'artwork'])->name('orders.artwork');

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

    Route::get('/teams', [TeamApiController::class, 'index'])->name('teams.index');
    Route::put('/department-heads', [TeamApiController::class, 'heads'])->name('teams.heads');
    Route::post('/teams', [TeamApiController::class, 'store'])->name('teams.store');
    Route::patch('/teams/{team}', [TeamApiController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamApiController::class, 'destroy'])->name('teams.destroy');

    Route::get('/mail-settings', [MailSettingApiController::class, 'show'])->name('mail.show');
    Route::put('/mail-settings', [MailSettingApiController::class, 'save'])->name('mail.save');
    Route::post('/mail-settings/send-now', [MailSettingApiController::class, 'sendNow'])->name('mail.send-now');

    Route::get('/dashboard', [ReportApiController::class, 'dashboard'])->name('dashboard');
    Route::get('/reports/pending/{basis}', [ReportApiController::class, 'pending'])->whereIn('basis', ['ldd', 'rfi'])->name('reports.pending');
    Route::get('/reports/buyer-month/{basis}', [ReportApiController::class, 'buyerMonth'])->whereIn('basis', ['ldd', 'rfi'])->name('reports.buyer-month');
    Route::get('/audit-log', [ReportApiController::class, 'audit'])->name('audit');
});
