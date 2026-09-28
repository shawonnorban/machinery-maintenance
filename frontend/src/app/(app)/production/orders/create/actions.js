"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `ProductionOrderApiController::store`. */
async function createProductionOrder(previousState, formData) {
  const payload = {
    factory_id: formData.get("factory_id"),
    order_no: formData.get("order_no"),
    buyer: formData.get("buyer"),
    order_qty: formData.get("order_qty"),
    order_date: formData.get("order_date") || undefined,
    ship_date: formData.get("ship_date") || undefined,
    notes: formData.get("notes") || undefined,
  };

  let order;
  try {
    order = await apiFetch("/production-orders", { method: "POST", body: JSON.stringify(payload) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/production/orders");
  redirect(`/production/orders/${order.id}`);
}

export { createProductionOrder };
