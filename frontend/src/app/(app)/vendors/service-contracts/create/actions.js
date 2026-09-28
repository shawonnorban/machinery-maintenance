"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `ServiceContractApiController::store` (`ManageServiceContract::create`). `redirect()` stays outside the try/catch — it works by throwing internally. */
async function createContract(previousState, formData) {
  let contract;

  try {
    contract = await apiFetch("/service-contracts", {
      method: "POST",
      body: JSON.stringify({
        vendor_id: formData.get("vendor_id"),
        contract_type: formData.get("contract_type"),
        contract_number: formData.get("contract_number") || null,
        asset_id: formData.get("asset_id") || null,
        factory_id: formData.get("factory_id") || null,
        asset_ids: formData.getAll("asset_ids"),
        start_date: formData.get("start_date"),
        end_date: formData.get("end_date"),
        renewal_date: formData.get("renewal_date") || null,
        value: formData.get("value") || null,
        visits_per_year: formData.get("visits_per_year") || null,
        response_time_hours: formData.get("response_time_hours") || null,
        coverage: formData.get("coverage") || null,
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/vendors/service-contracts/${contract.id}`);
}

export { createContract };
