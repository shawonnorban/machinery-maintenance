"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Order Summary (AnnotechERP 32): every call the screens make after the page
 * is drawn — the sheet's writes and its header-filter lists, Column Setup,
 * Merchandising Team and Mail Schedule — through one `osAction`. The artwork
 * goes through /api/order-summary/artwork.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

const CALLS = {
  store: (rows) => ["/order-summary/orders", json("POST", { rows })],
  update: (rows) => ["/order-summary/orders", json("PATCH", { rows })],
  bulkUpdate: (ids, values) => ["/order-summary/orders/bulk-update", json("POST", { ids, values })],
  copy: (ids) => ["/order-summary/orders/copy", json("POST", { ids })],
  remove: (ids) => ["/order-summary/orders", json("DELETE", { ids })],
  distinct: (qs, column) => [`/order-summary/orders/distinct?${qs ? `${qs}&` : ""}column=${encodeURIComponent(column)}`, { method: "GET" }],
  orders: (qs) => [`/order-summary/orders?${qs}`, { method: "GET" }],
  checkFormula: (body) => ["/order-summary/columns/check", json("POST", body)],
  saveColumn: (body, key) => [key ? `/order-summary/columns/${key}` : "/order-summary/columns", json(key ? "PATCH" : "POST", body)],
  freeze: (key) => ["/order-summary/columns/freeze", json("PUT", { key })],
  columnVisibility: (key, hidden) => [`/order-summary/columns/${key}/visibility`, json("PATCH", { hidden })],
  deleteColumn: (key) => [`/order-summary/columns/${key}`, json("DELETE")],
  saveGroup: (body, id) => [id ? `/order-summary/groups/${id}` : "/order-summary/groups", json(id ? "PATCH" : "POST", body)],
  history: () => ["/order-summary/columns/history", { method: "GET" }],
  saveTeam: (body, id) => [id ? `/order-summary/teams/${id}` : "/order-summary/teams", json(id ? "PATCH" : "POST", body)],
  deleteTeam: (id) => [`/order-summary/teams/${id}`, json("DELETE")],
  saveHeads: (body) => ["/order-summary/department-heads", json("PUT", body)],
  saveMail: (body) => ["/order-summary/mail-settings", json("PUT", body)],
  sendNow: () => ["/order-summary/mail-settings/send-now", json("POST", {})],
};

const READS = new Set(["distinct", "orders", "checkFormula", "history"]);

async function osAction(name, ...args) {
  const call = CALLS[name];
  if (!call) return { status: "error", message: `Unknown action ${name}` };
  try {
    const [path, options] = call(...args);
    const data = await apiFetch(path, options);
    if (!READS.has(name)) revalidatePath("/order-summary", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { osAction };
