"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `LandedCostApiController::store` (Procurement spec §11). */
async function createLandedCost(payload) {
  try {
    const data = await apiFetch("/landed-costs", { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/procurement/landed-costs");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createLandedCost };
