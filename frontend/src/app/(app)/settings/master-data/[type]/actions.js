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

/** Mirrors `MasterDataController::store`. */
async function createRow(type, previousState, formData) {
  try {
    await apiFetch(`/master-data/${type}`, {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
    revalidatePath(`/settings/master-data/${type}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `MasterDataController::update` — a platform row (no company_id) is rejected by SaveMasterDataRow itself, not guessed at here. */
async function updateRow(type, rowId, previousState, formData) {
  try {
    await apiFetch(`/master-data/${type}/${rowId}`, {
      method: "PATCH",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
    revalidatePath(`/settings/master-data/${type}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function setActive(type, rowId, active) {
  try {
    await apiFetch(`/master-data/${type}/${rowId}/active`, {
      method: "PATCH",
      body: JSON.stringify({ active }),
    });
    revalidatePath(`/settings/master-data/${type}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteRow(type, rowId) {
  try {
    await apiFetch(`/master-data/${type}/${rowId}`, { method: "DELETE" });
    revalidatePath(`/settings/master-data/${type}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createRow, updateRow, setActive, deleteRow };
