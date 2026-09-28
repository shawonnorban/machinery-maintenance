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

/** Mirrors `ItemApiController::setActive` — retire/restore, never a delete once anything names the item. */
async function setItemActive(itemId, active) {
  try {
    await apiFetch(`/items/${itemId}/active`, { method: "PATCH", body: JSON.stringify({ active }) });
    revalidatePath("/item-master");
    revalidatePath(`/item-master/${itemId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `ItemApiController::destroy` — refused (409) once a receipt names the item. */
async function deleteItem(itemId) {
  try {
    await apiFetch(`/items/${itemId}`, { method: "DELETE" });
    revalidatePath("/item-master");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { setItemActive, deleteItem };
