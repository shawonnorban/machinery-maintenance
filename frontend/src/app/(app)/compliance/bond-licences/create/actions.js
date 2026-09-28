"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `BondComplianceApiController::store` (Shared Systems spec §3.1). */
async function createBondLicence(payload) {
  try {
    const data = await apiFetch("/bond-licences", { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/compliance/bond-licences");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createBondLicence };
