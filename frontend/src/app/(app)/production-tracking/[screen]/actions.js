"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Production Tracking (AnnotechERP 30): every write the screens make — Line
 * Capacity Master, Production Master Data, Daily Production Plan (draft,
 * edit, submit, approve, reject, revise, cancel, history, preview), the TV
 * screens and their display links, and Settings — through one `ptAction`.
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

const CALLS = {
  saveCapacity: (body, id) => [id ? `/production-tracking/capacities/${id}` : "/production-tracking/capacities", json(id ? "PATCH" : "POST", body)],
  saveEntry: (body, id) => [id ? `/production-tracking/entries/${id}` : "/production-tracking/entries", json(id ? "PATCH" : "POST", body)],
  deleteEntry: (id) => [`/production-tracking/entries/${id}`, json("DELETE")],
  previewPlan: (body) => ["/production-tracking/plans/preview", json("POST", body)],
  savePlan: (body, id) => [id ? `/production-tracking/plans/${id}` : "/production-tracking/plans", json(id ? "PATCH" : "POST", body)],
  planAct: (id, action, body) => [`/production-tracking/plans/${id}/${action}`, json("POST", body ?? {})],
  planHistory: (id) => [`/production-tracking/plans/${id}/history`, { method: "GET" }],
  saveTv: (body, id) => [id ? `/production-tracking/tv-screens/${id}` : "/production-tracking/tv-screens", json(id ? "PATCH" : "POST", body)],
  deleteTv: (id) => [`/production-tracking/tv-screens/${id}`, json("DELETE")],
  previewTv: (id) => [`/production-tracking/tv-screens/${id}/preview`, json("GET")],
  issueTv: (id) => [`/production-tracking/tv-screens/${id}/link`, json("POST")],
  revokeTv: (id) => [`/production-tracking/tv-screens/${id}/link`, json("DELETE")],
  saveSettings: (body) => ["/production-tracking/settings", json("PUT", body)],
};

const READS = new Set(["previewPlan", "planHistory"]);

async function ptAction(name, ...args) {
  const call = CALLS[name];
  if (!call) return { status: "error", message: `Unknown action ${name}` };
  try {
    const [path, options] = call(...args);
    const data = await apiFetch(path, options);
    if (!READS.has(name)) revalidatePath("/production-tracking", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { ptAction };
