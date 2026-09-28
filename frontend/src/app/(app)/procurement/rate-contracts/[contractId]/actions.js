"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/rate-contracts", `/procurement/rate-contracts/${id}`];

/**
 * Switching a contract on. Refused when another live agreement already
 * prices one of the same items — two answers is no answer.
 */
async function activateRateContract(id) {
  return postDocumentAction(`/rate-contracts/${id}/activate`, {}, [...paths(id), "/procurement/orders"]);
}

async function cancelRateContract(id, reason) {
  return postDocumentAction(`/rate-contracts/${id}/cancel`, { reason }, paths(id));
}

export { activateRateContract, cancelRateContract };
