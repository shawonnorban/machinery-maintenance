"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Gate Pass → Master Data (AnnotechERP 11/01): the screen kit's
 * resources, added and edited through the dialogs. Dashboard, Reports and
 * the gate pass screens (11/02–11/04): every read and step they take in
 * place.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function body(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries())
    if (key !== "__intent") out[key] = value === "" ? null : value;
  for (const key of booleans)
    if (formData.has(key)) out[key] = formData.get(key) === "1";
  return out;
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, {
      method: "POST",
      body: JSON.stringify(body(formData, booleans)),
    });
    revalidatePath("/gate-pass", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify(body(formData, booleans)),
    });
    revalidatePath("/gate-pass", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Dashboard, Reports and the gate pass screens (11/02–11/04): a pass by its number, never its key. */
async function gpAction(op, ...args) {
  const base = "/gate-pass";
  const q = (query = {}) =>
    Object.keys(query).length ? `?${new URLSearchParams(query)}` : "";
  const pass = (no) => `${base}/passes/${encodeURIComponent(no)}`;
  const post = (url, payload) => [
    url,
    { method: "POST", body: JSON.stringify(payload ?? {}) },
  ];
  const calls = {
    employee: ([card]) => [
      `${base}/employees/${encodeURIComponent(card)}`,
      { method: "GET" },
    ],
    list: ([list, f]) => [
      `${base}/passes${q({ list, ...(f ?? {}) })}`,
      { method: "GET" },
    ],
    show: ([no]) => [pass(no), { method: "GET" }],
    save: ([payload]) => post(`${base}/passes`, payload),
    update: ([no, payload]) => [
      pass(no),
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    submit: ([no]) => post(`${pass(no)}/submit`),
    approve: ([no, payload]) => post(`${pass(no)}/approve`, payload),
    reject: ([no, payload]) => post(`${pass(no)}/reject`, payload),
    sendBack: ([no, payload]) => post(`${pass(no)}/send-back`, payload),
    cancel: ([no, payload]) => post(`${pass(no)}/cancel`, payload),
    out: ([no, payload]) => post(`${pass(no)}/out`, payload),
    in: ([no, payload]) => post(`${pass(no)}/in`, payload),
    close: ([no, payload]) => post(`${pass(no)}/close`, payload),
    report: ([key, f]) => [
      `${base}/reports/${encodeURIComponent(key)}${q(f)}`,
      { method: "GET" },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown gate pass call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/gate-pass", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, gpAction };
