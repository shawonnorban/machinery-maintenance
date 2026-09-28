"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * No single endpoint accepts both the ticket and a first attachment
 * together, so this is two calls: create the ticket, then — only if a file
 * was actually chosen — upload it against the id the first call returned.
 * A failure in the second call is not treated as the whole submission
 * failing: the ticket itself already exists at that point, so this still
 * redirects there rather than losing the customer's subject/message over
 * an attachment that can just as well be added from the ticket page.
 */
async function createTicket(previousState, formData) {
  let ticketId;

  try {
    const ticket = await apiFetch("/support/tickets", {
      method: "POST",
      body: JSON.stringify({
        subject: formData.get("subject"),
        body: formData.get("body"),
        priority: formData.get("priority") || undefined,
      }),
    });
    ticketId = ticket.id;
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  const file = formData.get("file");
  if (file instanceof File && file.size > 0) {
    const attachmentForm = new FormData();
    attachmentForm.set("file", file);
    // Already returns a {status: "error", ...} object rather than throwing
    // for the ordinary failure cases (too large, wrong type) — swallowed
    // here on purpose, per this function's own note above.
    await uploadTicketAttachment(ticketId, attachmentForm);
  }

  redirect(`/support/tickets/${ticketId}`);
}

/**
 * The reply form also carries an optional attachment now (moved there from
 * its own separate upload form — see `TicketAttachments`, now read-only) —
 * same two-call shape as `createTicket`, for the same reason: no endpoint
 * accepts a reply and a file together.
 */
async function replyTicket(ticketId, previousState, formData) {
  try {
    await apiFetch(`/support/tickets/${ticketId}/reply`, {
      method: "POST",
      body: JSON.stringify({ body: formData.get("body") }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  const file = formData.get("file");
  if (file instanceof File && file.size > 0) {
    const attachmentForm = new FormData();
    attachmentForm.set("file", file);
    await uploadTicketAttachment(ticketId, attachmentForm);
  }

  revalidatePath(`/support/tickets/${ticketId}`);
  return { status: "success" };
}

/** Mirrors `SupportTicketApiController::update` — refused (409) once anyone has replied, or if the caller isn't the ticket's own opener (403). */
async function updateTicket(ticketId, previousState, formData) {
  try {
    await apiFetch(`/support/tickets/${ticketId}`, {
      method: "PATCH",
      body: JSON.stringify({
        subject: formData.get("subject"),
        body: formData.get("body"),
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/support/tickets/${ticketId}`);
}

/** Mirrors `SupportTicketApiController::destroy` — same 409/403 shape as `updateTicket`. */
async function deleteTicket(ticketId) {
  try {
    await apiFetch(`/support/tickets/${ticketId}`, { method: "DELETE" });
    revalidatePath("/support/tickets");
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

async function uploadTicketAttachment(ticketId, formData) {
  try {
    const file = formData.get("file");
    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }
    const body = new FormData();
    body.set("file", file);
    await apiFetch(`/support/tickets/${ticketId}/attachments`, { method: "POST", body });
    revalidatePath(`/support/tickets/${ticketId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createTicket, replyTicket, uploadTicketAttachment, updateTicket, deleteTicket };
