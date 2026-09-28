<?php

declare(strict_types=1);

use App\Modules\Procurement\Http\Controllers\Api\FabricBookingApiController;
use App\Modules\Procurement\Http\Controllers\Api\GoodsReceiptApiController;
use App\Modules\Procurement\Http\Controllers\Api\ImportDocumentApiController;
use App\Modules\Procurement\Http\Controllers\Api\LandedCostApiController;
use App\Modules\Procurement\Http\Controllers\Api\MaterialRequirementApiController;
use App\Modules\Procurement\Http\Controllers\Api\PurchaseOrderApiController;
use App\Modules\Procurement\Http\Controllers\Api\PurchaseRequisitionApiController;
use App\Modules\Procurement\Http\Controllers\Api\PurchaseReturnApiController;
use App\Modules\Procurement\Http\Controllers\Api\RateContractApiController;
use App\Modules\Procurement\Http\Controllers\Api\SupplierAdvanceApiController;
use App\Modules\Procurement\Http\Controllers\Api\SupplierClaimApiController;
use App\Modules\Procurement\Http\Controllers\Api\SupplierScorecardApiController;
use Illuminate\Support\Facades\Route;

/*
 * Procurement (Procurement spec §5, §7, §9): requisition → order → receipt.
 * Static segments sit ahead of the wildcards they would otherwise match.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/purchase-requisitions', [PurchaseRequisitionApiController::class, 'index'])->name('purchase-requisitions.index');
    Route::post('/purchase-requisitions', [PurchaseRequisitionApiController::class, 'store'])->name('purchase-requisitions.store');
    Route::get('/purchase-requisitions/form-options', [PurchaseRequisitionApiController::class, 'formOptions'])->name('purchase-requisitions.form-options');
    Route::get('/purchase-requisitions/{requisition}', [PurchaseRequisitionApiController::class, 'show'])->name('purchase-requisitions.show');
    Route::patch('/purchase-requisitions/{requisition}', [PurchaseRequisitionApiController::class, 'update'])->name('purchase-requisitions.update');
    Route::post('/purchase-requisitions/{requisition}/submit', [PurchaseRequisitionApiController::class, 'submit'])->name('purchase-requisitions.submit');
    Route::post('/purchase-requisitions/{requisition}/cancel', [PurchaseRequisitionApiController::class, 'cancel'])->name('purchase-requisitions.cancel');
    Route::post('/purchase-requisitions/{requisition}/orders', [PurchaseOrderApiController::class, 'storeFromRequisition'])->name('purchase-requisitions.orders.store');

    Route::get('/purchase-orders', [PurchaseOrderApiController::class, 'index'])->name('purchase-orders.index');
    Route::post('/purchase-orders', [PurchaseOrderApiController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders/form-options', [PurchaseOrderApiController::class, 'formOptions'])->name('purchase-orders.form-options');
    Route::get('/purchase-orders/{order}', [PurchaseOrderApiController::class, 'show'])->name('purchase-orders.show');
    Route::get('/purchase-orders/{order}/receivable', [PurchaseOrderApiController::class, 'receivable'])->name('purchase-orders.receivable');
    Route::patch('/purchase-orders/{order}', [PurchaseOrderApiController::class, 'update'])->name('purchase-orders.update');
    Route::post('/purchase-orders/{order}/submit', [PurchaseOrderApiController::class, 'submit'])->name('purchase-orders.submit');
    Route::post('/purchase-orders/{order}/revise', [PurchaseOrderApiController::class, 'revise'])->name('purchase-orders.revise');
    Route::post('/purchase-orders/{order}/cancel', [PurchaseOrderApiController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('/purchase-orders/{order}/close', [PurchaseOrderApiController::class, 'close'])->name('purchase-orders.close');

    Route::get('/goods-receipts', [GoodsReceiptApiController::class, 'index'])->name('goods-receipts.index');
    Route::post('/goods-receipts', [GoodsReceiptApiController::class, 'store'])->name('goods-receipts.store');
    Route::get('/goods-receipts/form-options', [GoodsReceiptApiController::class, 'formOptions'])->name('goods-receipts.form-options');
    Route::get('/goods-receipts/{receipt}', [GoodsReceiptApiController::class, 'show'])->name('goods-receipts.show');
    Route::patch('/goods-receipts/{receipt}', [GoodsReceiptApiController::class, 'update'])->name('goods-receipts.update');
    Route::post('/goods-receipts/{receipt}/complete', [GoodsReceiptApiController::class, 'complete'])->name('goods-receipts.complete');
    Route::post('/goods-receipts/{receipt}/cancel', [GoodsReceiptApiController::class, 'cancel'])->name('goods-receipts.cancel');

    Route::get('/fabric-bookings', [FabricBookingApiController::class, 'index'])->name('fabric-bookings.index');
    Route::post('/fabric-bookings', [FabricBookingApiController::class, 'store'])->name('fabric-bookings.store');
    // Static segment ahead of the {booking} wildcard.
    Route::get('/fabric-bookings/form-options', [FabricBookingApiController::class, 'formOptions'])->name('fabric-bookings.form-options');
    Route::get('/fabric-bookings/{booking}', [FabricBookingApiController::class, 'show'])->name('fabric-bookings.show');
    Route::patch('/fabric-bookings/{booking}', [FabricBookingApiController::class, 'update'])->name('fabric-bookings.update');
    Route::post('/fabric-bookings/{booking}/confirm', [FabricBookingApiController::class, 'confirm'])->name('fabric-bookings.confirm');
    Route::post('/fabric-bookings/{booking}/cancel', [FabricBookingApiController::class, 'cancel'])->name('fabric-bookings.cancel');
    Route::post('/fabric-bookings/{booking}/convert', [FabricBookingApiController::class, 'convert'])->name('fabric-bookings.convert');

    // A calculation, not a document: read it, or raise the draft it implies.
    Route::get('/cutting-programs/{program}/material-requirement', [MaterialRequirementApiController::class, 'forProgram'])->name('material-requirement.program');
    Route::get('/production-styles/{style}/material-requirement', [MaterialRequirementApiController::class, 'forStyle'])->name('material-requirement.style');
    Route::post('/cutting-programs/{program}/material-requirement/requisition', [MaterialRequirementApiController::class, 'raiseRequisition'])->name('material-requirement.requisition');

    // Landed cost (spec §11): preview before posting, because posting
    // moves valuations that cannot be put back.
    Route::get('/landed-costs', [LandedCostApiController::class, 'index'])->name('landed-costs.index');
    Route::post('/landed-costs', [LandedCostApiController::class, 'store'])->name('landed-costs.store');
    Route::get('/landed-costs/{voucher}', [LandedCostApiController::class, 'show'])->name('landed-costs.show');
    Route::patch('/landed-costs/{voucher}', [LandedCostApiController::class, 'update'])->name('landed-costs.update');
    Route::get('/landed-costs/{voucher}/preview', [LandedCostApiController::class, 'preview'])->name('landed-costs.preview');
    Route::post('/landed-costs/{voucher}/post', [LandedCostApiController::class, 'post'])->name('landed-costs.post');
    Route::post('/landed-costs/{voucher}/cancel', [LandedCostApiController::class, 'cancel'])->name('landed-costs.cancel');

    // Goods back to the supplier (spec §12). Raised against the receipt
    // they came in on; nothing leaves the shelf until dispatch.
    Route::get('/purchase-returns', [PurchaseReturnApiController::class, 'index'])->name('purchase-returns.index');
    Route::get('/purchase-returns/{return}', [PurchaseReturnApiController::class, 'show'])->name('purchase-returns.show');
    Route::post('/goods-receipts/{receipt}/returns', [PurchaseReturnApiController::class, 'store'])->name('purchase-returns.store');
    Route::post('/purchase-returns/{return}/submit', [PurchaseReturnApiController::class, 'submit'])->name('purchase-returns.submit');
    Route::post('/purchase-returns/{return}/dispatch', [PurchaseReturnApiController::class, 'dispatchGoods'])->name('purchase-returns.dispatch');
    Route::post('/purchase-returns/{return}/cancel', [PurchaseReturnApiController::class, 'cancel'])->name('purchase-returns.cancel');

    // Claims (spec §12) and advances (§13) are money, not stock.
    Route::get('/supplier-claims', [SupplierClaimApiController::class, 'index'])->name('supplier-claims.index');
    Route::post('/supplier-claims', [SupplierClaimApiController::class, 'store'])->name('supplier-claims.store');
    Route::get('/supplier-claims/{claim}', [SupplierClaimApiController::class, 'show'])->name('supplier-claims.show');
    Route::post('/supplier-claims/{claim}/accept', [SupplierClaimApiController::class, 'accept'])->name('supplier-claims.accept');
    Route::post('/supplier-claims/{claim}/settle', [SupplierClaimApiController::class, 'settle'])->name('supplier-claims.settle');
    Route::post('/supplier-claims/{claim}/reject', [SupplierClaimApiController::class, 'reject'])->name('supplier-claims.reject');
    Route::post('/supplier-claims/{claim}/withdraw', [SupplierClaimApiController::class, 'withdraw'])->name('supplier-claims.withdraw');

    Route::get('/supplier-advances', [SupplierAdvanceApiController::class, 'index'])->name('supplier-advances.index');
    Route::post('/supplier-advances', [SupplierAdvanceApiController::class, 'store'])->name('supplier-advances.store');
    // Static segment ahead of the {advance} wildcard.
    Route::get('/supplier-advances/outstanding', [SupplierAdvanceApiController::class, 'outstanding'])->name('supplier-advances.outstanding');
    Route::get('/supplier-advances/{advance}', [SupplierAdvanceApiController::class, 'show'])->name('supplier-advances.show');
    Route::post('/supplier-advances/{advance}/adjust', [SupplierAdvanceApiController::class, 'adjust'])->name('supplier-advances.adjust');
    Route::post('/supplier-advances/{advance}/refund', [SupplierAdvanceApiController::class, 'refund'])->name('supplier-advances.refund');
    Route::post('/supplier-advances/{advance}/cancel', [SupplierAdvanceApiController::class, 'cancel'])->name('supplier-advances.cancel');
    Route::post('/advance-adjustments/{adjustment}/reverse', [SupplierAdvanceApiController::class, 'reverse'])->name('advance-adjustments.reverse');

    // Import papers (spec §10): the PI the bank opens an LC against, and
    // the shipment that sails against both. Read as one file per order.
    Route::get('/shipments', [ImportDocumentApiController::class, 'shipments'])->name('shipments.index');
    Route::get('/purchase-orders/{order}/import-documents', [ImportDocumentApiController::class, 'forOrder'])->name('import-documents.order');
    Route::post('/purchase-orders/{order}/proforma-invoices', [ImportDocumentApiController::class, 'storeProformaInvoice'])->name('proforma-invoices.store');
    Route::post('/proforma-invoices/{invoice}/confirm', [ImportDocumentApiController::class, 'confirmProformaInvoice'])->name('proforma-invoices.confirm');
    // A revision is a new version, never an edit — the bank opened against
    // the old one.
    Route::post('/proforma-invoices/{invoice}/revise', [ImportDocumentApiController::class, 'reviseProformaInvoice'])->name('proforma-invoices.revise');
    Route::post('/purchase-orders/{order}/letters-of-credit', [ImportDocumentApiController::class, 'storeLetterOfCredit'])->name('letters-of-credit.store');
    Route::post('/letters-of-credit/{letter}/open', [ImportDocumentApiController::class, 'markLetterOpened'])->name('letters-of-credit.open');
    Route::post('/letters-of-credit/{letter}/amend', [ImportDocumentApiController::class, 'amendLetterOfCredit'])->name('letters-of-credit.amend');
    Route::post('/letters-of-credit/{letter}/close', [ImportDocumentApiController::class, 'closeLetterOfCredit'])->name('letters-of-credit.close');
    Route::post('/purchase-orders/{order}/shipments', [ImportDocumentApiController::class, 'storeShipment'])->name('shipments.store');
    Route::patch('/shipments/{shipment}', [ImportDocumentApiController::class, 'updateShipment'])->name('shipments.update');
    Route::post('/shipments/{shipment}/cancel', [ImportDocumentApiController::class, 'cancelShipment'])->name('shipments.cancel');

    // Rate contracts (spec §14). A purchase order line with no rate takes
    // the agreed one; `lookup` is what shows the buyer that before saving.
    Route::get('/rate-contracts', [RateContractApiController::class, 'index'])->name('rate-contracts.index');
    Route::post('/rate-contracts', [RateContractApiController::class, 'store'])->name('rate-contracts.store');
    // Static segments ahead of the {contract} wildcard.
    Route::get('/rate-contracts/form-options', [RateContractApiController::class, 'formOptions'])->name('rate-contracts.form-options');
    Route::get('/rate-contracts/lookup', [RateContractApiController::class, 'lookup'])->name('rate-contracts.lookup');
    Route::get('/rate-contracts/{contract}', [RateContractApiController::class, 'show'])->name('rate-contracts.show');
    Route::patch('/rate-contracts/{contract}', [RateContractApiController::class, 'update'])->name('rate-contracts.update');
    Route::post('/rate-contracts/{contract}/activate', [RateContractApiController::class, 'activate'])->name('rate-contracts.activate');
    Route::post('/rate-contracts/{contract}/cancel', [RateContractApiController::class, 'cancel'])->name('rate-contracts.cancel');

    // Supplier scorecards (spec §15). Calculating writes a stored snapshot
    // and moves the supplier's rating, so it is a POST, never a side effect
    // of opening a screen.
    Route::get('/supplier-scorecards', [SupplierScorecardApiController::class, 'index'])->name('supplier-scorecards.index');
    Route::post('/supplier-scorecards/calculate', [SupplierScorecardApiController::class, 'calculate'])->name('supplier-scorecards.calculate');
    Route::get('/vendors/{vendor}/scorecard', [SupplierScorecardApiController::class, 'forVendor'])->name('vendors.scorecard');
});
