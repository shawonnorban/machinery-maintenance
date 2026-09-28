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

async function storeExpense(previousState, formData) {
  try {
    await platformApiFetch("/finance/expenses", {
      method: "POST",
      body: JSON.stringify({
        spent_on: formData.get("spent_on"),
        category: formData.get("category"),
        description: formData.get("description"),
        amount: formData.get("amount"),
        currency: formData.get("currency"),
        vendor: formData.get("vendor") || null,
        reference: formData.get("reference") || null,
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath("/platform/finance");
  return { status: "success" };
}

async function removeExpense(expenseId) {
  try {
    await platformApiFetch(`/finance/expenses/${expenseId}`, { method: "DELETE" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath("/platform/finance");
  return { status: "success" };
}

export { storeExpense, removeExpense };
