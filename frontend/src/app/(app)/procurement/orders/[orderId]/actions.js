"use server";

import { postDocumentAction, decideApproval } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/orders", `/procurement/orders/${id}`, "/procurement/requisitions", "/approvals"];

async function submitOrder(id) {
  return postDocumentAction(`/purchase-orders/${id}/submit`, {}, paths(id));
}

async function cancelOrder(id, reason) {
  return postDocumentAction(`/purchase-orders/${id}/cancel`, { reason }, paths(id));
}

async function closeOrder(id) {
  return postDocumentAction(`/purchase-orders/${id}/close`, {}, paths(id));
}

async function approveOrder(id, requestId, comment) {
  return decideApproval(requestId, "approve", comment, paths(id));
}

async function rejectOrder(id, requestId, comment) {
  return decideApproval(requestId, "reject", comment, paths(id));
}

export { submitOrder, cancelOrder, closeOrder, approveOrder, rejectOrder };
