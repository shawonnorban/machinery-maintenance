<?php

declare(strict_types=1);

use App\Modules\ProductionTracking\Http\Controllers\Api\DailyPlanApiController;
use App\Modules\ProductionTracking\Http\Controllers\Api\ProductionTrackingApiController;
use App\Modules\ProductionTracking\Http\Controllers\Api\TvScreenApiController;
use Illuminate\Support\Facades\Route;

/*
 * Production Tracking (AnnotechERP 30): lookups; the dashboards, order
 * progress and Daily Production Report (one board endpoint by view);
 * Settings; Line Capacity Master; Production Master Data; Daily Production
 * Plan with its actions and history; the TV screens and their display links.
 * A TV opens its display with the link alone — that one route takes no
 * sign-in and reads only.
 */

Route::middleware('throttle:api')->group(function (): void {
    Route::get('/public/production-tracking/tv/{token}', [TvScreenApiController::class, 'display'])
        ->where('token', '[A-Za-z0-9]{40}')->name('production-tracking.tv.display');
});

Route::middleware(['api.auth', 'throttle:api'])->prefix('production-tracking')->name('production-tracking.')->group(function (): void {
    Route::get('/lookups', [ProductionTrackingApiController::class, 'lookups'])->name('lookups');
    Route::get('/board/{view}', [ProductionTrackingApiController::class, 'board'])->whereIn('view', ['all', 'cutting', 'sewing', 'finishing', 'buyer', 'style', 'po', 'report'])->name('board');

    Route::get('/settings', [ProductionTrackingApiController::class, 'settings'])->name('settings');
    Route::put('/settings', [ProductionTrackingApiController::class, 'saveSettings'])->name('settings.save');

    Route::get('/capacities', [ProductionTrackingApiController::class, 'capacities'])->name('capacities.index');
    Route::post('/capacities', [ProductionTrackingApiController::class, 'saveCapacity'])->name('capacities.store');
    Route::patch('/capacities/{id}', [ProductionTrackingApiController::class, 'saveCapacity'])->name('capacities.update');

    Route::get('/entries', [ProductionTrackingApiController::class, 'entries'])->name('entries.index');
    Route::post('/entries', [ProductionTrackingApiController::class, 'saveEntry'])->name('entries.store');
    Route::patch('/entries/{id}', [ProductionTrackingApiController::class, 'saveEntry'])->name('entries.update');
    Route::delete('/entries/{id}', [ProductionTrackingApiController::class, 'deleteEntry'])->name('entries.destroy');

    Route::get('/plans', [DailyPlanApiController::class, 'index'])->name('plans.index');
    Route::post('/plans/preview', [DailyPlanApiController::class, 'preview'])->name('plans.preview');
    Route::post('/plans', [DailyPlanApiController::class, 'store'])->name('plans.store');
    Route::patch('/plans/{id}', [DailyPlanApiController::class, 'update'])->name('plans.update');
    Route::post('/plans/{id}/{action}', [DailyPlanApiController::class, 'act'])->whereIn('action', ['submit', 'approve', 'reject', 'cancel', 'revise'])->name('plans.act');
    Route::get('/plans/{id}/history', [DailyPlanApiController::class, 'history'])->name('plans.history');

    Route::get('/tv-screens', [TvScreenApiController::class, 'index'])->name('tv.index');
    Route::post('/tv-screens', [TvScreenApiController::class, 'save'])->name('tv.store');
    Route::patch('/tv-screens/{id}', [TvScreenApiController::class, 'save'])->name('tv.update');
    Route::delete('/tv-screens/{id}', [TvScreenApiController::class, 'destroy'])->name('tv.destroy');
    Route::get('/tv-screens/{id}/preview', [TvScreenApiController::class, 'preview'])->name('tv.preview');
    Route::post('/tv-screens/{id}/link', [TvScreenApiController::class, 'issue'])->name('tv.link');
    Route::delete('/tv-screens/{id}/link', [TvScreenApiController::class, 'revoke'])->name('tv.revoke');
});
