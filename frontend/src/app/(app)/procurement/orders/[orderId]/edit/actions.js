"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["po_date", "currency", "payment_type_id", "payment_terms", "delivery_date", "delivery_store_id", "notes"];

/** Mirrors `PurchaseOrderApiController::update` (draft only). */
async function updateOrder(orderId, previousState, formData) {
  try {
    await apiFetch(`/purchase-orders/${orderId}`, { method: "PATCH", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/orders");
  revalidatePath(`/procurement/orders/${orderId}`);
  redirect(`/procurement/orders/${orderId}`);
}

export { updateOrder };
