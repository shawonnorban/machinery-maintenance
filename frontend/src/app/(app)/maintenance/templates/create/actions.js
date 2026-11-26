"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { refOf } from "@/lib/ref-path";

async function createTemplate(previousState, formData) {
  let templateId;

  try {
    const body = Object.fromEntries(formData.entries());
    const created = await apiFetch("/maintenance-templates", { method: "POST", body: JSON.stringify(body) });
    templateId = refOf(created, "code");
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/maintenance/templates/${templateId}`);
}

export { createTemplate };
