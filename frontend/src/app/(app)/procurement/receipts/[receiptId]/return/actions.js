"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `PurchaseReturnApiController::store` (Procurement spec §12). */
async function createPurchaseReturn(receiptId, payload) {
  try {
    const data = await apiFetch(`/goods-receipts/${receiptId}/returns`, {
      method: "POST",
      body: JSON.stringify(payload),
    });
    revalidatePath("/procurement/returns");
    revalidatePath(`/procurement/receipts/${receiptId}`);
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createPurchaseReturn };
