<?php

declare(strict_types=1);

use App\Modules\WorkStudy\Http\Controllers\Api\WorkStudyApiController;
use App\Modules\WorkStudy\Http\Controllers\Api\WsCostingApiController as Costing;
use App\Modules\WorkStudy\Http\Controllers\Api\WsFloorOpsApiController as FloorOps;
use App\Modules\WorkStudy\Http\Controllers\Api\WsOvertimeApiController as Overtime;
use App\Modules\WorkStudy\Http\Controllers\Api\WsReportApiController as Reports;
use App\Modules\WorkStudy\Http\Controllers\Api\WsSectionProfitApiController as SectionProfit;
use App\Modules\WorkStudy\Http\Controllers\Api\WsSpecApiController as Specs;
use App\Modules\WorkStudy\Http\Controllers\Api\WsTargetApiController as Targets;
use Illuminate\Support\Facades\Route;

/*
 * Work Study (AnnotechERP 14). The plain lists are screen-kit resources under
 * /erp/{resource}; the access grids, responsible owners and criticality lines
 * live here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/workstudy/lookups', [WorkStudyApiController::class, 'lookups'])->name('workstudy.lookups');

    Route::get('/workstudy/target-access', [WorkStudyApiController::class, 'targetAccess'])->name('workstudy.target-access');
    Route::put('/workstudy/target-access', [WorkStudyApiController::class, 'saveTargetAccess'])->name('workstudy.target-access.save');
    Route::get('/workstudy/finishing-access', [WorkStudyApiController::class, 'finishingAccess'])->name('workstudy.finishing-access');
    Route::put('/workstudy/finishing-access', [WorkStudyApiController::class, 'saveFinishingAccess'])->name('workstudy.finishing-access.save');

    Route::get('/workstudy/departments', [WorkStudyApiController::class, 'departments'])->name('workstudy.departments');
    Route::post('/workstudy/departments', [WorkStudyApiController::class, 'saveDepartment'])->name('workstudy.departments.store');
    Route::get('/workstudy/persons', [WorkStudyApiController::class, 'allPersons'])->name('workstudy.persons.all');
    Route::get('/workstudy/departments/{dept}/persons', [WorkStudyApiController::class, 'persons'])->name('workstudy.persons');
    Route::put('/workstudy/departments/{dept}/persons', [WorkStudyApiController::class, 'savePersons'])->name('workstudy.persons.save');

    Route::get('/workstudy/criticality', [WorkStudyApiController::class, 'criticality'])->name('workstudy.criticality');
    Route::get('/workstudy/criticality/{band}', [WorkStudyApiController::class, 'criticalityLines'])->name('workstudy.criticality.lines');
    Route::put('/workstudy/criticality/{band}', [WorkStudyApiController::class, 'saveCriticality'])->name('workstudy.criticality.save');

    Route::put('/workstudy/learning-curve/{fabricClass}', [WorkStudyApiController::class, 'saveLearningCurve'])->name('workstudy.learning-curve.save');

    // Item Specification (14/04): a specification by ?style=&v=, a group or a category by its code.
    Route::get('/workstudy/spec-options', [Specs::class, 'options'])->name('workstudy.spec-options');
    Route::get('/workstudy/specs', [Specs::class, 'specs'])->name('workstudy.specs');
    Route::get('/workstudy/specs/one', [Specs::class, 'spec'])->name('workstudy.specs.show');
    Route::post('/workstudy/specs', [Specs::class, 'storeSpec'])->name('workstudy.specs.store');
    Route::put('/workstudy/specs/one', [Specs::class, 'updateSpec'])->name('workstudy.specs.update');
    Route::post('/workstudy/ob-files', [Specs::class, 'obFile'])->name('workstudy.ob-files');
    Route::get('/workstudy/item-list', [Specs::class, 'itemList'])->name('workstudy.item-list');
    Route::get('/workstudy/item-groups/{code}/categories', [Specs::class, 'categories'])->name('workstudy.categories');
    Route::put('/workstudy/item-groups/{code}/categories', [Specs::class, 'saveCategories'])->name('workstudy.categories.save');
    Route::get('/workstudy/categories/{code}/variations', [Specs::class, 'variations'])->name('workstudy.variations');
    Route::put('/workstudy/categories/{code}/variations', [Specs::class, 'saveVariations'])->name('workstudy.variations.save');

    // Costing (14/05): a month as ?period=2026-10, a floor's day as ?floor=<code>&date=2026-10-25.
    Route::get('/workstudy/costing/options', [Costing::class, 'options'])->name('workstudy.costing.options');
    Route::get('/workstudy/costing/sections/{section}/heads', [Costing::class, 'heads'])->name('workstudy.costing.heads');
    Route::put('/workstudy/costing/sections/{section}/heads', [Costing::class, 'saveHeads'])->name('workstudy.costing.heads.save');
    Route::get('/workstudy/costing/sheet', [Costing::class, 'sheet'])->name('workstudy.costing.sheet');
    Route::put('/workstudy/costing/sheet', [Costing::class, 'saveSheet'])->name('workstudy.costing.sheet.save');
    Route::get('/workstudy/costing/day/config', [Costing::class, 'config'])->name('workstudy.costing.config');
    Route::put('/workstudy/costing/day/config', [Costing::class, 'saveConfig'])->name('workstudy.costing.config.save');
    Route::get('/workstudy/costing/day/lines', [Costing::class, 'lines'])->name('workstudy.costing.lines');
    Route::put('/workstudy/costing/day/lines', [Costing::class, 'saveLines'])->name('workstudy.costing.lines.save');
    Route::get('/workstudy/costing/day/profit', [Costing::class, 'profit'])->name('workstudy.costing.profit');
    Route::get('/workstudy/costing/export/{sheet}/{format}', [Costing::class, 'export'])->name('workstudy.costing.export');

    // OT & Salary (14/06): a year as ?year=2026, a floor's day as ?floor=<code>&date=2026-10-25.
    Route::get('/workstudy/ot/options', [Overtime::class, 'options'])->name('workstudy.ot.options');
    Route::get('/workstudy/ot/rates', [Overtime::class, 'rates'])->name('workstudy.ot.rates');
    Route::put('/workstudy/ot/rates', [Overtime::class, 'saveRates'])->name('workstudy.ot.rates.save');
    Route::get('/workstudy/ot/plan', [Overtime::class, 'plan'])->name('workstudy.ot.plan');
    Route::put('/workstudy/ot/plan', [Overtime::class, 'savePlan'])->name('workstudy.ot.plan.save');
    Route::get('/workstudy/ot/export/{sheet}/{format}', [Overtime::class, 'export'])->name('workstudy.ot.export');

    // Target & Feeding (14/03): a month as ?period=2026-10, a floor's day as ?floor=<code>&date=, a line as ?floor=<code>&line=<code>.
    Route::get('/workstudy/targets/options', [Targets::class, 'options'])->name('workstudy.targets.options');
    Route::get('/workstudy/targets/yarn', [Targets::class, 'yarn'])->name('workstudy.targets.yarn');
    Route::put('/workstudy/targets/yarn', [Targets::class, 'saveYarn'])->name('workstudy.targets.yarn.save');
    Route::get('/workstudy/targets/changeovers', [Targets::class, 'changeovers'])->name('workstudy.targets.changeovers');
    Route::post('/workstudy/targets/changeovers/action', [Targets::class, 'changeoverAction'])->name('workstudy.targets.changeovers.action');
    Route::get('/workstudy/targets/sheet', [Targets::class, 'targets'])->name('workstudy.targets.sheet');
    Route::put('/workstudy/targets/sheet', [Targets::class, 'saveTargets'])->name('workstudy.targets.sheet.save');
    Route::post('/workstudy/targets/sheet/copy', [Targets::class, 'copyTargets'])->name('workstudy.targets.sheet.copy');
    Route::get('/workstudy/targets/allocations', [Targets::class, 'allocations'])->name('workstudy.targets.allocations');
    Route::put('/workstudy/targets/allocations', [Targets::class, 'saveAllocations'])->name('workstudy.targets.allocations.save');
    Route::put('/workstudy/targets/plans', [Targets::class, 'savePlans'])->name('workstudy.targets.plans.save');
    Route::get('/workstudy/targets/npt', [Targets::class, 'npt'])->name('workstudy.targets.npt');
    Route::put('/workstudy/targets/npt', [Targets::class, 'saveNpt'])->name('workstudy.targets.npt.save');
    Route::get('/workstudy/targets/export/{sheet}/{format}', [Targets::class, 'export'])->name('workstudy.targets.export');

    // Floor Operations (14/07): a floor's hour as ?floor=<code>&date=&hour=, a line's day as ?floor=<code>&line=<code>&date=.
    Route::get('/workstudy/floor-ops/options', [FloorOps::class, 'options'])->name('workstudy.floorops.options');
    Route::get('/workstudy/floor-ops/output', [FloorOps::class, 'output'])->name('workstudy.floorops.output');
    Route::put('/workstudy/floor-ops/output', [FloorOps::class, 'saveOutput'])->name('workstudy.floorops.output.save');
    Route::get('/workstudy/floor-ops/minutes', [FloorOps::class, 'minutes'])->name('workstudy.floorops.minutes');
    Route::put('/workstudy/floor-ops/minutes', [FloorOps::class, 'saveMinutes'])->name('workstudy.floorops.minutes.save');
    Route::get('/workstudy/floor-ops/wash', [FloorOps::class, 'wash'])->name('workstudy.floorops.wash');
    Route::post('/workstudy/floor-ops/wash/action', [FloorOps::class, 'washAction'])->name('workstudy.floorops.wash.action');
    Route::get('/workstudy/floor-ops/export/{sheet}/{format}', [FloorOps::class, 'export'])->name('workstudy.floorops.export');

    // Section Profitability (14/08): a month as ?period=2026-10, a day as ?date=2026-10-25.
    Route::get('/workstudy/section-pl/options', [SectionProfit::class, 'options'])->name('workstudy.sectionpl.options');
    Route::get('/workstudy/section-pl/targets', [SectionProfit::class, 'targets'])->name('workstudy.sectionpl.targets');
    Route::put('/workstudy/section-pl/targets', [SectionProfit::class, 'saveTargets'])->name('workstudy.sectionpl.targets.save');
    Route::post('/workstudy/section-pl/targets/copy', [SectionProfit::class, 'copyTargets'])->name('workstudy.sectionpl.targets.copy');
    Route::get('/workstudy/section-pl/results', [SectionProfit::class, 'results'])->name('workstudy.sectionpl.results');
    Route::put('/workstudy/section-pl/results', [SectionProfit::class, 'saveResults'])->name('workstudy.sectionpl.results.save');
    Route::get('/workstudy/section-pl/export/{sheet}/{format}', [SectionProfit::class, 'export'])->name('workstudy.sectionpl.export');

    // Reports (14/02): a report by its key (?date= or ?from=&to=, office/buyer/floors/lines by code).
    Route::get('/workstudy/reports/options', [Reports::class, 'options'])->name('workstudy.reports.options');
    Route::get('/workstudy/reports/{report}/preview', [Reports::class, 'preview'])->name('workstudy.reports.preview');
    Route::get('/workstudy/reports/{report}/export/{format}', [Reports::class, 'export'])->name('workstudy.reports.export');
});
