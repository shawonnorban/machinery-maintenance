import "server-only";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * One POST that moves a document (submit, cancel, complete…), with the
 * error shape every action panel already knows how to toast. Kept out of
 * the per-page `actions.js` files so four documents do not each carry the
 * same try/catch.
 */
async function postDocumentAction(path, body, revalidate = []) {
  try {
    const data = await apiFetch(path, { method: "POST", body: JSON.stringify(body ?? {}) });
    for (const target of revalidate) revalidatePath(target);
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Approve/reject through the approval engine (`/approval-requests/{id}`), then refresh the document page. */
async function decideApproval(requestId, decision, comment, revalidate = []) {
  return postDocumentAction(`/approval-requests/${requestId}/${decision}`, { comment: comment || undefined }, revalidate);
}

export { postDocumentAction, decideApproval };
