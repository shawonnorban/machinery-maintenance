<?php

declare(strict_types=1);

use App\Modules\FabricPlanning\Http\Controllers\Api\FpOperationsApiController as Ops;
use App\Modules\FabricPlanning\Http\Controllers\Api\FpReportsApiController as Reports;
use Illuminate\Support\Facades\Route;

/*
 * Fabric Requirement (AnnotechERP 13). The master and operation lists are
 * screen-kit resources under /erp/{resource}; the operations' in-place editors
 * live here — a family by its number, a section by its code, a week by its
 * Saturday, a batch plan or a board by its number.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/fabric-planning/options', [Ops::class, 'options'])->name('fabric-planning.options');

    // Planning Constraint Ruleset.
    Route::get('/fabric-planning/status-groups', [Ops::class, 'groups'])->name('fabric-planning.groups');
    Route::get('/fabric-planning/status-groups/{sl}/areas', [Ops::class, 'areas'])->whereNumber('sl')->name('fabric-planning.areas');
    Route::put('/fabric-planning/status-groups/{sl}/areas', [Ops::class, 'saveAreas'])->whereNumber('sl')->name('fabric-planning.areas.save');
    Route::get('/fabric-planning/status-groups/{sl}/rules', [Ops::class, 'rules'])->whereNumber('sl')->name('fabric-planning.rules');
    Route::put('/fabric-planning/status-groups/{sl}/rules', [Ops::class, 'saveRules'])->whereNumber('sl')->name('fabric-planning.rules.save');

    // Factory Calendar & Holidays.
    Route::get('/fabric-planning/sections', [Ops::class, 'sections'])->name('fabric-planning.sections');
    Route::get('/fabric-planning/sections/{code}/days', [Ops::class, 'sectionDays'])->name('fabric-planning.sections.days');
    Route::put('/fabric-planning/sections/{code}/days', [Ops::class, 'saveSectionDays'])->name('fabric-planning.sections.days.save');

    // Dye Machine Capacity Board.
    Route::get('/fabric-planning/capacity-weeks', [Ops::class, 'capacityWeeks'])->name('fabric-planning.capacity-weeks');
    Route::get('/fabric-planning/capacity-weeks/{week}', [Ops::class, 'capacityMachines'])->name('fabric-planning.capacity-weeks.machines');

    // Fabric Batch Plan Entry.
    Route::get('/fabric-planning/batch-plans', [Ops::class, 'batchPlans'])->name('fabric-planning.batch-plans');
    Route::post('/fabric-planning/batch-plans', [Ops::class, 'storeBatchPlan'])->name('fabric-planning.batch-plans.store');
    Route::get('/fabric-planning/batch-plans/{bp}', [Ops::class, 'batchPlan'])->name('fabric-planning.batch-plans.show');
    Route::put('/fabric-planning/batch-plans/{bp}', [Ops::class, 'updateBatchPlan'])->name('fabric-planning.batch-plans.update');

    // Batch Schedule Board.
    Route::get('/fabric-planning/schedule-plans/{plan}/batches', [Ops::class, 'scheduleBatches'])->name('fabric-planning.schedule.batches');
    Route::put('/fabric-planning/schedule-plans/{plan}/batches', [Ops::class, 'saveScheduleBatches'])->name('fabric-planning.schedule.batches.save');

    // Fabric Requirement Reports (13/03): a report by its code.
    Route::get('/fabric-planning/reports', [Reports::class, 'catalogue'])->name('fabric-planning.reports');
    Route::get('/fabric-planning/reports/{code}', [Reports::class, 'preview'])->name('fabric-planning.reports.preview');
    Route::get('/fabric-planning/reports/{code}/excel', [Reports::class, 'excel'])->name('fabric-planning.reports.excel');
});
