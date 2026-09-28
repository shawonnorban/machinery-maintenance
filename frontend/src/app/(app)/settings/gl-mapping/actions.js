"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Mirrors `GlMappingApiController::update` (Shared Systems §2). A finance
 * sign-off is this call, repeated once per event — no code changes with it.
 */
async function saveGlMapping(eventType, payload) {
  try {
    const data = await apiFetch(`/gl-mappings/${eventType}`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
    revalidatePath("/settings/gl-mapping");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { saveGlMapping };
