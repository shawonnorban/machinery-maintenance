"use server";

import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function createWebhook(previousState, formData) {
  try {
    const created = await apiFetch("/webhooks", {
      method: "POST",
      body: JSON.stringify({
        url: formData.get("url"),
        description: formData.get("description") || null,
        events: formData.getAll("events"),
      }),
    });

    return { status: "success", endpointId: created.id, secret: created.secret };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createWebhook };
