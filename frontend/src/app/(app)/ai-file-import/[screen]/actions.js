"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * AI File & PO Import (AnnotechERP 26): the screen kit's
 * resources — added and edited through the dialogs, deleted, and a row's
 * switch (Enabled, Mandatory) flipped in place.
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
    await apiFetch(`/erp/${resource}`, { method: "POST", body: JSON.stringify(body(formData, booleans)) });
    revalidatePath("/ai-file-import", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData, booleans)) });
    revalidatePath("/ai-file-import", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "DELETE" });
    revalidatePath("/ai-file-import", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** A row's switch: a signature or a step switched on or off, a rule made mandatory. */
async function setFlag(resource, field, id, next) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify({ [field]: !!next }) });
    revalidatePath("/ai-file-import", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Universal File Intake: send a file to the AI service to be read (again). */
async function readIntake(id) {
  try {
    const result = await apiFetch(`/ai-import/intake/${id}/read`, { method: "POST" });
    revalidatePath("/ai-file-import", "layout");
    return { status: "success", message: result.message };
  } catch (error) {
    if (error instanceof ApiError) return { status: "error", message: error.errors?.file?.[0] ?? error.message };
    throw error;
  }
}

export { createResource, updateResource, deleteResource, setFlag, readIntake };
