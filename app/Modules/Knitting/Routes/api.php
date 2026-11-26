<?php

declare(strict_types=1);

use App\Modules\Knitting\Http\Controllers\Api\KnitCcApiController as KnitCc;
use App\Modules\Knitting\Http\Controllers\Api\KnitCcYarnApiController as KnitCcYarn;
use App\Modules\Knitting\Http\Controllers\Api\KnitDirectBookingApiController as KnitDfb;
use App\Modules\Knitting\Http\Controllers\Api\KnitGreyDocApiController as GreyDoc;
use App\Modules\Knitting\Http\Controllers\Api\KnitGreyMoveApiController as GreyMove;
use App\Modules\Knitting\Http\Controllers\Api\KnitGreyStoreApiController as GreyStore;
use App\Modules\Knitting\Http\Controllers\Api\KnitInspectionApiController as KnitQc;
use App\Modules\Knitting\Http\Controllers\Api\KnitLoanReceiptApiController as LoanReceipt;
use App\Modules\Knitting\Http\Controllers\Api\KnitLoanReturnApiController as LoanReturn;
use App\Modules\Knitting\Http\Controllers\Api\KnitMachineLoadApiController as KnitMl;
use App\Modules\Knitting\Http\Controllers\Api\KnitNeedleFloorApiController as NeedleFloor;
use App\Modules\Knitting\Http\Controllers\Api\KnitNeedleStoreApiController as NeedleStore;
use App\Modules\Knitting\Http\Controllers\Api\KnitOilStoreApiController as OilStore;
use App\Modules\Knitting\Http\Controllers\Api\KnitReportApiController as KnitReports;
use App\Modules\Knitting\Http\Controllers\Api\KnitProdOrderApiController as KnitPo;
use App\Modules\Knitting\Http\Controllers\Api\KnitRollApiController as KnitRoll;
use App\Modules\Knitting\Http\Controllers\Api\KnitSamplePlanApiController as KnitSkp;
use App\Modules\Knitting\Http\Controllers\Api\KnitScBillApiController as ScBill;
use App\Modules\Knitting\Http\Controllers\Api\KnitScGreyApiController as ScGrey;
use App\Modules\Knitting\Http\Controllers\Api\KnitScProgramApiController as KnitScp;
use App\Modules\Knitting\Http\Controllers\Api\KnitScReceiptApiController as KnitScr;
use App\Modules\Knitting\Http\Controllers\Api\KnitScYarnChallanApiController as KnitSyc;
use App\Modules\Knitting\Http\Controllers\Api\KnitStationApiController as KnitSt;
use App\Modules\Knitting\Http\Controllers\Api\KnittingApiController;
use App\Modules\Knitting\Http\Controllers\Api\KnitTwistApiController as KnitTw;
use App\Modules\Knitting\Http\Controllers\Api\KnitYarnReplaceReqApiController as KnitYrr;
use App\Modules\Knitting\Http\Controllers\Api\KnitYarnTestApiController as KnitYt;
use App\Modules\Knitting\Http\Controllers\Api\KnitYarnTestOrderApiController as KnitYto;
use App\Modules\Knitting\Http\Controllers\Api\KnitYarnTransferApiController as YarnTr;
use App\Modules\Knitting\Http\Controllers\Api\KnitYdProgramApiController as KnitYd;
use App\Modules\Knitting\Http\Controllers\Api\KnitYdStoreApiController as KnitYds;
use App\Modules\Knitting\Http\Controllers\Api\YarnComparisonApiController as YarnCs;
use App\Modules\Knitting\Http\Controllers\Api\YarnIssueApiController as YarnIssue;
use App\Modules\Knitting\Http\Controllers\Api\YarnOutwardApiController as YarnOut;
use App\Modules\Knitting\Http\Controllers\Api\YarnPurchaseOrderApiController as YarnPo;
use App\Modules\Knitting\Http\Controllers\Api\YarnReceiptApiController as YarnMrr;
use App\Modules\Knitting\Http\Controllers\Api\YarnRequisitionApiController as YarnReq;
use App\Modules\Knitting\Http\Controllers\Api\YarnStoreApiController as YarnStore;
use Illuminate\Support\Facades\Route;

/*
 * Knit Production (AnnotechERP 15). The plain lists are screen-kit resources
 * under /erp/{resource}; the reference lists, user and office mapping and a
 * machine's postings live here. Sub-Contract Party Rate is Dye House's
 * quotation, under /dyehouse/sc-quotations.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/knitting/lookups', [KnittingApiController::class, 'lookups'])->name('knitting.lookups');

    Route::get('/knitting/ref-lists', [KnittingApiController::class, 'refLists'])->name('knitting.ref-lists');
    Route::get('/knitting/ref-lists/{list}', [KnittingApiController::class, 'refEntries'])->name('knitting.ref-entries');
    Route::put('/knitting/ref-lists/{list}', [KnittingApiController::class, 'saveRefEntries'])->name('knitting.ref-entries.save');

    Route::get('/knitting/user-offices', [KnittingApiController::class, 'userOffices'])->name('knitting.user-offices');
    Route::get('/knitting/office-users', [KnittingApiController::class, 'officeUsers'])->name('knitting.office-users');
    Route::get('/knitting/office-users/{user}', [KnittingApiController::class, 'officesOf'])->name('knitting.office-users.offices');
    Route::put('/knitting/office-users/{user}', [KnittingApiController::class, 'saveOfficesOf'])->name('knitting.office-users.save');

    Route::get('/knitting/posting-machines', [KnittingApiController::class, 'postingMachines'])->name('knitting.posting-machines');
    Route::get('/knitting/posting-machines/{machine}', [KnittingApiController::class, 'postingsOf'])->name('knitting.posting-machines.postings');
    Route::put('/knitting/posting-machines/{machine}', [KnittingApiController::class, 'savePostingsOf'])->name('knitting.posting-machines.save');

    // Yarn Procurement (15/02): a requisition, comparison or order by its number (YR-…, YCS-…, YPO-…).
    Route::get('/knitting/yarn/requisitions/options', [YarnReq::class, 'options'])->name('knitting.yarn.requisitions.options');
    Route::get('/knitting/yarn/approvals', [YarnReq::class, 'approvals'])->name('knitting.yarn.approvals');
    Route::post('/knitting/yarn/approvals/decide', [YarnReq::class, 'decide'])->name('knitting.yarn.approvals.decide');
    Route::get('/knitting/yarn/requisitions', [YarnReq::class, 'index'])->name('knitting.yarn.requisitions.index');
    Route::post('/knitting/yarn/requisitions', [YarnReq::class, 'store'])->name('knitting.yarn.requisitions.store');
    Route::get('/knitting/yarn/requisitions/{requisition}', [YarnReq::class, 'show'])->name('knitting.yarn.requisitions.show');
    Route::put('/knitting/yarn/requisitions/{requisition}', [YarnReq::class, 'update'])->name('knitting.yarn.requisitions.update');
    Route::delete('/knitting/yarn/requisitions/{requisition}', [YarnReq::class, 'destroy'])->name('knitting.yarn.requisitions.destroy');
    Route::post('/knitting/yarn/requisitions/{requisition}/{action}', [YarnReq::class, 'transition'])->whereIn('action', ['submit', 'cancel', 'close'])->name('knitting.yarn.requisitions.transition');

    Route::get('/knitting/yarn/comparisons/options', [YarnCs::class, 'options'])->name('knitting.yarn.comparisons.options');
    Route::get('/knitting/yarn/comparisons/source/{requisition}', [YarnCs::class, 'source'])->name('knitting.yarn.comparisons.source');
    Route::get('/knitting/yarn/comparisons', [YarnCs::class, 'index'])->name('knitting.yarn.comparisons.index');
    Route::post('/knitting/yarn/comparisons', [YarnCs::class, 'store'])->name('knitting.yarn.comparisons.store');
    Route::get('/knitting/yarn/comparisons/{comparison}', [YarnCs::class, 'show'])->name('knitting.yarn.comparisons.show');
    Route::put('/knitting/yarn/comparisons/{comparison}', [YarnCs::class, 'update'])->name('knitting.yarn.comparisons.update');
    Route::delete('/knitting/yarn/comparisons/{comparison}', [YarnCs::class, 'destroy'])->name('knitting.yarn.comparisons.destroy');
    Route::post('/knitting/yarn/comparisons/{comparison}/{action}', [YarnCs::class, 'transition'])->whereIn('action', ['finalize', 'cancel'])->name('knitting.yarn.comparisons.transition');

    Route::get('/knitting/yarn/orders/options', [YarnPo::class, 'options'])->name('knitting.yarn.orders.options');
    Route::get('/knitting/yarn/orders/source', [YarnPo::class, 'source'])->name('knitting.yarn.orders.source');
    Route::get('/knitting/yarn/orders', [YarnPo::class, 'index'])->name('knitting.yarn.orders.index');
    Route::post('/knitting/yarn/orders', [YarnPo::class, 'store'])->name('knitting.yarn.orders.store');
    Route::get('/knitting/yarn/orders/{order}', [YarnPo::class, 'show'])->name('knitting.yarn.orders.show');
    Route::put('/knitting/yarn/orders/{order}', [YarnPo::class, 'update'])->name('knitting.yarn.orders.update');
    Route::delete('/knitting/yarn/orders/{order}', [YarnPo::class, 'destroy'])->name('knitting.yarn.orders.destroy');
    Route::post('/knitting/yarn/orders/{order}/{action}', [YarnPo::class, 'transition'])->whereIn('action', ['place', 'close', 'cancel'])->name('knitting.yarn.orders.transition');

    // Yarn Store (15/03): documents by number (YOB-, YMRR-, YRC-, YADJ-), a store by its code, a month as 2026-12.
    Route::get('/knitting/ystore/options', [YarnStore::class, 'options'])->name('knitting.ystore.options');
    Route::get('/knitting/ystore/lots', [YarnStore::class, 'lots'])->name('knitting.ystore.lots');
    Route::get('/knitting/ystore/obs', [YarnStore::class, 'obIndex'])->name('knitting.ystore.obs.index');
    Route::post('/knitting/ystore/obs', [YarnStore::class, 'obSave'])->name('knitting.ystore.obs.store');
    Route::get('/knitting/ystore/obs/{ob}', [YarnStore::class, 'obShow'])->name('knitting.ystore.obs.show');
    Route::put('/knitting/ystore/obs/{ob}', [YarnStore::class, 'obSave'])->name('knitting.ystore.obs.update');
    Route::delete('/knitting/ystore/obs/{ob}', [YarnStore::class, 'obDestroy'])->name('knitting.ystore.obs.destroy');
    Route::post('/knitting/ystore/obs/{ob}/finalize', [YarnStore::class, 'obFinalize'])->name('knitting.ystore.obs.finalize');
    Route::get('/knitting/ystore/adjustments', [YarnStore::class, 'adjIndex'])->name('knitting.ystore.adjustments.index');
    Route::post('/knitting/ystore/adjustments', [YarnStore::class, 'adjStore'])->name('knitting.ystore.adjustments.store');
    Route::get('/knitting/ystore/adjustments/{adj}', [YarnStore::class, 'adjShow'])->name('knitting.ystore.adjustments.show');
    Route::post('/knitting/ystore/adjustments/{adj}/cancel', [YarnStore::class, 'adjCancel'])->name('knitting.ystore.adjustments.cancel');
    Route::get('/knitting/ystore/racks/board', [YarnStore::class, 'rackBoard'])->name('knitting.ystore.racks.board');
    Route::get('/knitting/ystore/racks/pending', [YarnStore::class, 'rackPending'])->name('knitting.ystore.racks.pending');
    Route::post('/knitting/ystore/racks', [YarnStore::class, 'rackAssign'])->name('knitting.ystore.racks.assign');
    Route::get('/knitting/ystore/period', [YarnStore::class, 'periodGrid'])->name('knitting.ystore.period');
    Route::put('/knitting/ystore/period', [YarnStore::class, 'periodSave'])->name('knitting.ystore.period.save');
    Route::post('/knitting/ystore/period/finalize', [YarnStore::class, 'periodFinalize'])->name('knitting.ystore.period.finalize');
    Route::get('/knitting/ystore/closes', [YarnStore::class, 'closes'])->name('knitting.ystore.closes');
    Route::get('/knitting/ystore/closes/exceptions', [YarnStore::class, 'exceptions'])->name('knitting.ystore.closes.exceptions');
    Route::post('/knitting/ystore/closes', [YarnStore::class, 'close'])->name('knitting.ystore.closes.close');

    Route::get('/knitting/ystore/receipts/options', [YarnMrr::class, 'options'])->name('knitting.ystore.receipts.options');
    Route::get('/knitting/ystore/receipts/po/{po}', [YarnMrr::class, 'poLines'])->name('knitting.ystore.receipts.po');
    Route::get('/knitting/ystore/receipts', [YarnMrr::class, 'index'])->name('knitting.ystore.receipts.index');
    Route::post('/knitting/ystore/receipts', [YarnMrr::class, 'store'])->name('knitting.ystore.receipts.store');
    Route::get('/knitting/ystore/receipts/{receipt}', [YarnMrr::class, 'show'])->name('knitting.ystore.receipts.show');
    Route::put('/knitting/ystore/receipts/{receipt}', [YarnMrr::class, 'update'])->name('knitting.ystore.receipts.update');
    Route::post('/knitting/ystore/receipts/{receipt}/return', [YarnMrr::class, 'raiseReturn'])->name('knitting.ystore.receipts.return');
    Route::post('/knitting/ystore/receipts/{receipt}/{action}', [YarnMrr::class, 'transition'])->whereIn('action', ['verify', 'audit', 'approve', 'cancel'])->name('knitting.ystore.receipts.transition');
    Route::get('/knitting/ystore/returns', [YarnMrr::class, 'returns'])->name('knitting.ystore.returns.index');
    Route::get('/knitting/ystore/returns/{return}', [YarnMrr::class, 'showReturn'])->name('knitting.ystore.returns.show');
    Route::put('/knitting/ystore/returns/{return}', [YarnMrr::class, 'updateReturn'])->name('knitting.ystore.returns.update');
    Route::delete('/knitting/ystore/returns/{return}', [YarnMrr::class, 'destroyReturn'])->name('knitting.ystore.returns.destroy');

    Route::get('/knitting/prod/options', [KnitPo::class, 'options'])->name('knitting.prod.options');
    Route::get('/knitting/prod/orders', [KnitPo::class, 'index'])->name('knitting.prod.orders.index');
    Route::post('/knitting/prod/orders', [KnitPo::class, 'store'])->name('knitting.prod.orders.store');
    Route::get('/knitting/prod/orders/{po}', [KnitPo::class, 'show'])->name('knitting.prod.orders.show');
    Route::put('/knitting/prod/orders/{po}', [KnitPo::class, 'update'])->name('knitting.prod.orders.update');
    Route::post('/knitting/prod/orders/{po}/{action}', [KnitPo::class, 'transition'])->whereIn('action', ['complete', 'hold', 'resume'])->name('knitting.prod.orders.transition');
    Route::get('/knitting/prod/orders/{po}/programs', [KnitPo::class, 'programs'])->name('knitting.prod.programs.index');
    Route::post('/knitting/prod/orders/{po}/programs', [KnitPo::class, 'release'])->name('knitting.prod.programs.release');
    Route::put('/knitting/prod/orders/{po}/programs', [KnitPo::class, 'savePrograms'])->name('knitting.prod.programs.save');
    Route::delete('/knitting/prod/programs/{kp}', [KnitPo::class, 'deleteProgram'])->name('knitting.prod.programs.destroy');
    Route::get('/knitting/prod/orders/{po}/cards', [KnitPo::class, 'cards'])->name('knitting.prod.cards.index');
    Route::post('/knitting/prod/orders/{po}/cards', [KnitPo::class, 'cut'])->name('knitting.prod.cards.cut');
    Route::put('/knitting/prod/orders/{po}/cards', [KnitPo::class, 'saveCards'])->name('knitting.prod.cards.save');
    Route::delete('/knitting/prod/cards/{kc}', [KnitPo::class, 'deleteCard'])->name('knitting.prod.cards.destroy');

    Route::get('/knitting/prod/rolls/options', [KnitRoll::class, 'options'])->name('knitting.prod.rolls.options');
    Route::get('/knitting/prod/rolls', [KnitRoll::class, 'index'])->name('knitting.prod.rolls.index');
    Route::post('/knitting/prod/rolls/{action}', [KnitRoll::class, 'rollAction'])->whereIn('action', ['pass', 'reject', 'grey'])->name('knitting.prod.rolls.action');
    Route::get('/knitting/prod/rolls/card/{kc}', [KnitRoll::class, 'card'])->name('knitting.prod.rolls.card');
    Route::post('/knitting/prod/roll-sheets', [KnitRoll::class, 'saveSheet'])->name('knitting.prod.roll_sheets.store');
    Route::get('/knitting/prod/roll-sheets/{sheet}', [KnitRoll::class, 'showSheet'])->name('knitting.prod.roll_sheets.show');
    Route::put('/knitting/prod/roll-sheets/{sheet}', [KnitRoll::class, 'saveSheet'])->name('knitting.prod.roll_sheets.update');
    Route::get('/knitting/prod/output', [KnitRoll::class, 'output'])->name('knitting.prod.output');

    Route::get('/knitting/prod/inspections/options', [KnitQc::class, 'options'])->name('knitting.prod.inspections.options');
    Route::get('/knitting/prod/inspections', [KnitQc::class, 'index'])->name('knitting.prod.inspections.index');
    Route::post('/knitting/prod/inspections', [KnitQc::class, 'save'])->name('knitting.prod.inspections.store');
    Route::get('/knitting/prod/inspections/{qci}', [KnitQc::class, 'show'])->name('knitting.prod.inspections.show');
    Route::put('/knitting/prod/inspections/{qci}', [KnitQc::class, 'save'])->name('knitting.prod.inspections.update');
    Route::post('/knitting/prod/inspections/{qci}/{action}', [KnitQc::class, 'decide'])->whereIn('action', ['pass', 'hold', 'reject'])->name('knitting.prod.inspections.decide');

    // Sub-contract knitting: programmes placed with a party, and programmes received from one.
    Route::get('/knitting/prod/sc/options', [KnitScp::class, 'options'])->name('knitting.prod.sc.options');
    Route::get('/knitting/prod/sc-receipts', [KnitScr::class, 'index'])->name('knitting.prod.sc_receipts.index');
    Route::post('/knitting/prod/sc-receipts', [KnitScr::class, 'save'])->name('knitting.prod.sc_receipts.store');
    Route::get('/knitting/prod/sc-receipts/store-yarns', [KnitScr::class, 'storeYarns'])->name('knitting.prod.sc_receipts.store_yarns');
    Route::get('/knitting/prod/sc-receipts/{no}', [KnitScr::class, 'show'])->name('knitting.prod.sc_receipts.show');
    Route::put('/knitting/prod/sc-receipts/{no}', [KnitScr::class, 'save'])->name('knitting.prod.sc_receipts.update');
    Route::post('/knitting/prod/sc-receipts/{no}/{action}', [KnitScr::class, 'transition'])->whereIn('action', ['confirm', 'start', 'close', 'cancel'])->name('knitting.prod.sc_receipts.transition');
    Route::get('/knitting/prod/sc/{kind}', [KnitScp::class, 'index'])->whereIn('kind', ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'])->name('knitting.prod.sc.index');
    Route::post('/knitting/prod/sc/{kind}', [KnitScp::class, 'save'])->whereIn('kind', ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'])->name('knitting.prod.sc.store');
    Route::get('/knitting/prod/sc/{kind}/{no}', [KnitScp::class, 'show'])->whereIn('kind', ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'])->name('knitting.prod.sc.show');
    Route::put('/knitting/prod/sc/{kind}/{no}', [KnitScp::class, 'save'])->whereIn('kind', ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'])->name('knitting.prod.sc.update');
    Route::delete('/knitting/prod/sc/{kind}/{no}', [KnitScp::class, 'destroy'])->whereIn('kind', ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'])->name('knitting.prod.sc.destroy');
    Route::post('/knitting/prod/sc/{kind}/{no}/{action}', [KnitScp::class, 'transition'])->whereIn('kind', ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'])->whereIn('action', ['submit', 'approve', 'reject'])->name('knitting.prod.sc.transition');

    // The yarn test chain: test orders, the lab's tests, approvals without test, and the register.
    Route::get('/knitting/prod/yarn-test/options', [KnitYto::class, 'options'])->name('knitting.prod.yarn_test.options');
    Route::get('/knitting/prod/yarn-test/orders', [KnitYto::class, 'index'])->name('knitting.prod.yarn_test.orders.index');
    Route::post('/knitting/prod/yarn-test/orders', [KnitYto::class, 'save'])->name('knitting.prod.yarn_test.orders.store');
    Route::get('/knitting/prod/yarn-test/orders/{no}', [KnitYto::class, 'show'])->name('knitting.prod.yarn_test.orders.show');
    Route::put('/knitting/prod/yarn-test/orders/{no}', [KnitYto::class, 'save'])->name('knitting.prod.yarn_test.orders.update');
    Route::post('/knitting/prod/yarn-test/orders/{no}/{action}', [KnitYto::class, 'transition'])->whereIn('action', ['issue', 'cancel'])->name('knitting.prod.yarn_test.orders.transition');
    Route::post('/knitting/prod/yarn-test/tests', [KnitYt::class, 'save'])->name('knitting.prod.yarn_test.tests.store');
    Route::get('/knitting/prod/yarn-test/tests/{no}', [KnitYt::class, 'show'])->name('knitting.prod.yarn_test.tests.show');
    Route::put('/knitting/prod/yarn-test/tests/{no}', [KnitYt::class, 'save'])->name('knitting.prod.yarn_test.tests.update');
    Route::get('/knitting/prod/yarn-test/results', [KnitYt::class, 'results'])->name('knitting.prod.yarn_test.results');
    Route::get('/knitting/prod/yarn-test/waiver-lots', [KnitYt::class, 'waiverLots'])->name('knitting.prod.yarn_test.waiver_lots');
    Route::post('/knitting/prod/yarn-test/waivers', [KnitYt::class, 'waive'])->name('knitting.prod.yarn_test.waivers');

    // Supplier Yarn Replacement Indent: claims on spinners.
    Route::get('/knitting/prod/replace-reqs/options', [KnitYrr::class, 'options'])->name('knitting.prod.replace_reqs.options');
    Route::get('/knitting/prod/replace-reqs', [KnitYrr::class, 'index'])->name('knitting.prod.replace_reqs.index');
    Route::post('/knitting/prod/replace-reqs', [KnitYrr::class, 'save'])->name('knitting.prod.replace_reqs.store');
    Route::get('/knitting/prod/replace-reqs/{no}', [KnitYrr::class, 'show'])->name('knitting.prod.replace_reqs.show');
    Route::put('/knitting/prod/replace-reqs/{no}', [KnitYrr::class, 'save'])->name('knitting.prod.replace_reqs.update');
    Route::post('/knitting/prod/replace-reqs/{no}/{action}', [KnitYrr::class, 'transition'])->whereIn('action', ['submit', 'issue', 'cancel'])->name('knitting.prod.replace_reqs.transition');

    // Sample knit plans (Solid, Y/D) and direct fabric bookings.
    Route::get('/knitting/prod/sample-plans/options', [KnitSkp::class, 'options'])->name('knitting.prod.sample_plans.options');
    Route::get('/knitting/prod/sample-plans/{kind}', [KnitSkp::class, 'index'])->whereIn('kind', ['SOLID', 'YD'])->name('knitting.prod.sample_plans.index');
    Route::post('/knitting/prod/sample-plans/{kind}', [KnitSkp::class, 'save'])->whereIn('kind', ['SOLID', 'YD'])->name('knitting.prod.sample_plans.store');
    Route::get('/knitting/prod/sample-plans/{kind}/{no}', [KnitSkp::class, 'show'])->whereIn('kind', ['SOLID', 'YD'])->name('knitting.prod.sample_plans.show');
    Route::put('/knitting/prod/sample-plans/{kind}/{no}', [KnitSkp::class, 'save'])->whereIn('kind', ['SOLID', 'YD'])->name('knitting.prod.sample_plans.update');
    Route::post('/knitting/prod/sample-plans/{kind}/{no}/{action}', [KnitSkp::class, 'transition'])->whereIn('kind', ['SOLID', 'YD'])->whereIn('action', ['submit', 'cancel'])->name('knitting.prod.sample_plans.transition');
    Route::get('/knitting/prod/direct-bookings/options', [KnitDfb::class, 'options'])->name('knitting.prod.direct_bookings.options');
    Route::get('/knitting/prod/direct-bookings', [KnitDfb::class, 'index'])->name('knitting.prod.direct_bookings.index');
    Route::post('/knitting/prod/direct-bookings', [KnitDfb::class, 'save'])->name('knitting.prod.direct_bookings.store');
    Route::get('/knitting/prod/direct-bookings/{no}', [KnitDfb::class, 'show'])->name('knitting.prod.direct_bookings.show');
    Route::put('/knitting/prod/direct-bookings/{no}', [KnitDfb::class, 'save'])->name('knitting.prod.direct_bookings.update');
    Route::post('/knitting/prod/direct-bookings/{no}/{action}', [KnitDfb::class, 'transition'])->whereIn('action', ['submit', 'approve', 'cancel'])->name('knitting.prod.direct_bookings.transition');

    // Yarn Twisting Entry: programmes, receive challans, bills.
    Route::get('/knitting/prod/twist/options', [KnitTw::class, 'options'])->name('knitting.prod.twist.options');
    Route::get('/knitting/prod/twist/programs', [KnitTw::class, 'programs'])->name('knitting.prod.twist.programs.index');
    Route::post('/knitting/prod/twist/programs', [KnitTw::class, 'saveProgram'])->name('knitting.prod.twist.programs.store');
    Route::get('/knitting/prod/twist/programs/{no}', [KnitTw::class, 'showProgram'])->name('knitting.prod.twist.programs.show');
    Route::put('/knitting/prod/twist/programs/{no}', [KnitTw::class, 'saveProgram'])->name('knitting.prod.twist.programs.update');
    Route::get('/knitting/prod/twist/receives', [KnitTw::class, 'receives'])->name('knitting.prod.twist.receives.index');
    Route::post('/knitting/prod/twist/receives', [KnitTw::class, 'saveReceive'])->name('knitting.prod.twist.receives.store');
    Route::get('/knitting/prod/twist/receives/{no}', [KnitTw::class, 'showReceive'])->name('knitting.prod.twist.receives.show');
    Route::put('/knitting/prod/twist/receives/{no}', [KnitTw::class, 'saveReceive'])->name('knitting.prod.twist.receives.update');
    Route::get('/knitting/prod/twist/bills', [KnitTw::class, 'bills'])->name('knitting.prod.twist.bills.index');
    Route::post('/knitting/prod/twist/bills', [KnitTw::class, 'saveBill'])->name('knitting.prod.twist.bills.store');
    Route::get('/knitting/prod/twist/bills/{no}', [KnitTw::class, 'showBill'])->name('knitting.prod.twist.bills.show');
    Route::put('/knitting/prod/twist/bills/{no}', [KnitTw::class, 'saveBill'])->name('knitting.prod.twist.bills.update');

    // Knit Machine Loading Plan and the operator's stations.
    Route::get('/knitting/prod/machine-loads/options', [KnitMl::class, 'options'])->name('knitting.prod.machine_loads.options');
    Route::get('/knitting/prod/machine-loads/grid', [KnitMl::class, 'grid'])->name('knitting.prod.machine_loads.grid');
    Route::get('/knitting/prod/machine-loads', [KnitMl::class, 'index'])->name('knitting.prod.machine_loads.index');
    Route::post('/knitting/prod/machine-loads', [KnitMl::class, 'plan'])->name('knitting.prod.machine_loads.plan');
    Route::post('/knitting/prod/machine-loads/clear', [KnitMl::class, 'clear'])->name('knitting.prod.machine_loads.clear');
    Route::get('/knitting/prod/stations/options', [KnitSt::class, 'options'])->name('knitting.prod.stations.options');
    Route::post('/knitting/prod/stations', [KnitSt::class, 'save'])->name('knitting.prod.stations.store');
    Route::get('/knitting/prod/stations/{code}', [KnitSt::class, 'show'])->name('knitting.prod.stations.show');
    Route::put('/knitting/prod/stations/{code}', [KnitSt::class, 'save'])->name('knitting.prod.stations.update');
    Route::post('/knitting/prod/stations/{code}/rolls', [KnitSt::class, 'roll'])->name('knitting.prod.stations.roll');

    // Yarn dyeing: the Y/D programmes (15/04) and the dye house's side of them in the yarn store (15/03).
    Route::get('/knitting/prod/yd/options', [KnitYd::class, 'options'])->name('knitting.prod.yd.options');
    Route::get('/knitting/prod/yd/programs', [KnitYd::class, 'index'])->name('knitting.prod.yd.programs.index');
    Route::post('/knitting/prod/yd/programs', [KnitYd::class, 'save'])->name('knitting.prod.yd.programs.store');
    Route::get('/knitting/prod/yd/programs/{no}', [KnitYd::class, 'show'])->name('knitting.prod.yd.programs.show');
    Route::put('/knitting/prod/yd/programs/{no}', [KnitYd::class, 'save'])->name('knitting.prod.yd.programs.update');
    Route::post('/knitting/prod/yd/revisions', [KnitYd::class, 'revise'])->name('knitting.prod.yd.revisions.store');
    Route::get('/knitting/ystore/yd/options', [KnitYd::class, 'options'])->name('knitting.ystore.yd.options');
    Route::get('/knitting/ystore/yd/programs/{no}', [KnitYd::class, 'show'])->name('knitting.ystore.yd.programs.show');
    Route::get('/knitting/ystore/yd/statement', [KnitYd::class, 'statement'])->name('knitting.ystore.yd.statement');
    Route::get('/knitting/ystore/yd/receipts', [KnitYds::class, 'receipts'])->name('knitting.ystore.yd.receipts.index');
    Route::post('/knitting/ystore/yd/receipts', [KnitYds::class, 'saveReceipt'])->name('knitting.ystore.yd.receipts.store');
    Route::get('/knitting/ystore/yd/receipts/{no}', [KnitYds::class, 'showReceipt'])->name('knitting.ystore.yd.receipts.show');
    Route::put('/knitting/ystore/yd/receipts/{no}', [KnitYds::class, 'saveReceipt'])->name('knitting.ystore.yd.receipts.update');
    Route::post('/knitting/ystore/yd/receipts/{no}/{action}', [KnitYds::class, 'receiptTransition'])->whereIn('action', ['confirm', 'cancel'])->name('knitting.ystore.yd.receipts.transition');
    Route::get('/knitting/ystore/yd/loss-memos', [KnitYds::class, 'memos'])->name('knitting.ystore.yd.memos.index');
    Route::post('/knitting/ystore/yd/loss-memos', [KnitYds::class, 'saveMemo'])->name('knitting.ystore.yd.memos.store');
    Route::get('/knitting/ystore/yd/loss-memos/{no}', [KnitYds::class, 'showMemo'])->name('knitting.ystore.yd.memos.show');
    Route::put('/knitting/ystore/yd/loss-memos/{no}', [KnitYds::class, 'saveMemo'])->name('knitting.ystore.yd.memos.update');
    Route::post('/knitting/ystore/yd/loss-memos/{no}/{action}', [KnitYds::class, 'memoTransition'])->whereIn('action', ['approve', 'cancel'])->name('knitting.ystore.yd.memos.transition');
    Route::get('/knitting/ystore/yd/reprocess/{kind}', [KnitYds::class, 'reprocesses'])->whereIn('kind', ['in-house', 'sc-knitting'])->name('knitting.ystore.yd.reprocess.index');
    Route::post('/knitting/ystore/yd/reprocess/{kind}', [KnitYds::class, 'saveReprocess'])->whereIn('kind', ['in-house', 'sc-knitting'])->name('knitting.ystore.yd.reprocess.store');
    Route::get('/knitting/ystore/yd/reprocess/{kind}/{no}', [KnitYds::class, 'showReprocess'])->whereIn('kind', ['in-house', 'sc-knitting'])->name('knitting.ystore.yd.reprocess.show');
    Route::put('/knitting/ystore/yd/reprocess/{kind}/{no}', [KnitYds::class, 'saveReprocess'])->whereIn('kind', ['in-house', 'sc-knitting'])->name('knitting.ystore.yd.reprocess.update');
    Route::post('/knitting/ystore/yd/reprocess/{kind}/{no}/{action}', [KnitYds::class, 'reprocessTransition'])->whereIn('kind', ['in-house', 'sc-knitting'])
        ->whereIn('action', ['issue', 'accept', 'reject', 'cancel'])->name('knitting.ystore.yd.reprocess.transition');
    Route::get('/knitting/ystore/sc-yarn-challans', [KnitSyc::class, 'index'])->name('knitting.ystore.sc_yarn_challans.index');
    Route::post('/knitting/ystore/sc-yarn-challans', [KnitSyc::class, 'save'])->name('knitting.ystore.sc_yarn_challans.store');
    Route::get('/knitting/ystore/sc-yarn-challans/{no}', [KnitSyc::class, 'show'])->where('no', '.+')->name('knitting.ystore.sc_yarn_challans.show');
    Route::put('/knitting/ystore/sc-yarn-challans/{no}', [KnitSyc::class, 'save'])->where('no', '.+')->name('knitting.ystore.sc_yarn_challans.update');
    Route::post('/knitting/ystore/sc-yarn-challans/{no}/{action}', [KnitSyc::class, 'transition'])->where('no', '.+')->whereIn('action', ['issue', 'accept', 'reject', 'cancel'])
        ->name('knitting.ystore.sc_yarn_challans.transition');

    // Fabric Store (15/05): the grey store.
    Route::get('/knitting/fstore/options', [GreyStore::class, 'options'])->name('knitting.fstore.options');
    Route::get('/knitting/fstore/receipts/day', [GreyStore::class, 'day'])->name('knitting.fstore.receipts.day');
    Route::post('/knitting/fstore/receipts/scan', [GreyStore::class, 'scan'])->name('knitting.fstore.receipts.scan');
    Route::post('/knitting/fstore/receipts/clear', [GreyStore::class, 'clearDay'])->name('knitting.fstore.receipts.clear');
    Route::delete('/knitting/fstore/receipts/rolls/{id}', [GreyStore::class, 'withdraw'])->name('knitting.fstore.receipts.withdraw');
    Route::get('/knitting/fstore/receipts', [GreyStore::class, 'notes'])->name('knitting.fstore.receipts.index');
    Route::get('/knitting/fstore/receipts/{no}', [GreyStore::class, 'note'])->name('knitting.fstore.receipts.show');
    Route::post('/knitting/fstore/receipts/{no}/confirm', [GreyStore::class, 'confirm'])->name('knitting.fstore.receipts.confirm');
    Route::get('/knitting/fstore/stock', [GreyStore::class, 'stock'])->name('knitting.fstore.stock');
    Route::get('/knitting/fstore/racks', [GreyStore::class, 'racks'])->name('knitting.fstore.racks');
    Route::get('/knitting/fstore/openings', [GreyStore::class, 'openings'])->name('knitting.fstore.openings.index');
    Route::post('/knitting/fstore/openings', [GreyStore::class, 'saveOpening'])->name('knitting.fstore.openings.store');
    Route::post('/knitting/fstore/openings/finalize', [GreyStore::class, 'finalizeOpenings'])->name('knitting.fstore.openings.finalize');
    Route::put('/knitting/fstore/openings/{no}', [GreyStore::class, 'saveOpening'])->name('knitting.fstore.openings.update');
    Route::get('/knitting/fstore/closes', [GreyStore::class, 'closes'])->name('knitting.fstore.closes.index');
    Route::get('/knitting/fstore/closes/exceptions', [GreyStore::class, 'exceptions'])->name('knitting.fstore.closes.exceptions');
    Route::post('/knitting/fstore/closes', [GreyStore::class, 'close'])->name('knitting.fstore.closes.store');
    Route::get('/knitting/fstore/lots', [GreyMove::class, 'sourceLots'])->name('knitting.fstore.lots');
    Route::get('/knitting/fstore/floor-rolls', [GreyMove::class, 'floorRolls'])->name('knitting.fstore.floor_rolls');
    Route::get('/knitting/fstore/transfers', [GreyMove::class, 'index'])->name('knitting.fstore.transfers.index');
    Route::post('/knitting/fstore/transfers', [GreyMove::class, 'storeSave'])->name('knitting.fstore.transfers.store');
    Route::get('/knitting/fstore/transfers/{no}', [GreyMove::class, 'storeShow'])->name('knitting.fstore.transfers.show');
    Route::put('/knitting/fstore/transfers/{no}', [GreyMove::class, 'storeSave'])->name('knitting.fstore.transfers.update');
    Route::post('/knitting/fstore/transfers/{no}/{action}', [GreyMove::class, 'storeTransition'])->whereIn('action', ['submit', 'receive', 'cancel'])->name('knitting.fstore.transfers.transition');
    Route::get('/knitting/fstore/moves/{kind}', [GreyMove::class, 'index'])->whereIn('kind', ['collar-cuff', 'to-batch', 'order', 'reuse', 'to-leftover', 'batch-leftover'])->name('knitting.fstore.moves.index');
    Route::post('/knitting/fstore/moves/{kind}', [GreyMove::class, 'save'])->whereIn('kind', ['collar-cuff', 'to-batch', 'order', 'reuse', 'to-leftover', 'batch-leftover'])->name('knitting.fstore.moves.store');
    Route::get('/knitting/fstore/moves/{kind}/{no}', [GreyMove::class, 'show'])->whereIn('kind', ['collar-cuff', 'to-batch', 'order', 'reuse', 'to-leftover', 'batch-leftover'])->name('knitting.fstore.moves.show');
    Route::put('/knitting/fstore/moves/{kind}/{no}', [GreyMove::class, 'save'])->whereIn('kind', ['collar-cuff', 'to-batch', 'order', 'reuse', 'to-leftover', 'batch-leftover'])->name('knitting.fstore.moves.update');
    Route::post('/knitting/fstore/moves/{kind}/{no}/{action}', [GreyMove::class, 'transition'])->whereIn('kind', ['collar-cuff', 'to-batch', 'order', 'reuse', 'to-leftover', 'batch-leftover'])->whereIn('action', ['submit', 'receive', 'cancel'])
        ->name('knitting.fstore.moves.transition');
    Route::get('/knitting/fstore/sc/options', [ScGrey::class, 'options'])->name('knitting.fstore.sc.options');
    Route::get('/knitting/fstore/sc/party-yarn', [ScGrey::class, 'partyYarn'])->name('knitting.fstore.sc.party_yarn');
    Route::get('/knitting/fstore/sc/receipts', [ScGrey::class, 'receipts'])->name('knitting.fstore.sc.receipts.index');
    Route::post('/knitting/fstore/sc/receipts', [ScGrey::class, 'saveReceipt'])->name('knitting.fstore.sc.receipts.store');
    Route::get('/knitting/fstore/sc/receipts/{no}', [ScGrey::class, 'showReceipt'])->name('knitting.fstore.sc.receipts.show');
    Route::put('/knitting/fstore/sc/receipts/{no}', [ScGrey::class, 'saveReceipt'])->name('knitting.fstore.sc.receipts.update');
    Route::post('/knitting/fstore/sc/receipts/{no}/{action}', [ScGrey::class, 'receiptTransition'])
        ->whereIn('action', ['receive', 'qc-pass', 'qc-hold', 'qc-reject', 'cancel'])->name('knitting.fstore.sc.receipts.transition');
    Route::get('/knitting/fstore/sc/returns/{kind}', [ScGrey::class, 'returns'])->whereIn('kind', ['out-house', 'in-house'])->name('knitting.fstore.sc.returns.index');
    Route::post('/knitting/fstore/sc/returns/{kind}', [ScGrey::class, 'saveReturn'])->whereIn('kind', ['out-house', 'in-house'])->name('knitting.fstore.sc.returns.store');
    Route::get('/knitting/fstore/sc/returns/{kind}/{no}', [ScGrey::class, 'showReturn'])->whereIn('kind', ['out-house', 'in-house'])->name('knitting.fstore.sc.returns.show');
    Route::put('/knitting/fstore/sc/returns/{kind}/{no}', [ScGrey::class, 'saveReturn'])->whereIn('kind', ['out-house', 'in-house'])->name('knitting.fstore.sc.returns.update');
    Route::post('/knitting/fstore/sc/returns/{kind}/{no}/{action}', [ScGrey::class, 'returnTransition'])->whereIn('kind', ['out-house', 'in-house'])
        ->whereIn('action', ['issue', 'received-back', 'cancel'])->name('knitting.fstore.sc.returns.transition');
    Route::get('/knitting/fstore/sc/deliveries', [ScGrey::class, 'deliveries'])->name('knitting.fstore.sc.deliveries.index');
    Route::post('/knitting/fstore/sc/deliveries', [ScGrey::class, 'saveDelivery'])->name('knitting.fstore.sc.deliveries.store');
    Route::get('/knitting/fstore/sc/deliveries/{no}', [ScGrey::class, 'showDelivery'])->name('knitting.fstore.sc.deliveries.show');
    Route::put('/knitting/fstore/sc/deliveries/{no}', [ScGrey::class, 'saveDelivery'])->name('knitting.fstore.sc.deliveries.update');
    Route::post('/knitting/fstore/sc/deliveries/{no}/{action}', [ScGrey::class, 'deliveryTransition'])->whereIn('action', ['issue', 'cancel'])
        ->name('knitting.fstore.sc.deliveries.transition');
    Route::get('/knitting/fstore/bills/options', [ScBill::class, 'options'])->name('knitting.fstore.bills.options');
    Route::get('/knitting/fstore/bills/{kind}', [ScBill::class, 'bills'])->whereIn('kind', ['in-house', 'out-house', 'yd'])->name('knitting.fstore.bills.index');
    Route::post('/knitting/fstore/bills/{kind}', [ScBill::class, 'saveBill'])->whereIn('kind', ['in-house', 'out-house', 'yd'])->name('knitting.fstore.bills.store');
    Route::get('/knitting/fstore/bills/{kind}/{no}', [ScBill::class, 'showBill'])->whereIn('kind', ['in-house', 'out-house', 'yd'])->name('knitting.fstore.bills.show');
    Route::put('/knitting/fstore/bills/{kind}/{no}', [ScBill::class, 'saveBill'])->whereIn('kind', ['in-house', 'out-house', 'yd'])->name('knitting.fstore.bills.update');
    Route::post('/knitting/fstore/bills/{kind}/{no}/{action}', [ScBill::class, 'billTransition'])->whereIn('kind', ['in-house', 'out-house', 'yd'])
        ->whereIn('action', ['submit', 'approve', 'cancel'])->name('knitting.fstore.bills.transition');
    Route::get('/knitting/fstore/debit-memos', [ScBill::class, 'memos'])->name('knitting.fstore.memos.index');
    Route::post('/knitting/fstore/debit-memos', [ScBill::class, 'saveMemo'])->name('knitting.fstore.memos.store');
    Route::get('/knitting/fstore/debit-memos/{no}', [ScBill::class, 'showMemo'])->name('knitting.fstore.memos.show');
    Route::put('/knitting/fstore/debit-memos/{no}', [ScBill::class, 'saveMemo'])->name('knitting.fstore.memos.update');
    Route::post('/knitting/fstore/debit-memos/{no}/{action}', [ScBill::class, 'memoTransition'])->whereIn('action', ['submit', 'approve', 'settle', 'cancel'])
        ->name('knitting.fstore.memos.transition');
    Route::get('/knitting/fstore/yarn/options', [YarnTr::class, 'options'])->name('knitting.fstore.yarn.options');
    Route::get('/knitting/fstore/yarn/forwardable', [YarnTr::class, 'forwardable'])->name('knitting.fstore.yarn.forwardable');
    Route::get('/knitting/fstore/yarn/prog-transfers', [YarnTr::class, 'progTransfers'])->name('knitting.fstore.yarn.prog_transfers.index');
    Route::post('/knitting/fstore/yarn/prog-transfers', [YarnTr::class, 'saveProgTransfer'])->name('knitting.fstore.yarn.prog_transfers.store');
    Route::get('/knitting/fstore/yarn/prog-transfers/{no}', [YarnTr::class, 'showProgTransfer'])->name('knitting.fstore.yarn.prog_transfers.show');
    Route::put('/knitting/fstore/yarn/prog-transfers/{no}', [YarnTr::class, 'saveProgTransfer'])->name('knitting.fstore.yarn.prog_transfers.update');
    Route::post('/knitting/fstore/yarn/prog-transfers/{no}/{action}', [YarnTr::class, 'progTransition'])->whereIn('action', ['issue', 'finalize', 'cancel'])->name('knitting.fstore.yarn.prog_transfers.transition');
    Route::get('/knitting/fstore/yarn/grey-transfers', [YarnTr::class, 'greyTransfers'])->name('knitting.fstore.yarn.grey_transfers.index');
    Route::post('/knitting/fstore/yarn/grey-transfers', [YarnTr::class, 'saveGreyTransfer'])->name('knitting.fstore.yarn.grey_transfers.store');
    Route::get('/knitting/fstore/yarn/grey-transfers/{no}', [YarnTr::class, 'showGreyTransfer'])->name('knitting.fstore.yarn.grey_transfers.show');
    Route::put('/knitting/fstore/yarn/grey-transfers/{no}', [YarnTr::class, 'saveGreyTransfer'])->name('knitting.fstore.yarn.grey_transfers.update');
    Route::post('/knitting/fstore/yarn/grey-transfers/{no}/{action}', [YarnTr::class, 'greyTransition'])->whereIn('action', ['issue', 'finalize', 'cancel'])->name('knitting.fstore.yarn.grey_transfers.transition');
    Route::get('/knitting/fstore/yarn/dyed-receipts', [YarnTr::class, 'dyedReceipts'])->name('knitting.fstore.yarn.dyed_receipts.index');
    Route::post('/knitting/fstore/yarn/dyed-receipts', [YarnTr::class, 'saveDyedReceipt'])->name('knitting.fstore.yarn.dyed_receipts.store');
    Route::get('/knitting/fstore/yarn/dyed-receipts/{no}', [YarnTr::class, 'showDyedReceipt'])->name('knitting.fstore.yarn.dyed_receipts.show');
    Route::put('/knitting/fstore/yarn/dyed-receipts/{no}', [YarnTr::class, 'saveDyedReceipt'])->name('knitting.fstore.yarn.dyed_receipts.update');
    Route::post('/knitting/fstore/yarn/dyed-receipts/{no}/{action}', [YarnTr::class, 'dyedTransition'])->whereIn('action', ['issue', 'finalize', 'cancel'])->name('knitting.fstore.yarn.dyed_receipts.transition');
    Route::get('/knitting/fstore/yarn/dyed-transfers', [YarnTr::class, 'dyedTransfers'])->name('knitting.fstore.yarn.dyed_transfers.index');
    Route::post('/knitting/fstore/yarn/dyed-transfers', [YarnTr::class, 'saveDyedTransfer'])->name('knitting.fstore.yarn.dyed_transfers.store');
    Route::get('/knitting/fstore/yarn/dyed-transfers/{no}', [YarnTr::class, 'showDyedTransfer'])->name('knitting.fstore.yarn.dyed_transfers.show');
    Route::put('/knitting/fstore/yarn/dyed-transfers/{no}', [YarnTr::class, 'saveDyedTransfer'])->name('knitting.fstore.yarn.dyed_transfers.update');
    Route::post('/knitting/fstore/yarn/dyed-transfers/{no}/{action}', [YarnTr::class, 'dyedTransferTransition'])->whereIn('action', ['issue', 'finalize', 'cancel'])->name('knitting.fstore.yarn.dyed_transfers.transition');
    Route::get('/knitting/fstore/adjustments', [GreyDoc::class, 'adjustments'])->name('knitting.fstore.adjustments.index');
    Route::post('/knitting/fstore/adjustments', [GreyDoc::class, 'saveAdjustment'])->name('knitting.fstore.adjustments.store');
    Route::get('/knitting/fstore/adjustments/{no}', [GreyDoc::class, 'showAdjustment'])->name('knitting.fstore.adjustments.show');
    Route::put('/knitting/fstore/adjustments/{no}', [GreyDoc::class, 'saveAdjustment'])->name('knitting.fstore.adjustments.update');
    Route::post('/knitting/fstore/adjustments/{no}/{action}', [GreyDoc::class, 'adjustmentTransition'])->whereIn('action', ['verify', 'approve', 'cancel'])->name('knitting.fstore.adjustments.transition');

    Route::get('/knitting/ystore/issue-reqs/options', [YarnIssue::class, 'options'])->name('knitting.ystore.issue_reqs.options');
    Route::get('/knitting/ystore/issue-reqs', [YarnIssue::class, 'index'])->name('knitting.ystore.issue_reqs.index');
    Route::post('/knitting/ystore/issue-reqs', [YarnIssue::class, 'store'])->name('knitting.ystore.issue_reqs.store');
    Route::get('/knitting/ystore/issue-reqs/{req}', [YarnIssue::class, 'show'])->name('knitting.ystore.issue_reqs.show');
    Route::put('/knitting/ystore/issue-reqs/{req}', [YarnIssue::class, 'update'])->name('knitting.ystore.issue_reqs.update');
    Route::get('/knitting/ystore/issue-reqs/{req}/source', [YarnIssue::class, 'source'])->name('knitting.ystore.issue_reqs.source');
    Route::post('/knitting/ystore/issue-reqs/{req}/{action}', [YarnIssue::class, 'transition'])->whereIn('action', ['close', 'cancel'])->name('knitting.ystore.issue_reqs.transition');
    Route::post('/knitting/ystore/issues', [YarnIssue::class, 'storeIssue'])->name('knitting.ystore.issues.store');
    Route::get('/knitting/ystore/issues/{issue}', [YarnIssue::class, 'showIssue'])->name('knitting.ystore.issues.show');
    Route::put('/knitting/ystore/issues/{issue}', [YarnIssue::class, 'updateIssue'])->name('knitting.ystore.issues.update');

    Route::get('/knitting/ystore/outward/options', [YarnOut::class, 'options'])->name('knitting.ystore.outward.options');
    Route::get('/knitting/ystore/issue-returns', [YarnOut::class, 'returns'])->name('knitting.ystore.issue_returns.index');
    Route::post('/knitting/ystore/issue-returns', [YarnOut::class, 'saveReturn'])->name('knitting.ystore.issue_returns.store');
    Route::get('/knitting/ystore/issue-returns/source/{req}', [YarnOut::class, 'returnSource'])->name('knitting.ystore.issue_returns.source');
    Route::get('/knitting/ystore/issue-returns/{return}', [YarnOut::class, 'showReturn'])->name('knitting.ystore.issue_returns.show');
    Route::put('/knitting/ystore/issue-returns/{return}', [YarnOut::class, 'saveReturn'])->name('knitting.ystore.issue_returns.update');
    Route::post('/knitting/ystore/issue-returns/{return}/{action}', [YarnOut::class, 'returnTransition'])->whereIn('action', ['issue', 'accept', 'reject', 'cancel'])->name('knitting.ystore.issue_returns.transition');
    Route::get('/knitting/ystore/party-challans', [YarnOut::class, 'challans'])->name('knitting.ystore.party_challans.index');
    Route::post('/knitting/ystore/party-challans', [YarnOut::class, 'saveChallan'])->name('knitting.ystore.party_challans.store');
    Route::get('/knitting/ystore/party-challans/{challan}', [YarnOut::class, 'showChallan'])->name('knitting.ystore.party_challans.show');
    Route::put('/knitting/ystore/party-challans/{challan}', [YarnOut::class, 'saveChallan'])->name('knitting.ystore.party_challans.update');
    Route::post('/knitting/ystore/party-challans/{challan}/{action}', [YarnOut::class, 'challanTransition'])->whereIn('action', ['issue', 'accept', 'reject', 'cancel'])->name('knitting.ystore.party_challans.transition');
    Route::get('/knitting/ystore/replacements', [YarnOut::class, 'replacements'])->name('knitting.ystore.replacements.index');
    Route::post('/knitting/ystore/replacements', [YarnOut::class, 'saveReplacement'])->name('knitting.ystore.replacements.store');
    Route::get('/knitting/ystore/replacements/{replacement}', [YarnOut::class, 'showReplacement'])->name('knitting.ystore.replacements.show');
    Route::put('/knitting/ystore/replacements/{replacement}', [YarnOut::class, 'saveReplacement'])->name('knitting.ystore.replacements.update');
    Route::post('/knitting/ystore/replacements/{replacement}/{action}', [YarnOut::class, 'replacementTransition'])->whereIn('action', ['submit', 'accept', 'cancel'])->name('knitting.ystore.replacements.transition');

    // Collar/Cuff (15/06): a requirement, programme, indent or challan by its number (CCR-…, CCP-…, CYS-/CYD-…, CCB-/CCS-…).
    Route::get('/knitting/prod/cc/options', [KnitCc::class, 'options'])->name('knitting.prod.cc.options');
    Route::get('/knitting/prod/cc/requirements', [KnitCc::class, 'requirements'])->name('knitting.prod.cc.requirements.index');
    Route::post('/knitting/prod/cc/requirements', [KnitCc::class, 'saveRequirement'])->name('knitting.prod.cc.requirements.store');
    Route::get('/knitting/prod/cc/requirements/{no}', [KnitCc::class, 'showRequirement'])->name('knitting.prod.cc.requirements.show');
    Route::put('/knitting/prod/cc/requirements/{no}', [KnitCc::class, 'saveRequirement'])->name('knitting.prod.cc.requirements.update');
    Route::post('/knitting/prod/cc/requirements/{no}/close', [KnitCc::class, 'closeRequirement'])->name('knitting.prod.cc.requirements.close');
    Route::get('/knitting/prod/cc/requirements/{no}/productions', [KnitCc::class, 'productions'])->name('knitting.prod.cc.productions.index');
    Route::post('/knitting/prod/cc/requirements/{no}/productions', [KnitCc::class, 'addProduction'])->name('knitting.prod.cc.productions.store');
    Route::put('/knitting/prod/cc/requirements/{no}/productions', [KnitCc::class, 'saveProductions'])->name('knitting.prod.cc.productions.save');
    Route::delete('/knitting/prod/cc/requirements/{no}/productions/{id}', [KnitCc::class, 'removeProduction'])->name('knitting.prod.cc.productions.destroy');
    Route::get('/knitting/prod/cc/programs', [KnitCc::class, 'programs'])->name('knitting.prod.cc.programs.index');
    Route::post('/knitting/prod/cc/programs', [KnitCc::class, 'saveProgram'])->name('knitting.prod.cc.programs.store');
    Route::get('/knitting/prod/cc/programs/{no}', [KnitCc::class, 'showProgram'])->name('knitting.prod.cc.programs.show');
    Route::put('/knitting/prod/cc/programs/{no}', [KnitCc::class, 'saveProgram'])->name('knitting.prod.cc.programs.update');
    Route::post('/knitting/prod/cc/programs/{no}/{action}', [KnitCc::class, 'programTransition'])->whereIn('action', ['submit', 'approve', 'send-back', 'start', 'close', 'cancel'])->name('knitting.prod.cc.programs.transition');
    Route::get('/knitting/prod/cc/challans/{kind}', [KnitCc::class, 'challans'])->whereIn('kind', ['bulk', 'sample'])->name('knitting.prod.cc.challans.index');
    Route::get('/knitting/prod/cc/challans/{kind}/open', [KnitCc::class, 'openRequirements'])->whereIn('kind', ['bulk', 'sample'])->name('knitting.prod.cc.challans.open');
    Route::post('/knitting/prod/cc/challans/{kind}', [KnitCc::class, 'saveChallan'])->whereIn('kind', ['bulk', 'sample'])->name('knitting.prod.cc.challans.store');
    Route::get('/knitting/prod/cc/challans/{kind}/{no}', [KnitCc::class, 'showChallan'])->whereIn('kind', ['bulk', 'sample'])->name('knitting.prod.cc.challans.show');
    Route::put('/knitting/prod/cc/challans/{kind}/{no}', [KnitCc::class, 'saveChallan'])->whereIn('kind', ['bulk', 'sample'])->name('knitting.prod.cc.challans.update');
    Route::post('/knitting/prod/cc/challans/{kind}/{no}/{action}', [KnitCc::class, 'challanTransition'])->whereIn('kind', ['bulk', 'sample'])->whereIn('action', ['issue', 'receive', 'reopen', 'cancel'])->name('knitting.prod.cc.challans.transition');
    Route::get('/knitting/prod/cc/yarn/plans', [KnitCcYarn::class, 'plans'])->name('knitting.prod.cc.yarn.plans');
    Route::get('/knitting/prod/cc/yarn/lots', [KnitCcYarn::class, 'lots'])->name('knitting.prod.cc.yarn.lots');
    Route::get('/knitting/prod/cc/yarn/{kind}', [KnitCcYarn::class, 'reqs'])->whereIn('kind', ['solid', 'yd'])->name('knitting.prod.cc.yarn.index');
    Route::post('/knitting/prod/cc/yarn/{kind}', [KnitCcYarn::class, 'saveReq'])->whereIn('kind', ['solid', 'yd'])->name('knitting.prod.cc.yarn.store');
    Route::get('/knitting/prod/cc/yarn/{kind}/{no}', [KnitCcYarn::class, 'showReq'])->whereIn('kind', ['solid', 'yd'])->name('knitting.prod.cc.yarn.show');
    Route::put('/knitting/prod/cc/yarn/{kind}/{no}', [KnitCcYarn::class, 'saveReq'])->whereIn('kind', ['solid', 'yd'])->name('knitting.prod.cc.yarn.update');
    Route::post('/knitting/prod/cc/yarn/{kind}/{no}/issue', [KnitCcYarn::class, 'issue'])->whereIn('kind', ['solid', 'yd'])->name('knitting.prod.cc.yarn.issue');
    Route::post('/knitting/prod/cc/yarn/{kind}/{no}/{action}', [KnitCcYarn::class, 'transition'])->whereIn('kind', ['solid', 'yd'])->whereIn('action', ['submit', 'approve', 'close', 'cancel'])->name('knitting.prod.cc.yarn.transition');

    // Needle Store (15/07): a paper by its number (NMR-…, NMR-…-R01, NBR-…, NRQ-…, NIS-…, NLR-…).
    Route::get('/knitting/needle/store/options', [NeedleStore::class, 'options'])->name('knitting.needle.store.options');
    Route::get('/knitting/needle/openings', [NeedleStore::class, 'openings'])->name('knitting.needle.openings.index');
    Route::get('/knitting/needle/openings/sheet', [NeedleStore::class, 'openingSheet'])->name('knitting.needle.openings.sheet');
    Route::put('/knitting/needle/openings/sheet', [NeedleStore::class, 'saveOpening'])->name('knitting.needle.openings.save');
    Route::get('/knitting/needle/loan-openings', [NeedleStore::class, 'loanOpenings'])->name('knitting.needle.loan_openings.index');
    Route::post('/knitting/needle/loan-openings', [NeedleStore::class, 'saveLoanOpening'])->name('knitting.needle.loan_openings.store');
    Route::put('/knitting/needle/loan-openings/{id}', [NeedleStore::class, 'saveLoanOpening'])->name('knitting.needle.loan_openings.update');
    Route::get('/knitting/needle/receipts', [NeedleStore::class, 'receipts'])->name('knitting.needle.receipts.index');
    Route::post('/knitting/needle/receipts', [NeedleStore::class, 'saveReceipt'])->name('knitting.needle.receipts.store');
    Route::get('/knitting/needle/receipts/{no}', [NeedleStore::class, 'showReceipt'])->name('knitting.needle.receipts.show');
    Route::put('/knitting/needle/receipts/{no}', [NeedleStore::class, 'saveReceipt'])->name('knitting.needle.receipts.update');
    Route::get('/knitting/needle/receipts/{no}/source', [NeedleStore::class, 'receiptSource'])->name('knitting.needle.receipts.source');
    Route::post('/knitting/needle/receipts/{no}/{action}', [NeedleStore::class, 'receiptTransition'])->whereIn('action', ['submit', 'verify', 'accounts', 'approve', 'cancel'])->name('knitting.needle.receipts.transition');
    Route::get('/knitting/needle/consumption', [NeedleStore::class, 'consumption'])->name('knitting.needle.consumption');
    Route::get('/knitting/needle/returns', [NeedleStore::class, 'returns'])->name('knitting.needle.returns.index');
    Route::post('/knitting/needle/returns', [NeedleStore::class, 'saveReturn'])->name('knitting.needle.returns.store');
    Route::get('/knitting/needle/returns/{no}', [NeedleStore::class, 'showReturn'])->name('knitting.needle.returns.show');
    Route::put('/knitting/needle/returns/{no}', [NeedleStore::class, 'saveReturn'])->name('knitting.needle.returns.update');
    Route::post('/knitting/needle/returns/{no}/{action}', [NeedleStore::class, 'returnTransition'])->whereIn('action', ['issue', 'finalize', 'cancel'])->name('knitting.needle.returns.transition');
    Route::get('/knitting/needle/closings', [NeedleStore::class, 'closings'])->name('knitting.needle.closings.index');
    Route::get('/knitting/needle/closings/exceptions', [NeedleStore::class, 'closingExceptions'])->name('knitting.needle.closings.exceptions');
    Route::post('/knitting/needle/closings', [NeedleStore::class, 'close'])->name('knitting.needle.closings.close');
    Route::get('/knitting/needle/floor/options', [NeedleFloor::class, 'options'])->name('knitting.needle.floor.options');
    Route::get('/knitting/needle/assignments', [NeedleFloor::class, 'assignments'])->name('knitting.needle.assignments.index');
    Route::get('/knitting/needle/assignments/sheet', [NeedleFloor::class, 'assignmentSheet'])->name('knitting.needle.assignments.sheet');
    Route::put('/knitting/needle/assignments/sheet', [NeedleFloor::class, 'saveAssignment'])->name('knitting.needle.assignments.save');
    Route::get('/knitting/needle/brokens', [NeedleFloor::class, 'brokens'])->name('knitting.needle.brokens.index');
    Route::post('/knitting/needle/brokens', [NeedleFloor::class, 'saveBroken'])->name('knitting.needle.brokens.store');
    Route::get('/knitting/needle/brokens/{no}', [NeedleFloor::class, 'showBroken'])->name('knitting.needle.brokens.show');
    Route::put('/knitting/needle/brokens/{no}', [NeedleFloor::class, 'saveBroken'])->name('knitting.needle.brokens.update');
    Route::post('/knitting/needle/brokens/{no}/{action}', [NeedleFloor::class, 'brokenTransition'])->whereIn('action', ['confirm', 'cancel'])->name('knitting.needle.brokens.transition');
    Route::get('/knitting/needle/reqs', [NeedleFloor::class, 'reqs'])->name('knitting.needle.reqs.index');
    Route::post('/knitting/needle/reqs', [NeedleFloor::class, 'saveReq'])->name('knitting.needle.reqs.store');
    Route::get('/knitting/needle/reqs/{no}', [NeedleFloor::class, 'showReq'])->name('knitting.needle.reqs.show');
    Route::put('/knitting/needle/reqs/{no}', [NeedleFloor::class, 'saveReq'])->name('knitting.needle.reqs.update');
    Route::post('/knitting/needle/reqs/{no}/{action}', [NeedleFloor::class, 'reqTransition'])->whereIn('action', ['submit', 'verify', 'submit-verify', 'close', 'cancel'])->name('knitting.needle.reqs.transition');
    Route::get('/knitting/needle/issues', [NeedleFloor::class, 'issues'])->name('knitting.needle.issues.index');
    Route::get('/knitting/needle/issues/owing', [NeedleFloor::class, 'owing'])->name('knitting.needle.issues.owing');
    Route::post('/knitting/needle/issues', [NeedleFloor::class, 'saveIssue'])->name('knitting.needle.issues.store');
    Route::get('/knitting/needle/issues/{no}', [NeedleFloor::class, 'showIssue'])->name('knitting.needle.issues.show');
    Route::post('/knitting/needle/issues/{no}/withdraw', [NeedleFloor::class, 'withdrawIssue'])->name('knitting.needle.issues.withdraw');
    Route::get('/knitting/needle/loan-reqs', [NeedleFloor::class, 'loanReqs'])->name('knitting.needle.loan_reqs.index');
    Route::post('/knitting/needle/loan-reqs', [NeedleFloor::class, 'saveLoanReq'])->name('knitting.needle.loan_reqs.store');
    Route::get('/knitting/needle/loan-reqs/{no}', [NeedleFloor::class, 'showLoanReq'])->name('knitting.needle.loan_reqs.show');
    Route::put('/knitting/needle/loan-reqs/{no}', [NeedleFloor::class, 'saveLoanReq'])->name('knitting.needle.loan_reqs.update');
    Route::post('/knitting/needle/loan-reqs/{no}/{action}', [NeedleFloor::class, 'loanTransition'])->whereIn('action', ['submit', 'verify', 'submit-verify', 'close', 'cancel'])->name('knitting.needle.loan_reqs.transition');

    // Loan Issue to Party (15/08): a party's return challan by our number (NLT- / OLT-…).
    Route::get('/knitting/needle/loan-returns/{book}', [LoanReturn::class, 'index'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_returns.index');
    Route::get('/knitting/needle/loan-returns/{book}/owing', [LoanReturn::class, 'owing'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_returns.owing');
    Route::post('/knitting/needle/loan-returns/{book}', [LoanReturn::class, 'save'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_returns.store');
    Route::get('/knitting/needle/loan-returns/{book}/{no}', [LoanReturn::class, 'show'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_returns.show');
    Route::put('/knitting/needle/loan-returns/{book}/{no}', [LoanReturn::class, 'save'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_returns.update');
    Route::post('/knitting/needle/loan-returns/{book}/{no}/{action}', [LoanReturn::class, 'transition'])->whereIn('book', ['needle', 'oil'])->whereIn('action', ['receive', 'finalize', 'cancel'])->name('knitting.needle.loan_returns.transition');

    // Loan Rcv from Party (15/09): a borrowed-stock receipt by its MRR (NLB- / OLB-…).
    Route::get('/knitting/needle/loan-receipts/{book}', [LoanReceipt::class, 'index'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_receipts.index');
    Route::post('/knitting/needle/loan-receipts/{book}', [LoanReceipt::class, 'save'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_receipts.store');
    Route::get('/knitting/needle/loan-receipts/{book}/{no}', [LoanReceipt::class, 'show'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_receipts.show');
    Route::put('/knitting/needle/loan-receipts/{book}/{no}', [LoanReceipt::class, 'save'])->whereIn('book', ['needle', 'oil'])->name('knitting.needle.loan_receipts.update');
    Route::post('/knitting/needle/loan-receipts/{book}/{no}/{action}', [LoanReceipt::class, 'transition'])->whereIn('book', ['needle', 'oil'])->whereIn('action', ['submit', 'verify', 'accounts', 'approve', 'cancel'])->name('knitting.needle.loan_receipts.transition');

    // Oil Store (15/10): the OIL book's own paper — indent ORQ-, opening, consumption OCS-, receipt OMR-, return challan.
    Route::get('/knitting/needle/oil/options', [OilStore::class, 'options'])->name('knitting.needle.oil.options');
    Route::get('/knitting/needle/oil/reqs', [OilStore::class, 'reqs'])->name('knitting.needle.oil.reqs.index');
    Route::post('/knitting/needle/oil/reqs', [OilStore::class, 'saveReq'])->name('knitting.needle.oil.reqs.store');
    Route::get('/knitting/needle/oil/reqs/{no}', [OilStore::class, 'showReq'])->name('knitting.needle.oil.reqs.show');
    Route::put('/knitting/needle/oil/reqs/{no}', [OilStore::class, 'saveReq'])->name('knitting.needle.oil.reqs.update');
    Route::post('/knitting/needle/oil/reqs/{no}/{action}', [OilStore::class, 'reqTransition'])->whereIn('action', ['submit', 'submit-verify', 'verify', 'issue-full', 'reopen', 'close', 'cancel'])->name('knitting.needle.oil.reqs.transition');
    Route::get('/knitting/needle/oil/openings', [OilStore::class, 'openings'])->name('knitting.needle.oil.openings.index');
    Route::post('/knitting/needle/oil/openings', [OilStore::class, 'saveOpening'])->name('knitting.needle.oil.openings.store');
    Route::post('/knitting/needle/oil/openings/finalize', [OilStore::class, 'finalizeOpenings'])->name('knitting.needle.oil.openings.finalize_all');
    Route::put('/knitting/needle/oil/openings/{id}', [OilStore::class, 'saveOpening'])->name('knitting.needle.oil.openings.update');
    Route::post('/knitting/needle/oil/openings/{id}/finalize', [OilStore::class, 'finalizeOpenings'])->name('knitting.needle.oil.openings.finalize');
    Route::get('/knitting/needle/oil/consumptions', [OilStore::class, 'consumptions'])->name('knitting.needle.oil.consumptions.index');
    Route::post('/knitting/needle/oil/consumptions', [OilStore::class, 'saveConsumption'])->name('knitting.needle.oil.consumptions.store');
    Route::get('/knitting/needle/oil/consumptions/{no}', [OilStore::class, 'showConsumption'])->name('knitting.needle.oil.consumptions.show');
    Route::put('/knitting/needle/oil/consumptions/{no}', [OilStore::class, 'saveConsumption'])->name('knitting.needle.oil.consumptions.update');
    Route::post('/knitting/needle/oil/consumptions/{no}/finalize', [OilStore::class, 'finalizeConsumption'])->name('knitting.needle.oil.consumptions.finalize');
    Route::get('/knitting/needle/oil/receipts', [OilStore::class, 'receipts'])->name('knitting.needle.oil.receipts.index');
    Route::post('/knitting/needle/oil/receipts', [OilStore::class, 'saveReceipt'])->name('knitting.needle.oil.receipts.store');
    Route::get('/knitting/needle/oil/receipts/{no}', [OilStore::class, 'showReceipt'])->name('knitting.needle.oil.receipts.show');
    Route::put('/knitting/needle/oil/receipts/{no}', [OilStore::class, 'saveReceipt'])->name('knitting.needle.oil.receipts.update');
    Route::get('/knitting/needle/oil/receipts/{no}/source', [OilStore::class, 'receiptSource'])->name('knitting.needle.oil.receipts.source');
    Route::post('/knitting/needle/oil/receipts/{no}/{action}', [OilStore::class, 'receiptTransition'])->whereIn('action', ['next', 'finalize', 'accounting', 'cancel'])->name('knitting.needle.oil.receipts.transition');
    Route::get('/knitting/needle/oil/returns', [OilStore::class, 'returns'])->name('knitting.needle.oil.returns.index');
    Route::post('/knitting/needle/oil/returns', [OilStore::class, 'saveReturn'])->name('knitting.needle.oil.returns.store');
    Route::get('/knitting/needle/oil/returns/{no}', [OilStore::class, 'showReturn'])->name('knitting.needle.oil.returns.show');
    Route::put('/knitting/needle/oil/returns/{no}', [OilStore::class, 'saveReturn'])->name('knitting.needle.oil.returns.update');
    Route::post('/knitting/needle/oil/returns/{no}/{action}', [OilStore::class, 'returnTransition'])->whereIn('action', ['issue', 'finalize', 'reopen', 'cancel'])->name('knitting.needle.oil.returns.transition');

    // Reports (AnnotechERP 15/11): the five report sets, the two stock positions and the day's output sheet.
    Route::get('/knitting/reports/options', [KnitReports::class, 'options'])->name('knitting.reports.options');
    Route::get('/knitting/reports/yarn-stock', [KnitReports::class, 'yarnStock'])->name('knitting.reports.yarn_stock');
    Route::get('/knitting/reports/grey-stock', [KnitReports::class, 'greyStock'])->name('knitting.reports.grey_stock');
    Route::get('/knitting/reports/daily-output', [KnitReports::class, 'dailyOutput'])->name('knitting.reports.daily_output');
    Route::get('/knitting/reports/{set}/{report}', [KnitReports::class, 'show'])->whereIn('set', array_keys(KnitReports::SETS))->name('knitting.reports.show');
});
