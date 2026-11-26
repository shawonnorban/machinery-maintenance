"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Pre-Budget → Master Data (AnnotechERP 33/34): the screen kit's resources —
 * add, edit, and the Status switch.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done() {
  revalidatePath("/pre-budget-master", "layout");
  return { status: "success" };
}

function body(formData) {
  const out = {};
  for (const [key, value] of formData.entries()) if (key !== "__intent") out[key] = value === "" ? null : value;
  if (formData.has("active")) out.active = formData.get("active") === "1";
  return out;
}

async function createResource(resource, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, { method: "POST", body: JSON.stringify(body(formData)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function setActive(resource, id, active) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify({ active }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, setActive };
