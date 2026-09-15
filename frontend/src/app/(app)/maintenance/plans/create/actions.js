"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function createPlan(previousState, formData) {
  let planId;

  try {
    const body = Object.fromEntries(formData.entries());
    const created = await apiFetch("/maintenance-plans", { method: "POST", body: JSON.stringify(body) });
    planId = created.id;
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/maintenance/plans/${planId}`);
}

export { createPlan };
