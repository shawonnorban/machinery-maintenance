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

async function markRead(notificationId) {
  try {
    await apiFetch(`/notifications/${notificationId}/read`, { method: "POST" });
    revalidatePath("/notifications");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function markAllRead() {
  try {
    await apiFetch("/notifications/read-all", { method: "POST" });
    revalidatePath("/notifications");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Distinct from marking read: this is the act that stops an escalation. */
async function acknowledge(notificationId) {
  try {
    await apiFetch(`/notifications/${notificationId}/acknowledge`, { method: "POST" });
    revalidatePath("/notifications");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function savePreferences(previousState, formData) {
  try {
    const preferences = {};
    for (const [key, value] of formData.entries()) {
      const match = key.match(/^preferences\[(.+)\]\[(.+)\]$/);
      if (!match) continue;
      const [, eventType, channel] = match;
      preferences[eventType] ??= {};
      // A hidden "0" field precedes each checkbox, so the last value wins
      // when checked ("1") and the hidden default survives when it isn't.
      preferences[eventType][channel] = value === "1";
    }

    await apiFetch("/notification-preferences", {
      method: "PATCH",
      body: JSON.stringify({ preferences }),
    });

    revalidatePath("/notifications/preferences");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { markRead, markAllRead, acknowledge, savePreferences };
