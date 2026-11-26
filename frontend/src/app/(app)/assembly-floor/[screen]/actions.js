"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Assembly Floor → Sewing (AnnotechERP 20/01): a scanned card resolved
 * against its line, the Bundle IN and Bundle OUT desks — a day's scans,
 * a scan, the withdrawals — and the Sewing Dashboard. Finishing (20/02):
 * a line's progress, a bundle resolved at a desk, today's scans, Bundle IN,
 * Bundle OUT, the rework status, the Finishing Dashboard and a trace.
 * Packing (20/04) and its Packing Process (20/05): the Packing In desk, a
 * scan previewed and confirmed or removed, a process desk and a batch, the
 * seven processes (start, complete, reopen, labels, cartons, marking,
 * sealing), Packing Out, the Packing Dashboard, a trace and the reports.
 * Ready For Shipment (20/06): the dashboard, a row's detail and the
 * finished goods stock. Shipment (20/07): the dashboard, entry, history,
 * a shipment entered, confirmed, shipped, cancelled or corrected, and the
 * reports. Accessories Store (20/08): trims receipts, issues and receipt
 * returns — listed, opened, saved and moved on — and a store's stock.
 * Reports (20/09): an Apparel Assembly Report run.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const query = (q) => new URLSearchParams(Object.entries(q ?? {}).filter(([, v]) => v !== "" && v !== null && v !== undefined)).toString();

async function get(path, q) {
  try {
    const s = query(q);
    return { status: "success", data: await apiFetch(`${path}${s ? `?${s}` : ""}`) };
  } catch (error) {
    return fail(error);
  }
}

async function post(path, payload) {
  try {
    const data = await apiFetch(path, { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/assembly-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function del(path) {
  try {
    const data = await apiFetch(path, { method: "DELETE" });
    revalidatePath("/assembly-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function resolveBundle(q) {
  return get("/assembly/sewing/resolve", q);
}

async function listIns(date) {
  return get("/assembly/sewing/ins", { date });
}

async function receiveBundle(payload) {
  return post("/assembly/sewing/ins", payload);
}

async function withdrawIns(bundles) {
  return post("/assembly/sewing/ins/withdraw", { bundles });
}

async function listOuts(date) {
  return get("/assembly/sewing/outs", { date });
}

async function sendOutBundle(payload) {
  return post("/assembly/sewing/outs", payload);
}

async function withdrawOuts(bundles) {
  return post("/assembly/sewing/outs/withdraw", { bundles });
}

async function sewingBoard(q) {
  return get("/assembly/sewing/dashboard", q);
}

async function finishingLine(lineId) {
  return get("/assembly/finishing/line", { line_id: lineId });
}

async function finishingResolve(q) {
  return get("/assembly/finishing/resolve", q);
}

async function finishingRecent(kind) {
  return get("/assembly/finishing/recent", { kind });
}

async function finishingReceive(payload) {
  return post("/assembly/finishing/ins", payload);
}

async function finishingSendOut(payload) {
  return post("/assembly/finishing/outs", payload);
}

async function finishingRework(payload) {
  return post("/assembly/finishing/rework", payload);
}

async function finishingBoard(q) {
  return get("/assembly/finishing/dashboard", q);
}

async function finishingTrace(code) {
  return get("/assembly/finishing/trace", { code });
}

async function qualityLine(lineId) {
  return get("/assembly/quality/line", { line_id: lineId });
}

async function qualityResolve(q) {
  return get("/assembly/quality/resolve", q);
}

async function qualityRecent(kind) {
  return get("/assembly/quality/recent", { kind });
}

async function qualityReceive(payload) {
  return post("/assembly/quality/ins", payload);
}

async function qualityInspect(payload) {
  return post("/assembly/quality/inspections", payload);
}

async function qualitySendOut(payload) {
  return post("/assembly/quality/outs", payload);
}

async function qualityRework(payload) {
  return post("/assembly/quality/rework", payload);
}

async function qualityBoard(q) {
  return get("/assembly/quality/dashboard", q);
}

async function qualityTrace(code) {
  return get("/assembly/quality/trace", { code });
}

export { qualityLine, qualityResolve, qualityRecent, qualityReceive, qualityInspect, qualitySendOut, qualityRework, qualityBoard, qualityTrace };
export { resolveBundle, listIns, receiveBundle, withdrawIns, listOuts, sendOutBundle, withdrawOuts, sewingBoard, finishingLine, finishingResolve, finishingRecent, finishingReceive, finishingSendOut, finishingRework, finishingBoard, finishingTrace };

const enc = encodeURIComponent;

async function packingInDesk() {
  return get("/assembly/packing/in");
}

async function packingPreviewIn(lineId, code) {
  return get("/assembly/packing/in/preview", { line_id: lineId, code });
}

async function packingConfirmIn(payload) {
  return post("/assembly/packing/ins", payload);
}

async function packingRemoveIn(id) {
  return del(`/assembly/packing/ins/${enc(id)}`);
}

async function packingProcess(step) {
  return get("/assembly/packing/process", { step });
}

async function packingFind(code) {
  return get("/assembly/packing/find", { code });
}

async function packingBatch(id) {
  return get(`/assembly/packing/batches/${enc(id)}`);
}

async function packingStep(id, step, action, payload = {}) {
  return post(`/assembly/packing/batches/${enc(id)}/steps/${enc(step)}/${enc(action)}`, payload);
}

async function packingPrintLabels(id) {
  return post(`/assembly/packing/batches/${enc(id)}/labels/print`, {});
}

async function packingVerifyLabel(id, barcode) {
  return post(`/assembly/packing/batches/${enc(id)}/labels/verify`, { barcode });
}

async function packingAddCartons(id, payload) {
  return post(`/assembly/packing/batches/${enc(id)}/cartons`, payload);
}

async function packingDeleteCarton(cartonId) {
  return del(`/assembly/packing/cartons/${enc(cartonId)}`);
}

async function packingSaveMarking(id, payload) {
  return post(`/assembly/packing/batches/${enc(id)}/marking`, payload);
}

async function packingPrintMarking(id) {
  return post(`/assembly/packing/batches/${enc(id)}/marking/print`, {});
}

async function packingSeal(id, payload) {
  return post(`/assembly/packing/batches/${enc(id)}/seal`, payload);
}

async function packingUnseal(cartonId, reason) {
  return post(`/assembly/packing/cartons/${enc(cartonId)}/unseal`, { reason });
}

async function packingOutDesk() {
  return get("/assembly/packing/out");
}

async function packingPreviewOut(code) {
  return get("/assembly/packing/out/preview", { code });
}

async function packingConfirmOut(payload) {
  return post("/assembly/packing/outs", payload);
}

async function packingTrace(code) {
  return get("/assembly/packing/trace", { code });
}

async function packingReport(q) {
  return get("/assembly/packing/reports", q);
}

export {
  packingInDesk,
  packingPreviewIn,
  packingConfirmIn,
  packingRemoveIn,
  packingProcess,
  packingFind,
  packingBatch,
  packingStep,
  packingPrintLabels,
  packingVerifyLabel,
  packingAddCartons,
  packingDeleteCarton,
  packingSaveMarking,
  packingPrintMarking,
  packingSeal,
  packingUnseal,
  packingOutDesk,
  packingPreviewOut,
  packingConfirmOut,
  packingTrace,
  packingReport,
};

async function readyDashboard(q) {
  return get("/assembly/ready/dashboard", q);
}

async function readyDetail(key) {
  return get("/assembly/ready/detail", { key });
}

async function readyStock(q) {
  return get("/assembly/ready/stock", q);
}

export { readyDashboard, readyDetail, readyStock };

async function shipmentDashboard(q) {
  return get("/assembly/shipments/dashboard", q);
}

async function shipmentHistory(q) {
  return get("/assembly/shipments/history", q);
}

async function shipmentEntry() {
  return get("/assembly/shipments/entry");
}

async function shipmentCreate(payload) {
  return post("/assembly/shipments", payload);
}

async function shipmentConfirm(id) {
  return post(`/assembly/shipments/${enc(id)}/confirm`, {});
}

async function shipmentShip(id) {
  return post(`/assembly/shipments/${enc(id)}/ship`, {});
}

async function shipmentCancel(id, reason) {
  return post(`/assembly/shipments/${enc(id)}/cancel`, { reason });
}

async function shipmentCorrect(id, payload) {
  return post(`/assembly/shipments/${enc(id)}/correct`, payload);
}

async function shipmentReport(q) {
  return get("/assembly/shipments-reports", q);
}

export { shipmentDashboard, shipmentHistory, shipmentEntry, shipmentCreate, shipmentConfirm, shipmentShip, shipmentCancel, shipmentCorrect, shipmentReport };

async function trimsStock(storeId) {
  return get("/assembly/trims/stock", { store_id: storeId });
}

async function trimsReceipts() {
  return get("/assembly/trims/receipts");
}

async function trimsReceipt(no) {
  return get(`/assembly/trims/receipts/${enc(no)}`);
}

async function trimsSaveReceipt(no, payload) {
  return post(no ? `/assembly/trims/receipts/${enc(no)}` : "/assembly/trims/receipts", payload);
}

async function trimsReceiptAction(no, action) {
  return post(`/assembly/trims/receipts/${enc(no)}/${enc(action)}`, {});
}

async function trimsIssues() {
  return get("/assembly/trims/issues");
}

async function trimsIssue(no) {
  return get(`/assembly/trims/issues/${enc(no)}`);
}

async function trimsSaveIssue(no, payload) {
  return post(no ? `/assembly/trims/issues/${enc(no)}` : "/assembly/trims/issues", payload);
}

async function trimsIssueAction(no, action, payload = {}) {
  return post(`/assembly/trims/issues/${enc(no)}/${enc(action)}`, payload);
}

async function trimsReturns() {
  return get("/assembly/trims/returns");
}

async function trimsReturn(no) {
  return get(`/assembly/trims/returns/${enc(no)}`);
}

async function trimsSaveReturn(no, payload) {
  return post(no ? `/assembly/trims/returns/${enc(no)}` : "/assembly/trims/returns", payload);
}

async function trimsReturnAction(no, action) {
  return post(`/assembly/trims/returns/${enc(no)}/${enc(action)}`, {});
}

async function trimsReturnSource(mrr, except) {
  return get(`/assembly/trims/returns/source/${enc(mrr)}`, { except });
}

export {
  trimsStock,
  trimsReceipts,
  trimsReceipt,
  trimsSaveReceipt,
  trimsReceiptAction,
  trimsIssues,
  trimsIssue,
  trimsSaveIssue,
  trimsIssueAction,
  trimsReturns,
  trimsReturn,
  trimsSaveReturn,
  trimsReturnAction,
  trimsReturnSource,
};

async function assemblyReport(q) {
  return get("/assembly/reports/run", q);
}

export { assemblyReport };
