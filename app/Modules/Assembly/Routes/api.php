<?php

declare(strict_types=1);

use App\Modules\Assembly\Http\Controllers\Api\AssemblyReportApiController;
use App\Modules\Assembly\Http\Controllers\Api\FinishingApiController;
use App\Modules\Assembly\Http\Controllers\Api\PackingApiController;
use App\Modules\Assembly\Http\Controllers\Api\PackingReportApiController;
use App\Modules\Assembly\Http\Controllers\Api\QualityApiController;
use App\Modules\Assembly\Http\Controllers\Api\ReadyApiController;
use App\Modules\Assembly\Http\Controllers\Api\SewingApiController;
use App\Modules\Assembly\Http\Controllers\Api\ShipmentApiController;
use App\Modules\Assembly\Http\Controllers\Api\ShipmentReportApiController;
use App\Modules\Assembly\Http\Controllers\Api\TrimsApiController;
use Illuminate\Support\Facades\Route;

/*
 * Assembly Floor (AnnotechERP 20). Sewing (20/01): what the desks pick from,
 * a scanned card resolved against its line's plan, the Bundle IN and Bundle
 * OUT desks with their withdrawals, and the Sewing Dashboard. Finishing
 * (20/02): its options, a line's progress, a scanned bundle resolved at a
 * desk, today's scans, Bundle IN, Bundle OUT (Good / Reject / Rework), the
 * rework a supervisor moves on, the Finishing Dashboard and a bundle trace.
 * Quality Management (20/03): the same for Quality IN, Quality Inspection
 * (Pass / Reject / Rework with the defect, round after round), Quality OUT,
 * the rework a supervisor moves on and the Quality Dashboard. Packing
 * (20/04) and its Packing Process (20/05): Packing In with a supervisor's
 * removal, the seven processes (start, complete, reopen; poly labels printed
 * and verified; cartons added and removed; marking saved and printed;
 * cartons sealed and unsealed), Packing Out, the dashboard, a code's whole
 * history and the eleven Packing Reports. Ready For Shipment (20/06): the
 * Ready for Shipment Dashboard, a PO / colour row's detail and Finished
 * Goods Stock with its ledger. Shipment (20/07): the dashboard, entry,
 * history, a shipment entered, confirmed, marked as shipped, cancelled or
 * corrected, and the seventeen Shipment Reports. Accessories Store (20/08):
 * trims receipts, issues and receipt returns — each listed, opened by its
 * number, saved while a draft and moved on — and a store's stock. Reports
 * (20/09): the Apparel Assembly Reports' options and a report run.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/assembly/sewing/options', [SewingApiController::class, 'options'])->name('assembly.sewing.options');
    Route::get('/assembly/sewing/resolve', [SewingApiController::class, 'resolve'])->name('assembly.sewing.resolve');
    Route::get('/assembly/sewing/dashboard', [SewingApiController::class, 'dashboard'])->name('assembly.sewing.dashboard');
    Route::get('/assembly/sewing/ins', [SewingApiController::class, 'ins'])->name('assembly.sewing.ins');
    Route::post('/assembly/sewing/ins', [SewingApiController::class, 'receive'])->name('assembly.sewing.receive');
    Route::post('/assembly/sewing/ins/withdraw', [SewingApiController::class, 'withdrawIns'])->name('assembly.sewing.ins.withdraw');
    Route::get('/assembly/sewing/outs', [SewingApiController::class, 'outs'])->name('assembly.sewing.outs');
    Route::post('/assembly/sewing/outs', [SewingApiController::class, 'sendOut'])->name('assembly.sewing.send-out');
    Route::post('/assembly/sewing/outs/withdraw', [SewingApiController::class, 'withdrawOuts'])->name('assembly.sewing.outs.withdraw');

    Route::get('/assembly/finishing/options', [FinishingApiController::class, 'options'])->name('assembly.finishing.options');
    Route::get('/assembly/finishing/line', [FinishingApiController::class, 'line'])->name('assembly.finishing.line');
    Route::get('/assembly/finishing/resolve', [FinishingApiController::class, 'resolve'])->name('assembly.finishing.resolve');
    Route::get('/assembly/finishing/recent', [FinishingApiController::class, 'recent'])->name('assembly.finishing.recent');
    Route::post('/assembly/finishing/ins', [FinishingApiController::class, 'receive'])->name('assembly.finishing.receive');
    Route::post('/assembly/finishing/outs', [FinishingApiController::class, 'sendOut'])->name('assembly.finishing.send-out');
    Route::post('/assembly/finishing/rework', [FinishingApiController::class, 'reworkStatus'])->name('assembly.finishing.rework');
    Route::get('/assembly/finishing/dashboard', [FinishingApiController::class, 'dashboard'])->name('assembly.finishing.dashboard');
    Route::get('/assembly/finishing/trace', [FinishingApiController::class, 'trace'])->name('assembly.finishing.trace');

    Route::get('/assembly/quality/options', [QualityApiController::class, 'options'])->name('assembly.quality.options');
    Route::get('/assembly/quality/line', [QualityApiController::class, 'line'])->name('assembly.quality.line');
    Route::get('/assembly/quality/resolve', [QualityApiController::class, 'resolve'])->name('assembly.quality.resolve');
    Route::get('/assembly/quality/recent', [QualityApiController::class, 'recent'])->name('assembly.quality.recent');
    Route::post('/assembly/quality/ins', [QualityApiController::class, 'receive'])->name('assembly.quality.receive');
    Route::post('/assembly/quality/inspections', [QualityApiController::class, 'inspect'])->name('assembly.quality.inspect');
    Route::post('/assembly/quality/outs', [QualityApiController::class, 'sendOut'])->name('assembly.quality.send-out');
    Route::post('/assembly/quality/rework', [QualityApiController::class, 'reworkStatus'])->name('assembly.quality.rework');
    Route::get('/assembly/quality/dashboard', [QualityApiController::class, 'dashboard'])->name('assembly.quality.dashboard');
    Route::get('/assembly/quality/trace', [QualityApiController::class, 'trace'])->name('assembly.quality.trace');

    Route::get('/assembly/packing/options', [PackingApiController::class, 'options'])->name('assembly.packing.options');
    Route::get('/assembly/packing/in', [PackingApiController::class, 'inDesk'])->name('assembly.packing.in');
    Route::get('/assembly/packing/in/preview', [PackingApiController::class, 'previewIn'])->name('assembly.packing.in.preview');
    Route::post('/assembly/packing/ins', [PackingApiController::class, 'confirmIn'])->name('assembly.packing.in.confirm');
    Route::delete('/assembly/packing/ins/{id}', [PackingApiController::class, 'removeIn'])->name('assembly.packing.in.remove');
    Route::get('/assembly/packing/process', [PackingApiController::class, 'processDesk'])->name('assembly.packing.process');
    Route::get('/assembly/packing/find', [PackingApiController::class, 'find'])->name('assembly.packing.find');
    Route::get('/assembly/packing/batches/{id}', [PackingApiController::class, 'show'])->name('assembly.packing.batch');
    Route::post('/assembly/packing/batches/{id}/steps/{step}/start', [PackingApiController::class, 'startStep'])->name('assembly.packing.step.start');
    Route::post('/assembly/packing/batches/{id}/steps/{step}/complete', [PackingApiController::class, 'completeStep'])->name('assembly.packing.step.complete');
    Route::post('/assembly/packing/batches/{id}/steps/{step}/reopen', [PackingApiController::class, 'reopenStep'])->name('assembly.packing.step.reopen');
    Route::post('/assembly/packing/batches/{id}/labels/print', [PackingApiController::class, 'printLabels'])->name('assembly.packing.labels.print');
    Route::post('/assembly/packing/batches/{id}/labels/verify', [PackingApiController::class, 'verifyLabel'])->name('assembly.packing.labels.verify');
    Route::post('/assembly/packing/batches/{id}/cartons', [PackingApiController::class, 'addCartons'])->name('assembly.packing.cartons.add');
    Route::delete('/assembly/packing/cartons/{id}', [PackingApiController::class, 'deleteCarton'])->name('assembly.packing.cartons.delete');
    Route::post('/assembly/packing/batches/{id}/marking', [PackingApiController::class, 'saveMarking'])->name('assembly.packing.marking.save');
    Route::post('/assembly/packing/batches/{id}/marking/print', [PackingApiController::class, 'printMarking'])->name('assembly.packing.marking.print');
    Route::post('/assembly/packing/batches/{id}/seal', [PackingApiController::class, 'sealCarton'])->name('assembly.packing.cartons.seal');
    Route::post('/assembly/packing/cartons/{id}/unseal', [PackingApiController::class, 'unsealCarton'])->name('assembly.packing.cartons.unseal');
    Route::get('/assembly/packing/out', [PackingApiController::class, 'outDesk'])->name('assembly.packing.out');
    Route::get('/assembly/packing/out/preview', [PackingApiController::class, 'previewOut'])->name('assembly.packing.out.preview');
    Route::post('/assembly/packing/outs', [PackingApiController::class, 'confirmOut'])->name('assembly.packing.out.confirm');
    Route::get('/assembly/packing/dashboard', [PackingApiController::class, 'dashboard'])->name('assembly.packing.dashboard');
    Route::get('/assembly/packing/trace', [PackingApiController::class, 'trace'])->name('assembly.packing.trace');
    Route::get('/assembly/packing/reports', [PackingReportApiController::class, 'run'])->name('assembly.packing.reports');

    Route::get('/assembly/ready/dashboard', [ReadyApiController::class, 'dashboard'])->name('assembly.ready.dashboard');
    Route::get('/assembly/ready/detail', [ReadyApiController::class, 'detail'])->name('assembly.ready.detail');
    Route::get('/assembly/ready/stock', [ReadyApiController::class, 'stock'])->name('assembly.ready.stock');

    Route::get('/assembly/shipments/dashboard', [ShipmentApiController::class, 'dashboard'])->name('assembly.shipment.dashboard');
    Route::get('/assembly/shipments/history', [ShipmentApiController::class, 'history'])->name('assembly.shipment.history');
    Route::get('/assembly/shipments/entry', [ShipmentApiController::class, 'entry'])->name('assembly.shipment.entry');
    Route::post('/assembly/shipments', [ShipmentApiController::class, 'create'])->name('assembly.shipment.create');
    Route::post('/assembly/shipments/{id}/confirm', [ShipmentApiController::class, 'confirm'])->name('assembly.shipment.confirm');
    Route::post('/assembly/shipments/{id}/ship', [ShipmentApiController::class, 'markShipped'])->name('assembly.shipment.ship');
    Route::post('/assembly/shipments/{id}/cancel', [ShipmentApiController::class, 'cancel'])->name('assembly.shipment.cancel');
    Route::post('/assembly/shipments/{id}/correct', [ShipmentApiController::class, 'correct'])->name('assembly.shipment.correct');
    Route::get('/assembly/shipments-reports', [ShipmentReportApiController::class, 'run'])->name('assembly.shipment.reports');

    Route::get('/assembly/reports/options', [AssemblyReportApiController::class, 'options'])->name('assembly.reports.options');
    Route::get('/assembly/reports/run', [AssemblyReportApiController::class, 'run'])->name('assembly.reports.run');

    Route::get('/assembly/trims/options', [TrimsApiController::class, 'options'])->name('assembly.trims.options');
    Route::get('/assembly/trims/stock', [TrimsApiController::class, 'stock'])->name('assembly.trims.stock');
    Route::get('/assembly/trims/receipts', [TrimsApiController::class, 'receipts'])->name('assembly.trims.receipts');
    Route::post('/assembly/trims/receipts', [TrimsApiController::class, 'saveReceipt'])->name('assembly.trims.receipts.create');
    Route::get('/assembly/trims/receipts/{no}', [TrimsApiController::class, 'receipt'])->name('assembly.trims.receipts.show');
    Route::post('/assembly/trims/receipts/{no}', [TrimsApiController::class, 'saveReceipt'])->name('assembly.trims.receipts.update');
    Route::post('/assembly/trims/receipts/{no}/{action}', [TrimsApiController::class, 'receiptAction'])->name('assembly.trims.receipts.action');
    Route::get('/assembly/trims/issues', [TrimsApiController::class, 'issues'])->name('assembly.trims.issues');
    Route::post('/assembly/trims/issues', [TrimsApiController::class, 'saveIssue'])->name('assembly.trims.issues.create');
    Route::get('/assembly/trims/issues/{no}', [TrimsApiController::class, 'issue'])->name('assembly.trims.issues.show');
    Route::post('/assembly/trims/issues/{no}', [TrimsApiController::class, 'saveIssue'])->name('assembly.trims.issues.update');
    Route::post('/assembly/trims/issues/{no}/{action}', [TrimsApiController::class, 'issueAction'])->name('assembly.trims.issues.action');
    Route::get('/assembly/trims/returns', [TrimsApiController::class, 'returns'])->name('assembly.trims.returns');
    Route::post('/assembly/trims/returns', [TrimsApiController::class, 'saveReturn'])->name('assembly.trims.returns.create');
    Route::get('/assembly/trims/returns/source/{mrr}', [TrimsApiController::class, 'returnSource'])->name('assembly.trims.returns.source');
    Route::get('/assembly/trims/returns/{no}', [TrimsApiController::class, 'return'])->name('assembly.trims.returns.show');
    Route::post('/assembly/trims/returns/{no}', [TrimsApiController::class, 'saveReturn'])->name('assembly.trims.returns.update');
    Route::post('/assembly/trims/returns/{no}/{action}', [TrimsApiController::class, 'returnAction'])->name('assembly.trims.returns.action');
});
