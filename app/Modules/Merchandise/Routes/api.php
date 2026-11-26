<?php

declare(strict_types=1);

use App\Modules\Merchandise\Http\Controllers\Api\AccBookingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\AccPurchaseOrderApiController;
use App\Modules\Merchandise\Http\Controllers\Api\AccReceiptApiController;
use App\Modules\Merchandise\Http\Controllers\Api\AccRequisitionApiController;
use App\Modules\Merchandise\Http\Controllers\Api\AccReturnApiController;
use App\Modules\Merchandise\Http\Controllers\Api\BudgetApiController;
use App\Modules\Merchandise\Http\Controllers\Api\BuyerMappingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\CadSizeApiController;
use App\Modules\Merchandise\Http\Controllers\Api\DevStyleApiController;
use App\Modules\Merchandise\Http\Controllers\Api\FabricBookingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\FabricOpeningBalanceApiController;
use App\Modules\Merchandise\Http\Controllers\Api\FabricRequisitionApiController;
use App\Modules\Merchandise\Http\Controllers\Api\FabricReturnApiController;
use App\Modules\Merchandise\Http\Controllers\Api\FabricTypePartsApiController;
use App\Modules\Merchandise\Http\Controllers\Api\FinishedFabricObApiController;
use App\Modules\Merchandise\Http\Controllers\Api\ImportBookingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\InquiryApiController;
use App\Modules\Merchandise\Http\Controllers\Api\LabdipShadeApiController;
use App\Modules\Merchandise\Http\Controllers\Api\MeasurePointApiController;
use App\Modules\Merchandise\Http\Controllers\Api\MerchAiApiController;
use App\Modules\Merchandise\Http\Controllers\Api\MerchandiseLookupsApiController;
use App\Modules\Merchandise\Http\Controllers\Api\MerchandiseReportApiController;
use App\Modules\Merchandise\Http\Controllers\Api\OrderApiController;
use App\Modules\Merchandise\Http\Controllers\Api\OrderConfirmationApiController;
use App\Modules\Merchandise\Http\Controllers\Api\OrderUploadApiController;
use App\Modules\Merchandise\Http\Controllers\Api\PrintLineApiController;
use App\Modules\Merchandise\Http\Controllers\Api\ProjectionApiController;
use App\Modules\Merchandise\Http\Controllers\Api\PurchasedFabricIndentApiController;
use App\Modules\Merchandise\Http\Controllers\Api\PurchasedFabricMrrApiController;
use App\Modules\Merchandise\Http\Controllers\Api\PurchasedFabricPoApiController;
use App\Modules\Merchandise\Http\Controllers\Api\RateChartApiController;
use App\Modules\Merchandise\Http\Controllers\Api\SampleChallanItemApiController;
use App\Modules\Merchandise\Http\Controllers\Api\SampleLineApiController;
use App\Modules\Merchandise\Http\Controllers\Api\SampleProductionApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StockClosingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleBomApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleColourApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleFabricApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleFabricMapApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleItemApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleOrderBoardApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StylePackingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleRegistrationApiController;
use App\Modules\Merchandise\Http\Controllers\Api\StyleServiceApiController;
use App\Modules\Merchandise\Http\Controllers\Api\TnaApiController;
use App\Modules\Merchandise\Http\Controllers\Api\TnaFollowupApiController;
use App\Modules\Merchandise\Http\Controllers\Api\TrimDyeingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\TrimSourcingApiController;
use App\Modules\Merchandise\Http\Controllers\Api\YarnSpecApiController;
use Illuminate\Support\Facades\Route;

/*
 * Merchandise Desk (AnnotechERP 06). The master lists themselves are screen-kit
 * resources under /erp/{resource}; only the screens with their own shape live
 * here.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    // Inquiry → Price Quotation Entry: the Quotation and Buyer Rates panels (the list itself is /erp/merch-inquiries).
    Route::get('/merchandise/inquiries/buyers', [InquiryApiController::class, 'buyers'])->name('merchandise.inquiries.buyers');
    Route::get('/merchandise/inquiries/buyers/{buyer}', [InquiryApiController::class, 'ofBuyer'])->name('merchandise.inquiries.of-buyer');
    Route::get('/merchandise/inquiries/{inquiry}/rates', [InquiryApiController::class, 'rates'])->name('merchandise.inquiries.rates');
    Route::post('/merchandise/inquiries/{inquiry}/{move}', [InquiryApiController::class, 'move'])->whereIn('move', ['send', 'revise', 'confirm', 'cancel'])->name('merchandise.inquiries.move');
    // Inquiry → Style Development Sheet.
    Route::get('/merchandise/styles/lookups', [StyleApiController::class, 'lookups'])->name('merchandise.styles.lookups');
    Route::get('/merchandise/styles', [StyleApiController::class, 'index'])->name('merchandise.styles.index');
    Route::post('/merchandise/styles', [StyleApiController::class, 'store'])->name('merchandise.styles.store');
    Route::get('/merchandise/styles/{style}', [StyleApiController::class, 'show'])->name('merchandise.styles.show');
    Route::put('/merchandise/styles/{style}', [StyleApiController::class, 'update'])->name('merchandise.styles.update');
    Route::delete('/merchandise/styles/{style}', [StyleApiController::class, 'destroy'])->name('merchandise.styles.destroy');
    Route::get('/merchandise/styles/{style}/size-ranges', [StyleApiController::class, 'sizeRanges'])->name('merchandise.styles.size-ranges');
    Route::post('/merchandise/styles/{style}/size-ranges', [StyleApiController::class, 'addSizeRange'])->name('merchandise.styles.size-ranges.add');
    Route::delete('/merchandise/style-size-ranges/{range}', [StyleApiController::class, 'removeSizeRange'])->name('merchandise.styles.size-ranges.remove');

    // Style Development Sheet → Item/Combo/Model → Registration.
    Route::get('/merchandise/styles/{style}/items', [StyleItemApiController::class, 'index'])->name('merchandise.style-items.index');
    Route::post('/merchandise/styles/{style}/items', [StyleItemApiController::class, 'store'])->name('merchandise.style-items.store');
    Route::put('/merchandise/style-items/{item}', [StyleItemApiController::class, 'update'])->name('merchandise.style-items.update');
    Route::delete('/merchandise/style-items/{item}', [StyleItemApiController::class, 'destroy'])->name('merchandise.style-items.destroy');
    Route::post('/merchandise/style-item-images', [StyleItemApiController::class, 'upload'])->name('merchandise.style-items.upload');

    // Item/Combo/Model → Colour Mapping.
    Route::get('/merchandise/styles/{style}/colours', [StyleColourApiController::class, 'index'])->name('merchandise.style-colours.index');
    Route::put('/merchandise/styles/{style}/colours', [StyleColourApiController::class, 'save'])->name('merchandise.style-colours.save');

    // Item/Combo/Model → Fabrication.
    Route::get('/merchandise/styles/{style}/fabrication', [StyleFabricApiController::class, 'index'])->name('merchandise.style-fabrics.index');
    Route::put('/merchandise/styles/{style}/fabrication', [StyleFabricApiController::class, 'save'])->name('merchandise.style-fabrics.save');

    // Item/Combo/Model → Services.
    Route::get('/merchandise/styles/{style}/services', [StyleServiceApiController::class, 'index'])->name('merchandise.style-services.index');
    Route::put('/merchandise/styles/{style}/services', [StyleServiceApiController::class, 'save'])->name('merchandise.style-services.save');

    // Item/Combo/Model → BOM.
    Route::get('/merchandise/styles/{style}/bom', [StyleBomApiController::class, 'index'])->name('merchandise.style-bom.index');
    Route::put('/merchandise/styles/{style}/bom', [StyleBomApiController::class, 'save'])->name('merchandise.style-bom.save');
    Route::post('/merchandise/styles/{style}/bom/approve', [StyleBomApiController::class, 'approve'])->name('merchandise.style-bom.approve');

    // Item/Combo/Model → Yarn/Fabric Mapping.
    Route::get('/merchandise/styles/{style}/fabric-mapping', [StyleFabricMapApiController::class, 'index'])->name('merchandise.style-fabric-maps.index');
    Route::put('/merchandise/styles/{style}/fabric-mapping', [StyleFabricMapApiController::class, 'save'])->name('merchandise.style-fabric-maps.save');

    // Style & Order → Order Register: the form's lists and the Size Breakdown panel (the list itself is /erp/merch-orders).
    Route::get('/merchandise/orders/lookups', [OrderApiController::class, 'lookups'])->name('merchandise.orders.lookups');
    Route::get('/merchandise/orders/{order}/sizes', [OrderApiController::class, 'sizes'])->name('merchandise.orders.sizes');
    Route::put('/merchandise/orders/{order}/sizes', [OrderApiController::class, 'saveSizes'])->name('merchandise.orders.sizes.save');

    // Style & Order → Order Confirmation: the screen's quick confirmation, and the style sheet's Order Confirmation tab.
    Route::get('/merchandise/order-confirmation/styles', [OrderConfirmationApiController::class, 'styles'])->name('merchandise.order-confirmation.styles');
    Route::post('/merchandise/order-confirmation', [OrderConfirmationApiController::class, 'confirm'])->name('merchandise.order-confirmation.confirm');
    Route::get('/merchandise/styles/{style}/orders', [OrderConfirmationApiController::class, 'ofStyle'])->name('merchandise.style-orders.index');
    Route::post('/merchandise/styles/{style}/orders', [OrderConfirmationApiController::class, 'storeForStyle'])->name('merchandise.style-orders.store');
    Route::put('/merchandise/style-orders/{order}', [OrderConfirmationApiController::class, 'updateForStyle'])->name('merchandise.style-orders.update');

    // Style & Order → Style & Order Dashboard and Style Progress Board.
    Route::get('/merchandise/style-order/dashboard', [StyleOrderBoardApiController::class, 'dashboard'])->name('merchandise.style-order.dashboard');
    Route::get('/merchandise/style-order/gallery', [StyleOrderBoardApiController::class, 'gallery'])->name('merchandise.style-order.gallery');
    Route::get('/merchandise/style-order/progress', [StyleOrderBoardApiController::class, 'progress'])->name('merchandise.style-order.progress');

    // Style & Order → Buyer-Wise Style & Order Follow-up, and Projection & Call-Off Order.
    Route::get('/merchandise/style-order/follow-up', [StyleOrderBoardApiController::class, 'followUp'])->name('merchandise.style-order.follow-up');
    Route::get('/merchandise/projections', [ProjectionApiController::class, 'index'])->name('merchandise.projections.index');
    Route::get('/merchandise/projections/{order}/call-offs', [ProjectionApiController::class, 'callOffs'])->name('merchandise.projections.call-offs');
    Route::post('/merchandise/projections/{order}/call-offs', [ProjectionApiController::class, 'store'])->name('merchandise.projections.call-offs.store');

    // Item/Combo/Model → Packing Instruction.
    Route::get('/merchandise/styles/{style}/packing', [StylePackingApiController::class, 'index'])->name('merchandise.style-packing.index');
    Route::put('/merchandise/styles/{style}/packing', [StylePackingApiController::class, 'save'])->name('merchandise.style-packing.save');

    // Style & Order → Development Style Catalogue: the list, Inbox and Sent, and the wizard's review, approval, attachments and packing details.
    Route::get('/merchandise/dev-styles', [DevStyleApiController::class, 'index'])->name('merchandise.dev-styles.index');
    Route::get('/merchandise/dev-styles/inbox', [DevStyleApiController::class, 'inbox'])->name('merchandise.dev-styles.inbox');
    Route::get('/merchandise/dev-styles/sent', [DevStyleApiController::class, 'sent'])->name('merchandise.dev-styles.sent');
    Route::get('/merchandise/styles/{style}/review', [DevStyleApiController::class, 'review'])->name('merchandise.dev-styles.review');
    Route::post('/merchandise/styles/{style}/approval/{move}', [DevStyleApiController::class, 'act'])->whereIn('move', ['send', 'approve', 'revision', 'reject', 'direct'])->name('merchandise.dev-styles.act');
    Route::get('/merchandise/styles/{style}/attachments', [DevStyleApiController::class, 'attachments'])->name('merchandise.dev-styles.attachments');
    Route::post('/merchandise/styles/{style}/attachments', [DevStyleApiController::class, 'attach'])->name('merchandise.dev-styles.attach');
    Route::delete('/merchandise/styles/{style}/attachments/{kind}', [DevStyleApiController::class, 'detach'])->name('merchandise.dev-styles.detach');
    Route::get('/merchandise/styles/{style}/packing-details', [DevStyleApiController::class, 'packingDetails'])->name('merchandise.dev-styles.packing');
    Route::put('/merchandise/styles/{style}/packing-details', [DevStyleApiController::class, 'savePackingDetails'])->name('merchandise.dev-styles.packing.save');

    // Style & Order → Style Registration Entry: requests raised from approved development styles, their approval, the registered styles.
    Route::get('/merchandise/style-registrations', [StyleRegistrationApiController::class, 'index'])->name('merchandise.style-registrations.index');
    Route::get('/merchandise/style-registrations/candidates', [StyleRegistrationApiController::class, 'candidates'])->name('merchandise.style-registrations.candidates');
    Route::post('/merchandise/style-registrations', [StyleRegistrationApiController::class, 'store'])->name('merchandise.style-registrations.store');
    Route::get('/merchandise/style-registrations/{registration}', [StyleRegistrationApiController::class, 'show'])->name('merchandise.style-registrations.show');
    Route::put('/merchandise/style-registrations/{registration}', [StyleRegistrationApiController::class, 'update'])->name('merchandise.style-registrations.update');
    Route::delete('/merchandise/style-registrations/{registration}', [StyleRegistrationApiController::class, 'discard'])->name('merchandise.style-registrations.discard');
    Route::post('/merchandise/style-registrations/{registration}/submit', [StyleRegistrationApiController::class, 'submit'])->name('merchandise.style-registrations.submit');
    Route::post('/merchandise/style-registrations/{registration}/change', [StyleRegistrationApiController::class, 'change'])->name('merchandise.style-registrations.change');
    Route::post('/merchandise/style-registrations/{registration}/{move}', [StyleRegistrationApiController::class, 'decide'])->whereIn('move', ['approve', 'return', 'reject'])->name('merchandise.style-registrations.decide');

    // Costing & Budget → CAD Consumption Register → Consumption (the records themselves are /erp/merch-cad-records).
    Route::get('/merchandise/cad-records/{record}/sizes', [CadSizeApiController::class, 'index'])->name('merchandise.cad-records.sizes');
    Route::put('/merchandise/cad-records/{record}/sizes', [CadSizeApiController::class, 'save'])->name('merchandise.cad-records.sizes.save');
    // Lab Dip & Sample → Lab Dip Programme → Labdip Specification (the programs themselves are /erp/merch-labdip-programs).
    Route::get('/merchandise/labdip-programs/{program}/shades', [LabdipShadeApiController::class, 'index'])->name('merchandise.labdip-programs.shades');
    Route::put('/merchandise/labdip-programs/{program}/shades', [LabdipShadeApiController::class, 'save'])->name('merchandise.labdip-programs.shades.save');
    // Lab Dip & Sample → Lab Dip Submission → Records (each shade on its way to the buyer).
    Route::get('/merchandise/labdip-submissions', [LabdipShadeApiController::class, 'submissions'])->name('merchandise.labdip-submissions');
    // Lab Dip & Sample → Sample Programme → Sample Lines (the programmes themselves are /erp/merch-sample-programs).
    Route::get('/merchandise/sample-programs/{program}/lines', [SampleLineApiController::class, 'index'])->name('merchandise.sample-programs.lines');
    Route::post('/merchandise/sample-programs/{program}/lines', [SampleLineApiController::class, 'add'])->name('merchandise.sample-programs.lines.add');
    Route::put('/merchandise/sample-programs/{program}/lines', [SampleLineApiController::class, 'save'])->name('merchandise.sample-programs.lines.save');
    // Lab Dip & Sample → Print Design Sheet → Style & Order Lines (the sheets themselves are /erp/merch-print-programs).
    Route::get('/merchandise/print-programs/{program}/lines', [PrintLineApiController::class, 'index'])->name('merchandise.print-programs.lines');
    Route::post('/merchandise/print-programs/{program}/lines', [PrintLineApiController::class, 'add'])->name('merchandise.print-programs.lines.add');
    Route::put('/merchandise/print-programs/{program}/lines', [PrintLineApiController::class, 'save'])->name('merchandise.print-programs.lines.save');
    Route::post('/merchandise/print-programs/{program}/lines/{line}/artwork', [PrintLineApiController::class, 'artwork'])->name('merchandise.print-programs.lines.artwork');
    // Lab Dip & Sample → Sample Production Entry: batches of planned sample lines.
    Route::get('/merchandise/sample-production', [SampleProductionApiController::class, 'index'])->name('merchandise.sample-production');
    Route::post('/merchandise/sample-batches', [SampleProductionApiController::class, 'open'])->name('merchandise.sample-batches.open');
    Route::get('/merchandise/sample-batches/{batch}', [SampleProductionApiController::class, 'show'])->name('merchandise.sample-batches.show');
    Route::put('/merchandise/sample-batches/{batch}', [SampleProductionApiController::class, 'save'])->name('merchandise.sample-batches.save');
    Route::post('/merchandise/sample-batches/{batch}/lines', [SampleProductionApiController::class, 'add'])->name('merchandise.sample-batches.lines.add');
    Route::delete('/merchandise/sample-batches/{batch}/lines/{line}', [SampleProductionApiController::class, 'drop'])->name('merchandise.sample-batches.lines.drop');
    Route::post('/merchandise/sample-batches/{batch}/submit', [SampleProductionApiController::class, 'submit'])->name('merchandise.sample-batches.submit');
    // Lab Dip & Sample → Sample Delivery Challan → Challan Items (the challans themselves are /erp/merch-sample-challans).
    Route::get('/merchandise/sample-challans/{challan}/items', [SampleChallanItemApiController::class, 'index'])->name('merchandise.sample-challans.items');
    Route::post('/merchandise/sample-challans/{challan}/items', [SampleChallanItemApiController::class, 'add'])->name('merchandise.sample-challans.items.add');
    Route::put('/merchandise/sample-challans/{challan}/items', [SampleChallanItemApiController::class, 'save'])->name('merchandise.sample-challans.items.save');
    // Lab Dip & Sample → Collar & Cuff Measurement Sheet → Add Measurement (the charts themselves are /erp/merch-measure-charts).
    Route::get('/merchandise/measure-charts/{chart}/points', [MeasurePointApiController::class, 'index'])->name('merchandise.measure-charts.points');
    Route::post('/merchandise/measure-charts/{chart}/points', [MeasurePointApiController::class, 'add'])->name('merchandise.measure-charts.points.add');
    Route::put('/merchandise/measure-charts/{chart}/points', [MeasurePointApiController::class, 'save'])->name('merchandise.measure-charts.points.save');
    // Fabric Booking → Bulk Fabric Booking Entry (and the other booking types, by ?type=): a booking is saved whole.
    Route::get('/merchandise/fabric-bookings/options', [FabricBookingApiController::class, 'options'])->name('merchandise.fabric-bookings.options');
    Route::get('/merchandise/acc-requisitions/options', [AccRequisitionApiController::class, 'options'])->name('merchandise.acc-requisitions.options');
    Route::get('/merchandise/acc-requisitions/bom', [AccRequisitionApiController::class, 'bom'])->name('merchandise.acc-requisitions.bom');
    Route::get('/merchandise/acc-requisitions', [AccRequisitionApiController::class, 'index'])->name('merchandise.acc-requisitions.index');
    Route::post('/merchandise/acc-requisitions', [AccRequisitionApiController::class, 'store'])->name('merchandise.acc-requisitions.store');
    Route::get('/merchandise/acc-requisitions/{requisition}', [AccRequisitionApiController::class, 'show'])->name('merchandise.acc-requisitions.show');
    Route::put('/merchandise/acc-requisitions/{requisition}', [AccRequisitionApiController::class, 'update'])->name('merchandise.acc-requisitions.update');
    Route::delete('/merchandise/acc-requisitions/{requisition}', [AccRequisitionApiController::class, 'destroy'])->name('merchandise.acc-requisitions.destroy');
    Route::post('/merchandise/acc-requisitions/{requisition}/{action}', [AccRequisitionApiController::class, 'transition'])->whereIn('action', ['submit', 'approve', 'reject', 'reopen'])->name('merchandise.acc-requisitions.transition');
    Route::get('/merchandise/pfab-mrrs/options', [PurchasedFabricMrrApiController::class, 'options'])->name('merchandise.pfab-mrrs.options');
    Route::get('/merchandise/pfab-mrrs/receivables/{order}', [PurchasedFabricMrrApiController::class, 'fromOrder'])->name('merchandise.pfab-mrrs.from-order');
    Route::get('/merchandise/pfab-mrrs', [PurchasedFabricMrrApiController::class, 'index'])->name('merchandise.pfab-mrrs.index');
    Route::post('/merchandise/pfab-mrrs', [PurchasedFabricMrrApiController::class, 'store'])->name('merchandise.pfab-mrrs.store');
    Route::get('/merchandise/pfab-mrrs/{receipt}', [PurchasedFabricMrrApiController::class, 'show'])->name('merchandise.pfab-mrrs.show');
    Route::put('/merchandise/pfab-mrrs/{receipt}', [PurchasedFabricMrrApiController::class, 'update'])->name('merchandise.pfab-mrrs.update');
    Route::delete('/merchandise/pfab-mrrs/{receipt}', [PurchasedFabricMrrApiController::class, 'destroy'])->name('merchandise.pfab-mrrs.destroy');
    Route::post('/merchandise/pfab-mrrs/{receipt}/finalize', [PurchasedFabricMrrApiController::class, 'finalize'])->name('merchandise.pfab-mrrs.finalize');
    Route::get('/merchandise/pfab-returns/options', [FabricReturnApiController::class, 'options'])->name('merchandise.pfab-returns.options');
    Route::get('/merchandise/pfab-returns/receipts/{receipt}', [FabricReturnApiController::class, 'fromReceipt'])->name('merchandise.pfab-returns.from-receipt');
    Route::get('/merchandise/pfab-returns', [FabricReturnApiController::class, 'index'])->name('merchandise.pfab-returns.index');
    Route::post('/merchandise/pfab-returns', [FabricReturnApiController::class, 'store'])->name('merchandise.pfab-returns.store');
    Route::get('/merchandise/pfab-returns/{challan}', [FabricReturnApiController::class, 'show'])->name('merchandise.pfab-returns.show');
    Route::put('/merchandise/pfab-returns/{challan}', [FabricReturnApiController::class, 'update'])->name('merchandise.pfab-returns.update');
    Route::delete('/merchandise/pfab-returns/{challan}', [FabricReturnApiController::class, 'destroy'])->name('merchandise.pfab-returns.destroy');
    Route::post('/merchandise/pfab-returns/{challan}/finalize', [FabricReturnApiController::class, 'finalize'])->name('merchandise.pfab-returns.finalize');
    Route::get('/merchandise/ffab-obs/options', [FinishedFabricObApiController::class, 'options'])->name('merchandise.ffab-obs.options');
    Route::get('/merchandise/ffab-obs', [FinishedFabricObApiController::class, 'index'])->name('merchandise.ffab-obs.index');
    Route::post('/merchandise/ffab-obs', [FinishedFabricObApiController::class, 'store'])->name('merchandise.ffab-obs.store');
    Route::get('/merchandise/ffab-obs/{balance}', [FinishedFabricObApiController::class, 'show'])->name('merchandise.ffab-obs.show');
    Route::put('/merchandise/ffab-obs/{balance}', [FinishedFabricObApiController::class, 'update'])->name('merchandise.ffab-obs.update');
    Route::delete('/merchandise/ffab-obs/{balance}', [FinishedFabricObApiController::class, 'destroy'])->name('merchandise.ffab-obs.destroy');
    Route::get('/merchandise/closings/{kind}', [StockClosingApiController::class, 'index'])->name('merchandise.closings.index');
    Route::post('/merchandise/closings/{kind}', [StockClosingApiController::class, 'close'])->name('merchandise.closings.close');
    Route::get('/merchandise/closings/{kind}/{period}/exceptions', [StockClosingApiController::class, 'exceptions'])->name('merchandise.closings.exceptions');
    Route::get('/merchandise/acc-bookings/options', [AccBookingApiController::class, 'options'])->name('merchandise.acc-bookings.options');
    Route::get('/merchandise/acc-bookings/orders', [AccBookingApiController::class, 'orders'])->name('merchandise.acc-bookings.orders');
    Route::get('/merchandise/acc-bookings/orders/{order}', [AccBookingApiController::class, 'forOrder'])->name('merchandise.acc-bookings.for-order');
    Route::post('/merchandise/acc-bookings/orders/{order}', [AccBookingApiController::class, 'store'])->name('merchandise.acc-bookings.store');
    Route::get('/merchandise/acc-bookings/purchase-register', [AccBookingApiController::class, 'purchaseRegister'])->name('merchandise.acc-bookings.purchase-register');
    Route::get('/merchandise/acc-bookings', [AccBookingApiController::class, 'index'])->name('merchandise.acc-bookings.index');
    Route::get('/merchandise/acc-bookings/{booking}', [AccBookingApiController::class, 'show'])->name('merchandise.acc-bookings.show');
    Route::post('/merchandise/acc-bookings/{booking}/lines', [AccBookingApiController::class, 'addLine'])->name('merchandise.acc-bookings.add-line');
    Route::put('/merchandise/acc-bookings/{booking}/lines', [AccBookingApiController::class, 'saveLines'])->name('merchandise.acc-bookings.save-lines');
    Route::get('/merchandise/trim-sourcings/options', [TrimSourcingApiController::class, 'options'])->name('merchandise.trim-sourcings.options');
    Route::get('/merchandise/trim-sourcings/orders', [TrimSourcingApiController::class, 'orders'])->name('merchandise.trim-sourcings.orders');
    Route::get('/merchandise/trim-sourcings/orders/{order}', [TrimSourcingApiController::class, 'forOrder'])->name('merchandise.trim-sourcings.for-order');
    Route::get('/merchandise/trim-sourcings', [TrimSourcingApiController::class, 'index'])->name('merchandise.trim-sourcings.index');
    Route::post('/merchandise/trim-sourcings', [TrimSourcingApiController::class, 'store'])->name('merchandise.trim-sourcings.store');
    Route::put('/merchandise/trim-sourcings/{sourcing}', [TrimSourcingApiController::class, 'update'])->name('merchandise.trim-sourcings.update');
    Route::delete('/merchandise/trim-sourcings/{sourcing}', [TrimSourcingApiController::class, 'destroy'])->name('merchandise.trim-sourcings.destroy');
    Route::get('/merchandise/trim-dyeing/options', [TrimDyeingApiController::class, 'options'])->name('merchandise.trim-dyeing.options');
    Route::get('/merchandise/trim-dyeing', [TrimDyeingApiController::class, 'index'])->name('merchandise.trim-dyeing.index');
    Route::post('/merchandise/trim-dyeing', [TrimDyeingApiController::class, 'store'])->name('merchandise.trim-dyeing.store');
    Route::get('/merchandise/trim-dyeing/{program}', [TrimDyeingApiController::class, 'show'])->name('merchandise.trim-dyeing.show');
    Route::put('/merchandise/trim-dyeing/{program}', [TrimDyeingApiController::class, 'update'])->name('merchandise.trim-dyeing.update');
    Route::post('/merchandise/trim-dyeing/{program}/items', [TrimDyeingApiController::class, 'addItem'])->name('merchandise.trim-dyeing.add-item');
    Route::delete('/merchandise/trim-dyeing/{program}/items/{item}', [TrimDyeingApiController::class, 'removeItem'])->name('merchandise.trim-dyeing.remove-item');
    Route::get('/merchandise/order-uploads', [OrderUploadApiController::class, 'index'])->name('merchandise.order-uploads.index');
    Route::post('/merchandise/order-uploads/verify', [OrderUploadApiController::class, 'verify'])->name('merchandise.order-uploads.verify');
    Route::post('/merchandise/order-uploads', [OrderUploadApiController::class, 'upload'])->name('merchandise.order-uploads.upload');
    Route::get('/merchandise/ai/snapshot', [MerchAiApiController::class, 'snapshot'])->name('merchandise.ai.snapshot');
    Route::post('/merchandise/ai/run', [MerchAiApiController::class, 'run'])->name('merchandise.ai.run');
    Route::post('/merchandise/ai/alerts/{alert}/{action}', [MerchAiApiController::class, 'alert'])->whereIn('action', ['acknowledge', 'resolve', 'dismiss', 'reopen'])->name('merchandise.ai.alert');
    Route::get('/merchandise/pfab-pos/options', [PurchasedFabricPoApiController::class, 'options'])->name('merchandise.pfab-pos.options');
    Route::get('/merchandise/pfab-pos/requisitions', [PurchasedFabricPoApiController::class, 'requisitions'])->name('merchandise.pfab-pos.requisitions');
    Route::get('/merchandise/pfab-pos/requisitions/{indent}', [PurchasedFabricPoApiController::class, 'fromIndent'])->name('merchandise.pfab-pos.from-indent');
    Route::get('/merchandise/pfab-pos', [PurchasedFabricPoApiController::class, 'index'])->name('merchandise.pfab-pos.index');
    Route::post('/merchandise/pfab-pos', [PurchasedFabricPoApiController::class, 'store'])->name('merchandise.pfab-pos.store');
    Route::get('/merchandise/pfab-pos/{order}', [PurchasedFabricPoApiController::class, 'show'])->name('merchandise.pfab-pos.show');
    Route::put('/merchandise/pfab-pos/{order}', [PurchasedFabricPoApiController::class, 'update'])->name('merchandise.pfab-pos.update');
    Route::delete('/merchandise/pfab-pos/{order}', [PurchasedFabricPoApiController::class, 'destroy'])->name('merchandise.pfab-pos.destroy');
    Route::get('/merchandise/pfab-indents/options', [PurchasedFabricIndentApiController::class, 'options'])->name('merchandise.pfab-indents.options');
    Route::get('/merchandise/pfab-indents/fill', [PurchasedFabricIndentApiController::class, 'fill'])->name('merchandise.pfab-indents.fill');
    Route::get('/merchandise/pfab-indents', [PurchasedFabricIndentApiController::class, 'index'])->name('merchandise.pfab-indents.index');
    Route::post('/merchandise/pfab-indents', [PurchasedFabricIndentApiController::class, 'store'])->name('merchandise.pfab-indents.store');
    Route::get('/merchandise/pfab-indents/{indent}', [PurchasedFabricIndentApiController::class, 'show'])->name('merchandise.pfab-indents.show');
    Route::put('/merchandise/pfab-indents/{indent}', [PurchasedFabricIndentApiController::class, 'update'])->name('merchandise.pfab-indents.update');
    Route::delete('/merchandise/pfab-indents/{indent}', [PurchasedFabricIndentApiController::class, 'destroy'])->name('merchandise.pfab-indents.destroy');
    Route::post('/merchandise/pfab-indents/{indent}/{action}', [PurchasedFabricIndentApiController::class, 'transition'])->whereIn('action', ['submit', 'approve', 'reject', 'reopen'])->name('merchandise.pfab-indents.transition');
    Route::get('/merchandise/fabric-obs/options', [FabricOpeningBalanceApiController::class, 'options'])->name('merchandise.fabric-obs.options');
    Route::get('/merchandise/fabric-obs', [FabricOpeningBalanceApiController::class, 'index'])->name('merchandise.fabric-obs.index');
    Route::post('/merchandise/fabric-obs', [FabricOpeningBalanceApiController::class, 'store'])->name('merchandise.fabric-obs.store');
    Route::get('/merchandise/fabric-obs/{balance}', [FabricOpeningBalanceApiController::class, 'show'])->name('merchandise.fabric-obs.show');
    Route::get('/merchandise/fabric-obs/{balance}/history', [FabricOpeningBalanceApiController::class, 'history'])->name('merchandise.fabric-obs.history');
    Route::put('/merchandise/fabric-obs/{balance}', [FabricOpeningBalanceApiController::class, 'update'])->name('merchandise.fabric-obs.update');
    Route::delete('/merchandise/fabric-obs/{balance}', [FabricOpeningBalanceApiController::class, 'destroy'])->name('merchandise.fabric-obs.destroy');
    Route::post('/merchandise/fabric-obs/{balance}/attachment', [FabricOpeningBalanceApiController::class, 'attach'])->name('merchandise.fabric-obs.attach');
    Route::post('/merchandise/fabric-obs/{balance}/{action}', [FabricOpeningBalanceApiController::class, 'transition'])->whereIn('action', ['submit', 'approve', 'reject', 'post', 'unpost', 'lock'])->name('merchandise.fabric-obs.transition');
    Route::get('/merchandise/acc-returns/options', [AccReturnApiController::class, 'options'])->name('merchandise.acc-returns.options');
    Route::get('/merchandise/acc-returns/receipts/{receipt}', [AccReturnApiController::class, 'fromReceipt'])->name('merchandise.acc-returns.from-receipt');
    Route::get('/merchandise/acc-returns', [AccReturnApiController::class, 'index'])->name('merchandise.acc-returns.index');
    Route::post('/merchandise/acc-returns', [AccReturnApiController::class, 'store'])->name('merchandise.acc-returns.store');
    Route::get('/merchandise/acc-returns/{challan}', [AccReturnApiController::class, 'show'])->name('merchandise.acc-returns.show');
    Route::put('/merchandise/acc-returns/{challan}', [AccReturnApiController::class, 'update'])->name('merchandise.acc-returns.update');
    Route::delete('/merchandise/acc-returns/{challan}', [AccReturnApiController::class, 'destroy'])->name('merchandise.acc-returns.destroy');
    Route::post('/merchandise/acc-returns/{challan}/finalize', [AccReturnApiController::class, 'finalize'])->name('merchandise.acc-returns.finalize');
    Route::get('/merchandise/acc-receipts/ledgers', [AccReceiptApiController::class, 'ledgers'])->name('merchandise.acc-receipts.ledgers');
    Route::get('/merchandise/acc-receipts/options', [AccReceiptApiController::class, 'options'])->name('merchandise.acc-receipts.options');
    Route::get('/merchandise/acc-receipts/receivables', [AccReceiptApiController::class, 'receivables'])->name('merchandise.acc-receipts.receivables');
    Route::get('/merchandise/acc-receipts/receivables/{order}', [AccReceiptApiController::class, 'fromOrder'])->name('merchandise.acc-receipts.from-order');
    Route::get('/merchandise/acc-receipts', [AccReceiptApiController::class, 'index'])->name('merchandise.acc-receipts.index');
    Route::post('/merchandise/acc-receipts', [AccReceiptApiController::class, 'store'])->name('merchandise.acc-receipts.store');
    Route::get('/merchandise/acc-receipts/{receipt}', [AccReceiptApiController::class, 'show'])->name('merchandise.acc-receipts.show');
    Route::put('/merchandise/acc-receipts/{receipt}', [AccReceiptApiController::class, 'update'])->name('merchandise.acc-receipts.update');
    Route::delete('/merchandise/acc-receipts/{receipt}', [AccReceiptApiController::class, 'destroy'])->name('merchandise.acc-receipts.destroy');
    Route::post('/merchandise/acc-receipts/{receipt}/finalize', [AccReceiptApiController::class, 'finalize'])->name('merchandise.acc-receipts.finalize');
    Route::get('/merchandise/acc-purchase-orders/options', [AccPurchaseOrderApiController::class, 'options'])->name('merchandise.acc-purchase-orders.options');
    Route::get('/merchandise/acc-purchase-orders/requisitions', [AccPurchaseOrderApiController::class, 'requisitions'])->name('merchandise.acc-purchase-orders.requisitions');
    Route::get('/merchandise/acc-purchase-orders/requisitions/{requisition}', [AccPurchaseOrderApiController::class, 'fromRequisition'])->name('merchandise.acc-purchase-orders.from-requisition');
    Route::get('/merchandise/acc-purchase-orders', [AccPurchaseOrderApiController::class, 'index'])->name('merchandise.acc-purchase-orders.index');
    Route::post('/merchandise/acc-purchase-orders', [AccPurchaseOrderApiController::class, 'store'])->name('merchandise.acc-purchase-orders.store');
    Route::get('/merchandise/acc-purchase-orders/{order}', [AccPurchaseOrderApiController::class, 'show'])->name('merchandise.acc-purchase-orders.show');
    Route::put('/merchandise/acc-purchase-orders/{order}', [AccPurchaseOrderApiController::class, 'update'])->name('merchandise.acc-purchase-orders.update');
    Route::delete('/merchandise/acc-purchase-orders/{order}', [AccPurchaseOrderApiController::class, 'destroy'])->name('merchandise.acc-purchase-orders.destroy');
    Route::get('/merchandise/reports/{report}', [MerchandiseReportApiController::class, 'show'])->name('merchandise.reports.show');
    Route::get('/merchandise/tna-followup/orders', [TnaFollowupApiController::class, 'orders'])->name('merchandise.tna-followup.orders');
    Route::get('/merchandise/tna-followup/{order}', [TnaFollowupApiController::class, 'show'])->name('merchandise.tna-followup.show');
    Route::post('/merchandise/tna-followup/{order}/recalculate', [TnaFollowupApiController::class, 'recalculate'])->name('merchandise.tna-followup.recalculate');
    Route::put('/merchandise/tna-followup/{order}/milestones/{milestone}', [TnaFollowupApiController::class, 'update'])->name('merchandise.tna-followup.update');
    Route::get('/merchandise/import-bookings/orders', [ImportBookingApiController::class, 'orders'])->name('merchandise.import-bookings.orders');
    Route::get('/merchandise/import-bookings', [ImportBookingApiController::class, 'index'])->name('merchandise.import-bookings.index');
    Route::post('/merchandise/import-bookings', [ImportBookingApiController::class, 'store'])->name('merchandise.import-bookings.store');
    Route::post('/merchandise/import-bookings/{booking}/approve', [ImportBookingApiController::class, 'approve'])->name('merchandise.import-bookings.approve');
    Route::delete('/merchandise/import-bookings/{booking}', [ImportBookingApiController::class, 'destroy'])->name('merchandise.import-bookings.destroy');
    Route::get('/merchandise/fabric-requisitions/options', [FabricRequisitionApiController::class, 'options'])->name('merchandise.fabric-requisitions.options');
    Route::get('/merchandise/fabric-requisitions', [FabricRequisitionApiController::class, 'index'])->name('merchandise.fabric-requisitions.index');
    Route::post('/merchandise/fabric-requisitions', [FabricRequisitionApiController::class, 'store'])->name('merchandise.fabric-requisitions.store');
    Route::get('/merchandise/fabric-requisitions/{booking}', [FabricRequisitionApiController::class, 'show'])->name('merchandise.fabric-requisitions.show');
    Route::put('/merchandise/fabric-requisitions/{booking}', [FabricRequisitionApiController::class, 'update'])->name('merchandise.fabric-requisitions.update');
    Route::delete('/merchandise/fabric-requisitions/{booking}', [FabricRequisitionApiController::class, 'destroy'])->name('merchandise.fabric-requisitions.destroy');
    Route::get('/merchandise/fabric-booking-items', [FabricRequisitionApiController::class, 'bookings'])->name('merchandise.fabric-booking-items.index');
    Route::get('/merchandise/fabric-booking-items/{booking}', [FabricRequisitionApiController::class, 'items'])->name('merchandise.fabric-booking-items.show');
    Route::post('/merchandise/fabric-booking-items/{booking}', [FabricRequisitionApiController::class, 'addItem'])->name('merchandise.fabric-booking-items.add');
    Route::put('/merchandise/fabric-booking-items/{booking}', [FabricRequisitionApiController::class, 'saveItems'])->name('merchandise.fabric-booking-items.save');
    Route::get('/merchandise/yarn-specs/options', [YarnSpecApiController::class, 'options'])->name('merchandise.yarn-specs.options');
    Route::get('/merchandise/yarn-specs/confirmation', [YarnSpecApiController::class, 'confirmation'])->name('merchandise.yarn-specs.confirmation');
    Route::get('/merchandise/yarn-specs', [YarnSpecApiController::class, 'index'])->name('merchandise.yarn-specs.index');
    Route::post('/merchandise/yarn-specs', [YarnSpecApiController::class, 'store'])->name('merchandise.yarn-specs.store');
    Route::post('/merchandise/yarn-specs/{spec}/confirm', [YarnSpecApiController::class, 'confirm'])->name('merchandise.yarn-specs.confirm');
    Route::post('/merchandise/yarn-specs/{spec}/release', [YarnSpecApiController::class, 'release'])->name('merchandise.yarn-specs.release');
    Route::delete('/merchandise/yarn-specs/{spec}', [YarnSpecApiController::class, 'destroy'])->name('merchandise.yarn-specs.destroy');
    Route::get('/merchandise/fabric-bookings/approved', [FabricBookingApiController::class, 'approved'])->name('merchandise.fabric-bookings.approved');
    Route::post('/merchandise/fabric-bookings/{booking}/submit', [FabricBookingApiController::class, 'submit'])->name('merchandise.fabric-bookings.submit');
    Route::get('/merchandise/fabric-bookings/suggest', [FabricBookingApiController::class, 'suggest'])->name('merchandise.fabric-bookings.suggest');
    Route::get('/merchandise/fabric-bookings', [FabricBookingApiController::class, 'index'])->name('merchandise.fabric-bookings.index');
    Route::post('/merchandise/fabric-bookings', [FabricBookingApiController::class, 'store'])->name('merchandise.fabric-bookings.store');
    Route::get('/merchandise/fabric-bookings/{booking}', [FabricBookingApiController::class, 'show'])->name('merchandise.fabric-bookings.show');
    Route::put('/merchandise/fabric-bookings/{booking}', [FabricBookingApiController::class, 'update'])->name('merchandise.fabric-bookings.update');
    Route::delete('/merchandise/fabric-bookings/{booking}', [FabricBookingApiController::class, 'destroy'])->name('merchandise.fabric-bookings.destroy');

    // Costing & Budget → Order Budget Sheet: one budget per order.
    // Costing & Budget → Budget vs Actual Costing.
    Route::get('/merchandise/budgets', [BudgetApiController::class, 'index'])->name('merchandise.budgets.index');
    Route::get('/merchandise/budgets/{budget}/actuals/{group}', [BudgetApiController::class, 'actuals'])->whereIn('group', ['yarn_trims', 'fabric_process', 'cmt', 'export'])->name('merchandise.budgets.actuals');
    Route::put('/merchandise/budgets/{budget}/actuals/{group}', [BudgetApiController::class, 'saveActuals'])->whereIn('group', ['yarn_trims', 'fabric_process', 'cmt', 'export'])->name('merchandise.budgets.actuals.save');
    Route::get('/merchandise/budget-workflow', [BudgetApiController::class, 'workflow'])->name('merchandise.budgets.workflow');
    Route::post('/merchandise/budgets/{budget}/approval', [BudgetApiController::class, 'decide'])->name('merchandise.budgets.approval');
    Route::get('/merchandise/budgets/options', [BudgetApiController::class, 'options'])->name('merchandise.budgets.options');
    Route::get('/merchandise/orders/{order}/budget', [BudgetApiController::class, 'show'])->name('merchandise.budgets.show');
    Route::put('/merchandise/orders/{order}/budget', [BudgetApiController::class, 'save'])->name('merchandise.budgets.save');
    Route::get('/merchandise/orders/{order}/budget/sources', [BudgetApiController::class, 'sources'])->name('merchandise.budgets.sources');
    Route::get('/merchandise/orders/{order}/budget/item', [BudgetApiController::class, 'item'])->name('merchandise.budgets.item');

    Route::get('/merchandise/lookups', [MerchandiseLookupsApiController::class, 'index'])->name('merchandise.lookups');
    // Fabric Type Master → GARM Part List.
    Route::get('/merchandise/fabric-types/{fabricType}/garment-parts', [FabricTypePartsApiController::class, 'show'])->name('merchandise.fabric-types.parts');
    Route::put('/merchandise/fabric-types/{fabricType}/garment-parts', [FabricTypePartsApiController::class, 'save'])->name('merchandise.fabric-types.parts.save');

    // Buyer Office → Buyer Acc, Buyer Account → Link to Office, Buyer Account
    // Permission (and Marketing), Buyer Test Lab Mapping.
    Route::get('/merchandise/mappings/{kind}', [BuyerMappingApiController::class, 'index'])->name('merchandise.mappings.index');
    Route::get('/merchandise/mappings/{kind}/subjects', [BuyerMappingApiController::class, 'subjectOptions'])->name('merchandise.mappings.subjects');
    Route::get('/merchandise/mappings/{kind}/{subject}', [BuyerMappingApiController::class, 'show'])->name('merchandise.mappings.show');
    Route::put('/merchandise/mappings/{kind}/{subject}', [BuyerMappingApiController::class, 'save'])->name('merchandise.mappings.save');
    // Buyer Sample Mapping.
    Route::get('/merchandise/buyers/{buyer}/sample-types', [BuyerMappingApiController::class, 'samples'])->name('merchandise.buyers.samples');
    Route::put('/merchandise/buyers/{buyer}/sample-types', [BuyerMappingApiController::class, 'saveSamples'])->name('merchandise.buyers.samples.save');

    // Inquiry Rate Chart → Rate Chart, Rate History.
    Route::get('/merchandise/rate-chart', [RateChartApiController::class, 'offices'])->name('merchandise.rate-chart.offices');
    Route::get('/merchandise/rate-chart/{office}', [RateChartApiController::class, 'show'])->name('merchandise.rate-chart.show');
    Route::put('/merchandise/rate-chart/{office}', [RateChartApiController::class, 'save'])->name('merchandise.rate-chart.save');
    Route::get('/merchandise/rate-chart/{office}/history', [RateChartApiController::class, 'history'])->name('merchandise.rate-chart.history');

    // TNA Template Setup → Template Tasks, Copy.
    Route::get('/merchandise/tna-templates/{template}/tasks', [TnaApiController::class, 'templateTasks'])->name('merchandise.tna-templates.tasks');
    Route::put('/merchandise/tna-templates/{template}/tasks', [TnaApiController::class, 'saveTemplateTasks'])->name('merchandise.tna-templates.tasks.save');
    Route::post('/merchandise/tna-templates/{template}/copy', [TnaApiController::class, 'copyTemplate'])->name('merchandise.tna-templates.copy');

    // TNA Task Rights.
    Route::get('/merchandise/tna-rights', [TnaApiController::class, 'rights'])->name('merchandise.tna-rights.index');
    Route::delete('/merchandise/tna-rights/entries/{right}', [TnaApiController::class, 'removeRight'])->name('merchandise.tna-rights.remove');
    Route::get('/merchandise/tna-rights/users', [TnaApiController::class, 'users'])->name('merchandise.tna-rights.users');
    Route::get('/merchandise/tna-rights/{user}', [TnaApiController::class, 'userRights'])->name('merchandise.tna-rights.show');
    Route::put('/merchandise/tna-rights/{user}', [TnaApiController::class, 'saveUserRights'])->name('merchandise.tna-rights.save');
});
