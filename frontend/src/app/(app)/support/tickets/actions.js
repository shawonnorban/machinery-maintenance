"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function createTicket(previousState, formData) {
  let ticketId;

  try {
    const ticket = await apiFetch("/support/tickets", {
      method: "POST",
      body: JSON.stringify({
        subject: formData.get("subject"),
        body: formData.get("body"),
      }),
    });
    ticketId = ticket.id;
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect(`/support/tickets/${ticketId}`);
}

async function replyTicket(ticketId, previousState, formData) {
  try {
    await apiFetch(`/support/tickets/${ticketId}/reply`, {
      method: "POST",
      body: JSON.stringify({ body: formData.get("body") }),
    });
    revalidatePath(`/support/tickets/${ticketId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createTicket, replyTicket };
