"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function updateWebhook(endpointId, previousState, formData) {
  try {
    await apiFetch(`/webhooks/${endpointId}`, {
      method: "PATCH",
      body: JSON.stringify({
        url: formData.get("url"),
        description: formData.get("description") || null,
        events: formData.getAll("events"),
      }),
    });
    revalidatePath(`/settings/webhooks/${endpointId}`);
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/settings/webhooks/${endpointId}`);
}

export { updateWebhook };
