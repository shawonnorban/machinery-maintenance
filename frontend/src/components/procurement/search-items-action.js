"use server";

import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * The item picker's lookup: active items in one item department, matching a
 * typed code/name. A Server Action rather than a client fetch because the
 * bearer token never leaves the server (`api-server.js`). Capped at 30 rows
 * — a general store has thousands, and the picker is for narrowing, not
 * browsing.
 */
async function searchItems({ departmentId, search }) {
  const query = new URLSearchParams({ per_page: "30", active: "1", sort: "name", direction: "asc" });
  if (departmentId) query.set("item_department_id", departmentId);
  if (search) query.set("search", search);

  try {
    const { data } = await apiFetch(`/items?${query.toString()}`, { includeMeta: true });
    return {
      status: "success",
      items: data.map((item) => ({
        id: item.id,
        item_code: item.item_code,
        name: item.name,
        tracking_mode: item.tracking_mode,
        uom: item.uom,
        item_department: item.item_department,
        on_hand: item.on_hand,
      })),
    };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, items: [] };
    }
    throw error;
  }
}

export { searchItems };
