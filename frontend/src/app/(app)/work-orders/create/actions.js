"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { refOf } from "@/lib/ref-path";

/** Mirrors `WorkOrderController::store`. `redirect()` stays outside the try/catch — it works by throwing internally. */
async function createWorkOrder(previousState, formData) {
  let workOrder;

  try {
    workOrder = await apiFetch("/work-orders", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/work-orders/${refOf(workOrder, "work_order_number")}`);
}

export { createWorkOrder };
