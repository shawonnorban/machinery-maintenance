"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `TransferController::store`. The receiving factory is never checked against what this caller can reach — sending stock to a plant you don't administer is the ordinary case. */
async function requestTransfer(previousState, formData) {
  const items = JSON.parse(formData.get("items") || "[]");

  let transfer;
  try {
    transfer = await apiFetch("/inventory-transfers", {
      method: "POST",
      body: JSON.stringify({
        from_factory_id: formData.get("from_factory_id"),
        to_factory_id: formData.get("to_factory_id"),
        notes: formData.get("notes") || null,
        items,
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/inventory/transfers");
  redirect(`/inventory/transfers/${transfer.id}`);
}

export { requestTransfer };
