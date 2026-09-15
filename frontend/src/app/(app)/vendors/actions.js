"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `VendorController::destroy` — archived, never deleted; a vendor named on a five-year-old cost entry has to stay resolvable (ADR-057). */
async function archiveVendor(vendorId) {
  try {
    await apiFetch(`/vendors/${vendorId}`, { method: "DELETE" });
    revalidatePath("/vendors");
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

export { archiveVendor };
