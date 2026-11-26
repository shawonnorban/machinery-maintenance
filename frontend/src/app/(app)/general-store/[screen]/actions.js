"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * General Store → Master Data (AnnotechERP 10/01): the screen kit's
 * resources, added and edited through the dialogs. Dashboard and Inventory
 * (10/02–10/03): every read and write the document, stock and ledger screens
 * make in place.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function body(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries())
    if (key !== "__intent") out[key] = value === "" ? null : value;
  for (const key of booleans)
    if (formData.has(key)) out[key] = formData.get(key) === "1";
  return out;
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, {
      method: "POST",
      body: JSON.stringify(body(formData, booleans)),
    });
    revalidatePath("/general-store", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify(body(formData, booleans)),
    });
    revalidatePath("/general-store", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function gsAction(op, ...args) {
  const base = "/general-store";
  const q = (query = {}) =>
    Object.keys(query).length ? `?${new URLSearchParams(query)}` : "";
  const doc = (no) => `${base}/documents/${encodeURIComponent(no)}`;
  const req = (no) => `${base}/requisitions/${encodeURIComponent(no)}`;
  const calls = {
    options: ([kind]) => [`${base}/${kind}/options`, { method: "GET" }],
    list: ([kind, f]) => [
      `${base}/${kind}/documents${q(f)}`,
      { method: "GET" },
    ],
    save: ([kind, payload]) => [
      `${base}/${kind}/documents`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    show: ([no]) => [doc(no), { method: "GET" }],
    returnable: ([no]) => [`${doc(no)}/returnable`, { method: "GET" }],
    cancel: ([no, payload]) => [
      `${doc(no)}/cancel`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    currentStock: ([f]) => [`${base}/current-stock${q(f)}`, { method: "GET" }],
    ledger: ([f]) => [`${base}/ledger${q(f)}`, { method: "GET" }],
    issuable: ([no]) => [`${req(no)}/issuable`, { method: "GET" }],
    reqList: ([list]) => [
      `${base}/requisitions?list=${list}`,
      { method: "GET" },
    ],
    reqShow: ([no]) => [req(no), { method: "GET" }],
    reqSave: ([payload]) => [
      `${base}/requisitions`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    reqUpdate: ([no, payload]) => [
      req(no),
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    reqSubmit: ([no]) => [`${req(no)}/submit`, { method: "POST", body: "{}" }],
    reqApprove: ([no, payload]) => [
      `${req(no)}/approve`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    reqReject: ([no, payload]) => [
      `${req(no)}/reject`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    reqCancel: ([no, payload]) => [
      `${req(no)}/cancel`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    reqPrinted: ([no, payload]) => [
      `${req(no)}/printed`,
      { method: "POST", body: JSON.stringify(payload ?? {}) },
    ],
    audit: ([qq]) => [
      `${base}/audit-log${q(qq ? { q: qq } : {})}`,
      { method: "GET" },
    ],
    saveSettings: ([payload]) => [
      `${base}/settings`,
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    report: ([key, f]) => [
      `${base}/reports/${encodeURIComponent(key)}${q(f)}`,
      { method: "GET" },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown general store call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/general-store", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, gsAction };
