<?php

declare(strict_types=1);

use App\Modules\SalesShipment\Http\Controllers\Api\AmendmentApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\BillApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\ChallanApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\ExportDocApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\MonthCloseApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\SalesOrderApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\SalesReportApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\SalesWatchApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\ShipmentConfirmApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\ShipmentInvoiceApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\ShipmentPaperApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\ShipmentPlanApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\SsSettingsApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\SubconDeliveryApiController;
use App\Modules\SalesShipment\Http\Controllers\Api\SubconSettleApiController;
use Illuminate\Support\Facades\Route;

/*
 * Sales & Shipment (AnnotechERP 21). Operation (21/01): the Sales & Shipment
 * Settings. Order & Delivery (21/02): sales orders and their S/C programmes,
 * the confirmation chain, amendments, delivery challans and their returns,
 * check bills, delivery completion and the month close — every paper by its
 * own number. Sub-Contract (21/03): S/C delivery chalans, receipts, returns
 * and reconciliations, and what is still pending with each party. General
 * (21/04): Shipment Planning. Shipment (21/05): booking, containers, document
 * handover, invoice, packing list, confirmation and closing. Export
 * Documentation (21/06): export documents, certificates, the final document
 * handover and shipment attachments.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('sales-shipment')->group(function (): void {
    Route::get('/settings', [SsSettingsApiController::class, 'show'])->name('salesshipment.settings');
    Route::put('/settings', [SsSettingsApiController::class, 'save'])->name('salesshipment.settings.save');

    Route::get('/options', [SalesOrderApiController::class, 'options'])->name('salesshipment.options');
    Route::get('/orders', [SalesOrderApiController::class, 'index'])->name('salesshipment.orders');
    Route::post('/orders', [SalesOrderApiController::class, 'save'])->name('salesshipment.orders.create');
    Route::get('/orders/{no}', [SalesOrderApiController::class, 'show'])->name('salesshipment.orders.show');
    Route::put('/orders/{no}', [SalesOrderApiController::class, 'save'])->name('salesshipment.orders.update');
    Route::get('/confirmations', [SalesOrderApiController::class, 'confirmations'])->name('salesshipment.confirmations');
    Route::post('/orders/{no}/{action}', [SalesOrderApiController::class, 'act'])->whereIn('action', ['submit', 'check', 'approve', 'confirm', 'return-to-draft', 'close-order'])->name('salesshipment.orders.act');
    Route::get('/completion', [SalesOrderApiController::class, 'completion'])->name('salesshipment.completion');
    Route::get('/sc-programmes', [SalesOrderApiController::class, 'programmes'])->name('salesshipment.sc');
    Route::post('/sc-programmes', [SalesOrderApiController::class, 'saveProgramme'])->name('salesshipment.sc.create');
    Route::get('/sc-programmes/source/{so}', [SalesOrderApiController::class, 'programmeSource'])->name('salesshipment.sc.source');
    Route::get('/sc-programmes/{no}', [SalesOrderApiController::class, 'programme'])->name('salesshipment.sc.show');
    Route::put('/sc-programmes/{no}', [SalesOrderApiController::class, 'saveProgramme'])->name('salesshipment.sc.update');

    Route::get('/amendments', [AmendmentApiController::class, 'index'])->name('salesshipment.amendments');
    Route::post('/amendments', [AmendmentApiController::class, 'save'])->name('salesshipment.amendments.create');
    Route::get('/amendments/orders', [AmendmentApiController::class, 'orders'])->name('salesshipment.amendments.orders');
    Route::get('/amendments/source/{so}', [AmendmentApiController::class, 'source'])->name('salesshipment.amendments.source');
    Route::get('/amendments/{no}', [AmendmentApiController::class, 'show'])->name('salesshipment.amendments.show');
    Route::put('/amendments/{no}', [AmendmentApiController::class, 'save'])->name('salesshipment.amendments.update');
    Route::post('/amendments/{no}/{action}', [AmendmentApiController::class, 'act'])->whereIn('action', ['submit', 'approve', 'reject', 'apply', 'cancel'])->name('salesshipment.amendments.act');

    Route::get('/challans', [ChallanApiController::class, 'index'])->name('salesshipment.challans');
    Route::post('/challans', [ChallanApiController::class, 'save'])->name('salesshipment.challans.create');
    Route::get('/challans/orders', [ChallanApiController::class, 'orders'])->name('salesshipment.challans.orders');
    Route::get('/challans/source/{so}', [ChallanApiController::class, 'source'])->name('salesshipment.challans.source');
    Route::get('/challans/{no}', [ChallanApiController::class, 'show'])->name('salesshipment.challans.show');
    Route::put('/challans/{no}', [ChallanApiController::class, 'save'])->name('salesshipment.challans.update');
    Route::delete('/challans/{no}', [ChallanApiController::class, 'destroy'])->name('salesshipment.challans.delete');
    Route::post('/challans/{no}/{action}', [ChallanApiController::class, 'act'])->whereIn('action', ['submit', 'deliver', 'cancel'])->name('salesshipment.challans.act');

    Route::get('/challan-returns', [ChallanApiController::class, 'returns'])->name('salesshipment.returns');
    Route::post('/challan-returns', [ChallanApiController::class, 'saveReturn'])->name('salesshipment.returns.create');
    Route::get('/challan-returns/challans', [ChallanApiController::class, 'delivered'])->name('salesshipment.returns.challans');
    Route::get('/challan-returns/source/{chalan}', [ChallanApiController::class, 'returnSource'])->name('salesshipment.returns.source');
    Route::get('/challan-returns/{no}', [ChallanApiController::class, 'returnShow'])->name('salesshipment.returns.show');
    Route::put('/challan-returns/{no}', [ChallanApiController::class, 'saveReturn'])->name('salesshipment.returns.update');
    Route::delete('/challan-returns/{no}', [ChallanApiController::class, 'returnDestroy'])->name('salesshipment.returns.delete');
    Route::post('/challan-returns/{no}/{action}', [ChallanApiController::class, 'returnAct'])->whereIn('action', ['confirm', 'cancel'])->name('salesshipment.returns.act');

    Route::get('/bills', [BillApiController::class, 'index'])->name('salesshipment.bills');
    Route::post('/bills', [BillApiController::class, 'save'])->name('salesshipment.bills.create');
    Route::get('/bills/challans', [BillApiController::class, 'challans'])->name('salesshipment.bills.challans');
    Route::get('/bills/source/{chalan}', [BillApiController::class, 'source'])->name('salesshipment.bills.source');
    Route::get('/bills/{no}', [BillApiController::class, 'show'])->name('salesshipment.bills.show');
    Route::put('/bills/{no}', [BillApiController::class, 'save'])->name('salesshipment.bills.update');
    Route::delete('/bills/{no}', [BillApiController::class, 'destroy'])->name('salesshipment.bills.delete');
    Route::post('/bills/{no}/{action}', [BillApiController::class, 'act'])->whereIn('action', ['verify', 'approve', 'pay', 'reject'])->name('salesshipment.bills.act');

    Route::get('/month-close', [MonthCloseApiController::class, 'index'])->name('salesshipment.close');
    Route::get('/month-close/exceptions', [MonthCloseApiController::class, 'exceptions'])->name('salesshipment.close.exceptions');
    Route::post('/month-close', [MonthCloseApiController::class, 'close'])->name('salesshipment.close.create');
    Route::delete('/month-close/{period}', [MonthCloseApiController::class, 'reopen'])->name('salesshipment.close.reopen');

    Route::get('/sc-deliveries', [SubconDeliveryApiController::class, 'index'])->name('salesshipment.scd');
    Route::post('/sc-deliveries', [SubconDeliveryApiController::class, 'save'])->name('salesshipment.scd.create');
    Route::get('/sc-deliveries/contracts', [SubconDeliveryApiController::class, 'contracts'])->name('salesshipment.scd.contracts');
    Route::get('/sc-deliveries/outstanding', [SubconDeliveryApiController::class, 'outstanding'])->name('salesshipment.scd.outstanding');
    Route::get('/sc-deliveries/source/{sc}', [SubconDeliveryApiController::class, 'source'])->name('salesshipment.scd.source');
    Route::get('/sc-deliveries/{no}', [SubconDeliveryApiController::class, 'show'])->name('salesshipment.scd.show');
    Route::put('/sc-deliveries/{no}', [SubconDeliveryApiController::class, 'save'])->name('salesshipment.scd.update');
    Route::delete('/sc-deliveries/{no}', [SubconDeliveryApiController::class, 'destroy'])->name('salesshipment.scd.delete');
    Route::post('/sc-deliveries/{no}/{action}', [SubconDeliveryApiController::class, 'act'])->whereIn('action', ['deliver', 'finalize', 'cancel'])->name('salesshipment.scd.act');
    Route::get('/sc-receipts', [SubconDeliveryApiController::class, 'receipts'])->name('salesshipment.scr');
    Route::post('/sc-receipts', [SubconDeliveryApiController::class, 'saveReceipt'])->name('salesshipment.scr.create');
    Route::get('/sc-receipts/source/{chalan}', [SubconDeliveryApiController::class, 'receiptSource'])->name('salesshipment.scr.source');
    Route::get('/sc-receipts/{no}', [SubconDeliveryApiController::class, 'receipt'])->name('salesshipment.scr.show');
    Route::put('/sc-receipts/{no}', [SubconDeliveryApiController::class, 'saveReceipt'])->name('salesshipment.scr.update');
    Route::delete('/sc-receipts/{no}', [SubconDeliveryApiController::class, 'receiptDestroy'])->name('salesshipment.scr.delete');
    Route::post('/sc-receipts/{no}/{action}', [SubconDeliveryApiController::class, 'receiptAct'])->whereIn('action', ['finalize', 'cancel'])->name('salesshipment.scr.act');
    Route::get('/sc-pending', [SubconDeliveryApiController::class, 'pending'])->name('salesshipment.scp');
    Route::get('/sc-returns', [SubconSettleApiController::class, 'returns'])->name('salesshipment.sct');
    Route::post('/sc-returns', [SubconSettleApiController::class, 'saveReturn'])->name('salesshipment.sct.create');
    Route::get('/sc-returns/receipts', [SubconSettleApiController::class, 'receipts'])->name('salesshipment.sct.receipts');
    Route::get('/sc-returns/source/{mrr}', [SubconSettleApiController::class, 'returnSource'])->name('salesshipment.sct.source');
    Route::get('/sc-returns/{no}', [SubconSettleApiController::class, 'returnShow'])->name('salesshipment.sct.show');
    Route::put('/sc-returns/{no}', [SubconSettleApiController::class, 'saveReturn'])->name('salesshipment.sct.update');
    Route::delete('/sc-returns/{no}', [SubconSettleApiController::class, 'returnDestroy'])->name('salesshipment.sct.delete');
    Route::post('/sc-returns/{no}/{action}', [SubconSettleApiController::class, 'returnAct'])->whereIn('action', ['confirm', 'cancel'])->name('salesshipment.sct.act');
    Route::get('/sc-reconciliations', [SubconSettleApiController::class, 'recons'])->name('salesshipment.scc');
    Route::post('/sc-reconciliations', [SubconSettleApiController::class, 'saveRecon'])->name('salesshipment.scc.create');
    Route::get('/sc-reconciliations/source/{chalan}', [SubconSettleApiController::class, 'reconSource'])->name('salesshipment.scc.source');
    Route::get('/sc-reconciliations/{no}', [SubconSettleApiController::class, 'recon'])->name('salesshipment.scc.show');
    Route::put('/sc-reconciliations/{no}', [SubconSettleApiController::class, 'saveRecon'])->name('salesshipment.scc.update');
    Route::delete('/sc-reconciliations/{no}', [SubconSettleApiController::class, 'reconDestroy'])->name('salesshipment.scc.delete');
    Route::post('/sc-reconciliations/{no}/{action}', [SubconSettleApiController::class, 'reconAct'])->whereIn('action', ['reconcile', 'reopen'])->name('salesshipment.scc.act');
    Route::get('/shipments', [ShipmentPlanApiController::class, 'index'])->name('salesshipment.shipments');
    Route::post('/shipments', [ShipmentPlanApiController::class, 'save'])->name('salesshipment.shipments.create');
    Route::get('/shipments/orders', [ShipmentPlanApiController::class, 'orders'])->name('salesshipment.shipments.orders');
    Route::get('/shipments/source/{so}', [ShipmentPlanApiController::class, 'source'])->name('salesshipment.shipments.source');
    Route::get('/shipments/{no}', [ShipmentPlanApiController::class, 'show'])->name('salesshipment.shipments.show');
    Route::put('/shipments/{no}', [ShipmentPlanApiController::class, 'save'])->name('salesshipment.shipments.update');
    Route::delete('/shipments/{no}', [ShipmentPlanApiController::class, 'destroy'])->name('salesshipment.shipments.delete');
    Route::post('/shipments/{no}/cancel', [ShipmentPlanApiController::class, 'cancel'])->name('salesshipment.shipments.cancel');
    Route::get('/shipment-papers/options', [ShipmentPaperApiController::class, 'options'])->name('salesshipment.papers.options');
    Route::get('/bookings', [ShipmentPaperApiController::class, 'bookings'])->name('salesshipment.bk');
    Route::post('/bookings', [ShipmentPaperApiController::class, 'saveBooking'])->name('salesshipment.bk.create');
    Route::put('/bookings/{no}', [ShipmentPaperApiController::class, 'saveBooking'])->name('salesshipment.bk.update');
    Route::delete('/bookings/{no}', [ShipmentPaperApiController::class, 'bookingDestroy'])->name('salesshipment.bk.delete');
    Route::post('/bookings/{no}/{action}', [ShipmentPaperApiController::class, 'bookingAct'])->whereIn('action', ['confirm', 'complete', 'cancel'])->name('salesshipment.bk.act');
    Route::get('/containers', [ShipmentPaperApiController::class, 'containers'])->name('salesshipment.ct');
    Route::post('/containers', [ShipmentPaperApiController::class, 'saveContainer'])->name('salesshipment.ct.create');
    Route::put('/containers/{shipmentNo}/{containerNo}', [ShipmentPaperApiController::class, 'saveContainer'])->name('salesshipment.ct.update');
    Route::delete('/containers/{shipmentNo}/{containerNo}', [ShipmentPaperApiController::class, 'containerDestroy'])->name('salesshipment.ct.delete');
    Route::get('/handovers', [ShipmentPaperApiController::class, 'handovers'])->name('salesshipment.dh');
    Route::post('/handovers', [ShipmentPaperApiController::class, 'saveHandover'])->name('salesshipment.dh.create');
    Route::put('/handovers/{no}', [ShipmentPaperApiController::class, 'saveHandover'])->name('salesshipment.dh.update');
    Route::delete('/handovers/{no}', [ShipmentPaperApiController::class, 'handoverDestroy'])->name('salesshipment.dh.delete');
    Route::post('/handovers/{no}/attachment', [ShipmentPaperApiController::class, 'handoverAttach'])->name('salesshipment.dh.attach');
    Route::post('/handovers/{no}/{action}', [ShipmentPaperApiController::class, 'handoverAct'])->whereIn('action', ['handed-over', 'received', 'returned'])->name('salesshipment.dh.act');
    Route::get('/invoice-shipments', [ShipmentInvoiceApiController::class, 'shipments'])->name('salesshipment.si.shipments');
    Route::get('/invoices', [ShipmentInvoiceApiController::class, 'invoices'])->name('salesshipment.si');
    Route::post('/invoices', [ShipmentInvoiceApiController::class, 'saveInvoice'])->name('salesshipment.si.create');
    Route::get('/invoices/source/{shipment}', [ShipmentInvoiceApiController::class, 'invoiceSource'])->name('salesshipment.si.source');
    Route::get('/invoices/{no}', [ShipmentInvoiceApiController::class, 'invoice'])->name('salesshipment.si.show');
    Route::put('/invoices/{no}', [ShipmentInvoiceApiController::class, 'saveInvoice'])->name('salesshipment.si.update');
    Route::delete('/invoices/{no}', [ShipmentInvoiceApiController::class, 'invoiceDestroy'])->name('salesshipment.si.delete');
    Route::post('/invoices/{no}/{action}', [ShipmentInvoiceApiController::class, 'invoiceAct'])->whereIn('action', ['finalize', 'cancel'])->name('salesshipment.si.act');
    Route::get('/packing-lists', [ShipmentInvoiceApiController::class, 'packings'])->name('salesshipment.pl');
    Route::post('/packing-lists', [ShipmentInvoiceApiController::class, 'savePacking'])->name('salesshipment.pl.create');
    Route::get('/packing-lists/source/{shipment}', [ShipmentInvoiceApiController::class, 'packingSource'])->name('salesshipment.pl.source');
    Route::get('/packing-lists/{no}', [ShipmentInvoiceApiController::class, 'packing'])->name('salesshipment.pl.show');
    Route::put('/packing-lists/{no}', [ShipmentInvoiceApiController::class, 'savePacking'])->name('salesshipment.pl.update');
    Route::delete('/packing-lists/{no}', [ShipmentInvoiceApiController::class, 'packingDestroy'])->name('salesshipment.pl.delete');
    Route::post('/packing-lists/{no}/{action}', [ShipmentInvoiceApiController::class, 'packingAct'])->whereIn('action', ['finalize', 'cancel'])->name('salesshipment.pl.act');
    Route::get('/confirmations-shipment', [ShipmentConfirmApiController::class, 'confirmations'])->name('salesshipment.cf');
    Route::get('/confirmations-shipment/{no}/check', [ShipmentConfirmApiController::class, 'check'])->name('salesshipment.cf.check');
    Route::post('/confirmations-shipment/{no}/confirm', [ShipmentConfirmApiController::class, 'confirm'])->name('salesshipment.cf.confirm');
    Route::post('/confirmations-shipment/{no}/unconfirm', [ShipmentConfirmApiController::class, 'unconfirm'])->name('salesshipment.cf.unconfirm');
    Route::get('/closings', [ShipmentConfirmApiController::class, 'closings'])->name('salesshipment.sc_close');
    Route::post('/closings/{no}/close', [ShipmentConfirmApiController::class, 'close'])->name('salesshipment.sc_close.close');
    Route::post('/closings/{no}/reopen', [ShipmentConfirmApiController::class, 'reopen'])->name('salesshipment.sc_close.reopen');
    Route::get('/export-documentation/options', [ExportDocApiController::class, 'options'])->name('salesshipment.ed.options');
    Route::get('/export-docs/{family}', [ExportDocApiController::class, 'docs'])->whereIn('family', ['export', 'coo', 'gsp', 'blawb'])->name('salesshipment.ed');
    Route::post('/export-docs/{family}', [ExportDocApiController::class, 'saveDoc'])->whereIn('family', ['export', 'coo', 'gsp', 'blawb'])->name('salesshipment.ed.create');
    Route::put('/export-docs/{family}/{id}', [ExportDocApiController::class, 'saveDoc'])->whereIn('family', ['export', 'coo', 'gsp', 'blawb'])->name('salesshipment.ed.update');
    Route::delete('/export-doc-items/{id}', [ExportDocApiController::class, 'docDestroy'])->name('salesshipment.ed.delete');
    Route::post('/export-doc-items/{id}/{action}', [ExportDocApiController::class, 'docAct'])->whereIn('action', ['received', 'submitted', 'approved'])->name('salesshipment.ed.act');
    Route::post('/attachments-of/{kind}/{id}', [ExportDocApiController::class, 'attach'])->whereIn('kind', ['export-docs', 'certificates'])->name('salesshipment.ed.attach');
    Route::get('/certificates', [ExportDocApiController::class, 'certificates'])->name('salesshipment.ce');
    Route::post('/certificates', [ExportDocApiController::class, 'saveCertificate'])->name('salesshipment.ce.create');
    Route::put('/certificates/{id}', [ExportDocApiController::class, 'saveCertificate'])->name('salesshipment.ce.update');
    Route::delete('/certificates/{id}', [ExportDocApiController::class, 'certificateDestroy'])->name('salesshipment.ce.delete');
    Route::get('/shipment-attachments', [ExportDocApiController::class, 'attachments'])->name('salesshipment.sa');
    Route::post('/shipment-attachments', [ExportDocApiController::class, 'saveAttachment'])->name('salesshipment.sa.create');
    Route::post('/shipment-attachments/{id}', [ExportDocApiController::class, 'saveAttachment'])->name('salesshipment.sa.update');
    Route::delete('/shipment-attachments/{id}', [ExportDocApiController::class, 'attachmentDestroy'])->name('salesshipment.sa.delete');
    Route::get('/final-handovers', [ShipmentPaperApiController::class, 'finalHandovers'])->name('salesshipment.fh');
    Route::post('/final-handovers', [ShipmentPaperApiController::class, 'saveFinalHandover'])->name('salesshipment.fh.create');
    Route::put('/final-handovers/{no}', [ShipmentPaperApiController::class, 'saveFinalHandover'])->name('salesshipment.fh.update');

    // Report (AnnotechERP 21/07): the report set, Order-wise Tracking, Notifications & Alerts.
    Route::get('/reports/options', [SalesReportApiController::class, 'options'])->name('salesshipment.sr.options');
    Route::post('/reports/run', [SalesReportApiController::class, 'run'])->name('salesshipment.sr.run');
    Route::get('/order-tracking', [SalesWatchApiController::class, 'trackOrders'])->name('salesshipment.ot.orders');
    Route::get('/order-tracking/{no}', [SalesWatchApiController::class, 'track'])->name('salesshipment.ot.track');
    Route::get('/alerts', [SalesWatchApiController::class, 'alerts'])->name('salesshipment.al');
});
