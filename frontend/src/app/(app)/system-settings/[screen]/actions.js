"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Settings (AnnotechERP 03): the preference forms, the numbering series and the
 * screen kit's resources (currencies, label templates). Every action answers
 * `{status}` for the shared EntryForm / RecordsGrid.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done() {
  revalidatePath("/system-settings", "layout");
  return { status: "success" };
}

/** A setting's value as the form holds it, to tell what changed. */
function asText(type, value) {
  if (type === "BOOL") return value ? "1" : "0";
  if (type === "LIST") return Array.isArray(value) ? value.join(",") : String(value ?? "");
  return value === null || value === undefined ? "" : String(value);
}

/**
 * Saves the preference form: only the keys whose value changed are written, so
 * an untouched setting keeps following its default.
 */
async function saveSettings(keys, previousState, formData) {
  try {
    const [definitions, current] = await Promise.all([apiFetch("/settings/definitions"), apiFetch("/settings")]);
    const types = Object.fromEntries(definitions.map((d) => [d.key, d.value_type]));
    const now = Object.fromEntries(current.map((row) => [row.key, row.value]));
    const errors = {};
    for (const key of keys) {
      const type = types[key];
      const sent = formData.has(key) ? String(formData.get(key)) : type === "BOOL" ? "0" : "";
      if (sent === asText(type, now[key])) continue;
      try {
        await apiFetch(`/settings/${key}`, { method: "PUT", body: JSON.stringify({ value: sent }) });
      } catch (error) {
        if (!(error instanceof ApiError)) throw error;
        errors[key] = [error.errors?.value?.[0] ?? error.message];
      }
    }
    if (Object.keys(errors).length > 0) return { status: "error", message: Object.values(errors)[0][0], errors };
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateNumbering(documentType, previousState, formData) {
  try {
    await apiFetch(`/numbering/${documentType}`, { method: "PATCH", body: JSON.stringify({ format: formData.get("format"), padding: Number(formData.get("padding")) }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Back to the built-in pattern (the grid's delete action). */
async function resetNumbering(documentType) {
  try {
    await apiFetch(`/numbering/${documentType}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

function resourceBody(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries()) out[key] = value === "" ? null : value;
  for (const key of booleans) out[key] = formData.get(key) === "1";
  return out;
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, { method: "POST", body: JSON.stringify(resourceBody(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(resourceBody(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** SMS Gateway → "Send test SMS": the provider's answer becomes the message. */
async function testSms(previousState, formData) {
  try {
    const result = await apiFetch(`/settings/sms-gateways/${formData.get("gateway_id")}/test`, {
      method: "POST",
      body: JSON.stringify({ to: formData.get("to"), message: formData.get("message") || null }),
    });
    revalidatePath("/system-settings", "layout");
    return result.ok ? { status: "success", message: result.message } : { status: "error", message: result.message };
  } catch (error) {
    return fail(error);
  }
}

/** Bank & Payment Integration → "Test connection" (the grid's row action). */
async function testBank(id) {
  try {
    const result = await apiFetch(`/settings/bank-integrations/${id}/test`, { method: "POST" });
    revalidatePath("/system-settings", "layout");
    return { status: result.ok ? "success" : "error", message: result.message };
  } catch (error) {
    return fail(error);
  }
}

/** AI Services: asks the service one small question; the result is recorded on the row. */
async function testAiService(id) {
  try {
    const result = await apiFetch(`/ai/services/${id}/test`, { method: "POST" });
    revalidatePath("/system-settings", "layout");
    return { status: result.ok ? "success" : "error", message: result.message };
  } catch (error) {
    return fail(error);
  }
}

/** Notification Preferences: the whole matrix at once, `{EVENT: {email, sms, whatsapp, locked}}`. */
async function saveNotificationDefaults(defaults) {
  try {
    await apiFetch("/settings/notification-defaults", { method: "PUT", body: JSON.stringify({ defaults }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Back to the built-in default (the Records grid's delete). */
async function resetNotificationDefault(event) {
  try {
    await apiFetch(`/settings/notification-defaults/${encodeURIComponent(event)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function createBackup() {
  try {
    await apiFetch("/settings/backups", { method: "POST" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function applyRestore(id, code) {
  try {
    await apiFetch(`/settings/restores/${id}/apply`, { method: "POST", body: JSON.stringify({ confirm_code: code }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Data Retention → Policies: fields arrive as `CATEGORY__keep_days` / `CATEGORY__active`. */
async function saveRetention(categories, previousState, formData) {
  try {
    const policies = Object.fromEntries(categories.map((c) => [c, { keep_days: Number(formData.get(`${c}__keep_days`)), active: formData.get(`${c}__active`) === "1" }]));
    await apiFetch("/settings/retention", { method: "PUT", body: JSON.stringify({ policies }) });
    return done();
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      const errors = Object.fromEntries(Object.entries(error.errors).map(([key, value]) => [key.replace(/^policies\.([A-Z_]+)\.(\w+)$/, "$1__$2"), value]));
      return { status: "error", message: error.message, errors };
    }
    return fail(error);
  }
}

async function purgeRetention() {
  try {
    const result = await apiFetch("/settings/retention/purge", { method: "POST" });
    revalidatePath("/system-settings", "layout");
    return { status: "success", removed: Object.values(result.removed ?? {}).reduce((a, b) => a + b, 0) };
  } catch (error) {
    return fail(error);
  }
}

export {
  saveSettings,
  updateNumbering,
  resetNumbering,
  createResource,
  updateResource,
  deleteResource,
  testSms,
  testBank,
  saveNotificationDefaults,
  resetNotificationDefault,
  createBackup,
  applyRestore,
  saveRetention,
  purgeRetention,
  testAiService,
};
