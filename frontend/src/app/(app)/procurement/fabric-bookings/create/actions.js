"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { documentPayload } from "@/components/procurement/document-payload";

const HEADER = ["factory_id", "vendor_id", "currency", "style_id", "booking_date", "required_date", "notes"];

/** Mirrors `FabricBookingApiController::store` (Procurement spec §3). */
async function createFabricBooking(previousState, formData) {
  let booking;
  try {
    booking = await apiFetch("/fabric-bookings", {
      method: "POST",
      body: JSON.stringify(documentPayload(formData, HEADER)),
    });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/procurement/fabric-bookings");
  redirect(`/procurement/fabric-bookings/${booking.id}`);
}

export { createFabricBooking };
