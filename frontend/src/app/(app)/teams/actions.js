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

/** Mirrors `TeamController::store`. */
async function createTeam(previousState, formData) {
  try {
    await apiFetch("/teams", {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        code: formData.get("code"),
        factory_id: formData.get("factory_id"),
        specialization: formData.get("specialization") || null,
      }),
    });
    revalidatePath("/teams");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `TeamController::update`. */
async function updateTeam(teamId, previousState, formData) {
  try {
    await apiFetch(`/teams/${teamId}`, {
      method: "PATCH",
      body: JSON.stringify({
        name: formData.get("name"),
        code: formData.get("code"),
        factory_id: formData.get("factory_id"),
        specialization: formData.get("specialization") || null,
      }),
    });
    revalidatePath("/teams");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `TeamController::toggle` — `TeamApiController::update` folds status into the same PATCH rather than a separate route. */
async function toggleTeam(team) {
  try {
    await apiFetch(`/teams/${team.id}`, {
      method: "PATCH",
      body: JSON.stringify({
        name: team.name,
        code: team.code,
        factory_id: team.factory.id,
        specialization: team.specialization,
        status: team.status === "ACTIVE" ? "INACTIVE" : "ACTIVE",
      }),
    });
    revalidatePath("/teams");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteTeam(teamId) {
  try {
    await apiFetch(`/teams/${teamId}`, { method: "DELETE" });
    revalidatePath("/teams");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createTeam, updateTeam, toggleTeam, deleteTeam };
