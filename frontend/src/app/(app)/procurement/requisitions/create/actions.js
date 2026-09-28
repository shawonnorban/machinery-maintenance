"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["factory_id", "store_id", "pr_date", "pr_type", "priority", "currency", "notes"];

/** Mirrors `PurchaseRequisitionApiController::store`. `redirect()` stays outside the try/catch — it works by throwing. */
async function createRequisition(previousState, formData) {
  let requisition;
  try {
    requisition = await apiFetch("/purchase-requisitions", { method: "POST", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/requisitions");
  redirect(`/procurement/requisitions/${requisition.id}`);
}

export { createRequisition };
