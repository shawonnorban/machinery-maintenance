"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { refOf } from "@/lib/ref-path";

/** Mirrors `AssetController::store`. `redirect()` must stay outside the try/catch — it works by throwing internally. */
async function createAsset(previousState, formData) {
  let asset;

  try {
    const payload = Object.fromEntries(formData.entries());
    // `custom_fields` travels as one JSON-encoded FormData field (see
    // `AssetForm`) rather than bracket-notation keys — this is a JSON body,
    // not a real multipart submit, so `custom_fields[code]` keys would never
    // get parsed back into a nested object the way an actual form post does.
    if (typeof payload.custom_fields === "string") {
      payload.custom_fields = JSON.parse(payload.custom_fields);
    }

    asset = await apiFetch("/assets", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/assets/${refOf(asset, "asset_code")}`);
}

export { createAsset };
