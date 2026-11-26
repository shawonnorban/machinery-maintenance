"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Dye House → Master Data (AnnotechERP 17/01): the screen kit's resources,
 * the colour group grid, buyers' audit checks and subcontract quotations;
 * Lab-Dip (17/02): dosing templates by their code.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/dye-house", "layout");
  return { status: "success", ...extra };
}

function body(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries()) if (key !== "__intent") out[key] = value === "" ? null : value;
  for (const key of booleans) if (formData.has(key)) out[key] = formData.get(key) === "1";
  return out;
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, { method: "POST", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function saveColourGroups(groups) {
  try {
    await apiFetch("/dyehouse/colour-groups", { method: "PUT", body: JSON.stringify({ groups }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadAuditChecks(buyer) {
  try {
    return { status: "success", rows: await apiFetch(`/dyehouse/buyers/${buyer}/audit-checks`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveAuditChecks(buyer, checks) {
  try {
    const rows = await apiFetch(`/dyehouse/buyers/${buyer}/audit-checks`, { method: "PUT", body: JSON.stringify({ checks }) });
    return done({ rows });
  } catch (error) {
    return fail(error);
  }
}

async function loadQuotation(id) {
  try {
    return { status: "success", quotation: await apiFetch(`/dyehouse/sc-quotations/${id}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadHeads(group) {
  try {
    return { status: "success", heads: await apiFetch(`/dyehouse/sc-quotations/heads?group=${group}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveQuotation(id, payload) {
  try {
    const quotation = await apiFetch(id ? `/dyehouse/sc-quotations/${id}` : "/dyehouse/sc-quotations", { method: id ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ quotation });
  } catch (error) {
    return fail(error);
  }
}

async function loadTemplate(code) {
  try {
    return { status: "success", template: await apiFetch(`/dyehouse/dosing-templates/${encodeURIComponent(code)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveTemplate(code, payload) {
  try {
    const template = await apiFetch(code ? `/dyehouse/dosing-templates/${encodeURIComponent(code)}` : "/dyehouse/dosing-templates", { method: code ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ template });
  } catch (error) {
    return fail(error);
  }
}

async function deleteTemplate(code) {
  try {
    await apiFetch(`/dyehouse/dosing-templates/${encodeURIComponent(code)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/* Batch (Bulk) (17/03): the board by plan code, cards by batch number, requisitions by their number. */

const enc = encodeURIComponent;

/** A read that answers its data, for the client screens. */
async function read(path, key) {
  try {
    return { status: "success", [key]: await apiFetch(path) };
  } catch (error) {
    return fail(error);
  }
}

/** A write that answers what the server returned, under `key`. */
async function write(path, method, payload, key = "data") {
  try {
    const data = await apiFetch(path, { method, ...(payload === undefined ? {} : { body: JSON.stringify(payload) }) });
    return done({ [key]: data });
  } catch (error) {
    return fail(error);
  }
}

async function loadBoard(code, params) {
  return read(`/dyehouse/batch-plans/${enc(code)}/board?${new URLSearchParams(Object.entries(params).filter(([, v]) => v !== "" && v !== null && v !== undefined))}`, "board");
}

async function pullBatches(code, payload) {
  return write(`/dyehouse/batch-plans/${enc(code)}/pull`, "POST", payload);
}

async function placeBatch(code, payload) {
  return write(`/dyehouse/batch-plans/${enc(code)}/bars`, "POST", payload);
}

async function clearBar(code, batchNo) {
  return write(`/dyehouse/batch-plans/${enc(code)}/bars/${enc(batchNo)}`, "DELETE");
}

async function saveBoardView(code, payload) {
  return write(`/dyehouse/batch-plans/${enc(code)}/view`, "PUT", payload);
}

async function loadBatch(no) {
  return read(`/dyehouse/batches/${enc(no)}`, "batch");
}

async function splitBatch(no, payload) {
  return write(`/dyehouse/batches/${enc(no)}/split`, "POST", payload);
}

async function loadIssue(reqNo) {
  return read(`/dyehouse/grey-indents/${enc(reqNo)}/issue`, "sheet");
}

async function issueGrey(reqNo, payload) {
  return write(`/dyehouse/grey-indents/${enc(reqNo)}/issue`, "POST", payload, "sheet");
}

async function makeLeftover(payload) {
  return write("/dyehouse/batch-leftover", "POST", payload);
}

/* Batch (Sample) (17/04): a card's production by its batch number, and the sample lists. */

async function loadProduction(no) {
  return read(`/dyehouse/batches/${enc(no)}/production`, "sheet");
}

async function saveProduction(no, payload) {
  return write(`/dyehouse/batches/${enc(no)}/production`, "POST", payload, "sheet");
}

/* Requisition (17/05): requisitions by their number (IR-DC-…), check-roll returns by theirs (CRR-…). */

async function loadRequisition(no) {
  return read(`/dyehouse/chem-requisitions/${enc(no)}`, "requisition");
}

async function saveRequisition(no, payload) {
  return write(no ? `/dyehouse/chem-requisitions/${enc(no)}` : "/dyehouse/chem-requisitions", no ? "PUT" : "POST", payload, "requisition");
}

async function deleteRequisition(no) {
  return write(`/dyehouse/chem-requisitions/${enc(no)}`, "DELETE");
}

async function issueRequisition(no) {
  return write(`/dyehouse/chem-requisitions/${enc(no)}/issue`, "POST", {});
}

async function approveRequisition(no) {
  return write(`/dyehouse/chem-requisitions/${enc(no)}/approve`, "POST", {});
}

async function rejectRequisition(no) {
  return write(`/dyehouse/chem-requisitions/${enc(no)}/reject`, "POST", {});
}

async function sendBackReturn(no) {
  return write(`/dyehouse/check-roll-returns/${enc(no)}/back`, "POST", {});
}

async function previewSampleList(kind, from, to) {
  return read(`/dyehouse/sample/lists/${enc(kind)}?${new URLSearchParams({ from, to })}`, "data");
}

/* Chemical Store (17/06): receipts by MRR-/MRR-DC-/RTN-LP-, returns by RTN-, issues by REQ-ST-/IS-…, statements by CS-. */

async function loadChemReceipt(no) {
  return read(`/dyehouse/chem-receipts/${enc(no)}`, "receipt");
}

async function saveChemReceipt(no, payload) {
  return write(no ? `/dyehouse/chem-receipts/${enc(no)}` : "/dyehouse/chem-receipts", no ? "PUT" : "POST", payload, "receipt");
}

async function finaliseChemReceipt(no) {
  return write(`/dyehouse/chem-receipts/${enc(no)}/finalise`, "POST", {});
}

async function verifyChemReceipt(no) {
  return write(`/dyehouse/chem-receipts/${enc(no)}/verify`, "POST", {});
}

async function cancelChemReceipt(no) {
  return write(`/dyehouse/chem-receipts/${enc(no)}/cancel`, "POST", {});
}

async function loadChemReturn(no) {
  return read(`/dyehouse/chem-returns/${enc(no)}`, "return");
}

async function loadChemReturnSource(mrrNo, exceptNo) {
  return read(`/dyehouse/chem-receipts/${enc(mrrNo)}/return-source${exceptNo ? `?except=${enc(exceptNo)}` : ""}`, "source");
}

async function saveChemReturn(no, payload) {
  return write(no ? `/dyehouse/chem-returns/${enc(no)}` : "/dyehouse/chem-returns", no ? "PUT" : "POST", payload, "return");
}

async function finaliseChemReturn(no) {
  return write(`/dyehouse/chem-returns/${enc(no)}/finalise`, "POST", {});
}

async function cancelChemReturn(no) {
  return write(`/dyehouse/chem-returns/${enc(no)}/cancel`, "POST", {});
}

async function loadChemIssue(no) {
  return read(`/dyehouse/chem-issues/${enc(no)}`, "issue");
}

async function saveChemIssue(no, payload) {
  return write(no ? `/dyehouse/chem-issues/${enc(no)}` : "/dyehouse/chem-issues", no ? "PUT" : "POST", payload, "issue");
}

async function verifyChemIssue(no) {
  return write(`/dyehouse/chem-issues/${enc(no)}/verify`, "POST", {});
}

async function issueChemStock(no, payload) {
  return write(`/dyehouse/chem-issues/${enc(no)}/issue`, "POST", payload, "issue");
}

async function cancelChemIssue(no) {
  return write(`/dyehouse/chem-issues/${enc(no)}/cancel`, "POST", {});
}

async function loadChemCs(no) {
  return read(`/dyehouse/chem-cs/${enc(no)}`, "statement");
}

async function saveChemCs(no, payload) {
  return write(no ? `/dyehouse/chem-cs/${enc(no)}` : "/dyehouse/chem-cs", no ? "PUT" : "POST", payload, "statement");
}

async function submitChemCs(no) {
  return write(`/dyehouse/chem-cs/${enc(no)}/submit`, "POST", {});
}

async function approveChemCs(no) {
  return write(`/dyehouse/chem-cs/${enc(no)}/approve`, "POST", {});
}

async function rejectChemCs(no) {
  return write(`/dyehouse/chem-cs/${enc(no)}/reject`, "POST", {});
}

async function closeChemCs(no) {
  return write(`/dyehouse/chem-cs/${enc(no)}/close`, "POST", {});
}

/** Chemical Month Closing's form: the period and remarks. */
async function closeChemMonth(previousState, formData) {
  try {
    await apiFetch("/dyehouse/chem-closings", { method: "POST", body: JSON.stringify(body(formData, [])) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/* Production Board (17/07): load times, wash productions by their requisition's number, bookings and trim programmes by theirs, closings by order and scope. */

async function saveProductionLoads(payload) {
  return write("/dyehouse/production-loads", "PUT", payload);
}

async function loadWash(reqNo) {
  return read(`/dyehouse/wash-productions/${enc(reqNo)}`, "sheet");
}

async function saveWash(reqNo, payload) {
  return write(`/dyehouse/wash-productions/${enc(reqNo)}`, "PUT", payload, "sheet");
}

async function loadBooking(no) {
  return read(`/dyehouse/fabric-bookings/${enc(no)}`, "paper");
}

async function saveBooking(no, payload) {
  return write(no ? `/dyehouse/fabric-bookings/${enc(no)}` : "/dyehouse/fabric-bookings", no ? "PUT" : "POST", payload, "paper");
}

async function loadTrim(no) {
  return read(`/dyehouse/trim-programmes/${enc(no)}`, "paper");
}

async function saveTrim(no, payload) {
  return write(no ? `/dyehouse/trim-programmes/${enc(no)}` : "/dyehouse/trim-programmes", no ? "PUT" : "POST", payload, "paper");
}

/** Dyeing & Finishing Closing's Close: the row's key is "order number|scope". */
async function closeDfOrder(key) {
  const [order_no, scope] = String(key).split("|");
  return write("/dyehouse/df-closings", "POST", { order_no, scope });
}

/* Dyeing Q.C (17/08): a card's check-roll sheet by its batch number, inspections by theirs (CRI-…), completions by the requisition's. */

async function loadCheckRoll(batchNo) {
  return read(`/dyehouse/batches/${enc(batchNo)}/check-roll`, "sheet");
}

async function saveInspection(no, payload) {
  return write(no ? `/dyehouse/check-roll-inspections/${enc(no)}` : "/dyehouse/check-roll-inspections", no ? "PUT" : "POST", payload, "inspection");
}

async function completeBatchProgramme(reqNo) {
  return write(`/dyehouse/batch-completions/${enc(reqNo)}`, "POST", {});
}

/* S/C Dyeing (17/09): programmes, requisitions, challans and receipts by their numbers. */

async function loadScProgramme(no) {
  return read(`/dyehouse/sc-programmes/${enc(no)}`, "programme");
}

async function saveScProgramme(no, payload) {
  return write(no ? `/dyehouse/sc-programmes/${enc(no)}` : "/dyehouse/sc-programmes", no ? "PUT" : "POST", payload, "programme");
}

async function loadScRequisition(no) {
  return read(`/dyehouse/sc-requisitions/${enc(no)}`, "requisition");
}

async function saveScRequisition(no, payload) {
  return write(no ? `/dyehouse/sc-requisitions/${enc(no)}` : "/dyehouse/sc-requisitions", no ? "PUT" : "POST", payload, "requisition");
}

/** A sub-contract indent shaped as the register's Issue sheet reads one. */
async function loadScIssue(no) {
  const res = await read(`/dyehouse/sc-requisitions/${enc(no)}`, "requisition");
  if (res.status !== "success") return res;
  const r = res.requisition;
  return {
    status: "success",
    issue: {
      issue_no: r.req_no,
      kind: "SC",
      store: r.store,
      to_store: r.supplier,
      req_type: `${r.program_no} · ${r.batch_no}`,
      lines: r.lines.map((l) => ({ id: l.id, item: l.shade, lot_no: r.batch_no, required_qty: l.req_kg, issued_qty: l.issued_kg, balance_qty: l.left_kg, in_store: l.left_kg, uom: "KG" })),
    },
  };
}

async function issueScRequisition(no, payload) {
  return write(`/dyehouse/sc-requisitions/${enc(no)}/issue`, "POST", { lines: payload.lines }, "issue");
}

async function loadScChallan(no) {
  return read(`/dyehouse/sc-challans/${enc(no)}`, "challan");
}

async function saveScChallan(no, payload) {
  return write(no ? `/dyehouse/sc-challans/${enc(no)}` : "/dyehouse/sc-challans", no ? "PUT" : "POST", payload, "challan");
}

async function loadScReceipt(no) {
  return read(`/dyehouse/sc-receipts/${enc(no)}`, "receipt");
}

async function saveScReceipt(no, payload) {
  return write(no ? `/dyehouse/sc-receipts/${enc(no)}` : "/dyehouse/sc-receipts", no ? "PUT" : "POST", payload, "receipt");
}

export {
  createResource,
  updateResource,
  deleteResource,
  saveColourGroups,
  loadAuditChecks,
  saveAuditChecks,
  loadQuotation,
  loadHeads,
  saveQuotation,
  loadTemplate,
  saveTemplate,
  deleteTemplate,
  loadBoard,
  pullBatches,
  placeBatch,
  clearBar,
  saveBoardView,
  loadBatch,
  splitBatch,
  loadIssue,
  issueGrey,
  makeLeftover,
  loadProduction,
  saveProduction,
  previewSampleList,
  loadRequisition,
  saveRequisition,
  deleteRequisition,
  issueRequisition,
  approveRequisition,
  rejectRequisition,
  sendBackReturn,
  loadChemReceipt,
  saveChemReceipt,
  finaliseChemReceipt,
  verifyChemReceipt,
  cancelChemReceipt,
  loadChemReturn,
  loadChemReturnSource,
  saveChemReturn,
  finaliseChemReturn,
  cancelChemReturn,
  loadChemIssue,
  saveChemIssue,
  verifyChemIssue,
  issueChemStock,
  cancelChemIssue,
  loadChemCs,
  saveChemCs,
  submitChemCs,
  approveChemCs,
  rejectChemCs,
  closeChemCs,
  closeChemMonth,
  saveProductionLoads,
  loadWash,
  saveWash,
  loadBooking,
  saveBooking,
  loadTrim,
  saveTrim,
  closeDfOrder,
  loadCheckRoll,
  saveInspection,
  completeBatchProgramme,
  loadScProgramme,
  saveScProgramme,
  loadScRequisition,
  saveScRequisition,
  loadScIssue,
  issueScRequisition,
  loadScChallan,
  saveScChallan,
  loadScReceipt,
  saveScReceipt,
};
