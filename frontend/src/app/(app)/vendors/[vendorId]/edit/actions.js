"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { vendorPayload } from "@/components/vendors/vendor-payload";

/** Mirrors `VendorController::update`. `redirect()` stays outside the try/catch — it works by throwing internally. */
async function updateVendor(vendorId, previousState, formData) {
  try {
    await apiFetch(`/vendors/${vendorId}`, { method: "PATCH", body: JSON.stringify(vendorPayload(formData)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect("/vendors");
}

export { updateVendor };
