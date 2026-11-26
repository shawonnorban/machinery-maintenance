"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Export Statement (AnnotechERP 35): every call the screens make after the
 * page is drawn — the sheet's writes (new statements, changed ones, Delete),
 * the header-filter lists, the approval decisions and Column Setup — through
 * one `esAction`. Excel goes through /api/export-statement/{export,import}.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

const CALLS = {
  store: (rows) => ["/export-statement/statements", json("POST", { rows })],
  update: (rows) => ["/export-statement/statements", json("PATCH", { rows })],
  remove: (ids) => ["/export-statement/statements", json("DELETE", { ids })],
  distinct: (qs, column) => [`/export-statement/statements/distinct?${qs ? `${qs}&` : ""}column=${encodeURIComponent(column)}`, { method: "GET" }],
  decide: (id, action, comment) => [`/export-statement/edits/${id}/decision`, json("POST", { action, comment: comment || null })],
  withdraw: (id) => [`/export-statement/edits/${id}/withdraw`, json("POST", {})],
  checkFormula: (body) => ["/export-statement/columns/check", json("POST", body)],
  saveColumn: (body, key) => [key ? `/export-statement/columns/${key}` : "/export-statement/columns", json(key ? "PATCH" : "POST", body)],
  freeze: (key) => ["/export-statement/columns/freeze", json("PUT", { key })],
  columnVisibility: (key, hidden) => [`/export-statement/columns/${key}/visibility`, json("PATCH", { hidden })],
  deleteColumn: (key) => [`/export-statement/columns/${key}`, json("DELETE")],
  saveGroup: (body, id) => [id ? `/export-statement/groups/${id}` : "/export-statement/groups", json(id ? "PATCH" : "POST", body)],
  history: () => ["/export-statement/columns/history", { method: "GET" }],
};

const READS = new Set(["distinct", "checkFormula", "history"]);

async function esAction(name, ...args) {
  const call = CALLS[name];
  if (!call) return { status: "error", message: `Unknown action ${name}` };
  try {
    const [path, options] = call(...args);
    const data = await apiFetch(path, options);
    if (!READS.has(name)) revalidatePath("/export-statement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { esAction };
