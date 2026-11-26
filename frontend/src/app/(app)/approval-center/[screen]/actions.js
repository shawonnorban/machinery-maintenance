"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Approval Center (AnnotechERP 04): requests written by hand, decisions on
 * every module's requests, and the request types. Every action answers
 * `{status}` for the shared EntryForm / RecordsGrid / dialogs.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/approval-center", "layout");
  return { status: "success", ...extra };
}

const TICKET_FIELDS = [
  "request_type_id",
  "priority",
  "title",
  "reference_type",
  "reference_no",
  "buyer_name",
  "order_no",
  "style_no",
  "po_no",
  "quantity",
  "unit",
  "amount",
  "currency",
  "request_to_user_id",
  "due_date",
  "remarks",
];

/** New Request / edit a draft or a returned request: "Save draft" or "Submit request". */
async function saveTicket(ticketId, previousState, formData) {
  try {
    const body = { submit: formData.get("__intent") !== "secondary" };
    for (const key of TICKET_FIELDS) body[key] = formData.has(key) ? formData.get(key) : null;
    const ticket = await apiFetch(ticketId ? `/approval-center/tickets/${ticketId}` : "/approval-center/tickets", {
      method: ticketId ? "PATCH" : "POST",
      body: JSON.stringify(body),
    });
    return done({ ticket });
  } catch (error) {
    return fail(error);
  }
}

async function loadRequest(id) {
  try {
    return { status: "success", request: await apiFetch(`/approval-center/requests/${id}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadTicket(id) {
  try {
    return { status: "success", ticket: await apiFetch(`/approval-center/tickets/${id}`) };
  } catch (error) {
    return fail(error);
  }
}

/** Approve, reject or return; a refusal or a return carries its reason. */
async function decide(id, decision, comment) {
  try {
    const path = { approve: "approve", reject: "reject", return: "return" }[decision];
    const request = await apiFetch(`/approval-center/requests/${id}/${path}`, { method: "POST", body: JSON.stringify({ comment: comment || null }) });
    return done({ request });
  } catch (error) {
    return fail(error);
  }
}

async function cancelTicket(id, comment) {
  try {
    await apiFetch(`/approval-center/tickets/${id}/cancel`, { method: "POST", body: JSON.stringify({ comment: comment || null }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteTicket(id) {
  try {
    await apiFetch(`/approval-center/tickets/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

function typeBody(formData) {
  const out = {};
  for (const [key, value] of formData.entries()) if (key !== "__intent") out[key] = value === "" ? null : value;
  out.active = formData.get("active") === "1";
  return out;
}

async function createRequestType(previousState, formData) {
  try {
    await apiFetch("/erp/approval-request-types", { method: "POST", body: JSON.stringify(typeBody(formData)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateRequestType(id, previousState, formData) {
  try {
    await apiFetch(`/erp/approval-request-types/${id}`, { method: "PATCH", body: JSON.stringify(typeBody(formData)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteRequestType(id) {
  try {
    await apiFetch(`/erp/approval-request-types/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

export { saveTicket, loadRequest, loadTicket, decide, cancelTicket, deleteTicket, createRequestType, updateRequestType, deleteRequestType };
