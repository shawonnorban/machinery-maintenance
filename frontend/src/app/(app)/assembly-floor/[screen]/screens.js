import { apiFetch } from "@/lib/api-server";
import { BundleInDesk, BundleOutDesk, SewingBoard, SewingNav } from "@/components/assembly/sewing";
import { FinishingBoard, FinishingDesk, FinishingNav } from "@/components/assembly/finishing";
import { QualityBoard, QualityDesk, QualityNav } from "@/components/assembly/quality";
import { PackingDashboard, PackingInDesk, PackingNav, PackingOutDesk, PackingReports, ProcessDesk } from "@/components/assembly/packing";
import { FinishedGoods, ReadyDashboard, ReadyNav } from "@/components/assembly/ready";
import { MarkShipped, ShipmentDashboard, ShipmentEntry, ShipmentHistory, ShipmentNav } from "@/components/assembly/shipment";
import { TrimsIssueDesk, TrimsReceiptDesk, TrimsReturnDesk } from "@/components/assembly/trims";
import { AssemblyReports } from "@/components/assembly/reports";
import * as actions from "./actions";

/*
 * Assembly Floor → Sewing (AnnotechERP 20/01), each laid out as Brickland
 * lays it out under the same Dashboard / Bundle IN / Bundle OUT buttons:
 * the Sewing Dashboard (every line's day on one board), the Bundle IN desk
 * (location, scan, the line's plan, confirm) and the Bundle OUT desk (scan
 * off the line, full bundle or short output). Finishing (20/02): the
 * Finishing Dashboard and its two desks, under their own three buttons.
 * Quality Management (20/03): the Quality Dashboard and the Quality IN,
 * Inspection and Quality OUT desks, under their four buttons. Packing
 * (20/04) and its Packing Process (20/05): the Packing Dashboard, Packing In,
 * the seven process desks, Packing Out and the Packing Reports, all under
 * Brickland's one row of eleven buttons. Ready For Shipment (20/06): the
 * Ready for Shipment Dashboard and Finished Goods Stock. Shipment (20/07):
 * the Shipment Dashboard, Shipment Entry, Mark as Shipped, Shipment History
 * and Shipment Reports, under their five buttons. Accessories Store
 * (20/08): Trims & Accessories Receipt, Issue and the Receipt Return
 * Register, each a list and an entry tab switching in place. Reports
 * (20/09): the Apparel Assembly Reports.
 */

const act = { resolve: actions.resolveBundle, listIns: actions.listIns, receive: actions.receiveBundle, withdrawIns: actions.withdrawIns, listOuts: actions.listOuts, sendOut: actions.sendOutBundle, withdrawOuts: actions.withdrawOuts, board: actions.sewingBoard };

const dashboard = async () => {
  const [options, board] = await Promise.all([apiFetch("/assembly/sewing/options"), apiFetch("/assembly/sewing/dashboard")]);
  return {
    body: () => (
      <>
        <SewingNav active="dashboard" />
        <SewingBoard options={options} initial={board} act={act} />
      </>
    ),
  };
};

const bundleIn = async ({ canManage }) => {
  const options = await apiFetch("/assembly/sewing/options");
  const day = await apiFetch(`/assembly/sewing/ins?date=${options.today}`);
  return {
    body: () => (
      <>
        <SewingNav active="in" />
        <BundleInDesk options={options} initial={day} canManage={canManage} act={act} />
      </>
    ),
  };
};

const bundleOut = async ({ canManage }) => {
  const options = await apiFetch("/assembly/sewing/options");
  const day = await apiFetch(`/assembly/sewing/outs?date=${options.today}`);
  return {
    body: () => (
      <>
        <SewingNav active="out" />
        <BundleOutDesk options={options} initial={day} canManage={canManage} act={act} />
      </>
    ),
  };
};

const fin = {
  line: actions.finishingLine,
  resolve: actions.finishingResolve,
  recent: actions.finishingRecent,
  receive: actions.finishingReceive,
  sendOut: actions.finishingSendOut,
  rework: actions.finishingRework,
  board: actions.finishingBoard,
  trace: actions.finishingTrace,
};

const finishingDashboard = async ({ can }) => {
  const [options, board] = await Promise.all([apiFetch("/assembly/finishing/options"), apiFetch("/assembly/finishing/dashboard")]);
  return {
    body: () => (
      <>
        <FinishingNav active="dashboard" />
        <FinishingBoard options={options} initial={board} canSupervise={can("assembly.finishing.supervise")} act={fin} />
      </>
    ),
  };
};

const finishingDesk = (kind) => async ({ can }) => {
  const [options, recent] = await Promise.all([apiFetch("/assembly/finishing/options"), apiFetch(`/assembly/finishing/recent?kind=${kind}`)]);
  return {
    body: () => (
      <>
        <FinishingNav active={kind} />
        <FinishingDesk kind={kind} options={options} initial={recent.rows} canManage={can("assembly.finishing.manage")} act={fin} />
      </>
    ),
  };
};

const qm = {
  line: actions.qualityLine,
  resolve: actions.qualityResolve,
  recent: actions.qualityRecent,
  receive: actions.qualityReceive,
  inspect: actions.qualityInspect,
  sendOut: actions.qualitySendOut,
  rework: actions.qualityRework,
  board: actions.qualityBoard,
  trace: actions.qualityTrace,
};

const qualityDashboard = async ({ can }) => {
  const [options, board] = await Promise.all([apiFetch("/assembly/quality/options"), apiFetch("/assembly/quality/dashboard")]);
  return {
    body: () => (
      <>
        <QualityNav active="dashboard" />
        <QualityBoard options={options} initial={board} canSupervise={can("assembly.quality.supervise")} act={qm} />
      </>
    ),
  };
};

const qualityDesk = (kind) => async ({ can }) => {
  const [options, recent] = await Promise.all([apiFetch("/assembly/quality/options"), apiFetch(`/assembly/quality/recent?kind=${kind}`)]);
  return {
    body: () => (
      <>
        <QualityNav active={kind} />
        <QualityDesk kind={kind} options={options} initial={recent.rows} canManage={can("assembly.quality.manage")} canSupervise={can("assembly.quality.supervise")} act={qm} />
      </>
    ),
  };
};

const pk = {
  inDesk: actions.packingInDesk,
  previewIn: actions.packingPreviewIn,
  confirmIn: actions.packingConfirmIn,
  removeIn: actions.packingRemoveIn,
  process: actions.packingProcess,
  find: actions.packingFind,
  batch: actions.packingBatch,
  step: actions.packingStep,
  printLabels: actions.packingPrintLabels,
  verifyLabel: actions.packingVerifyLabel,
  addCartons: actions.packingAddCartons,
  deleteCarton: actions.packingDeleteCarton,
  saveMarking: actions.packingSaveMarking,
  printMarking: actions.packingPrintMarking,
  seal: actions.packingSeal,
  unseal: actions.packingUnseal,
  outDesk: actions.packingOutDesk,
  previewOut: actions.packingPreviewOut,
  confirmOut: actions.packingConfirmOut,
  trace: actions.packingTrace,
  report: actions.packingReport,
};

const packingDashboard = async () => {
  const board = await apiFetch("/assembly/packing/dashboard");
  return {
    body: () => (
      <>
        <PackingNav active="dashboard" />
        <PackingDashboard initial={board} act={pk} />
      </>
    ),
  };
};

const packingIn = async ({ can }) => {
  const [options, desk] = await Promise.all([apiFetch("/assembly/packing/options"), apiFetch("/assembly/packing/in")]);
  return {
    body: () => (
      <>
        <PackingNav active="in" />
        <PackingInDesk options={options} initial={desk} canManage={can("assembly.packing.manage")} canSupervise={can("assembly.packing.supervise")} act={pk} />
      </>
    ),
  };
};

const packingStep = (step) => async ({ can }) => {
  const desk = await apiFetch(`/assembly/packing/process?step=${step}`);
  return {
    body: () => (
      <>
        <PackingNav active={step} />
        <ProcessDesk step={step} initial={desk} canManage={can("assembly.packing.manage")} canSupervise={can("assembly.packing.supervise")} act={pk} />
      </>
    ),
  };
};

const packingOut = async ({ can }) => {
  const desk = await apiFetch("/assembly/packing/out");
  return {
    body: () => (
      <>
        <PackingNav active="out" />
        <PackingOutDesk initial={desk} canManage={can("assembly.packing.manage")} act={pk} />
      </>
    ),
  };
};

const packingReports = async () => {
  const options = await apiFetch("/assembly/packing/options");
  return {
    body: () => (
      <>
        <PackingNav active="reports" />
        <PackingReports kinds={options.reports} today={options.today} act={pk} />
      </>
    ),
  };
};

const rs = { dashboard: actions.readyDashboard, detail: actions.readyDetail, stock: actions.readyStock };

const readyDashboard = async ({ can }) => {
  const view = await apiFetch("/assembly/ready/dashboard");
  return {
    body: () => (
      <>
        <ReadyNav active="ready" />
        <ReadyDashboard initial={view} canShip={can("assembly.shipment.manage")} act={rs} />
      </>
    ),
  };
};

const finishedGoods = async () => {
  const view = await apiFetch("/assembly/ready/stock");
  return {
    body: () => (
      <>
        <ReadyNav active="stock" />
        <FinishedGoods initial={view} act={rs} />
      </>
    ),
  };
};

const sh = {
  dashboard: actions.shipmentDashboard,
  history: actions.shipmentHistory,
  entry: actions.shipmentEntry,
  create: actions.shipmentCreate,
  confirm: actions.shipmentConfirm,
  ship: actions.shipmentShip,
  cancel: actions.shipmentCancel,
  correct: actions.shipmentCorrect,
  report: actions.shipmentReport,
};
const shipCan = (can) => ({ ship: can("assembly.shipment.manage"), supervise: can("assembly.shipment.supervise") });

const shipmentDashboard = async ({ can }) => {
  const view = await apiFetch("/assembly/shipments/dashboard");
  return {
    body: () => (
      <>
        <ShipmentNav active="dashboard" />
        <ShipmentDashboard initial={view} can={shipCan(can)} act={sh} />
      </>
    ),
  };
};

const shipmentEntry = async ({ can, query }) => {
  const view = await apiFetch("/assembly/shipments/entry");
  const pick = query?.po || query?.colour ? { po: String(query.po ?? ""), colour: String(query.colour ?? "") } : null;
  return {
    body: () => (
      <>
        <ShipmentNav active="entry" />
        <ShipmentEntry initial={view} pick={pick} can={shipCan(can)} act={sh} />
      </>
    ),
  };
};

const markShipped = async ({ can }) => {
  const view = await apiFetch("/assembly/shipments/entry");
  return {
    body: () => (
      <>
        <ShipmentNav active="mark" />
        <MarkShipped initial={view} can={shipCan(can)} act={sh} />
      </>
    ),
  };
};

const shipmentHistory = async ({ can }) => {
  const view = await apiFetch("/assembly/shipments/history");
  return {
    body: () => (
      <>
        <ShipmentNav active="history" />
        <ShipmentHistory initial={view} can={shipCan(can)} act={sh} />
      </>
    ),
  };
};

const shipmentReports = async () => {
  const entry = await apiFetch("/assembly/shipments/entry");
  return {
    body: () => (
      <>
        <ShipmentNav active="reports" />
        <PackingReports kinds={entry.reports} today={entry.today} act={{ report: sh.report }} prefix="sh.rep" stem="shipment" />
      </>
    ),
  };
};

const ta = {
  stock: actions.trimsStock,
  receipts: actions.trimsReceipts,
  receipt: actions.trimsReceipt,
  saveReceipt: actions.trimsSaveReceipt,
  receiptAction: actions.trimsReceiptAction,
  issues: actions.trimsIssues,
  issue: actions.trimsIssue,
  saveIssue: actions.trimsSaveIssue,
  issueAction: actions.trimsIssueAction,
  returns: actions.trimsReturns,
  return: actions.trimsReturn,
  saveReturn: actions.trimsSaveReturn,
  returnAction: actions.trimsReturnAction,
  returnSource: actions.trimsReturnSource,
};

const trimsReceive = async ({ can }) => {
  const [options, rows] = await Promise.all([apiFetch("/assembly/trims/options"), apiFetch("/assembly/trims/receipts")]);
  return { body: () => <TrimsReceiptDesk initial={rows} options={options} canManage={can("assembly.trims.manage")} act={ta} /> };
};

const trimsIssue = async ({ can }) => {
  const [options, rows] = await Promise.all([apiFetch("/assembly/trims/options"), apiFetch("/assembly/trims/issues")]);
  return { body: () => <TrimsIssueDesk initial={rows} options={options} canManage={can("assembly.trims.manage")} canApprove={can("assembly.trims.approve")} act={ta} /> };
};

const trimsReturn = async ({ can }) => {
  const [options, rows, receipts] = await Promise.all([apiFetch("/assembly/trims/options"), apiFetch("/assembly/trims/returns"), apiFetch("/assembly/trims/receipts")]);
  return { body: () => <TrimsReturnDesk initial={rows} receipts={receipts} options={options} canManage={can("assembly.trims.manage")} act={ta} /> };
};

const ar = { run: actions.assemblyReport };

const garmentsReports = async () => {
  const options = await apiFetch("/assembly/reports/options");
  return { body: () => <AssemblyReports options={options} act={ar} /> };
};

const SCREENS = {
  "sewing-dashboard": dashboard,
  "bundle-in": bundleIn,
  "bundle-out": bundleOut,
  "finishing-dashboard": finishingDashboard,
  "finishing-bundle-in": finishingDesk("in"),
  "finishing-bundle-out": finishingDesk("out"),
  "quality-dashboard": qualityDashboard,
  "quality-in": qualityDesk("in"),
  "quality-inspection": qualityDesk("inspect"),
  "quality-out": qualityDesk("out"),
  "packing-dashboard": packingDashboard,
  "packing-in": packingIn,
  "packing-folding": packingStep("FOLDING"),
  "packing-poly": packingStep("POLY"),
  "packing-barcode-label": packingStep("LABEL"),
  "packing-assortment": packingStep("ASSORTMENT"),
  "packing-carton": packingStep("CARTON"),
  "packing-carton-marking": packingStep("MARKING"),
  "packing-carton-sealing": packingStep("SEALING"),
  "packing-out": packingOut,
  "packing-reports": packingReports,
  "ready-dashboard": readyDashboard,
  "finished-goods-stock": finishedGoods,
  "shipment-dashboard": shipmentDashboard,
  "shipment-entry": shipmentEntry,
  "shipment-mark-shipped": markShipped,
  "shipment-history": shipmentHistory,
  "shipment-reports": shipmentReports,
  "trims-accessories-receive": trimsReceive,
  "trims-accessories-issue": trimsIssue,
  "trims-accessories-receive-return-list": trimsReturn,
  "garments-reports": garmentsReports,
};

export { SCREENS };
