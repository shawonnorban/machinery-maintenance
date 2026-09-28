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

async function startDraft(templateId) {
  try {
    const draft = await apiFetch(`/maintenance-templates/${templateId}/draft`, { method: "POST" });
    revalidatePath(`/maintenance/templates/${templateId}`);
    return { status: "success", versionId: draft.id };
  } catch (error) {
    return fail(error);
  }
}

async function publishVersion(templateId, versionId) {
  try {
    await apiFetch(`/maintenance-templates/${templateId}/versions/${versionId}/publish`, { method: "POST" });
    revalidatePath(`/maintenance/templates/${templateId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function addItem(templateId, versionId, previousState, formData) {
  try {
    await apiFetch(`/maintenance-templates/${templateId}/versions/${versionId}/items`, {
      method: "POST",
      body: JSON.stringify({
        label: formData.get("label"),
        input_type: formData.get("input_type"),
        unit: formData.get("unit") || null,
        tolerance_min: formData.get("tolerance_min") || null,
        tolerance_max: formData.get("tolerance_max") || null,
        help_text: formData.get("help_text") || null,
        required: formData.get("required") === "1",
        is_safety_item: formData.get("is_safety_item") === "1",
      }),
    });
    revalidatePath(`/maintenance/templates/${templateId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function removeItem(templateId, versionId, itemId) {
  try {
    await apiFetch(`/maintenance-templates/${templateId}/versions/${versionId}/items/${itemId}`, { method: "DELETE" });
    revalidatePath(`/maintenance/templates/${templateId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { startDraft, publishVersion, addItem, removeItem };
