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

/** Mirrors `AssetLocationController::store` — no redirect: create/edit both happen in a modal over this same list. */
async function createLocation(previousState, formData) {
  try {
    await apiFetch("/locations", {
      method: "POST",
      body: JSON.stringify({
        factory_id: formData.get("factory_id"),
        name: formData.get("name"),
        code: formData.get("code"),
        building_id: formData.get("building_id") || null,
        floor_id: formData.get("floor_id") || null,
        department_id: formData.get("department_id") || null,
        section_id: formData.get("section_id") || null,
        production_line_id: formData.get("production_line_id") || null,
        workstation_id: formData.get("workstation_id") || null,
      }),
    });
    revalidatePath("/settings/locations");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `AssetLocationController::update`. */
async function updateLocation(locationId, previousState, formData) {
  try {
    await apiFetch(`/locations/${locationId}`, {
      method: "PATCH",
      body: JSON.stringify({
        factory_id: formData.get("factory_id"),
        name: formData.get("name"),
        code: formData.get("code"),
        building_id: formData.get("building_id") || null,
        floor_id: formData.get("floor_id") || null,
        department_id: formData.get("department_id") || null,
        section_id: formData.get("section_id") || null,
        production_line_id: formData.get("production_line_id") || null,
        workstation_id: formData.get("workstation_id") || null,
      }),
    });
    revalidatePath("/settings/locations");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** A closed location keeps every machine that ever stood in it readable; it just stops being offered when something is registered or moved. */
async function toggleLocation(location) {
  try {
    await apiFetch(`/locations/${location.id}/active`, {
      method: "PATCH",
      body: JSON.stringify({ active: location.status !== "ACTIVE" }),
    });
    revalidatePath("/settings/locations");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteLocation(locationId) {
  try {
    await apiFetch(`/locations/${locationId}`, { method: "DELETE" });
    revalidatePath("/settings/locations");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * The six master-data lists the create/edit modal's own dropdowns need —
 * fetched only when that modal is actually about to open (`LocationsTable`
 * calls this once, caching the result, on the first "New location"/"Edit"
 * click) rather than by the page on every load. This page's original
 * all-at-once fetch (eight concurrent calls, most of them a 200-row
 * master-data list nobody looks at unless they're actually adding or
 * editing a location) was slow enough on this product's actual shared
 * hosting to time out on a poor connection before the page ever painted —
 * the same problem already found and fixed on several detail pages.
 */
async function getLocationFormLists() {
  const [buildings, floors, departments, sections, productionLines, workstations] = await Promise.all([
    apiFetch("/master-data/buildings?per_page=200"),
    apiFetch("/master-data/floors?per_page=200"),
    apiFetch("/master-data/departments?per_page=200"),
    apiFetch("/master-data/sections?per_page=200"),
    apiFetch("/master-data/production-lines?per_page=200"),
    apiFetch("/master-data/workstations?per_page=200"),
  ]);

  return { buildings, floors, departments, sections, productionLines, workstations };
}

export { createLocation, updateLocation, toggleLocation, deleteLocation, getLocationFormLists };
