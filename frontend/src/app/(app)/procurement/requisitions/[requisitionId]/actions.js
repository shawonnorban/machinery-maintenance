"use server";

import { postDocumentAction, decideApproval } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/requisitions", `/procurement/requisitions/${id}`, "/approvals"];

async function submitRequisition(id) {
  return postDocumentAction(`/purchase-requisitions/${id}/submit`, {}, paths(id));
}

async function cancelRequisition(id, reason) {
  return postDocumentAction(`/purchase-requisitions/${id}/cancel`, { reason }, paths(id));
}

async function approveRequisition(id, requestId, comment) {
  return decideApproval(requestId, "approve", comment, paths(id));
}

async function rejectRequisition(id, requestId, comment) {
  return decideApproval(requestId, "reject", comment, paths(id));
}

export { submitRequisition, cancelRequisition, approveRequisition, rejectRequisition };
