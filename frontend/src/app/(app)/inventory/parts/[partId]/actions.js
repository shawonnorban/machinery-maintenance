"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `SparePartApiController::receive` (`StockController::store`, ADR-003). */
async function receiveStock(partId, previousState, formData) {
  try {
    await apiFetch(`/spare-parts/${partId}/receive`, {
      method: "POST",
      body: JSON.stringify({
        bin_id: formData.get("bin_id"),
        quantity: formData.get("quantity"),
        unit_cost: formData.get("unit_cost"),
        transaction_type: formData.get("transaction_type") || "RECEIPT",
        notes: formData.get("notes") || null,
      }),
    });

    revalidatePath(`/inventory/parts/${partId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Mirrors `SparePartApiController::adjust` — one-directional (ADJUSTMENT_OUT/SCRAP); the increasing direction is a receipt. */
async function adjustStock(partId, previousState, formData) {
  try {
    await apiFetch(`/spare-parts/${partId}/adjust`, {
      method: "POST",
      body: JSON.stringify({
        bin_id: formData.get("bin_id"),
        quantity: formData.get("quantity"),
        transaction_type: formData.get("transaction_type"),
        notes: formData.get("notes"),
      }),
    });

    revalidatePath(`/inventory/parts/${partId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Mirrors `SparePartApiController::reverseTransaction` — an opposing row, never an edit of the original. */
async function reverseTransaction(partId, transactionId, previousState, formData) {
  try {
    await apiFetch(`/inventory-transactions/${transactionId}/reverse`, {
      method: "POST",
      body: JSON.stringify({ reason: formData.get("reason") }),
    });

    revalidatePath(`/inventory/parts/${partId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Mirrors `SparePartCompatibilityApiController::store` — a "fits" row (asset_model_id) or a "substitute" row (substitute_for_part_id), never both. */
async function addCompatibility(partId, previousState, formData) {
  try {
    const type = formData.get("compatibility_type");
    await apiFetch(`/spare-parts/${partId}/compatibility`, {
      method: "POST",
      body: JSON.stringify({
        compatibility_type: type,
        asset_model_id: type === "FITS" ? formData.get("asset_model_id") : null,
        substitute_for_part_id: type === "SUBSTITUTE" ? formData.get("substitute_for_part_id") : null,
      }),
    });

    revalidatePath(`/inventory/parts/${partId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function deleteCompatibility(partId, compatibilityId) {
  try {
    await apiFetch(`/spare-parts/${partId}/compatibility/${compatibilityId}`, { method: "DELETE" });
    revalidatePath(`/inventory/parts/${partId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

export { receiveStock, adjustStock, reverseTransaction, addCompatibility, deleteCompatibility };
