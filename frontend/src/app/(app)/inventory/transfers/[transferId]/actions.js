"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

async function approveTransfer(transferId) {
  try {
    await apiFetch(`/inventory-transfers/${transferId}/approve`, { method: "POST" });
    revalidatePath(`/inventory/transfers/${transferId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function rejectTransfer(transferId, reason) {
  try {
    await apiFetch(`/inventory-transfers/${transferId}/reject`, {
      method: "POST",
      body: JSON.stringify({ reason }),
    });
    revalidatePath(`/inventory/transfers/${transferId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Full requested quantities, left to the ledger's own default (mirrors the web form left blank). */
async function dispatchTransfer(transferId) {
  try {
    await apiFetch(`/inventory-transfers/${transferId}/dispatch`, {
      method: "POST",
      body: JSON.stringify({}),
    });
    revalidatePath(`/inventory/transfers/${transferId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `TransferStock::receive` — a destination bin is required per item unless one was already set at creation, which never happens on this form. */
async function receiveTransfer(transferId, bins) {
  try {
    await apiFetch(`/inventory-transfers/${transferId}/receive`, {
      method: "POST",
      body: JSON.stringify({ bins }),
    });
    revalidatePath(`/inventory/transfers/${transferId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { approveTransfer, rejectTransfer, dispatchTransfer, receiveTransfer };
