"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Mirrors `FabricRollApiController::inspect` — one call for accept or
 * reject (Inventory spec §4.2–§4.3); which it is comes from `grade`.
 * Revalidates the item's own page too, since its `rolls` summary changes.
 */
async function inspectRoll(rollId, data) {
  let roll;

  try {
    roll = await apiFetch(`/fabric-rolls/${rollId}/inspect`, { method: "POST", body: JSON.stringify(data) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath("/item-master/inspection");
  revalidatePath(`/item-master/inspection/${rollId}`);
  if (roll?.item?.id) revalidatePath(`/item-master/${roll.item.id}`);
  if (roll?.goods_receipt?.id) revalidatePath(`/procurement/receipts/${roll.goods_receipt.id}`);

  return { status: "success", data: roll };
}

/**
 * A photo filed against the roll itself — usable at any point once it
 * exists from receiving, not only while it waits on a grade. Mirrors
 * `WorkOrderDetail`'s own `uploadAttachment` action.
 */
async function uploadRollPhoto(rollId, formData) {
  try {
    const file = formData.get("file");

    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }

    const body = new FormData();
    body.set("file", file);

    await apiFetch(`/fabric-rolls/${rollId}/attachments`, { method: "POST", body });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath(`/item-master/inspection/${rollId}`);

  return { status: "success" };
}

export { inspectRoll, uploadRollPhoto };
