<?php

declare(strict_types=1);

use App\Modules\Prototype\Http\Controllers\Api\BundleCardApiController;
use App\Modules\Prototype\Http\Controllers\Api\BundleTrackingApiController;
use App\Modules\Prototype\Http\Controllers\Api\MaterialIssueApiController;
use App\Modules\Prototype\Http\Controllers\Api\MaterialRequisitionApiController;
use App\Modules\Prototype\Http\Controllers\Api\PrototypeDashboardApiController;
use App\Modules\Prototype\Http\Controllers\Api\Sample360ApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleAlertApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleApprovalApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleDaySheetApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleDevelopmentApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleDocumentApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleFeedbackApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleHandoverApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleQualityCheckApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleRequisitionApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleRevisionApiController;
use App\Modules\Prototype\Http\Controllers\Api\SampleTargetPlanApiController;
use Illuminate\Support\Facades\Route;

/*
 * Prototype Desk (AnnotechERP 07): the dashboard, the sample 360° view and
 * its documents, the desk's alerts; the sample requisition and its approval
 * levels, sample development and revision, the sample room's daily target
 * and plans, the material requisition and its issues, the bundle cards and their tracking, the quality check, approval, buyer feedback and handover. A day
 * sheet is addressed by its date. A document is addressed
 * by its number (SR-2026-00001), never its key.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/prototype/dashboard', [PrototypeDashboardApiController::class, 'index'])->name('prototype.dashboard');
    Route::get('/prototype/samples', [Sample360ApiController::class, 'index'])->name('prototype.samples.index');
    Route::get('/prototype/samples/{sample}', [Sample360ApiController::class, 'show'])->name('prototype.samples.show');
    Route::get('/prototype/documents/options', [SampleDocumentApiController::class, 'options'])->name('prototype.documents.options');
    Route::get('/prototype/documents', [SampleDocumentApiController::class, 'index'])->name('prototype.documents.index');
    Route::get('/prototype/documents/{document}', [SampleDocumentApiController::class, 'show'])->name('prototype.documents.show');
    Route::post('/prototype/documents', [SampleDocumentApiController::class, 'store'])->name('prototype.documents.store');
    Route::put('/prototype/documents/{document}', [SampleDocumentApiController::class, 'update'])->name('prototype.documents.update');
    Route::get('/prototype/alerts', [SampleAlertApiController::class, 'index'])->name('prototype.alerts.index');
    Route::post('/prototype/alerts/{alert}/read', [SampleAlertApiController::class, 'read'])->name('prototype.alerts.read');
    Route::post('/prototype/alerts/{alert}/resolve', [SampleAlertApiController::class, 'resolve'])->name('prototype.alerts.resolve');
    Route::post('/prototype/alerts/{alert}/reopen', [SampleAlertApiController::class, 'reopen'])->name('prototype.alerts.reopen');

    Route::get('/prototype/requisitions/options', [SampleRequisitionApiController::class, 'options'])->name('prototype.requisitions.options');
    Route::get('/prototype/requisitions', [SampleRequisitionApiController::class, 'index'])->name('prototype.requisitions.index');
    Route::get('/prototype/requisitions/{requisition}', [SampleRequisitionApiController::class, 'show'])->name('prototype.requisitions.show');
    Route::post('/prototype/requisitions', [SampleRequisitionApiController::class, 'store'])->name('prototype.requisitions.store');
    Route::put('/prototype/requisitions/{requisition}', [SampleRequisitionApiController::class, 'update'])->name('prototype.requisitions.update');
    Route::post('/prototype/requisitions/{requisition}/submit', [SampleRequisitionApiController::class, 'submit'])->name('prototype.requisitions.submit');
    Route::post('/prototype/requisitions/{requisition}/decide', [SampleRequisitionApiController::class, 'decide'])->name('prototype.requisitions.decide');
    Route::post('/prototype/requisitions/{requisition}/assign', [SampleRequisitionApiController::class, 'assign'])->name('prototype.requisitions.assign');
    Route::post('/prototype/requisitions/{requisition}/cancel', [SampleRequisitionApiController::class, 'cancel'])->name('prototype.requisitions.cancel');

    Route::get('/prototype/developments/options', [SampleDevelopmentApiController::class, 'options'])->name('prototype.developments.options');
    Route::get('/prototype/developments', [SampleDevelopmentApiController::class, 'index'])->name('prototype.developments.index');
    Route::get('/prototype/developments/{development}', [SampleDevelopmentApiController::class, 'show'])->name('prototype.developments.show');
    Route::post('/prototype/developments', [SampleDevelopmentApiController::class, 'store'])->name('prototype.developments.store');
    Route::put('/prototype/developments/{development}', [SampleDevelopmentApiController::class, 'update'])->name('prototype.developments.update');

    Route::get('/prototype/revisions/options', [SampleRevisionApiController::class, 'options'])->name('prototype.revisions.options');
    Route::get('/prototype/revisions', [SampleRevisionApiController::class, 'index'])->name('prototype.revisions.index');
    Route::get('/prototype/revisions/{revision}', [SampleRevisionApiController::class, 'show'])->name('prototype.revisions.show');
    Route::post('/prototype/revisions', [SampleRevisionApiController::class, 'store'])->name('prototype.revisions.store');
    Route::put('/prototype/revisions/{revision}', [SampleRevisionApiController::class, 'update'])->name('prototype.revisions.update');
    Route::post('/prototype/revisions/{revision}/approve', [SampleRevisionApiController::class, 'approve'])->name('prototype.revisions.approve');

    Route::get('/prototype/material-requisitions/options', [MaterialRequisitionApiController::class, 'options'])->name('prototype.material_requisitions.options');
    Route::get('/prototype/material-requisitions', [MaterialRequisitionApiController::class, 'index'])->name('prototype.material_requisitions.index');
    Route::get('/prototype/material-requisitions/{mr}', [MaterialRequisitionApiController::class, 'show'])->name('prototype.material_requisitions.show');
    Route::post('/prototype/material-requisitions', [MaterialRequisitionApiController::class, 'store'])->name('prototype.material_requisitions.store');
    Route::put('/prototype/material-requisitions/{mr}', [MaterialRequisitionApiController::class, 'update'])->name('prototype.material_requisitions.update');
    Route::delete('/prototype/material-requisitions/{mr}', [MaterialRequisitionApiController::class, 'destroy'])->name('prototype.material_requisitions.destroy');
    Route::post('/prototype/material-requisitions/{mr}/submit', [MaterialRequisitionApiController::class, 'submit'])->name('prototype.material_requisitions.submit');
    Route::post('/prototype/material-requisitions/{mr}/dept-approve', [MaterialRequisitionApiController::class, 'deptApprove'])->name('prototype.material_requisitions.dept_approve');
    Route::post('/prototype/material-requisitions/{mr}/verify', [MaterialRequisitionApiController::class, 'verify'])->name('prototype.material_requisitions.verify');
    Route::post('/prototype/material-requisitions/{mr}/approve', [MaterialRequisitionApiController::class, 'approve'])->name('prototype.material_requisitions.approve');
    Route::post('/prototype/material-requisitions/{mr}/reject', [MaterialRequisitionApiController::class, 'reject'])->name('prototype.material_requisitions.reject');
    Route::post('/prototype/material-requisitions/{mr}/cancel', [MaterialRequisitionApiController::class, 'cancel'])->name('prototype.material_requisitions.cancel');

    Route::get('/prototype/material-issues/options', [MaterialIssueApiController::class, 'options'])->name('prototype.material_issues.options');
    Route::get('/prototype/material-issues', [MaterialIssueApiController::class, 'index'])->name('prototype.material_issues.index');
    Route::get('/prototype/material-issues/{issue}', [MaterialIssueApiController::class, 'show'])->name('prototype.material_issues.show');
    Route::post('/prototype/material-issues', [MaterialIssueApiController::class, 'store'])->name('prototype.material_issues.store');

    Route::get('/prototype/bundle-cards/options', [BundleCardApiController::class, 'options'])->name('prototype.bundle_cards.options');
    Route::get('/prototype/bundle-cards', [BundleCardApiController::class, 'index'])->name('prototype.bundle_cards.index');
    Route::get('/prototype/bundle-cards/{card}', [BundleCardApiController::class, 'show'])->name('prototype.bundle_cards.show');
    Route::post('/prototype/bundle-cards', [BundleCardApiController::class, 'store'])->name('prototype.bundle_cards.store');
    Route::put('/prototype/bundle-cards/{card}', [BundleCardApiController::class, 'update'])->name('prototype.bundle_cards.update');
    Route::post('/prototype/bundle-cards/{card}/{action}', [BundleCardApiController::class, 'move'])->whereIn('action', ['issue', 'receive', 'start', 'hold', 'return', 'cancel'])->name('prototype.bundle_cards.move');

    Route::get('/prototype/tracking/options', [BundleTrackingApiController::class, 'options'])->name('prototype.tracking.options');
    Route::get('/prototype/tracking', [BundleTrackingApiController::class, 'index'])->name('prototype.tracking.index');
    Route::get('/prototype/tracking/desk/{process}', [BundleTrackingApiController::class, 'desk'])->name('prototype.tracking.desk');
    Route::post('/prototype/tracking/scan', [BundleTrackingApiController::class, 'scan'])->name('prototype.tracking.scan');
    Route::put('/prototype/tracking/movements/{movement}', [BundleTrackingApiController::class, 'update'])->name('prototype.tracking.update');
    Route::delete('/prototype/tracking/movements/{movement}', [BundleTrackingApiController::class, 'undo'])->name('prototype.tracking.undo');
    Route::get('/prototype/tracking/bundles', [BundleTrackingApiController::class, 'bundles'])->name('prototype.tracking.bundles');
    Route::get('/prototype/tracking/lookup', [BundleTrackingApiController::class, 'lookup'])->name('prototype.tracking.lookup');
    Route::get('/prototype/tracking/sample-cards', [BundleTrackingApiController::class, 'sampleCards'])->name('prototype.tracking.sample_cards');
    Route::put('/prototype/tracking/sample-cards/{card}', [BundleTrackingApiController::class, 'deliver'])->name('prototype.tracking.deliver');

    Route::get('/prototype/day-sheets/options', [SampleDaySheetApiController::class, 'options'])->name('prototype.day_sheets.options');
    Route::get('/prototype/day-sheets', [SampleDaySheetApiController::class, 'index'])->name('prototype.day_sheets.index');
    Route::get('/prototype/day-sheets/{date}', [SampleDaySheetApiController::class, 'show'])->name('prototype.day_sheets.show');
    Route::post('/prototype/day-sheets', [SampleDaySheetApiController::class, 'store'])->name('prototype.day_sheets.store');
    Route::put('/prototype/day-sheets/{date}', [SampleDaySheetApiController::class, 'update'])->name('prototype.day_sheets.update');
    Route::delete('/prototype/day-sheets/{date}', [SampleDaySheetApiController::class, 'destroy'])->name('prototype.day_sheets.destroy');

    Route::get('/prototype/target-plans/options', [SampleTargetPlanApiController::class, 'options'])->name('prototype.target_plans.options');
    Route::get('/prototype/target-plans', [SampleTargetPlanApiController::class, 'index'])->name('prototype.target_plans.index');
    Route::get('/prototype/target-plans/{plan}', [SampleTargetPlanApiController::class, 'show'])->name('prototype.target_plans.show');
    Route::post('/prototype/target-plans', [SampleTargetPlanApiController::class, 'store'])->name('prototype.target_plans.store');
    Route::put('/prototype/target-plans/{plan}', [SampleTargetPlanApiController::class, 'update'])->name('prototype.target_plans.update');

    Route::get('/prototype/quality-checks/options', [SampleQualityCheckApiController::class, 'options'])->name('prototype.quality_checks.options');
    Route::get('/prototype/quality-checks', [SampleQualityCheckApiController::class, 'index'])->name('prototype.quality_checks.index');
    Route::get('/prototype/quality-checks/{check}', [SampleQualityCheckApiController::class, 'show'])->name('prototype.quality_checks.show');
    Route::post('/prototype/quality-checks', [SampleQualityCheckApiController::class, 'store'])->name('prototype.quality_checks.store');
    Route::put('/prototype/quality-checks/{check}', [SampleQualityCheckApiController::class, 'update'])->name('prototype.quality_checks.update');

    Route::get('/prototype/approvals/options', [SampleApprovalApiController::class, 'options'])->name('prototype.approvals.options');
    Route::get('/prototype/approvals', [SampleApprovalApiController::class, 'index'])->name('prototype.approvals.index');
    Route::get('/prototype/approvals/{approval}', [SampleApprovalApiController::class, 'show'])->name('prototype.approvals.show');
    Route::post('/prototype/approvals', [SampleApprovalApiController::class, 'store'])->name('prototype.approvals.store');
    Route::put('/prototype/approvals/{approval}', [SampleApprovalApiController::class, 'update'])->name('prototype.approvals.update');

    Route::get('/prototype/feedback/options', [SampleFeedbackApiController::class, 'options'])->name('prototype.feedback.options');
    Route::get('/prototype/feedback', [SampleFeedbackApiController::class, 'index'])->name('prototype.feedback.index');
    Route::get('/prototype/feedback/{feedback}', [SampleFeedbackApiController::class, 'show'])->name('prototype.feedback.show');
    Route::post('/prototype/feedback', [SampleFeedbackApiController::class, 'store'])->name('prototype.feedback.store');
    Route::put('/prototype/feedback/{feedback}', [SampleFeedbackApiController::class, 'update'])->name('prototype.feedback.update');

    Route::get('/prototype/handovers/options', [SampleHandoverApiController::class, 'options'])->name('prototype.handovers.options');
    Route::get('/prototype/handovers', [SampleHandoverApiController::class, 'index'])->name('prototype.handovers.index');
    Route::get('/prototype/handovers/{handover}', [SampleHandoverApiController::class, 'show'])->name('prototype.handovers.show');
    Route::post('/prototype/handovers', [SampleHandoverApiController::class, 'store'])->name('prototype.handovers.store');
    Route::put('/prototype/handovers/{handover}', [SampleHandoverApiController::class, 'update'])->name('prototype.handovers.update');
    Route::delete('/prototype/handovers/{handover}', [SampleHandoverApiController::class, 'destroy'])->name('prototype.handovers.destroy');
});
