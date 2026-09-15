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

async function toggleTechnician(technician) {
  try {
    await apiFetch(`/technicians/${technician.id}/active`, {
      method: "PATCH",
      body: JSON.stringify({ active: technician.status !== "ACTIVE" }),
    });
    revalidatePath("/technicians");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteTechnician(technicianId) {
  try {
    await apiFetch(`/technicians/${technicianId}`, { method: "DELETE" });
    revalidatePath("/technicians");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { toggleTechnician, deleteTechnician };
