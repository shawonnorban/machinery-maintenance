"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `RateContractApiController::store` (Procurement spec §14). */
async function createRateContract(payload) {
  try {
    const data = await apiFetch("/rate-contracts", { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/procurement/rate-contracts");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createRateContract };
