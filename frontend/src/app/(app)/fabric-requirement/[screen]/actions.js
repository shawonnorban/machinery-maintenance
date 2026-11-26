"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Fabric Requirement → Master Data and Operations (AnnotechERP 13/01, 13/02):
 * the screen kit's resources, and the operations' in-place editors — a
 * family by its number, a section by its code, a plan or board by its number.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done() {
  revalidatePath("/fabric-requirement", "layout");
  return { status: "success" };
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

/** New batch plan (the New tab): the header with a typed BP quantity, no fabric lines. */
async function createBatchPlan(previousState, formData) {
  try {
    await apiFetch("/fabric-planning/batch-plans", { method: "POST", body: JSON.stringify(body(formData, [])) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save the operation editors make in place. */
async function opsAction(op, ...args) {
  const enc = encodeURIComponent;
  const put = (url, rows) => [url, { method: "PUT", body: JSON.stringify({ rows }) }];
  const calls = {
    saveAreas: ([sl, rows]) => put(`/fabric-planning/status-groups/${sl}/areas`, rows),
    saveRules: ([sl, rows]) => put(`/fabric-planning/status-groups/${sl}/rules`, rows),
    saveDays: ([code, rows]) => put(`/fabric-planning/sections/${enc(code)}/days`, rows),
    saveBoard: ([no, rows]) => put(`/fabric-planning/schedule-plans/${enc(no)}/batches`, rows),
    batchPlans: () => ["/fabric-planning/batch-plans", { method: "GET" }],
    batchPlan: ([no]) => [`/fabric-planning/batch-plans/${enc(no)}`, { method: "GET" }],
    storeBatchPlan: ([payload]) => ["/fabric-planning/batch-plans", { method: "POST", body: JSON.stringify(payload) }],
    updateBatchPlan: ([no, payload]) => [`/fabric-planning/batch-plans/${enc(no)}`, { method: "PUT", body: JSON.stringify(payload) }],
  };
  if (!calls[op]) throw new Error(`Unknown fabric planning call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/fabric-requirement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, deleteResource, createBatchPlan, opsAction };
