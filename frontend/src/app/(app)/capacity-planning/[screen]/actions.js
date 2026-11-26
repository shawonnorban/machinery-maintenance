"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Capacity Planning → Master Data (AnnotechERP 12/01): the screen kit's
 * resources, and the factory calendar's days and weekly holidays.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/capacity-planning", "layout");
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

/** Production Plan (12/02): every read and step the plan screens take in place — an order and a plan by number, never key. */
async function planAction(op, ...args) {
  const q = (query = {}) => {
    const kept = Object.fromEntries(Object.entries(query).filter(([, v]) => v !== "" && v !== null && v !== undefined));
    return Object.keys(kept).length ? `?${new URLSearchParams(kept)}` : "";
  };
  const plan = (no) => `/planning/master-plans/${encodeURIComponent(no)}`;
  const post = (url, payload) => [url, { method: "POST", body: JSON.stringify(payload ?? {}) }];
  const calls = {
    projections: ([f]) => [`/planning/projections${q(f)}`, { method: "GET" }],
    orders: ([f]) => [`/planning/master-plans/orders${q(f)}`, { method: "GET" }],
    order: ([no]) => [`/planning/master-plans/order/${encodeURIComponent(no)}`, { method: "GET" }],
    calculate: ([payload]) => post("/planning/master-plans/calculate", payload),
    save: ([payload]) => post("/planning/master-plans", payload),
    show: ([no, v]) => [`${plan(no)}${q({ v })}`, { method: "GET" }],
    deleteDraft: ([no]) => [`${plan(no)}/draft`, { method: "DELETE" }],
    confirm: ([no]) => post(`${plan(no)}/confirm`),
    requestRevision: ([no, payload]) => post(`${plan(no)}/revisions`, payload),
    list: ([f]) => [`/planning/master-plans${q(f)}`, { method: "GET" }],
    revisions: ([f]) => [`/planning/revision-requests${q(f)}`, { method: "GET" }],
    approveRevision: ([id, payload]) => post(`/planning/revision-requests/${id}/approve`, payload),
    rejectRevision: ([id, payload]) => post(`/planning/revision-requests/${id}/reject`, payload),
    day: ([f]) => [`/planning/daily-plans/day${q(f)}`, { method: "GET" }],
    saveDay: ([payload]) => post("/planning/daily-plans", payload),
    confirmDay: ([payload]) => post("/planning/daily-plans/confirm", payload),
    pva: ([f]) => [`/planning/plan-vs-actual${q(f)}`, { method: "GET" }],
    lineCapacity: ([f]) => [`/planning/line-capacity${q(f)}`, { method: "GET" }],
    timeline: ([f]) => [`/planning/timeline${q(f)}`, { method: "GET" }],
  };
  if (!calls[op]) throw new Error(`Unknown planning call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET" && op !== "calculate") revalidatePath("/capacity-planning", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, deleteResource, planAction };
