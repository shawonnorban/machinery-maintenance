"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function updateScopes(clientId, previousState, formData) {
  try {
    await apiFetch(`/api-clients/${clientId}`, {
      method: "PATCH",
      body: JSON.stringify({ scopes: formData.getAll("scopes") }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect("/settings/api-clients");
}

export { updateScopes };
