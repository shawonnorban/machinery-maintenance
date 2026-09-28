"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/returns", `/procurement/returns/${id}`];

async function submitPurchaseReturn(id) {
  return postDocumentAction(`/purchase-returns/${id}/submit`, {}, paths(id));
}

/** Gate pass, stock movement and debit note, together (spec §12). */
async function dispatchPurchaseReturn(id, gatePassNo) {
  return postDocumentAction(
    `/purchase-returns/${id}/dispatch`,
    { gate_pass_no: gatePassNo || undefined },
    [...paths(id), "/item-master"],
  );
}

async function cancelPurchaseReturn(id, reason) {
  return postDocumentAction(`/purchase-returns/${id}/cancel`, { reason }, paths(id));
}

export { submitPurchaseReturn, dispatchPurchaseReturn, cancelPurchaseReturn };
