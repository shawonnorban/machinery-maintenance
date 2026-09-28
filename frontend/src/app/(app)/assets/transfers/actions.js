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
    await apiFetch(`/transfers/${transferId}/approve`, { method: "POST" });
    revalidatePath("/assets/transfers");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function receiveTransfer(transferId) {
  try {
    await apiFetch(`/transfers/${transferId}/receive`, { method: "POST" });
    revalidatePath("/assets/transfers");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function rejectTransfer(transferId, previousState, formData) {
  try {
    await apiFetch(`/transfers/${transferId}/reject`, {
      method: "POST",
      body: JSON.stringify({ rejection_reason: formData.get("rejection_reason") }),
    });
    revalidatePath("/assets/transfers");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { approveTransfer, receiveTransfer, rejectTransfer };
