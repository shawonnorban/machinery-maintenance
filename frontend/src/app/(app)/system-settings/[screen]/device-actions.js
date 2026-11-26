"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Settings → Device Integration (AnnotechERP 03/01): the register, a
 * terminal's actions, the punch log, enrolled users and the command queue.
 * The screens keep their own state, so nothing reloads.
 */
async function deviceAction(op, ...args) {
  const enc = encodeURIComponent;
  const qs = (query) => new URLSearchParams(query ?? {}).toString();
  const calls = {
    index: () => ["/settings/devices", { method: "GET" }],
    store: ([body]) => ["/settings/devices", { method: "POST", body: JSON.stringify(body) }],
    update: ([code, body]) => [`/settings/devices/${enc(code)}`, { method: "PUT", body: JSON.stringify(body) }],
    destroy: ([code]) => [`/settings/devices/${enc(code)}`, { method: "DELETE" }],
    action: ([code, action]) => [`/settings/devices/${enc(code)}/action`, { method: "POST", body: JSON.stringify({ action }) }],
    punches: ([query]) => [`/settings/device-punches?${qs(query)}`, { method: "GET" }],
    postPending: () => ["/settings/device-punches/post-pending", { method: "POST" }],
    users: ([query]) => [`/settings/device-users?${qs(query)}`, { method: "GET" }],
    commands: () => ["/settings/device-commands", { method: "GET" }],
    sendCommand: ([body]) => ["/settings/device-commands", { method: "POST", body: JSON.stringify(body) }],
    cancelCommand: ([id]) => [`/settings/device-commands/${enc(id)}/cancel`, { method: "POST" }],
  };
  if (!calls[op]) throw new Error(`Unknown device call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/system-settings", "layout");
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
    throw error;
  }
}

export { deviceAction };
