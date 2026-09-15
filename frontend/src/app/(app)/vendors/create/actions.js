"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `VendorController::store`. `redirect()` stays outside the try/catch — it works by throwing internally. */
async function createVendor(previousState, formData) {
  try {
    await apiFetch("/vendors", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect("/vendors");
}

export { createVendor };
