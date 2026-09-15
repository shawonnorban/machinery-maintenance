"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `AssetController::store`. `redirect()` must stay outside the try/catch — it works by throwing internally. */
async function createAsset(previousState, formData) {
  let asset;

  try {
    asset = await apiFetch("/assets", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/assets/${asset.id}`);
}

export { createAsset };
