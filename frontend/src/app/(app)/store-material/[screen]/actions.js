"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Inventory & Stores → Store & Material (AnnotechERP 09): every read and write
 * the screens make in place — the dashboard, the warehouses and the ledger by
 * their filters; a PR fetched for receiving; a GRN against it or direct; a
 * GRN reversed; an employee found by ID or card.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

async function storeAction(op, ...args) {
  const base = "/store-material";
  const q = (query = {}) =>
    Object.keys(query).length ? `?${new URLSearchParams(query)}` : "";
  const calls = {
    dashboard: ([f]) => [`${base}/dashboard${q(f)}`, { method: "GET" }],
    fabric: ([f]) => [`${base}/fabric-warehouse${q(f)}`, { method: "GET" }],
    trims: ([f]) => [`${base}/trims-warehouse${q(f)}`, { method: "GET" }],
    ledger: ([f]) => [`${base}/stock-ledger${q(f)}`, { method: "GET" }],
    employee: ([id]) => [`${base}/employee${q({ id })}`, { method: "GET" }],
    requisition: ([pr]) => [
      `${base}/requisitions/${encodeURIComponent(pr)}`,
      { method: "GET" },
    ],
    againstPr: ([payload]) => [
      `${base}/grns/against-pr`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    direct: ([payload]) => [
      `${base}/grns/direct`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    reverse: ([grn, payload]) => [
      `${base}/grns/${encodeURIComponent(grn)}/reverse`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    materialOut: ([payload]) => [
      `${base}/material-outs`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    materialOutOf: ([mo]) => [
      `${base}/material-outs/${encodeURIComponent(mo)}`,
      { method: "GET" },
    ],
    materialReturn: ([payload]) => [
      `${base}/material-returns`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    adjust: ([payload]) => [
      `${base}/adjustments`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    decide: ([no, payload]) => [
      `${base}/adjustments/${encodeURIComponent(no)}/decide`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    minLevel: ([payload]) => [
      `${base}/min-levels`,
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    trace: ([f]) => [`${base}/traceability${q(f)}`, { method: "GET" }],
    report: ([key, f]) => [
      `${base}/reports/${encodeURIComponent(key)}${q(f)}`,
      { method: "GET" },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown store call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/store-material", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { storeAction };
