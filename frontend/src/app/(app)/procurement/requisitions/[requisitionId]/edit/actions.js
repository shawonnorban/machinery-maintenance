"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["store_id", "pr_date", "pr_type", "priority", "currency", "notes"];

/** Mirrors `PurchaseRequisitionApiController::update`. Bound to the id; `previousState` keeps useActionState's arity. */
async function updateRequisition(requisitionId, previousState, formData) {
  try {
    await apiFetch(`/purchase-requisitions/${requisitionId}`, { method: "PATCH", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/requisitions");
  revalidatePath(`/procurement/requisitions/${requisitionId}`);
  redirect(`/procurement/requisitions/${requisitionId}`);
}

export { updateRequisition };
