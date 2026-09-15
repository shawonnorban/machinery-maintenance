"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function payload(formData) {
  return {
    part_number: formData.get("part_number"),
    name: formData.get("name"),
    category_id: formData.get("category_id") || null,
    brand: formData.get("brand") || null,
    manufacturer: formData.get("manufacturer") || null,
    unit: formData.get("unit"),
    minimum_stock: formData.get("minimum_stock") || "0",
    reorder_level: formData.get("reorder_level") || "0",
    lead_time_days: formData.get("lead_time_days") || null,
    shelf_life_days: formData.get("shelf_life_days") || null,
    is_critical_spare: formData.get("is_critical_spare") === "1",
    hazardous: formData.get("hazardous") === "1",
    notes: formData.get("notes") || null,
  };
}

/** Mirrors `SparePartController::store` — the catalogue only; quantity never enters here (stock is a ledger receipt with a bin and a cost). */
async function createSparePart(previousState, formData) {
  let part;
  try {
    part = await apiFetch("/spare-parts", { method: "POST", body: JSON.stringify(payload(formData)) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/inventory/parts");
  redirect(`/inventory/parts/${part.id}`);
}

export { createSparePart };
