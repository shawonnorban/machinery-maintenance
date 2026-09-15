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

async function activatePlan(planId) {
  try {
    await apiFetch(`/maintenance-plans/${planId}/activate`, { method: "POST" });
    revalidatePath("/maintenance/plans");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deactivatePlan(planId) {
  try {
    await apiFetch(`/maintenance-plans/${planId}/deactivate`, { method: "POST" });
    revalidatePath("/maintenance/plans");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deletePlan(planId) {
  try {
    await apiFetch(`/maintenance-plans/${planId}`, { method: "DELETE" });
    revalidatePath("/maintenance/plans");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * Mirrors `plans::_form.blade.php`'s own live preview panel — advisory
 * only, never blocks the form on failure (the caller swallows any error
 * and just shows nothing, the same as the Blade script's own catch). The
 * endpoint is a GET (`MaintenancePlanApiController::preview()` computes
 * from query params, no state change), unlike the Blade page's own script
 * which posts to the identically-named *web* route — the two aren't the
 * same endpoint despite the shared name.
 */
async function previewPlan(input) {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(input)) {
    if (value !== null && value !== undefined && value !== "") query.set(key, String(value));
  }

  return apiFetch(`/maintenance-plans/preview?${query.toString()}`);
}

export { activatePlan, deactivatePlan, deletePlan, previewPlan };
