"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Pre-Budget (AnnotechERP 33/34): every call the requisition screens make
 * after the page is drawn — the sheet's writes, the header-filter lists,
 * deleting a draft and Column Setup — through one `pbAction`. The artwork
 * goes through /api/pre-budget/artwork, the draft auto-save through
 * /api/pre-budget/drafts.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

const CALLS = {
  // draft_no / client_key: the draft the page was saving into (either one finds it), dropped once completed.
  store: (rows, status, draftNo, clientKey) => ["/pre-budget/requisitions", json("POST", { rows, status: "COMPLETE", draft_no: draftNo ?? null, client_key: clientKey ?? null })],
  update: (rows) => ["/pre-budget/requisitions", json("PATCH", { rows })],
  deleteDraft: (no) => [`/pre-budget/drafts/${no}`, json("DELETE")],
  createBudget: (ids) => ["/pre-budget/budgets", json("POST", { requisition_ids: ids })],
  saveBudget: (no, body) => [`/pre-budget/budgets/${encodeURIComponent(no)}`, json("PATCH", body)],
  addStyles: (no, ids) => [`/pre-budget/budgets/${encodeURIComponent(no)}/styles`, json("POST", { requisition_ids: ids })],
  removeStyles: (no, ids) => [`/pre-budget/budgets/${encodeURIComponent(no)}/styles`, json("DELETE", { requisition_ids: ids })],
  saveCosting: (no, body) => [`/pre-budget/budgets/${encodeURIComponent(no)}/costing`, json("PUT", body)],
  saveStock: (no, body) => [`/pre-budget/budgets/${encodeURIComponent(no)}/stock`, json("PUT", body)],
  createBom: (no, body) => [`/pre-budget/budgets/${encodeURIComponent(no)}/boms`, json("POST", body)],
  saveBom: (no, body) => [`/pre-budget/boms/${encodeURIComponent(no)}`, json("PUT", body)],
  deleteBom: (no) => [`/pre-budget/boms/${encodeURIComponent(no)}`, json("DELETE")],
  finalizeBudget: (no) => [`/pre-budget/budgets/${encodeURIComponent(no)}/finalize`, json("POST")],
  withdrawBudget: (no) => [`/pre-budget/budgets/${encodeURIComponent(no)}/withdraw`, json("POST", {})],
  decideBudget: (no, body) => [`/pre-budget/budgets/${encodeURIComponent(no)}/decision`, json("POST", body)],
  reviseBudget: (no) => [`/pre-budget/budgets/${encodeURIComponent(no)}/revise`, json("POST")],
  deleteBudget: (no) => [`/pre-budget/budgets/${encodeURIComponent(no)}`, json("DELETE")],
  budgetCandidates: (no) => [`/pre-budget/budgets/candidates?budget=${encodeURIComponent(no)}`, { method: "GET" }],
  copy: (ids) => ["/pre-budget/requisitions/copy", json("POST", { ids })],
  // Add Requisition's order picker: the cells a picked Order Summary order fills (its artwork copied; nothing else saved).
  pickOrder: (id) => ["/pre-budget/orders/pick", json("POST", { order_id: id })],
  remove: (ids) => ["/pre-budget/requisitions", json("DELETE", { ids })],
  distinct: (qs, column) => [`/pre-budget/requisitions/distinct?${qs ? `${qs}&` : ""}column=${encodeURIComponent(column)}`, { method: "GET" }],
  checkFormula: (body) => ["/pre-budget/columns/check", json("POST", body)],
  saveColumn: (body, key) => [key ? `/pre-budget/columns/${key}` : "/pre-budget/columns", json(key ? "PATCH" : "POST", body)],
  freeze: (key) => ["/pre-budget/columns/freeze", json("PUT", { key })],
  columnVisibility: (key, hidden) => [`/pre-budget/columns/${key}/visibility`, json("PATCH", { hidden })],
  deleteColumn: (key) => [`/pre-budget/columns/${key}`, json("DELETE")],
  saveGroup: (body, id) => [id ? `/pre-budget/groups/${id}` : "/pre-budget/groups", json(id ? "PATCH" : "POST", body)],
  history: () => ["/pre-budget/columns/history", { method: "GET" }],
  // Acc Budget Column Setup: the same calls on the BOM worksheet, and its Unit Cost Rules.
  bom_checkFormula: (body) => ["/pre-budget/bom-columns/check", json("POST", body)],
  bom_saveColumn: (body, key) => [key ? `/pre-budget/bom-columns/${key}` : "/pre-budget/bom-columns", json(key ? "PATCH" : "POST", body)],
  bom_freeze: (key) => ["/pre-budget/bom-columns/freeze", json("PUT", { key })],
  bom_columnVisibility: (key, hidden) => [`/pre-budget/bom-columns/${key}/visibility`, json("PATCH", { hidden })],
  bom_deleteColumn: (key) => [`/pre-budget/bom-columns/${key}`, json("DELETE")],
  bom_saveGroup: (body, id) => [id ? `/pre-budget/bom-columns/groups/${id}` : "/pre-budget/bom-columns/groups", json(id ? "PATCH" : "POST", body)],
  bom_history: () => ["/pre-budget/bom-columns/history", { method: "GET" }],
  saveUnitRule: (uom, body) => [`/pre-budget/bom-columns/unit-rules/${encodeURIComponent(uom)}`, json("PUT", body)],
  deleteUnitRule: (uom) => [`/pre-budget/bom-columns/unit-rules/${encodeURIComponent(uom)}`, json("DELETE")],
};

const READS = new Set(["pickOrder", "distinct", "checkFormula", "history", "budgetCandidates", "bom_checkFormula", "bom_history"]);

async function pbAction(name, ...args) {
  const call = CALLS[name];
  if (!call) return { status: "error", message: `Unknown action ${name}` };
  try {
    const [path, options] = call(...args);
    const data = await apiFetch(path, options);
    if (!READS.has(name)) {
      revalidatePath("/pre-budget", "layout");
      revalidatePath("/pre-budget-master", "layout");
    }
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { pbAction };
