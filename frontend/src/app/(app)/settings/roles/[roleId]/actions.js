"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `RoleApiController::update` — never reachable for a seeded role, `ManageRole::assertEditable()` refuses it with a 403 the form surfaces as a plain error. */
async function updateRole(roleId, previousState, formData) {
  try {
    await apiFetch(`/roles/${roleId}`, {
      method: "PATCH",
      body: JSON.stringify({
        code: formData.get("code"),
        name: formData.get("name"),
        permissions: formData.getAll("permissions"),
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/settings/roles");
  redirect("/settings/roles");
}

async function deleteRole(roleId) {
  try {
    await apiFetch(`/roles/${roleId}`, { method: "DELETE" });
    revalidatePath("/settings/roles");
    return { status: "success" };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

export { updateRole, deleteRole };
