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
 * Mirrors `NumberingApiController::update` (SRS 52) — a change takes effect
 * from the next period; numbers already issued keep the shape they were
 * given.
 */
async function updateFormat(documentType, previousState, formData) {
  try {
    await apiFetch(`/numbering/${documentType}`, {
      method: "PATCH",
      body: JSON.stringify({
        format: formData.get("format"),
        padding: Number(formData.get("padding")),
      }),
    });

    revalidatePath("/settings/numbering");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function resetFormat(documentType) {
  try {
    await apiFetch(`/numbering/${documentType}`, { method: "DELETE" });
    revalidatePath("/settings/numbering");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { updateFormat, resetFormat };
