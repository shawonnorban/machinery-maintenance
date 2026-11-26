"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Trade & Export → Master Data (AnnotechERP 22/01): the screen kit's
 * resources.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/trade-export", "layout");
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

/** Brickland's ⊘: deactivate a row, or bring an inactive one back. */
async function toggleActive(resource, id) {
  try {
    const row = await apiFetch(`/erp/${resource}/${id}`);
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify({ active: !row.active }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

const own = (path) => {
  if (typeof path !== "string" || !path.startsWith("/tradeexport/ops/") || path.includes("..")) throw new Error("Not a Trade & Export path");
  return path;
};

/** Operations (AnnotechERP 22/02): a read of the export papers' API. */
async function opRead(path) {
  try {
    return { status: "success", data: await apiFetch(own(path)) };
  } catch (error) {
    return fail(error);
  }
}

/** Operations (AnnotechERP 22/02): a write to the export papers' API. */
async function opWrite(method, path, payload) {
  try {
    const data = await apiFetch(own(path), { method, ...(payload === undefined ? {} : { body: JSON.stringify(payload) }) });
    revalidatePath("/trade-export", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, toggleActive, opRead, opWrite };
