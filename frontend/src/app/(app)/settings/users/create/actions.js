"use server";

import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Mirrors `UserController::store` — the returned password is shown once,
 * in the response of the request that created it, and never again.
 * Deliberately NOT a `redirect()`: the password can only ever be handed
 * back through this action's own return value. Putting it in the target
 * URL instead (a query string) would leave it sitting in browser history
 * and any request log between here and there — the exact thing "shown
 * once" is meant to prevent. The form displays it and links onward itself.
 */
async function createUser(previousState, formData) {
  try {
    const result = await apiFetch("/users", {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        email: formData.get("email"),
        phone: formData.get("phone") || null,
        locale: formData.get("locale") || null,
        roles: formData.getAll("roles").map(Number),
        factory_id: formData.get("factory_id") || null,
        department_id: formData.get("department_id") || null,
        production_line_id: formData.get("production_line_id") || null,
      }),
    });

    return { status: "success", userId: result.id, password: result.password };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createUser };
