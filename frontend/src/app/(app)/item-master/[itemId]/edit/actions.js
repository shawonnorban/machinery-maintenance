"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { itemPayload } from "@/components/item-master/item-payload";

/** Mirrors `ItemApiController::update`. */
async function updateItem(itemId, previousState, formData) {
  try {
    await apiFetch(`/items/${itemId}`, { method: "PATCH", body: JSON.stringify(itemPayload(formData)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath(`/item-master/${itemId}`);
  redirect(`/item-master/${itemId}`);
}

export { updateItem };
