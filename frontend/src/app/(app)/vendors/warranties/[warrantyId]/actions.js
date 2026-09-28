"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

/** Mirrors `WarrantyApiController::storeClaim` (`FileWarrantyClaim`) — cover is judged on the incident date, not the day someone got round to filing. */
async function fileClaim(warrantyId, previousState, formData) {
  try {
    await apiFetch(`/warranties/${warrantyId}/claims`, {
      method: "POST",
      body: JSON.stringify({
        claim_date: formData.get("claim_date"),
        incident_date: formData.get("incident_date") || null,
        description: formData.get("description"),
        claimed_amount: formData.get("claimed_amount") || null,
      }),
    });

    revalidatePath(`/vendors/warranties/${warrantyId}`);

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `WarrantyApiController::decideClaim` (`DecideWarrantyClaim`). */
async function decideClaim(warrantyId, claimId, previousState, formData) {
  try {
    await apiFetch(`/warranty-claims/${claimId}`, {
      method: "PATCH",
      body: JSON.stringify({
        status: formData.get("status"),
        resolution: formData.get("resolution") || null,
        settled_amount: formData.get("settled_amount") || null,
      }),
    });

    revalidatePath(`/vendors/warranties/${warrantyId}`);

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { fileClaim, decideClaim };
