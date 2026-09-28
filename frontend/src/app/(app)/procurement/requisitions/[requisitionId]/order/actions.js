"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["vendor_id", "po_date", "currency", "payment_type_id", "payment_terms", "delivery_date", "delivery_store_id", "notes"];

async function createOrderFromRequisition(requisitionId, previousState, formData) {
  let order;
  try {
    order = await apiFetch(`/purchase-requisitions/${requisitionId}/orders`, {
      method: "POST",
      body: JSON.stringify(documentPayload(formData, HEADER)),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/requisitions");
  revalidatePath(`/procurement/requisitions/${requisitionId}`);
  revalidatePath("/procurement/orders");
  redirect(`/procurement/orders/${order.id}`);
}

export { createOrderFromRequisition };
