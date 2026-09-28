"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `BondComplianceApiController::storeDeclaration` (Shared Systems spec §3.2). */
async function createDeclaration(licenceId, payload) {
  try {
    const data = await apiFetch(`/bond-licences/${licenceId}/declarations`, {
      method: "POST",
      body: JSON.stringify(payload),
    });
    revalidatePath(`/compliance/bond-licences/${licenceId}`);
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createDeclaration };
