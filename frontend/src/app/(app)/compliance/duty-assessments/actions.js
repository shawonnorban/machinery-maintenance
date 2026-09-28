"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Mirrors `BondComplianceApiController::assess` (Shared Systems spec §3.4).
 *
 * Deliberate, never automatic: the API refuses a store that is not bonded,
 * because nothing leaving an ordinary store owes duty at all.
 */
async function assessDuty(payload) {
  try {
    const data = await apiFetch("/duty-assessments", { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/compliance/duty-assessments");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { assessDuty };
