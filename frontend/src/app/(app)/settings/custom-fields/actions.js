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

function payload(formData) {
  const data = Object.fromEntries(formData.entries());
  if (data.field_type === "SELECT") {
    data.options = formData.getAll("options").filter((option) => option.trim() !== "");
  } else {
    delete data.options;
  }
  data.required = formData.get("required") === "1";
  if (data.sort_order !== undefined) {
    data.sort_order = Number(data.sort_order) || 0;
  }
  return data;
}

/** Mirrors `CustomFieldDefinitionApiController::store`. */
async function createDefinition(fieldableType, previousState, formData) {
  try {
    await apiFetch("/custom-fields/definitions", {
      method: "POST",
      body: JSON.stringify({ fieldable_type: fieldableType, ...payload(formData) }),
    });
    revalidatePath("/settings/custom-fields");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `CustomFieldDefinitionApiController::update` — `code` is never sent, it's immutable server-side. */
async function updateDefinition(definitionId, previousState, formData) {
  try {
    const data = payload(formData);
    delete data.code;
    await apiFetch(`/custom-fields/definitions/${definitionId}`, {
      method: "PATCH",
      body: JSON.stringify(data),
    });
    revalidatePath("/settings/custom-fields");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function setActive(definitionId, active) {
  try {
    await apiFetch(`/custom-fields/definitions/${definitionId}/active`, {
      method: "PATCH",
      body: JSON.stringify({ active }),
    });
    revalidatePath("/settings/custom-fields");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteDefinition(definitionId) {
  try {
    await apiFetch(`/custom-fields/definitions/${definitionId}`, { method: "DELETE" });
    revalidatePath("/settings/custom-fields");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { createDefinition, updateDefinition, setActive, deleteDefinition };
