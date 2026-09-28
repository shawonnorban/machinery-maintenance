"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Mirrors `StockCountApiController::store` (Inventory spec §12). This call
 * generates the sheet and freezes the system quantities — there is no
 * draft in between, by design.
 */
async function createStockCount(payload) {
  try {
    const data = await apiFetch("/stock-counts", { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/item-master/stock-counts");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createStockCount };
