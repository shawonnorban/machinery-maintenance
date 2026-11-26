<?php

declare(strict_types=1);

use App\Modules\ColourLab\Http\Controllers\Api\ColourLabApiController as Lab;
use App\Modules\ColourLab\Http\Controllers\Api\ColourLabReportApiController as LabReports;
use Illuminate\Support\Facades\Route;

/*
 * Colour Lab (AnnotechERP 16). The certificates, their types and equipment,
 * and the issue register are screen-kit resources under /erp/{resource};
 * the trial workbench and fabric testing live here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/colour-lab/options', [Lab::class, 'options'])->name('colourlab.options');
    Route::get('/colour-lab/items', [Lab::class, 'items'])->name('colourlab.items');

    // Chemical Trial Workbench.
    Route::get('/colour-lab/trials', [Lab::class, 'trials'])->name('colourlab.trials.index');
    Route::post('/colour-lab/trials', [Lab::class, 'saveTrial'])->name('colourlab.trials.store');
    Route::get('/colour-lab/trials/{no}', [Lab::class, 'showTrial'])->name('colourlab.trials.show');
    Route::put('/colour-lab/trials/{no}', [Lab::class, 'saveTrial'])->name('colourlab.trials.update');
    Route::delete('/colour-lab/trials/{no}', [Lab::class, 'deleteTrial'])->name('colourlab.trials.destroy');
    Route::post('/colour-lab/trials/{no}/{action}', [Lab::class, 'trialTransition'])->whereIn('action', ['start', 'complete', 'cancel'])->name('colourlab.trials.transition');

    // In-House Fabric Testing.
    Route::get('/colour-lab/fabric-tests', [Lab::class, 'fabricTests'])->name('colourlab.tests.index');
    Route::post('/colour-lab/fabric-tests', [Lab::class, 'saveFabricTest'])->name('colourlab.tests.store');
    Route::get('/colour-lab/fabric-tests/{no}', [Lab::class, 'showFabricTest'])->name('colourlab.tests.show');
    Route::put('/colour-lab/fabric-tests/{no}', [Lab::class, 'saveFabricTest'])->name('colourlab.tests.update');
    Route::delete('/colour-lab/fabric-tests/{no}', [Lab::class, 'deleteFabricTest'])->name('colourlab.tests.destroy');
    Route::post('/colour-lab/fabric-tests/{no}/{action}', [Lab::class, 'fabricTestTransition'])->whereIn('action', ['submit', 'approve', 'reject'])->name('colourlab.tests.transition');

    // Lab Test Reports (AnnotechERP 16/02).
    Route::get('/colour-lab/reports/{report}', [LabReports::class, 'show'])->whereIn('report', array_keys(LabReports::REPORTS))->name('colourlab.reports.show');
});
