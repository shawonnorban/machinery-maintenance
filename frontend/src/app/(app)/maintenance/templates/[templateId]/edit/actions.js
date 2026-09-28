"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function updateTemplate(templateId, previousState, formData) {
  try {
    const body = Object.fromEntries(formData.entries());
    await apiFetch(`/maintenance-templates/${templateId}`, { method: "PATCH", body: JSON.stringify(body) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/maintenance/templates/${templateId}`);
}

export { updateTemplate };
