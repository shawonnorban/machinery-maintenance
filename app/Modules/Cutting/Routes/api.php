<?php

declare(strict_types=1);

use App\Modules\Cutting\Http\Controllers\Api\CutBundleApiController;
use App\Modules\Cutting\Http\Controllers\Api\CutInputApiController;
use App\Modules\Cutting\Http\Controllers\Api\CutReportApiController;
use Illuminate\Support\Facades\Route;

/*
 * Cutting Floor (AnnotechERP 19): the bundle cards — what the entry picks
 * from, the dashboard, the list, adding and correcting a card, printing and
 * reprinting, cancelling, the cutting list and the serial search.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/cutting/options', [CutBundleApiController::class, 'options'])->name('cutting.options');
    Route::get('/cutting/dashboard', [CutBundleApiController::class, 'dashboard'])->name('cutting.dashboard');
    Route::get('/cutting/cutting-list', [CutBundleApiController::class, 'cuttingList'])->name('cutting.cutting-list');
    Route::get('/cutting/serial', [CutBundleApiController::class, 'serial'])->name('cutting.serial');
    Route::get('/cutting/context', [CutBundleApiController::class, 'context'])->name('cutting.context');
    Route::get('/cutting/inputs', [CutInputApiController::class, 'index'])->name('cutting.inputs.index');
    Route::post('/cutting/inputs', [CutInputApiController::class, 'save'])->name('cutting.inputs.store');
    Route::get('/cutting/inputs/{no}', [CutInputApiController::class, 'show'])->name('cutting.inputs.show');
    Route::put('/cutting/inputs/{no}', [CutInputApiController::class, 'save'])->name('cutting.inputs.update');
    Route::post('/cutting/inputs/{no}/cancel', [CutInputApiController::class, 'cancel'])->name('cutting.inputs.cancel');
    Route::put('/cutting/inputs/{no}/output', [CutInputApiController::class, 'output'])->name('cutting.inputs.output');
    Route::post('/cutting/inputs/{no}/bundles', [CutInputApiController::class, 'bundles'])->name('cutting.inputs.bundles');
    Route::get('/cutting/bundles', [CutBundleApiController::class, 'index'])->name('cutting.bundles.index');
    Route::post('/cutting/bundles', [CutBundleApiController::class, 'store'])->name('cutting.bundles.store');
    Route::post('/cutting/bundles/print', [CutBundleApiController::class, 'print'])->name('cutting.bundles.print');
    Route::get('/cutting/bundles/{no}', [CutBundleApiController::class, 'show'])->name('cutting.bundles.show');
    Route::put('/cutting/bundles/{no}', [CutBundleApiController::class, 'update'])->name('cutting.bundles.update');
    Route::post('/cutting/bundles/{no}/cancel', [CutBundleApiController::class, 'cancel'])->name('cutting.bundles.cancel');
    // Reports (19/04): the bundle report and the cards counted per PO, style or size.
    Route::get('/cutting/additional-cutting', [CutReportApiController::class, 'additional'])->name('cutting.additional');
    Route::get('/cutting/reports/{kind}', [CutReportApiController::class, 'show'])->whereIn('kind', CutReportApiController::KINDS)->name('cutting.reports.show');
});
