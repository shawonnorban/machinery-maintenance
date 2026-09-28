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

/** Mirrors `StockController::issue` — a consumable leaving the store with no work order behind it, same shape as `returnStock` in reverse. */
async function issuePart(previousState, formData) {
  try {
    await apiFetch(`/spare-parts/${formData.get("spare_part_id")}/issue`, {
      method: "POST",
      body: JSON.stringify({
        bin_id: formData.get("bin_id"),
        quantity: formData.get("quantity"),
        notes: formData.get("notes"),
      }),
    });
    revalidatePath("/inventory/issue");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function returnPart(previousState, formData) {
  try {
    await apiFetch(`/spare-parts/${formData.get("spare_part_id")}/return`, {
      method: "POST",
      body: JSON.stringify({
        bin_id: formData.get("bin_id"),
        quantity: formData.get("quantity"),
        notes: formData.get("notes"),
      }),
    });
    revalidatePath("/inventory/issue");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { issuePart, returnPart };
