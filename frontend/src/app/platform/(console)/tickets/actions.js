"use server";

import { revalidatePath } from "next/cache";
import { platformApiFetch } from "@/lib/platform-api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

async function replyToTicket(ticketId, previousState, formData) {
  try {
    await platformApiFetch(`/tickets/${ticketId}/reply`, {
      method: "POST",
      body: JSON.stringify({
        body: formData.get("body"),
        is_internal: formData.get("is_internal") === "true",
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tickets/${ticketId}`);
  return { status: "success" };
}

async function setTicketStatus(ticketId, status) {
  try {
    await platformApiFetch(`/tickets/${ticketId}/status`, {
      method: "PATCH",
      body: JSON.stringify({ status }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tickets/${ticketId}`);
  revalidatePath("/platform/tickets");
  return { status: "success" };
}

async function assignTicket(ticketId, assignedTo) {
  try {
    await platformApiFetch(`/tickets/${ticketId}/assign`, {
      method: "PATCH",
      body: JSON.stringify({ assigned_to: assignedTo || null }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tickets/${ticketId}`);
  revalidatePath("/platform/tickets");
  return { status: "success" };
}

async function reclassifyTicket(ticketId, priority, category) {
  try {
    await platformApiFetch(`/tickets/${ticketId}/classification`, {
      method: "PATCH",
      body: JSON.stringify({ priority, category }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tickets/${ticketId}`);
  revalidatePath("/platform/tickets");
  return { status: "success" };
}

async function uploadTicketAttachment(ticketId, formData) {
  try {
    const file = formData.get("file");
    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }
    const body = new FormData();
    body.set("file", file);
    await platformApiFetch(`/tickets/${ticketId}/attachments`, { method: "POST", body });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tickets/${ticketId}`);
  return { status: "success" };
}

export { replyToTicket, setTicketStatus, assignTicket, reclassifyTicket, uploadTicketAttachment };
