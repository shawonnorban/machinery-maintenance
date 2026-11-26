"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Prototype Desk (AnnotechERP 07): load, save and move the sample documents —
 * requisitions, developments, revisions, day sheets (by date), target
 * plans, quality checks, approvals, buyer feedback and handovers — by their
 * number.
 */

const KINDS = ["requisitions", "developments", "revisions", "material-requisitions", "material-issues", "bundle-cards", "day-sheets", "target-plans", "quality-checks", "approvals", "feedback", "handovers"];
/** The documents a list removes (a requisition before approval, a day sheet not closed, a Ready handover). */
const REMOVABLE = ["material-requisitions", "day-sheets", "handovers"];
const ACTIONS = { requisitions: ["submit", "decide", "assign", "cancel"], revisions: ["approve"], "material-requisitions": ["submit", "dept-approve", "verify", "approve", "reject", "cancel"], "bundle-cards": ["issue", "receive", "start", "hold", "return", "cancel"] };

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function path(kind, no) {
  if (!KINDS.includes(kind)) throw new Error(`Unknown prototype document: ${kind}`);
  return `/prototype/${kind}${no ? `/${encodeURIComponent(no)}` : ""}`;
}

/** One document, with its history, to load back into its form. */
async function loadProto(kind, no) {
  try {
    return { status: "success", data: await apiFetch(path(kind, no)) };
  } catch (error) {
    return fail(error);
  }
}

/** Create (no number yet) or update a document. */
async function saveProto(kind, no, payload) {
  try {
    const data = await apiFetch(path(kind, no), { method: no ? "PUT" : "POST", body: JSON.stringify(payload) });
    revalidatePath("/prototype-desk", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

/** Submit, decide, assign, cancel, approve. */
async function actProto(kind, no, action, payload = {}) {
  if (!(ACTIONS[kind] ?? []).includes(action)) throw new Error(`Unknown action: ${kind}/${action}`);
  try {
    const data = await apiFetch(`${path(kind, no)}/${action}`, { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/prototype-desk", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

/** Remove a document the list allows removing. */
async function removeProto(kind, no) {
  if (!REMOVABLE.includes(kind)) throw new Error(`Not removable: ${kind}`);
  try {
    const data = await apiFetch(path(kind, no), { method: "DELETE" });
    revalidatePath("/prototype-desk", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { actProto, loadProto, removeProto, saveProto };

/*
 * Sample Bundle Tracking: each scan is its own record, so these act at once —
 * a scan, its undo, a movement's tracking, and a sample card's delivery.
 */
async function trackProto(op, ...args) {
  const calls = {
    desk: ([process]) => [`/prototype/tracking/desk/${encodeURIComponent(process)}`, { method: "GET" }],
    scan: ([process, code]) => ["/prototype/tracking/scan", { method: "POST", body: JSON.stringify({ process, code }) }],
    undo: ([id]) => [`/prototype/tracking/movements/${encodeURIComponent(id)}`, { method: "DELETE" }],
    update: ([id, payload]) => [`/prototype/tracking/movements/${encodeURIComponent(id)}`, { method: "PUT", body: JSON.stringify(payload) }],
    deliver: ([card, payload]) => [`/prototype/tracking/sample-cards/${encodeURIComponent(card)}`, { method: "PUT", body: JSON.stringify(payload) }],
  };
  if (!calls[op]) throw new Error(`Unknown tracking call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/prototype-desk", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { trackProto };

/*
 * Prototype Desk → Dashboard (AnnotechERP 07/02): the dashboard for a period,
 * one sample end to end, an alert read, resolved or reopened, and a sample
 * document loaded or retitled. A new document (with its file) goes through
 * the /api/prototype/documents route instead — a file runs past a server
 * action's one megabyte.
 */
async function deskProto(op, ...args) {
  const calls = {
    dashboard: ([range]) => [`/prototype/dashboard?range=${encodeURIComponent(range)}`, { method: "GET" }],
    sample: ([no]) => [`/prototype/samples/${encodeURIComponent(no)}`, { method: "GET" }],
    alert: ([id, action]) => {
      if (!["read", "resolve", "reopen"].includes(action)) throw new Error(`Unknown alert action: ${action}`);
      return [`/prototype/alerts/${encodeURIComponent(id)}/${action}`, { method: "POST", body: JSON.stringify({}) }];
    },
    document: ([no]) => [`/prototype/documents/${encodeURIComponent(no)}`, { method: "GET" }],
    retitle: ([no, payload]) => [`/prototype/documents/${encodeURIComponent(no)}`, { method: "PUT", body: JSON.stringify(payload) }],
  };
  if (!calls[op]) throw new Error(`Unknown desk call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/prototype-desk", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { deskProto };
