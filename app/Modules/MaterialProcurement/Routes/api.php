<?php

declare(strict_types=1);

use App\Modules\MaterialProcurement\Http\Controllers\Api\AssignedMaterialApiController;
use App\Modules\MaterialProcurement\Http\Controllers\Api\PriceReviewApiController;
use App\Modules\MaterialProcurement\Http\Controllers\Api\ProcurementControlApiController;
use App\Modules\MaterialProcurement\Http\Controllers\Api\ProcurementDashboardApiController;
use App\Modules\MaterialProcurement\Http\Controllers\Api\PurchaseRequisitionApiController;
use App\Modules\MaterialProcurement\Http\Controllers\Api\SupplierLoginApiController;
use App\Modules\MaterialProcurement\Http\Controllers\Api\SupplierPortalApiController;
use Illuminate\Support\Facades\Route;

/*
 * Material Procurement (AnnotechERP 08): the purchase requisition — by order
 * or direct — and its fabric and accessories lines; the suppliers' portal
 * logins, the portal they quote in, the price review of their quotes, and
 * the approved material's follow-up to delivery, and the module's own
 * notifications and audit trail. A requisition is addressed by its number (PR-2026-00001), never
 * its key.
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/material-procurement/requisitions/options', [PurchaseRequisitionApiController::class, 'options'])->name('material-procurement.requisitions.options');
    Route::get('/material-procurement/requisitions', [PurchaseRequisitionApiController::class, 'index'])->name('material-procurement.requisitions.index');
    Route::post('/material-procurement/requisitions', [PurchaseRequisitionApiController::class, 'store'])->name('material-procurement.requisitions.store');
    Route::get('/material-procurement/requisitions/{requisition}', [PurchaseRequisitionApiController::class, 'show'])->name('material-procurement.requisitions.show');
    Route::put('/material-procurement/requisitions/{requisition}', [PurchaseRequisitionApiController::class, 'update'])->name('material-procurement.requisitions.update');
    Route::post('/material-procurement/requisitions/{requisition}/submit', [PurchaseRequisitionApiController::class, 'submit'])->name('material-procurement.requisitions.submit');
    Route::delete('/material-procurement/requisitions/{requisition}', [PurchaseRequisitionApiController::class, 'destroy'])->name('material-procurement.requisitions.destroy');

    Route::get('/material-procurement/supplier-logins', [SupplierLoginApiController::class, 'index'])->name('material-procurement.supplier-logins.index');
    Route::post('/material-procurement/supplier-logins', [SupplierLoginApiController::class, 'store'])->name('material-procurement.supplier-logins.store');
    Route::put('/material-procurement/supplier-logins/{login}/active', [SupplierLoginApiController::class, 'setActive'])->name('material-procurement.supplier-logins.active');

    Route::get('/material-procurement/portal/dashboard', [SupplierPortalApiController::class, 'dashboard'])->name('material-procurement.portal.dashboard');
    Route::get('/material-procurement/portal/requirements', [SupplierPortalApiController::class, 'requirements'])->name('material-procurement.portal.requirements');
    Route::post('/material-procurement/portal/requirements/{line}/quote', [SupplierPortalApiController::class, 'quote'])->name('material-procurement.portal.quote');
    Route::get('/material-procurement/portal/quotations', [SupplierPortalApiController::class, 'quotations'])->name('material-procurement.portal.quotations');

    Route::get('/material-procurement/price-review', [PriceReviewApiController::class, 'index'])->name('material-procurement.price-review.index');
    Route::post('/material-procurement/price-review/quotations/{quotation}/negotiate', [PriceReviewApiController::class, 'negotiate'])->name('material-procurement.price-review.negotiate');
    Route::post('/material-procurement/price-review/quotations/{quotation}/approve', [PriceReviewApiController::class, 'approve'])->name('material-procurement.price-review.approve');
    Route::post('/material-procurement/price-review/quotations/{quotation}/reject', [PriceReviewApiController::class, 'reject'])->name('material-procurement.price-review.reject');
    Route::post('/material-procurement/price-review/lines/{line}/reject', [PriceReviewApiController::class, 'rejectLine'])->name('material-procurement.price-review.reject-line');

    Route::get('/material-procurement/assigned-materials', [AssignedMaterialApiController::class, 'index'])->name('material-procurement.assigned.index');
    Route::get('/material-procurement/assigned-materials/grn-options', [AssignedMaterialApiController::class, 'grnOptions'])->name('material-procurement.assigned.grn-options');
    Route::get('/material-procurement/assigned-materials/{line}', [AssignedMaterialApiController::class, 'show'])->name('material-procurement.assigned.show');
    Route::post('/material-procurement/assigned-materials/{line}/followups', [AssignedMaterialApiController::class, 'followup'])->name('material-procurement.assigned.followup');
    Route::post('/material-procurement/followups/{followup}/grn', [AssignedMaterialApiController::class, 'raiseGrn'])->name('material-procurement.followups.grn');
    Route::get('/material-procurement/delivery-tracking', [AssignedMaterialApiController::class, 'tracking'])->name('material-procurement.delivery-tracking');

    Route::get('/material-procurement/procurement-notifications', [ProcurementControlApiController::class, 'notifications'])->name('material-procurement.notifications.index');
    Route::post('/material-procurement/procurement-notifications/read-all', [ProcurementControlApiController::class, 'readAll'])->name('material-procurement.notifications.read-all');
    Route::post('/material-procurement/procurement-notifications/{notification}/read', [ProcurementControlApiController::class, 'read'])->name('material-procurement.notifications.read');
    Route::get('/material-procurement/procurement-audit-trail', [ProcurementControlApiController::class, 'auditTrail'])->name('material-procurement.audit-trail');

    Route::get('/material-procurement/procurement-dashboard', [ProcurementDashboardApiController::class, 'index'])->name('material-procurement.dashboard');
});
