<?php

declare(strict_types=1);

use App\Modules\Maintenance\Http\Controllers\Web\PlanController;
use App\Modules\Maintenance\Http\Controllers\Web\ScheduleController;
use App\Modules\Maintenance\Http\Controllers\Web\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/maintenance/templates', [TemplateController::class, 'index'])
        ->name('maintenance.templates');

    // Before the wildcard, or "create" is read as a template id.
    Route::get('/maintenance/templates/create', [TemplateController::class, 'create'])
        ->name('maintenance.templates.create');
    Route::post('/maintenance/templates', [TemplateController::class, 'store'])
        ->name('maintenance.templates.store');

    Route::get('/maintenance/templates/{template}', [TemplateController::class, 'show'])
        ->name('maintenance.templates.show');
    Route::get('/maintenance/templates/{template}/edit', [TemplateController::class, 'edit'])
        ->name('maintenance.templates.edit');
    Route::patch('/maintenance/templates/{template}', [TemplateController::class, 'update'])
        ->name('maintenance.templates.update');

    /*
     * A published checklist is frozen: editing it would rewrite what a
     * technician signed to say they had checked. Changes go into a new draft
     * and take effect when that draft is published.
     */
    Route::post('/maintenance/templates/{template}/draft', [TemplateController::class, 'draft'])
        ->name('maintenance.templates.draft');
    Route::post('/maintenance/templates/{template}/versions/{version}/items', [TemplateController::class, 'storeItem'])
        ->name('maintenance.templates.items.store');
    Route::patch('/maintenance/templates/{template}/versions/{version}/items/{item}', [TemplateController::class, 'updateItem'])
        ->name('maintenance.templates.items.update');
    Route::delete('/maintenance/templates/{template}/versions/{version}/items/{item}', [TemplateController::class, 'destroyItem'])
        ->name('maintenance.templates.items.destroy');
    Route::post('/maintenance/templates/{template}/versions/{version}/publish', [TemplateController::class, 'publish'])
        ->name('maintenance.templates.publish');
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
    // Only a plan nothing has been generated from. One that has produced
    // occurrences is deactivated, which leaves that history explainable.
    Route::delete('/maintenance/plans/{plan}', [PlanController::class, 'destroy'])->name('maintenance.plans.destroy');
    Route::post('/maintenance/plans/{plan}/activate', [PlanController::class, 'activate'])->name('maintenance.plans.activate');
    Route::post('/maintenance/plans/{plan}/deactivate', [PlanController::class, 'deactivate'])->name('maintenance.plans.deactivate');

    Route::get('/maintenance/schedule', [ScheduleController::class, 'index'])->name('maintenance.schedule');
    Route::post('/maintenance/schedules/{schedule}/complete', [ScheduleController::class, 'complete'])->name('maintenance.schedules.complete');
    Route::post('/maintenance/schedules/{schedule}/skip', [ScheduleController::class, 'skip'])->name('maintenance.schedules.skip');
    Route::post('/maintenance/schedules/{schedule}/reschedule', [ScheduleController::class, 'reschedule'])->name('maintenance.schedules.reschedule');
});
