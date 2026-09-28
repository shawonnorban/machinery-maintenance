"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/receipts", `/procurement/receipts/${id}`, "/procurement/orders", "/item-master"];

/**
 * Mirrors `GoodsReceiptApiController::complete` — bins per QUANTITY/LOT
 * line drafted without one, roll entries per ROLL-tracked line (Inventory
 * spec §3.1). Also revalidates the inspection queue, since a fabric line
 * lands new rolls there.
 */
async function completeReceipt(id, bins, rolls) {
  return postDocumentAction(`/goods-receipts/${id}/complete`, { bins, rolls }, [...paths(id), "/item-master/inspection"]);
}

async function cancelReceipt(id, reason) {
  return postDocumentAction(`/goods-receipts/${id}/cancel`, { reason }, paths(id));
}

export { completeReceipt, cancelReceipt };
