"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Mirrors `AssetStatusController::store` (ADR-003). `version` is the
 * optimistic-locking value the detail page fetched moments before the
 * modal opened (ADR-025) — a 409 here means someone else changed the
 * asset in between, surfaced as a field error rather than a generic
 * failure so the form can tell the difference from a validation mistake.
 */
async function changeStatus(assetId, previousState, formData) {
  try {
    await apiFetch(`/assets/${assetId}/status`, {
      method: "POST",
      body: JSON.stringify({
        status: formData.get("status"),
        reason: formData.get("reason") || null,
        version: Number(formData.get("version")),
      }),
    });

    revalidatePath(`/assets/${assetId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Mirrors `AssetTransferController::store` — auto-received by the API itself when the destination is in the same factory. */
async function requestTransfer(assetId, previousState, formData) {
  try {
    await apiFetch(`/assets/${assetId}/transfer`, {
      method: "POST",
      body: JSON.stringify({
        to_location_id: formData.get("to_location_id"),
        reason: formData.get("reason"),
        notes: formData.get("notes") || null,
        version: Number(formData.get("version")),
      }),
    });

    revalidatePath(`/assets/${assetId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function approveTransfer(assetId, transferId) {
  try {
    await apiFetch(`/transfers/${transferId}/approve`, { method: "POST" });
    revalidatePath(`/assets/${assetId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

async function receiveTransfer(assetId, transferId) {
  try {
    await apiFetch(`/transfers/${transferId}/receive`, { method: "POST" });
    revalidatePath(`/assets/${assetId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

/** Takes a reason, unlike approve/receive, so it goes through useActionState rather than a plain confirm. */
async function rejectTransfer(assetId, transferId, previousState, formData) {
  try {
    await apiFetch(`/transfers/${transferId}/reject`, {
      method: "POST",
      body: JSON.stringify({ rejection_reason: formData.get("rejection_reason") }),
    });
    revalidatePath(`/assets/${assetId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Mirrors `AssetCostController::store` — labour/parts are derived from the work order and the poster refuses them, so the form never offers those categories. */
async function postCost(assetId, previousState, formData) {
  try {
    const occurredAt = formData.get("occurred_at");

    await apiFetch("/costs", {
      method: "POST",
      body: JSON.stringify({
        asset_id: assetId,
        cost_category_id: formData.get("cost_category_id"),
        amount: formData.get("amount"),
        currency: formData.get("currency"),
        exchange_rate: formData.get("exchange_rate") || null,
        source_type: formData.get("source_type"),
        occurred_at: occurredAt ? new Date(occurredAt).toISOString() : null,
        description: formData.get("description") || null,
        invoice_reference: formData.get("invoice_reference") || null,
      }),
    });

    revalidatePath(`/assets/${assetId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Its own permission, not create: undoing a posted cost changes a figure somebody has already reported. */
async function reverseCost(assetId, entryId, reason) {
  try {
    await apiFetch(`/costs/${entryId}/reverse`, {
      method: "POST",
      body: JSON.stringify({ reason }),
    });

    revalidatePath(`/assets/${assetId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

/**
 * Mirrors `AssetLabelController::regenerate` — invalidates the printed
 * label, so it's a plain confirm (like approve/receive above) rather than
 * a form, and the permission check is the API's `asset.qr.regenerate`
 * gate on submit, not a client-side guess.
 */
async function regenerateQr(assetId) {
  try {
    await apiFetch(`/assets/${assetId}/qr/regenerate`, { method: "POST" });
    revalidatePath(`/assets/${assetId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

/** Mirrors `AssetDocumentController::store` — multipart, like `uploadAttachment` on work orders. */
async function uploadDocument(assetId, formData) {
  try {
    const file = formData.get("file");
    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }
    const body = new FormData();
    body.set("file", file);
    await apiFetch(`/assets/${assetId}/documents`, { method: "POST", body });
    revalidatePath(`/assets/${assetId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

/**
 * Mirrors `AssetDocumentController::destroy` — deletion itself runs through
 * the generic `FileApiController::destroy` (`DELETE /files/{file}`), the
 * one place a file's row and bytes are removed regardless of what it's
 * attached to (asset document, work order attachment); the `asset.
 * document.manage` permission check happens there, keyed off the file's
 * own `attachable_type`.
 */
async function deleteDocument(assetId, attachmentId) {
  try {
    await apiFetch(`/files/${attachmentId}`, { method: "DELETE" });
    revalidatePath(`/assets/${assetId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

/** Mirrors `metering/[meterId]/actions.js`'s own `recordReading` — bound per-meter from the asset's Metering tab instead of the meter's own standalone page. */
async function recordReading(assetId, meterId, previousState, formData) {
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

    revalidatePath(`/assets/${assetId}`);

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export {
  changeStatus, requestTransfer, approveTransfer, receiveTransfer, rejectTransfer, postCost, reverseCost, regenerateQr,
  uploadDocument, deleteDocument, recordReading,
};
