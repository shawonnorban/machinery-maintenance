"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["po_id", "factory_id", "store_id", "vendor_id", "currency", "grn_date", "gate_pass_no", "notes"];

/** Mirrors `GoodsReceiptApiController::store` — direct or against an order, decided by `po_id`. */
async function createReceipt(previousState, formData) {
  let receipt;
  try {
    receipt = await apiFetch("/goods-receipts", { method: "POST", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/receipts");
  revalidatePath("/procurement/orders");
  redirect(`/procurement/receipts/${receipt.id}`);
}

export { createReceipt };
