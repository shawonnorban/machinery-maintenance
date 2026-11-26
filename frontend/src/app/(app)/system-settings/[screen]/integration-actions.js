"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Settings → API Keys & Webhooks (AnnotechERP 03/02): every action on an API
 * credential or a webhook endpoint. The secrets (a new key, a new webhook, a
 * rotation) come back once in the result and are never shown again.
 */

const SCREEN = "/system-settings/api-keys-webhooks";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

async function run(call) {
  try {
    const result = await call();
    revalidatePath(SCREEN);
    return { status: "success", ...(result ?? {}) };
  } catch (error) {
    return fail(error);
  }
}

/* ── API keys ── */

async function createApiClient(previousState, formData) {
  return run(async () => {
    const created = await apiFetch("/api-clients", {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        expires_at: formData.get("expires_at") || null,
        scopes: formData.getAll("scopes"),
      }),
    });
    return { clientId: created.client_id, secret: created.secret };
  });
}

async function updateScopes(clientId, ref, previousState, formData) {
  try {
    await apiFetch(`/api-clients/${clientId}`, { method: "PATCH", body: JSON.stringify({ scopes: formData.getAll("scopes") }) });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(SCREEN);
  redirect(`${SCREEN}?tab=key&key=${encodeURIComponent(ref)}`);
}

async function rotateClientSecret(clientId) {
  return run(async () => {
    const result = await apiFetch(`/api-clients/${clientId}/rotate`, { method: "POST" });
    return { secret: result.secret };
  });
}

async function revokeClient(clientId) {
  return run(() => apiFetch(`/api-clients/${clientId}`, { method: "DELETE" }));
}

/* ── Webhooks ── */

async function createWebhook(previousState, formData) {
  return run(async () => {
    const created = await apiFetch("/webhooks", {
      method: "POST",
      body: JSON.stringify({
        url: formData.get("url"),
        description: formData.get("description") || null,
        events: formData.getAll("events"),
      }),
    });
    return { endpointId: created.id, secret: created.secret };
  });
}

async function updateWebhook(endpointId, ref, previousState, formData) {
  try {
    await apiFetch(`/webhooks/${endpointId}`, {
      method: "PATCH",
      body: JSON.stringify({
        url: formData.get("url"),
        description: formData.get("description") || null,
        events: formData.getAll("events"),
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(SCREEN);
  redirect(`${SCREEN}?tab=webhook&hook=${encodeURIComponent(ref)}`);
}

async function enableEndpoint(endpointId) {
  return run(() => apiFetch(`/webhooks/${endpointId}/enable`, { method: "POST" }));
}

async function pauseEndpoint(endpointId) {
  return run(() => apiFetch(`/webhooks/${endpointId}/pause`, { method: "POST" }));
}

async function deleteEndpoint(endpointId) {
  return run(() => apiFetch(`/webhooks/${endpointId}`, { method: "DELETE" }));
}

async function rotateWebhookSecret(endpointId) {
  return run(async () => {
    const result = await apiFetch(`/webhooks/${endpointId}/rotate-secret`, { method: "POST" });
    return { secret: result.secret };
  });
}

async function redeliver(deliveryId) {
  return run(() => apiFetch(`/webhook-deliveries/${deliveryId}/redeliver`, { method: "POST" }));
}

export {
  createApiClient, updateScopes, rotateClientSecret, revokeClient,
  createWebhook, updateWebhook, enableEndpoint, pauseEndpoint, deleteEndpoint, rotateWebhookSecret, redeliver,
};
