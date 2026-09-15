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

/**
 * Mirrors `EscalationRuleApiController::store` (`ManageEscalationRule::create`)
 * — two rules cannot cover the same level for one event, refused with a 422
 * on `escalation_level` rather than silently telling the same person twice.
 */
async function createRule(previousState, formData) {
  try {
    await apiFetch("/escalation-rules", {
      method: "POST",
      body: JSON.stringify({
        event_type: formData.get("event_type"),
        severity: formData.get("severity") || null,
        factory_id: formData.get("factory_id") || null,
        delay_minutes: Number(formData.get("delay_minutes")),
        escalation_level: Number(formData.get("escalation_level")),
        escalation_role_id: Number(formData.get("escalation_role_id")),
        stop_on_acknowledge: formData.get("stop_on_acknowledge") === "1",
      }),
    });

    revalidatePath("/settings/escalations");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function toggleRule(ruleId) {
  try {
    await apiFetch(`/escalation-rules/${ruleId}/toggle`, { method: "POST" });
    revalidatePath("/settings/escalations");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteRule(ruleId) {
  try {
    await apiFetch(`/escalation-rules/${ruleId}`, { method: "DELETE" });
    revalidatePath("/settings/escalations");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createRule, toggleRule, deleteRule };
