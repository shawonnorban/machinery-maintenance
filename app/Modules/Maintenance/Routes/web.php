<?php

declare(strict_types=1);

use App\Modules\Maintenance\Http\Controllers\Web\PlanController;
use App\Modules\Maintenance\Http\Controllers\Web\ScheduleController;
use App\Modules\Maintenance\Http\Controllers\Web\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/maintenance/templates', [TemplateController::class, 'index'])
        ->name('maintenance.templates');
    Route::get('/maintenance/templates/{template}', [TemplateController::class, 'show'])
        ->name('maintenance.templates.show');
    Route::get('/maintenance/templates/{template}/versions/{version}', [TemplateController::class, 'version'])
        ->name('maintenance.templates.version');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/maintenance/plans', [PlanController::class, 'index'])->name('maintenance.plans');
    Route::get('/maintenance/plans/create', [PlanController::class, 'create'])->name('maintenance.plans.create');
    Route::post('/maintenance/plans', [PlanController::class, 'store'])->name('maintenance.plans.store');
    Route::post('/maintenance/plans/preview', [PlanController::class, 'preview'])->name('maintenance.plans.preview');
    Route::get('/maintenance/plans/{plan}', [PlanController::class, 'show'])->name('maintenance.plans.show');
    Route::get('/maintenance/plans/{plan}/edit', [PlanController::class, 'edit'])->name('maintenance.plans.edit');
    Route::patch('/maintenance/plans/{plan}', [PlanController::class, 'update'])->name('maintenance.plans.update');
    Route::post('/maintenance/plans/{plan}/activate', [PlanController::class, 'activate'])->name('maintenance.plans.activate');
    Route::post('/maintenance/plans/{plan}/deactivate', [PlanController::class, 'deactivate'])->name('maintenance.plans.deactivate');

    Route::get('/maintenance/schedule', [ScheduleController::class, 'index'])->name('maintenance.schedule');
    Route::post('/maintenance/schedules/{schedule}/complete', [ScheduleController::class, 'complete'])->name('maintenance.schedules.complete');
    Route::post('/maintenance/schedules/{schedule}/skip', [ScheduleController::class, 'skip'])->name('maintenance.schedules.skip');
    Route::post('/maintenance/schedules/{schedule}/reschedule', [ScheduleController::class, 'reschedule'])->name('maintenance.schedules.reschedule');
});
