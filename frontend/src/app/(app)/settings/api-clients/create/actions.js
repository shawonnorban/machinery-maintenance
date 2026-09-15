"use server";

import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function createApiClient(previousState, formData) {
  try {
    const created = await apiFetch("/api-clients", {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        expires_at: formData.get("expires_at") || null,
        scopes: formData.getAll("scopes"),
      }),
    });

    return { status: "success", clientId: created.client_id, secret: created.secret };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createApiClient };
