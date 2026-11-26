<?php

declare(strict_types=1);

use App\Modules\Planning\Http\Controllers\Api\PlanningBoardApiController;
use App\Modules\Planning\Http\Controllers\Api\PlanningDailyApiController;
use App\Modules\Planning\Http\Controllers\Api\PlanningLookupApiController;
use App\Modules\Planning\Http\Controllers\Api\PlanningPlanApiController;
use Illuminate\Support\Facades\Route;

/*
 * Capacity Planning (AnnotechERP 12). The master lists are screen-kit
 * resources under /erp/{resource}; the calendar and the form lookups live here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/planning/lookups', PlanningLookupApiController::class)->name('planning.lookups');

    // Factory Calendar: HR & Payroll's holidays and the working week (Settings) — no calendar of its own.

    // Production Plan (12/02): an order by its number, a plan by its number (and ?v= version).
    Route::get('/planning/projections', [PlanningPlanApiController::class, 'projections'])->name('planning.projections');
    Route::get('/planning/master-plans/orders', [PlanningPlanApiController::class, 'orders'])->name('planning.master-plans.orders');
    Route::get('/planning/master-plans/order/{order}', [PlanningPlanApiController::class, 'order'])->name('planning.master-plans.order');
    Route::post('/planning/master-plans/calculate', [PlanningPlanApiController::class, 'calculate'])->name('planning.master-plans.calculate');
    Route::get('/planning/master-plans', [PlanningPlanApiController::class, 'index'])->name('planning.master-plans.index');
    Route::get('/planning/master-plans/excel', [PlanningPlanApiController::class, 'excel'])->name('planning.master-plans.excel');
    Route::post('/planning/master-plans', [PlanningPlanApiController::class, 'save'])->name('planning.master-plans.save');
    Route::get('/planning/master-plans/{plan}', [PlanningPlanApiController::class, 'show'])->name('planning.master-plans.show');
    Route::delete('/planning/master-plans/{plan}/draft', [PlanningPlanApiController::class, 'deleteDraft'])->name('planning.master-plans.draft.delete');
    Route::post('/planning/master-plans/{plan}/confirm', [PlanningPlanApiController::class, 'confirm'])->name('planning.master-plans.confirm');
    Route::post('/planning/master-plans/{plan}/revisions', [PlanningPlanApiController::class, 'requestRevision'])->name('planning.master-plans.revisions');
    Route::get('/planning/revision-requests', [PlanningPlanApiController::class, 'revisions'])->name('planning.revision-requests.index');
    Route::post('/planning/revision-requests/{revision}/approve', [PlanningPlanApiController::class, 'approveRevision'])->name('planning.revision-requests.approve');
    Route::post('/planning/revision-requests/{revision}/reject', [PlanningPlanApiController::class, 'rejectRevision'])->name('planning.revision-requests.reject');

    // Daily Plan, Daily Plan List and Plan vs Actual & Forecast: a day by its date, an order by its number.
    Route::get('/planning/daily-plans/orders', [PlanningDailyApiController::class, 'orders'])->name('planning.daily-plans.orders');
    Route::get('/planning/daily-plans/day', [PlanningDailyApiController::class, 'day'])->name('planning.daily-plans.day');
    Route::post('/planning/daily-plans', [PlanningDailyApiController::class, 'save'])->name('planning.daily-plans.save');
    Route::post('/planning/daily-plans/confirm', [PlanningDailyApiController::class, 'confirm'])->name('planning.daily-plans.confirm');
    Route::get('/planning/daily-plans', [PlanningDailyApiController::class, 'index'])->name('planning.daily-plans.index');
    Route::get('/planning/plan-vs-actual', [PlanningDailyApiController::class, 'planVsActual'])->name('planning.plan-vs-actual');
    Route::get('/planning/plan-vs-actual/excel', [PlanningDailyApiController::class, 'planVsActualExcel'])->name('planning.plan-vs-actual.excel');

    // Line Capacity View and Gantt / Timeline: a month (?year=&month=).
    Route::get('/planning/line-capacity', [PlanningBoardApiController::class, 'lineCapacity'])->name('planning.line-capacity');
    Route::get('/planning/timeline', [PlanningBoardApiController::class, 'timeline'])->name('planning.timeline');
});
