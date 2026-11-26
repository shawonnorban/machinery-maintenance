"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Dev Sample Status (AnnotechERP 31; Column Setup 2026-11-21): every call the
 * screens make after the page is drawn — the sheet's writes (new samples,
 * changed ones, Copy, Delete), the header-filter lists and Column Setup —
 * through one `dsAction`. The image goes through /api/dev-sample/image.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

const CALLS = {
  store: (rows) => ["/dev-sample/samples", json("POST", { rows })],
  update: (rows) => ["/dev-sample/samples", json("PATCH", { rows })],
  copy: (ids) => ["/dev-sample/samples/copy", json("POST", { ids })],
  remove: (ids) => ["/dev-sample/samples", json("DELETE", { ids })],
  distinct: (qs, column) => [`/dev-sample/samples/distinct?${qs ? `${qs}&` : ""}column=${encodeURIComponent(column)}`, { method: "GET" }],
  checkFormula: (body) => ["/dev-sample/columns/check", json("POST", body)],
  saveColumn: (body, key) => [key ? `/dev-sample/columns/${key}` : "/dev-sample/columns", json(key ? "PATCH" : "POST", body)],
  freeze: (key) => ["/dev-sample/columns/freeze", json("PUT", { key })],
  columnVisibility: (key, hidden) => [`/dev-sample/columns/${key}/visibility`, json("PATCH", { hidden })],
  deleteColumn: (key) => [`/dev-sample/columns/${key}`, json("DELETE")],
  saveGroup: (body, id) => [id ? `/dev-sample/groups/${id}` : "/dev-sample/groups", json(id ? "PATCH" : "POST", body)],
  history: () => ["/dev-sample/columns/history", { method: "GET" }],
};

const READS = new Set(["distinct", "checkFormula", "history"]);

async function dsAction(name, ...args) {
  const call = CALLS[name];
  if (!call) return { status: "error", message: `Unknown action ${name}` };
  try {
    const [path, options] = call(...args);
    const data = await apiFetch(path, options);
    if (!READS.has(name)) revalidatePath("/dev-sample-status", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { dsAction };
