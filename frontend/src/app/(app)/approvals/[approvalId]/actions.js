"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Server Actions behind the two decision forms — mirrors
 * `ApprovalController::approve`/`::reject` exactly (both delegate to the
 * same `DecideApproval` action per ADR-003).
 */
async function approveRequest(approvalId, previousState, formData) {
  try {
    await apiFetch(`/approval-requests/${approvalId}/approve`, {
      method: "POST",
      body: JSON.stringify({ comment: formData.get("comment") || null }),
    });

    revalidatePath(`/approvals/${approvalId}`);
    revalidatePath("/approvals");

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function rejectRequest(approvalId, previousState, formData) {
  try {
    await apiFetch(`/approval-requests/${approvalId}/reject`, {
      method: "POST",
      body: JSON.stringify({ comment: formData.get("comment") }),
    });

    revalidatePath(`/approvals/${approvalId}`);
    revalidatePath("/approvals");

    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { approveRequest, rejectRequest };
