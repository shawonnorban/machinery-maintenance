"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["vendor_id", "currency", "grn_date", "gate_pass_no", "notes"];

/** Mirrors `GoodsReceiptApiController::update` (draft only). */
async function updateReceipt(receiptId, previousState, formData) {
  try {
    await apiFetch(`/goods-receipts/${receiptId}`, { method: "PATCH", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/receipts");
  revalidatePath(`/procurement/receipts/${receiptId}`);
  redirect(`/procurement/receipts/${receiptId}`);
}

export { updateReceipt };
