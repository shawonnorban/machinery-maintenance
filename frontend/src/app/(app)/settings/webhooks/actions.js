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

async function enableEndpoint(endpointId) {
  try {
    await apiFetch(`/webhooks/${endpointId}/enable`, { method: "POST" });
    revalidatePath("/settings/webhooks");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function pauseEndpoint(endpointId) {
  try {
    await apiFetch(`/webhooks/${endpointId}/pause`, { method: "POST" });
    revalidatePath("/settings/webhooks");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteEndpoint(endpointId) {
  try {
    await apiFetch(`/webhooks/${endpointId}`, { method: "DELETE" });
    revalidatePath("/settings/webhooks");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function rotateSecret(endpointId) {
  try {
    const result = await apiFetch(`/webhooks/${endpointId}/rotate-secret`, { method: "POST" });
    revalidatePath(`/settings/webhooks/${endpointId}`);
    return { status: "success", secret: result.secret };
  } catch (error) {
    return fail(error);
  }
}

async function redeliver(deliveryId, endpointId) {
  try {
    await apiFetch(`/webhook-deliveries/${deliveryId}/redeliver`, { method: "POST" });
    revalidatePath(`/settings/webhooks/${endpointId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { enableEndpoint, pauseEndpoint, deleteEndpoint, rotateSecret, redeliver };
