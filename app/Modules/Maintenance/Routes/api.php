<?php

declare(strict_types=1);

use App\Modules\Maintenance\Http\Controllers\Api\MaintenancePlanApiController;
use App\Modules\Maintenance\Http\Controllers\Api\MaintenanceScheduleApiController;
use App\Modules\Maintenance\Http\Controllers\Api\MaintenanceTemplateApiController;
use Illuminate\Support\Facades\Route;

/*
 * Maintenance plans (API 8), schedules (API 9) and checklist templates
 * (API 9, SRS 12).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/maintenance-plans', [MaintenancePlanApiController::class, 'index'])->name('maintenance-plans.index');
    Route::post('/maintenance-plans', [MaintenancePlanApiController::class, 'store'])->name('maintenance-plans.store');
    Route::get('/maintenance-plans/preview', [MaintenancePlanApiController::class, 'preview'])
        ->name('maintenance-plans.preview');
    // Ahead of the {plan} show route below, which would otherwise swallow
    // "form-options" as an attempted plan id.
    Route::get('/maintenance-plans/form-options', [MaintenancePlanApiController::class, 'formOptions'])
        ->name('maintenance-plans.form-options');
    Route::get('/maintenance-plans/{plan}', [MaintenancePlanApiController::class, 'show'])
        ->name('maintenance-plans.show');
    Route::patch('/maintenance-plans/{plan}', [MaintenancePlanApiController::class, 'update'])
        ->name('maintenance-plans.update');
    Route::post('/maintenance-plans/{plan}/activate', [MaintenancePlanApiController::class, 'activate'])
        ->name('maintenance-plans.activate');
    Route::post('/maintenance-plans/{plan}/deactivate', [MaintenancePlanApiController::class, 'deactivate'])
        ->name('maintenance-plans.deactivate');
    Route::delete('/maintenance-plans/{plan}', [MaintenancePlanApiController::class, 'destroy'])
        ->name('maintenance-plans.destroy');

    Route::get('/maintenance-schedules', [MaintenanceScheduleApiController::class, 'index'])
        ->name('maintenance-schedules.index');
    // Ahead of GET /maintenance-schedules/{schedule}: a wildcard segment
    // would otherwise swallow "counts" as an attempted id.
    Route::get('/maintenance-schedules/counts', [MaintenanceScheduleApiController::class, 'counts'])
        ->name('maintenance-schedules.counts');
    Route::get('/maintenance-schedules/{schedule}', [MaintenanceScheduleApiController::class, 'show'])
        ->name('maintenance-schedules.show');
    Route::post('/maintenance-schedules/{schedule}/complete', [MaintenanceScheduleApiController::class, 'complete'])
        ->name('maintenance-schedules.complete');
    Route::post('/maintenance-schedules/{schedule}/skip', [MaintenanceScheduleApiController::class, 'skip'])
        ->name('maintenance-schedules.skip');
    Route::post(
        '/maintenance-schedules/{schedule}/reschedule',
        [MaintenanceScheduleApiController::class, 'reschedule'],
    )->name('maintenance-schedules.reschedule');

    // Checklist templates (SRS 12). form-options ahead of {template}: a
    // wildcard segment would otherwise swallow it as an attempted id.
    Route::get('/maintenance-templates/form-options', [MaintenanceTemplateApiController::class, 'formOptions'])
        ->name('maintenance-templates.form-options');
    Route::get('/maintenance-templates', [MaintenanceTemplateApiController::class, 'index'])
        ->name('maintenance-templates.index');
    Route::post('/maintenance-templates', [MaintenanceTemplateApiController::class, 'store'])
        ->name('maintenance-templates.store');
    Route::get('/maintenance-templates/{template}', [MaintenanceTemplateApiController::class, 'show'])
        ->name('maintenance-templates.show');
    Route::patch('/maintenance-templates/{template}', [MaintenanceTemplateApiController::class, 'update'])
        ->name('maintenance-templates.update');
    Route::post('/maintenance-templates/{template}/draft', [MaintenanceTemplateApiController::class, 'draft'])
        ->name('maintenance-templates.draft');
    Route::post(
        '/maintenance-templates/{template}/versions/{version}/items',
        [MaintenanceTemplateApiController::class, 'storeItem'],
    )->name('maintenance-templates.items.store');
    Route::patch(
        '/maintenance-templates/{template}/versions/{version}/items/{item}',
        [MaintenanceTemplateApiController::class, 'updateItem'],
    )->name('maintenance-templates.items.update');
    Route::delete(
        '/maintenance-templates/{template}/versions/{version}/items/{item}',
        [MaintenanceTemplateApiController::class, 'destroyItem'],
    )->name('maintenance-templates.items.destroy');
    Route::post(
        '/maintenance-templates/{template}/versions/{version}/publish',
        [MaintenanceTemplateApiController::class, 'publish'],
    )->name('maintenance-templates.publish');
});
