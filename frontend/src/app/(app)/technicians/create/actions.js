"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function createTechnician(previousState, formData) {
  try {
    await apiFetch("/technicians", {
      method: "POST",
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

export { createTechnician };
