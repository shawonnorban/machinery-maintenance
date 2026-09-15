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

async function updateTechnician(technicianId, previousState, formData) {
  try {
    await apiFetch(`/technicians/${technicianId}`, {
      method: "PATCH",
      body: JSON.stringify({
        name: formData.get("name"),
        employee_id: formData.get("employee_id"),
        factory_id: formData.get("factory_id"),
        department_id: formData.get("department_id") || null,
        production_line_id: formData.get("production_line_id") || null,
        user_id: formData.get("user_id") || null,
        phone: formData.get("phone") || null,
        email: formData.get("email") || null,
        specialization: formData.get("specialization") || null,
        joining_date: formData.get("joining_date") || null,
        max_concurrent_work_orders: formData.get("max_concurrent_work_orders") || null,
      }),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  redirect("/technicians");
}

/** Mirrors `TechnicianSkillController::store` — a free-text skill name, no fixed catalog, since every factory has its own words for what its people can do. */
async function addSkill(technicianId, previousState, formData) {
  try {
    await apiFetch(`/technicians/${technicianId}/skills`, {
      method: "POST",
      body: JSON.stringify({
        skill_name: formData.get("skill_name"),
        proficiency: formData.get("proficiency"),
      }),
    });
    revalidatePath(`/technicians/${technicianId}/edit`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function removeSkill(technicianId, skillId) {
  try {
    await apiFetch(`/technicians/${technicianId}/skills/${skillId}`, { method: "DELETE" });
    revalidatePath(`/technicians/${technicianId}/edit`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { updateTechnician, addSkill, removeSkill };
