"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/landed-costs", `/procurement/landed-costs/${id}`];

/**
 * The distribution, worked out but not written. Read-only on purpose:
 * posting is what cannot be undone, so it is a separate press.
 */
async function previewLandedCost(id) {
  try {
    return { status: "success", data: await apiFetch(`/landed-costs/${id}/preview`) };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function postLandedCost(id) {
  // Re-prices stock, so the item pages behind it are stale too.
  return postDocumentAction(`/landed-costs/${id}/post`, {}, [...paths(id), "/item-master", "/procurement/receipts"]);
}

async function cancelLandedCost(id, reason) {
  return postDocumentAction(`/landed-costs/${id}/cancel`, { reason }, paths(id));
}

export { previewLandedCost, postLandedCost, cancelLandedCost };
