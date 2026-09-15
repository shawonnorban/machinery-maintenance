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

/** Mirrors `ApprovalWorkflowApiController::store` (`ManageApprovalWorkflow::createWorkflow`). */
async function createWorkflow(previousState, formData) {
  try {
    await apiFetch("/approval-workflows", {
      method: "POST",
      body: JSON.stringify({ name: formData.get("name"), entity_type: formData.get("entity_type") }),
    });

    revalidatePath("/settings/approval-workflows");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function toggleWorkflow(workflowId) {
  try {
    await apiFetch(`/approval-workflows/${workflowId}/toggle`, { method: "POST" });
    revalidatePath("/settings/approval-workflows");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * Mirrors `ApprovalWorkflowApiController::storeRule` — a rule with no
 * condition is refused, since it would match every request and send a needle
 * change to the company owner.
 */
async function addRule(workflowId, previousState, formData) {
  try {
    await apiFetch(`/approval-workflows/${workflowId}/rules`, {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        role_id: Number(formData.get("role_id")),
        min_cost: formData.get("min_cost") || null,
        max_cost: formData.get("max_cost") || null,
      }),
    });

    revalidatePath("/settings/approval-workflows");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** A rule is never edited, only removed and re-added — visible as a change rather than a silent rewrite. */
async function removeRule(workflowId, ruleId) {
  try {
    await apiFetch(`/approval-workflows/${workflowId}/rules/${ruleId}`, { method: "DELETE" });
    revalidatePath("/settings/approval-workflows");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createWorkflow, toggleWorkflow, addRule, removeRule };
