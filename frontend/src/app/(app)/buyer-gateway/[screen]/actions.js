"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Buyer Gateway → Access & Onboarding (AnnotechERP 25/01): the screen kit's
 * resources — added and edited through the dialogs, deleted, and a row's
 * switch (Login, Approve, Enabled) flipped in place.
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
    revalidatePath("/buyer-gateway", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData, booleans)) });
    revalidatePath("/buyer-gateway", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "DELETE" });
    revalidatePath("/buyer-gateway", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** A row's switch: a user's Login, a set's Approve, a preference's Enabled. */
async function setFlag(resource, field, id, next) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify({ [field]: !!next }) });
    revalidatePath("/buyer-gateway", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, deleteResource, setFlag };
