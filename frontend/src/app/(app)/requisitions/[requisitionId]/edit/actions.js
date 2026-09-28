"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["target_item_department_id", "requester_department_id", "fr_date", "priority", "purpose"];

/** Mirrors `FloorRequisitionApiController::update` (draft only). */
async function updateFloorRequisition(requisitionId, previousState, formData) {
  try {
    await apiFetch(`/floor-requisitions/${requisitionId}`, { method: "PATCH", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/requisitions");
  revalidatePath(`/requisitions/${requisitionId}`);
  redirect(`/requisitions/${requisitionId}`);
}

export { updateFloorRequisition };
