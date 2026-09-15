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

/** Mirrors `AccountController::changePassword` (adapted for a bearer-token client — see `AuthController::changePassword()`'s own docblock). */
async function changePassword(previousState, formData) {
  try {
    await apiFetch("/auth/password", {
      method: "POST",
      body: JSON.stringify({
        current_password: formData.get("current_password"),
        password: formData.get("password"),
        password_confirmation: formData.get("password_confirmation"),
      }),
    });
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function revokeSession(sessionId) {
  try {
    await apiFetch(`/auth/sessions/${sessionId}`, { method: "DELETE" });
    revalidatePath("/account");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function revokeToken(tokenId) {
  try {
    await apiFetch(`/auth/tokens/${tokenId}`, { method: "DELETE" });
    revalidatePath("/account");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { changePassword, revokeSession, revokeToken };
