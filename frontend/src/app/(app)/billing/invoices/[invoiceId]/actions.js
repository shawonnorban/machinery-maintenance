"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `BillingController::pay`/`SubscriptionApiController::storePayment` — a separate permission from managing the subscription, since the person who signs the contract and the person who confirms a bank transfer arrived are rarely the same person. */
async function recordPayment(invoiceId, previousState, formData) {
  try {
    await apiFetch(`/subscription/invoices/${invoiceId}/pay`, {
      method: "POST",
      body: JSON.stringify({
        amount: formData.get("amount"),
        method: formData.get("method"),
        payment_reference: formData.get("payment_reference") || null,
        paid_at: formData.get("paid_at") ? new Date(formData.get("paid_at")).toISOString() : null,
        notes: formData.get("notes") || null,
      }),
    });
    revalidatePath(`/billing/invoices/${invoiceId}`);
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { recordPayment };
