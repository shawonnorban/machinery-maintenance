"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Sales & Shipment (AnnotechERP 21). Operation (21/01): the settings saved.
 * Order & Delivery (21/02): every paper read and written through the
 * module's own API — a read and a write, both kept to /sales-shipment/.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const own = (path) => {
  if (typeof path !== "string" || !path.startsWith("/sales-shipment/") || path.includes("..")) throw new Error("Not a Sales & Shipment path");
  return path;
};

async function saveSettings(payload) {
  try {
    const data = await apiFetch("/sales-shipment/settings", { method: "PUT", body: JSON.stringify(payload) });
    revalidatePath("/sales-shipment", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

/** A read of the module's API (a paper, a pick-list, a register). */
async function ssRead(path) {
  try {
    return { status: "success", data: await apiFetch(own(path)) };
  } catch (error) {
    return fail(error);
  }
}

/** A write to the module's API: POST / PUT / DELETE. */
async function ssWrite(method, path, payload = null) {
  if (!["POST", "PUT", "DELETE"].includes(method)) throw new Error("Not a write");
  try {
    const data = await apiFetch(own(path), { method, ...(payload ? { body: JSON.stringify(payload) } : {}) });
    revalidatePath("/sales-shipment", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { saveSettings, ssRead, ssWrite };
