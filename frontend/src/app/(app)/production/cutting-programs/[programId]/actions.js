"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

const paths = (id) => ["/production/cutting-programs", `/production/cutting-programs/${id}`];

async function call(path, body, revalidate = []) {
  try {
    const data = await apiFetch(path, { method: "POST", body: JSON.stringify(body ?? {}) });
    for (const target of revalidate) revalidatePath(target);
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

/** Mirrors `MaterialIssueApiController::store` (Inventory spec §9). */
async function issueMaterial(programId, payload) {
  return call(`/cutting-programs/${programId}/material-issues`, payload, paths(programId));
}

/** Mirrors `MaterialRequirementApiController::raiseRequisition` (Procurement spec §5.3). */
async function raiseRequirementRequisition(programId) {
  return call(`/cutting-programs/${programId}/material-requirement/requisition`, {}, [
    ...paths(programId),
    "/procurement/requisitions",
  ]);
}

export { issueMaterial, raiseRequirementRequisition };
