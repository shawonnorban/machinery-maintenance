"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `AssetController::update`. `redirect()` must stay outside the try/catch — it works by throwing internally. */
async function updateAsset(assetId, previousState, formData) {
  try {
    const payload = Object.fromEntries(formData.entries());
    // See `createAsset` for why `custom_fields` needs this special case.
    if (typeof payload.custom_fields === "string") {
      payload.custom_fields = JSON.parse(payload.custom_fields);
    }

    await apiFetch(`/assets/${assetId}`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/assets/${assetId}`);
}

export { updateAsset };
