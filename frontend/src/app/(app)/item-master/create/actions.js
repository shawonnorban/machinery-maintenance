"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { itemPayload } from "@/components/item-master/item-payload";

/** Mirrors `ItemApiController::store`. `redirect()` stays outside the try/catch — it works by throwing internally. */
async function createItem(previousState, formData) {
  let item;
  try {
    item = await apiFetch("/items", { method: "POST", body: JSON.stringify(itemPayload(formData)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/item-master");
  redirect(`/item-master/${item.id}`);
}

export { createItem };
