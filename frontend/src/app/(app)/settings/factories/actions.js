"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

/** Mirrors `FactoryController::store` — no redirect: create/edit both happen in a modal over this same list. */
async function createFactory(previousState, formData) {
  try {
    await apiFetch("/factories", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
    revalidatePath("/settings/factories");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `FactoryController::update`. */
async function updateFactory(factoryId, previousState, formData) {
  try {
    await apiFetch(`/factories/${factoryId}`, {
      method: "PATCH",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
    revalidatePath("/settings/factories");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function toggleFactory(factory) {
  try {
    await apiFetch(`/factories/${factory.id}/active`, {
      method: "PATCH",
      body: JSON.stringify({ active: factory.status !== "ACTIVE" }),
    });
    revalidatePath("/settings/factories");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `FactoryController::destroy` — only reaches a factory nothing is filed against; a factory that has run is closed instead. */
async function deleteFactory(factoryId) {
  try {
    await apiFetch(`/factories/${factoryId}`, { method: "DELETE" });
    revalidatePath("/settings/factories");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createFactory, updateFactory, toggleFactory, deleteFactory };
