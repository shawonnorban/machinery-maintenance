"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `WarrantyApiController::store` (`RecordWarranty`). `redirect()` stays outside the try/catch — it works by throwing internally. */
async function createWarranty(previousState, formData) {
  let warranty;

  try {
    warranty = await apiFetch("/warranties", {
      method: "POST",
      body: JSON.stringify({
        asset_id: formData.get("asset_id"),
        vendor_id: formData.get("vendor_id") || null,
        warranty_type: formData.get("warranty_type"),
        start_date: formData.get("start_date"),
        end_date: formData.get("end_date"),
        reference: formData.get("reference") || null,
        coverage: formData.get("coverage") || null,
        exclusions: formData.get("exclusions") || null,
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/vendors/warranties/${warranty.id}`);
}

export { createWarranty };
