<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Middleware\EnforceSubscriptionState;
use App\Modules\QualityTally\Http\Controllers\Api\QtInputApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtMobileApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtPanelApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtPhoneApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtPhoneTallyApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtReportApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtSetupApiController;
use App\Modules\QualityTally\Http\Controllers\Api\QtTallyApiController;
use App\Modules\QualityTally\Http\Middleware\AuthenticatePhone;
use Illuminate\Support\Facades\Route;

/*
 * Quality Tally (AnnotechERP 36): lookups; Defect Types, Jobs, Line Target and
 * QC Assignment; the QC's tally (context, one tap, an offline batch, undo,
 * summary); Dashboard, Tally Sheet and History. Every permission is qtally.*,
 * which LicensedModules refuses while the platform has not switched the
 * module on for the company.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('quality-tally')->name('quality-tally.')->group(function (): void {
    Route::get('/lookups', [QtSetupApiController::class, 'lookups'])->name('lookups');

    Route::get('/defect-types', [QtSetupApiController::class, 'defectTypes'])->name('defects.index');
    Route::post('/defect-types', [QtSetupApiController::class, 'saveDefectType'])->name('defects.store');
    Route::post('/defect-types/standard', [QtSetupApiController::class, 'standardDefects'])->name('defects.standard');
    Route::patch('/defect-types/{id}', [QtSetupApiController::class, 'saveDefectType'])->name('defects.update');
    Route::delete('/defect-types/{id}', [QtSetupApiController::class, 'deleteDefectType'])->name('defects.destroy');

    Route::get('/jobs', [QtSetupApiController::class, 'jobs'])->name('jobs.index');
    Route::post('/jobs', [QtSetupApiController::class, 'saveJob'])->name('jobs.store');
    Route::patch('/jobs/{id}', [QtSetupApiController::class, 'saveJob'])->name('jobs.update');
    Route::delete('/jobs/{id}', [QtSetupApiController::class, 'deleteJob'])->name('jobs.destroy');

    Route::get('/targets', [QtSetupApiController::class, 'targets'])->name('targets.index');
    Route::post('/targets', [QtSetupApiController::class, 'saveTarget'])->name('targets.store');
    Route::post('/targets/copy', [QtSetupApiController::class, 'copyTargets'])->name('targets.copy');
    Route::post('/targets/bulk', [QtSetupApiController::class, 'bulkTargets'])->name('targets.bulk');
    Route::get('/targets/pending', [QtSetupApiController::class, 'pending'])->name('targets.pending');
    Route::get('/targets/shortfall', [QtSetupApiController::class, 'shortfall'])->name('targets.shortfall');

    Route::get('/attendance', [QtSetupApiController::class, 'attendance'])->name('attendance.index');
    Route::post('/attendance', [QtSetupApiController::class, 'saveAttendance'])->name('attendance.save');
    Route::patch('/targets/{id}', [QtSetupApiController::class, 'saveTarget'])->name('targets.update');
    Route::delete('/targets/{id}', [QtSetupApiController::class, 'deleteTarget'])->name('targets.destroy');

    Route::get('/inspectors', [QtSetupApiController::class, 'inspectors'])->name('inspectors.index');
    Route::get('/inspectors/options', [QtSetupApiController::class, 'inspectorOptions'])->name('inspectors.options');
    Route::post('/inspectors', [QtSetupApiController::class, 'saveInspector'])->name('inspectors.store');
    Route::patch('/inspectors/{id}', [QtSetupApiController::class, 'saveInspector'])->name('inspectors.update');
    Route::delete('/inspectors/{id}', [QtSetupApiController::class, 'deleteInspector'])->name('inspectors.destroy');

    Route::post('/inspectors/{id}/reset-pin', [QtSetupApiController::class, 'resetPin'])->name('inspectors.reset-pin');

    // The phones the app runs on (AnnotechERP 37).
    Route::get('/phones', [QtPhoneApiController::class, 'index'])->name('phones.index');
    Route::patch('/phones/{id}', [QtPhoneApiController::class, 'update'])->name('phones.update');
    Route::post('/phones/{id}/block', [QtPhoneApiController::class, 'block'])->name('phones.block');
    Route::post('/phones/{id}/unblock', [QtPhoneApiController::class, 'unblock'])->name('phones.unblock');
    Route::delete('/phones/{id}', [QtPhoneApiController::class, 'destroy'])->name('phones.destroy');

    // Each garment type's panels (a cutting tap is one panel).
    Route::get('/panels', [QtPanelApiController::class, 'panels'])->name('panels.index');
    Route::post('/panels', [QtPanelApiController::class, 'savePanel'])->name('panels.store');
    Route::post('/panels/standard', [QtPanelApiController::class, 'standardPanels'])->name('panels.standard');
    Route::patch('/panels/{id}', [QtPanelApiController::class, 'savePanel'])->name('panels.update');
    Route::delete('/panels/{id}', [QtPanelApiController::class, 'deletePanel'])->name('panels.destroy');
    // The cut panel inspection is read from the cutting taps (a lot = day, table, job, cutting no, batch).
    Route::get('/panel-inspections/form', [QtPanelApiController::class, 'form'])->name('panel-inspections.form');
    Route::get('/panel-inspections', [QtPanelApiController::class, 'index'])->name('panel-inspections.index');
    Route::get('/panel-inspections/lot', [QtPanelApiController::class, 'show'])->name('panel-inspections.show');

    // The cutting manager's input (bundle cards), and a job's flow. Sewing's and quality's input is their line target.
    Route::get('/cut-bundles', [QtInputApiController::class, 'bundles'])->name('cut-bundles.index');
    Route::get('/cut-bundles/scan', [QtInputApiController::class, 'scan'])->name('cut-bundles.scan');
    Route::post('/cut-bundles', [QtInputApiController::class, 'saveBundle'])->name('cut-bundles.store');
    Route::delete('/cut-bundles/{id}', [QtInputApiController::class, 'deleteBundle'])->name('cut-bundles.destroy');
    Route::get('/flow', [QtInputApiController::class, 'flow'])->name('flow');

    Route::get('/dashboard', [QtReportApiController::class, 'dashboard'])->name('dashboard');
    Route::get('/sheet', [QtReportApiController::class, 'sheet'])->name('sheet');
    Route::get('/history', [QtReportApiController::class, 'history'])->name('history');
    Route::get('/analytics', [QtReportApiController::class, 'analytics'])->name('analytics');

    Route::get('/tally/context', [QtTallyApiController::class, 'context'])->name('tally.context');
    Route::get('/tally/summary', [QtTallyApiController::class, 'summary'])->name('tally.summary');
});

// The QC's taps: a floor tablet taps hundreds of times an hour, so its own, wider limit.
Route::middleware(['api.auth', 'throttle:600,1'])->prefix('quality-tally')->name('quality-tally.')->group(function (): void {
    Route::post('/tally', [QtTallyApiController::class, 'store'])->name('tally.store');
    Route::post('/tally/bulk', [QtTallyApiController::class, 'bulk'])->name('tally.bulk');
    Route::post('/tally/undo', [QtTallyApiController::class, 'undo'])->name('tally.undo');
});

/*
 * The QC's phone (AnnotechERP 37). Sign-in is limited by phone and ID, not by
 * address: a floor's phones share one address and all sign in at shift start.
 */
Route::middleware('throttle:qt-sign-in')->post('/quality-tally/mobile/sign-in', [QtMobileApiController::class, 'signIn'])->name('quality-tally.mobile.sign-in');

// Inside a PIN session.
Route::middleware(['api.auth', 'throttle:api'])->prefix('quality-tally/mobile')->name('quality-tally.mobile.')->group(function (): void {
    Route::get('/session', [QtMobileApiController::class, 'session'])->name('session');
    Route::post('/sign-out', [QtMobileApiController::class, 'signOut'])->name('sign-out');
    Route::post('/pin', [QtMobileApiController::class, 'changePin'])->name('pin');
    // A QC who signed in on the app by email: their QC session on this phone.
    Route::post('/attach', [QtMobileApiController::class, 'attach'])->name('attach');
});

// As the phone itself: its QCs' sessions and taps, whoever is signed in now.
Route::middleware([AuthenticatePhone::class, EnforceSubscriptionState::class, 'throttle:qt-phone'])->prefix('quality-tally/phone')->name('quality-tally.phone.')->group(function (): void {
    Route::get('/hello', [QtMobileApiController::class, 'hello'])->name('hello');
    Route::post('/sessions', [QtMobileApiController::class, 'syncSessions'])->name('sessions');
    Route::post('/taps', [QtMobileApiController::class, 'taps'])->name('taps');
    // The tally, for the QC whose session is named (phase 2).
    Route::get('/tally', [QtPhoneTallyApiController::class, 'context'])->name('tally');
    Route::get('/summary', [QtPhoneTallyApiController::class, 'summary'])->name('summary');
    Route::get('/scan', [QtPhoneTallyApiController::class, 'scan'])->name('scan');
    Route::post('/undo', [QtPhoneTallyApiController::class, 'undo'])->name('undo');
});
