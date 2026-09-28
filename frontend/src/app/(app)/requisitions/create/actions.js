"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["factory_id", "target_item_department_id", "requester_department_id", "fr_date", "priority", "purpose"];

/** Mirrors `FloorRequisitionApiController::store`. */
async function createFloorRequisition(previousState, formData) {
  let requisition;
  try {
    requisition = await apiFetch("/floor-requisitions", { method: "POST", body: JSON.stringify(documentPayload(formData, HEADER)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/requisitions");
  redirect(`/requisitions/${requisition.id}`);
}

export { createFloorRequisition };
