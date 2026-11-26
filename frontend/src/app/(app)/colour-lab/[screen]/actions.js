"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Colour Lab → Operations (AnnotechERP 16/01): the screen kit's resources
 * (certificates, their types and equipment, the issue register) and every
 * read and write the trial workbench and fabric testing make in place.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/colour-lab", "layout");
  return { status: "success", ...extra };
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
    return done();
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

/** A certificate saved from its own form (new when `id` is null). */
async function saveCertificate(id, payload) {
  try {
    const data = await apiFetch(
      id ? `/erp/lab-certificates/${id}` : "/erp/lab-certificates",
      { method: id ? "PATCH" : "POST", body: JSON.stringify(payload) },
    );
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

/** A certification type or a piece of equipment added from the certificate form's ✚; answers the new row. */
async function saveMaster(resource, payload) {
  if (!["lab-cert-types", "lab-equipment"].includes(resource))
    throw new Error("Bad master");
  try {
    const data = await apiFetch(`/erp/${resource}`, {
      method: "POST",
      body: JSON.stringify(payload),
    });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

/**
 * The trial workbench and fabric testing: `path` under /colour-lab (its
 * query included), GET unless a method is given.
 */
async function labAction(path, method = "GET", payload) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path))
    throw new Error("Bad colour lab path");
  try {
    const data = await apiFetch(`/colour-lab/${path}`, {
      method,
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
    return method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

export {
  createResource,
  updateResource,
  deleteResource,
  saveCertificate,
  saveMaster,
  labAction,
};
