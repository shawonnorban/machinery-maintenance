"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Server Actions behind the two forms on the meter detail screen — mirrors
 * `MeterController::record`/`::reset` exactly (docs/12-Stack-Migration-
 * Implementation-Plan.md Phase C §5: every Laravel API call happens
 * server-side; a Client Component form posts here via `action={}`, never
 * to Laravel directly). Errors are returned as state rather than thrown —
 * `useActionState` needs a plain serializable value on the client side,
 * and this keeps the API's own field-level messages intact instead of
 * losing them to Next's server-error redaction.
 */
async function recordReading(meterId, previousState, formData) {
  try {
    const readingAt = formData.get("reading_at");

    await apiFetch(`/meters/${meterId}/readings`, {
      method: "POST",
      body: JSON.stringify({
        value: formData.get("value"),
        reading_at: readingAt ? new Date(readingAt).toISOString() : null,
        notes: formData.get("notes") || null,
      }),
    });

    revalidatePath(`/metering/${meterId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function resetMeter(meterId, previousState, formData) {
  try {
    await apiFetch(`/meters/${meterId}/reset`, {
      method: "POST",
      body: JSON.stringify({
        new_value: formData.get("new_value"),
        reason: formData.get("reason"),
      }),
    });

    revalidatePath(`/metering/${meterId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { recordReading, resetMeter };
