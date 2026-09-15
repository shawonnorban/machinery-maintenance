"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

async function activateUser(userId) {
  try {
    await apiFetch(`/users/${userId}/activate`, { method: "POST" });
    revalidatePath(`/settings/users/${userId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deactivateUser(userId) {
  try {
    await apiFetch(`/users/${userId}/deactivate`, { method: "POST" });
    revalidatePath(`/settings/users/${userId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function assignRole(userId, previousState, formData) {
  try {
    await apiFetch(`/users/${userId}/roles`, {
      method: "POST",
      body: JSON.stringify({
        role_id: Number(formData.get("role_id")),
        factory_id: formData.get("factory_id") || null,
      }),
    });
    revalidatePath(`/settings/users/${userId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function removeRoleAssignment(userId, assignmentId) {
  try {
    await apiFetch(`/users/${userId}/roles/${assignmentId}`, { method: "DELETE" });
    revalidatePath(`/settings/users/${userId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `UserController::resetPassword` — the returned password is shown once, the same rule `createUser`'s own follows. */
async function resetPassword(userId) {
  try {
    const result = await apiFetch(`/users/${userId}/reset-password`, { method: "POST" });
    return { status: "success", password: result.password };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `UserController::destroy` — ends this company's membership; the account and everything it ever signed off stay. */
async function removeUser(userId) {
  try {
    await apiFetch(`/users/${userId}`, { method: "DELETE" });
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `UserController::update` — resubmits `roles`/`factory_id` alongside the profile fields in the same call, per `ManageCompanyUser::syncRoles()`'s own shape. */
async function updateUser(userId, previousState, formData) {
  try {
    await apiFetch(`/users/${userId}`, {
      method: "PATCH",
      body: JSON.stringify({
        name: formData.get("name"),
        phone: formData.get("phone") || null,
        locale: formData.get("locale") || null,
        roles: formData.getAll("roles").map(Number),
        factory_id: formData.get("factory_id") || null,
        department_id: formData.get("department_id") || null,
        production_line_id: formData.get("production_line_id") || null,
      }),
    });
  } catch (error) {
    return fail(error);
  }

  revalidatePath(`/settings/users/${userId}`);
  redirect(`/settings/users/${userId}`);
}

export { activateUser, deactivateUser, assignRole, removeRoleAssignment, updateUser, resetPassword, removeUser };
