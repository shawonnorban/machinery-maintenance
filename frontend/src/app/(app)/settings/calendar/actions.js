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

/**
 * Mirrors `CalendarController::storeCalendar` — a new effective-dated
 * version, never an edit in place. Last quarter's availability was computed
 * against last quarter's week; rewriting it would restate a number somebody
 * already reported.
 */
async function setCalendar(factoryId, previousState, formData) {
  try {
    await apiFetch(`/factories/${factoryId}/calendar`, {
      method: "PUT",
      body: JSON.stringify({
        operating_mode: formData.get("operating_mode"),
        weekly_off_days: formData.getAll("weekly_off_days").map(Number),
        effective_from: formData.get("effective_from"),
      }),
    });

    revalidatePath("/settings/calendar");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function createShift(factoryId, previousState, formData) {
  try {
    await apiFetch(`/factories/${factoryId}/shifts`, {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        code: formData.get("code"),
        start_time: formData.get("start_time"),
        end_time: formData.get("end_time"),
        days_of_week: formData.getAll("days_of_week").map(Number),
        is_overtime: formData.get("is_overtime") === "1",
        effective_from: formData.get("effective_from"),
      }),
    });

    revalidatePath("/settings/calendar");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Ends the shift from today rather than deleting it — past availability keeps reading against the hours it actually had. */
async function endShift(shiftId) {
  try {
    await apiFetch(`/shifts/${shiftId}`, { method: "DELETE" });
    revalidatePath("/settings/calendar");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function createHoliday(factoryId, previousState, formData) {
  try {
    await apiFetch(`/factories/${factoryId}/holidays`, {
      method: "POST",
      body: JSON.stringify({
        date: formData.get("date"),
        name: formData.get("name"),
        is_working_day: formData.get("is_working_day") === "1",
      }),
    });

    revalidatePath("/settings/calendar");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteHoliday(holidayId) {
  try {
    await apiFetch(`/holidays/${holidayId}`, { method: "DELETE" });
    revalidatePath("/settings/calendar");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { setCalendar, createShift, endShift, createHoliday, deleteHoliday };
