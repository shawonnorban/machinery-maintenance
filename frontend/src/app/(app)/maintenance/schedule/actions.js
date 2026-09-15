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

async function completeSchedule(scheduleId) {
  try {
    await apiFetch(`/maintenance-schedules/${scheduleId}/complete`, { method: "POST" });
    revalidatePath("/maintenance/schedule");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function skipSchedule(scheduleId, previousState, formData) {
  try {
    await apiFetch(`/maintenance-schedules/${scheduleId}/skip`, {
      method: "POST",
      body: JSON.stringify({ skipped_reason: formData.get("skipped_reason") }),
    });
    revalidatePath("/maintenance/schedule");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function rescheduleSchedule(scheduleId, previousState, formData) {
  try {
    await apiFetch(`/maintenance-schedules/${scheduleId}/reschedule`, {
      method: "POST",
      body: JSON.stringify({
        due_at: formData.get("due_at"),
        rescheduled_reason: formData.get("rescheduled_reason"),
      }),
    });
    revalidatePath("/maintenance/schedule");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { completeSchedule, skipSchedule, rescheduleSchedule };
