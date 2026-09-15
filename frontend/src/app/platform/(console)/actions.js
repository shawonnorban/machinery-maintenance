"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { platformApiFetch } from "@/lib/platform-api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

/** Mirrors `TenantController::restore` — reopens a closed account; nothing to put back, the rows never left. */
async function restoreTenant(companyId) {
  try {
    await platformApiFetch(`/tenants/${companyId}/restore`, { method: "POST" });
  } catch (error) {
    return fail(error);
  }

  revalidatePath("/platform");
  redirect(`/platform/tenants/${companyId}`);
}

export { restoreTenant };
