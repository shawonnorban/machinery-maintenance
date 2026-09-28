<?php

declare(strict_types=1);

use App\Modules\Compliance\Http\Controllers\Api\BondComplianceApiController;
use Illuminate\Support\Facades\Route;

/*
 * Bonded warehouse compliance (Shared Systems spec §3): the licence that
 * permits a duty-free import, the declaration that says what it is for,
 * and the assessment raised when the goods go somewhere else.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/bond-licences', [BondComplianceApiController::class, 'index'])->name('bond-licences.index');
    Route::post('/bond-licences', [BondComplianceApiController::class, 'store'])->name('bond-licences.store');
    // Static segment ahead of the {licence} wildcard.
    Route::get('/bond-licences/form-options', [BondComplianceApiController::class, 'formOptions'])->name('bond-licences.form-options');
    Route::get('/bond-licences/{licence}', [BondComplianceApiController::class, 'show'])->name('bond-licences.show');
    Route::patch('/bond-licences/{licence}', [BondComplianceApiController::class, 'update'])->name('bond-licences.update');
    Route::post('/bond-licences/{licence}/status', [BondComplianceApiController::class, 'setStatus'])->name('bond-licences.status');
    Route::get('/bond-licences/{licence}/utilisation', [BondComplianceApiController::class, 'utilisation'])->name('bond-licences.utilisation');
    Route::post('/bond-licences/{licence}/declarations', [BondComplianceApiController::class, 'storeDeclaration'])->name('bond-licences.declarations.store');

    Route::get('/utilisation-declarations/{declaration}', [BondComplianceApiController::class, 'showDeclaration'])->name('utilisation-declarations.show');
    Route::post('/utilisation-declarations/{declaration}/issue', [BondComplianceApiController::class, 'issueDeclaration'])->name('utilisation-declarations.issue');
    Route::post('/utilisation-declarations/{declaration}/close', [BondComplianceApiController::class, 'closeDeclaration'])->name('utilisation-declarations.close');
    Route::post('/utilisation-declarations/{declaration}/cancel', [BondComplianceApiController::class, 'cancelDeclaration'])->name('utilisation-declarations.cancel');

    // Duty is assessed deliberately, never as a side effect of a stock
    // movement: issuing bonded fabric to the floor is the bond working,
    // and only a person knows when goods left for something else.
    Route::get('/duty-assessments', [BondComplianceApiController::class, 'assessments'])->name('duty-assessments.index');
    Route::post('/duty-assessments', [BondComplianceApiController::class, 'assess'])->name('duty-assessments.store');
});
