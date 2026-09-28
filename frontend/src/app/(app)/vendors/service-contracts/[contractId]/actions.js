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

/**
 * Mirrors `ServiceContractApiController::renew` (`ManageServiceContract::renew`)
 * — a renewal is a new contract row; the old one flips to RENEWED rather than
 * being edited in place, so last year's terms stay on the record.
 */
async function renewContract(contractId, previousState, formData) {
  let renewal;

  try {
    renewal = await apiFetch(`/service-contracts/${contractId}/renew`, {
      method: "POST",
      body: JSON.stringify({
        start_date: formData.get("start_date"),
        end_date: formData.get("end_date"),
        value: formData.get("value") || null,
      }),
    });
  } catch (error) {
    return fail(error);
  }

  revalidatePath(`/vendors/service-contracts/${contractId}`);

  return { status: "success", renewalId: renewal.id };
}

async function cancelContract(contractId, previousState, formData) {
  try {
    await apiFetch(`/service-contracts/${contractId}/cancel`, {
      method: "POST",
      body: JSON.stringify({ reason: formData.get("reason") }),
    });

    revalidatePath(`/vendors/service-contracts/${contractId}`);

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { renewContract, cancelContract };
