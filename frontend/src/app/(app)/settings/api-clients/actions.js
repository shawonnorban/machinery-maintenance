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

async function rotateSecret(clientId) {
  try {
    const result = await apiFetch(`/api-clients/${clientId}/rotate`, { method: "POST" });
    revalidatePath("/settings/api-clients");
    return { status: "success", secret: result.secret };
  } catch (error) {
    return fail(error);
  }
}

async function revokeClient(clientId) {
  try {
    await apiFetch(`/api-clients/${clientId}`, { method: "DELETE" });
    revalidatePath("/settings/api-clients");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { rotateSecret, revokeClient };
