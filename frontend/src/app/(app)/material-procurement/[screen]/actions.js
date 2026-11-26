"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Material Procurement → Master Data (AnnotechERP 08/01): the screen kit's
 * resources — add, edit, and the Status switch.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done() {
  revalidatePath("/material-procurement", "layout");
  return { status: "success" };
}

function body(formData) {
  const out = {};
  for (const [key, value] of formData.entries())
    if (key !== "__intent") out[key] = value === "" ? null : value;
  if (formData.has("active")) out.active = formData.get("active") === "1";
  return out;
}

async function createResource(resource, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, {
      method: "POST",
      body: JSON.stringify(body(formData)),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify(body(formData)),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function setActive(resource, id, active) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify({ active }),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, setActive };

/*
 * Material Procurement → Purchase Requisition (AnnotechERP 08/03): a
 * requisition saved (new or a draft again, `submit` to send it at once),
 * submitted to all suppliers, or a draft deleted — by its number.
 */
async function requisitionAction(op, ...args) {
  const base = "/material-procurement/requisitions";
  const calls = {
    save: ([no, payload]) => [
      no ? `${base}/${encodeURIComponent(no)}` : base,
      { method: no ? "PUT" : "POST", body: JSON.stringify(payload) },
    ],
    submit: ([no]) => [
      `${base}/${encodeURIComponent(no)}/submit`,
      { method: "POST", body: JSON.stringify({}) },
    ],
    remove: ([no]) => [
      `${base}/${encodeURIComponent(no)}`,
      { method: "DELETE" },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown requisition call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    revalidatePath("/material-procurement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { requisitionAction };

/*
 * Material Procurement → Control → Supplier Portal Access (AnnotechERP 08/07):
 * a new supplier login, and its Active switch.
 */
async function supplierLoginAction(op, ...args) {
  const base = "/material-procurement/supplier-logins";
  const calls = {
    create: ([payload]) => [
      base,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    active: ([id, on]) => [
      `${base}/${encodeURIComponent(id)}/active`,
      { method: "PUT", body: JSON.stringify({ active: on }) },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown supplier login call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    revalidatePath("/material-procurement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { supplierLoginAction };

/*
 * Material Procurement → Supplier Portal (AnnotechERP 08/04): the portal's
 * screens read again in place, and a quotation submitted on a line — the
 * first price, or its next revision.
 */
async function portalAction(op, ...args) {
  const base = "/material-procurement/portal";
  const calls = {
    dashboard: () => [`${base}/dashboard`, { method: "GET" }],
    requirements: () => [`${base}/requirements`, { method: "GET" }],
    quotations: () => [`${base}/quotations`, { method: "GET" }],
    quote: ([line, payload]) => [
      `${base}/requirements/${encodeURIComponent(line)}/quote`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown portal call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET")
      revalidatePath("/material-procurement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { portalAction };

/*
 * Material Procurement → Price Review & Approval (AnnotechERP 08/05): the
 * review read again in place, and a decision — negotiate, approve and hand
 * over, or reject a quotation; or reject a whole line.
 */
async function reviewAction(op, ...args) {
  const base = "/material-procurement/price-review";
  const calls = {
    load: () => [base, { method: "GET" }],
    negotiate: ([id, payload]) => [
      `${base}/quotations/${encodeURIComponent(id)}/negotiate`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    approve: ([id, payload]) => [
      `${base}/quotations/${encodeURIComponent(id)}/approve`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    reject: ([id, payload]) => [
      `${base}/quotations/${encodeURIComponent(id)}/reject`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    rejectLine: ([id, payload]) => [
      `${base}/lines/${encodeURIComponent(id)}/reject`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown review call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET")
      revalidatePath("/material-procurement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { reviewAction };

/*
 * Material Procurement → Approved Material & Follow-Up (AnnotechERP 08/06): the
 * list for a scope, one line in full, a follow-up logged, and a delivery
 * raised as a draft goods receipt on Merchandise Desk.
 */
async function followupAction(op, ...args) {
  const base = "/material-procurement";
  const calls = {
    list: ([scope]) => [
      `${base}/assigned-materials${scope === "all" ? "?scope=all" : ""}`,
      { method: "GET" },
    ],
    show: ([line]) => [
      `${base}/assigned-materials/${encodeURIComponent(line)}`,
      { method: "GET" },
    ],
    grnOptions: () => [
      `${base}/assigned-materials/grn-options`,
      { method: "GET" },
    ],
    followup: ([line, payload]) => [
      `${base}/assigned-materials/${encodeURIComponent(line)}/followups`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    grn: ([followup, payload]) => [
      `${base}/followups/${encodeURIComponent(followup)}/grn`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown follow-up call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET")
      revalidatePath("/material-procurement", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { followupAction };

/*
 * Material Procurement → Control (AnnotechERP 08/07): one notification read,
 * or all of this module's. Nothing else on the page changes, so nothing is
 * revalidated.
 */
async function controlAction(op, ...args) {
  const base = "/material-procurement/procurement-notifications";
  const calls = {
    read: ([id]) => `${base}/${encodeURIComponent(id)}/read`,
    readAll: () => `${base}/read-all`,
  };
  if (!calls[op]) throw new Error(`Unknown control call: ${op}`);
  try {
    return {
      status: "success",
      data: await apiFetch(calls[op](args), { method: "POST" }),
    };
  } catch (error) {
    return fail(error);
  }
}

export { controlAction };
