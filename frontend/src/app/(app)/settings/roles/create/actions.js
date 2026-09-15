"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `RoleApiController::store` — built from scratch, or cloned from a seeded role's own permission set. */
async function createRole(previousState, formData) {
  const permissions = formData.getAll("permissions");
  const cloneFrom = formData.get("clone_from");

  let role;
  try {
    role = await apiFetch("/roles", {
      method: "POST",
      body: JSON.stringify({
        code: formData.get("code"),
        name: formData.get("name"),
        scope: formData.get("scope"),
        clone_from: cloneFrom ? Number(cloneFrom) : null,
        // Omitted entirely when cloning with nothing checked yet — the API
        // copies the source's own permissions only when the key is absent.
        ...(permissions.length > 0 || !cloneFrom ? { permissions } : {}),
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/settings/roles");
  redirect(`/settings/roles/${role.id}/edit`);
}

export { createRole };
