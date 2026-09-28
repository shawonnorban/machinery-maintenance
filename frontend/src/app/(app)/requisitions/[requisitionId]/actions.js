"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/requisitions", `/requisitions/${id}`];

async function submitFloorRequisition(id) {
  return postDocumentAction(`/floor-requisitions/${id}/submit`, {}, paths(id));
}

async function cancelFloorRequisition(id, reason) {
  return postDocumentAction(`/floor-requisitions/${id}/cancel`, { reason }, paths(id));
}

async function reviewFloorRequisition(id, lines) {
  return postDocumentAction(`/floor-requisitions/${id}/review`, { lines: lines ?? [] }, paths(id));
}

async function issueFloorRequisition(id, lines) {
  return postDocumentAction(`/floor-requisitions/${id}/issue`, { lines }, paths(id));
}

async function rejectFloorRequisition(id, reason) {
  return postDocumentAction(`/floor-requisitions/${id}/reject`, { reason }, paths(id));
}

async function closeFloorRequisition(id) {
  return postDocumentAction(`/floor-requisitions/${id}/close`, {}, paths(id));
}

/** A borrowed item coming back over the counter (Floor/Store Requisition spec §7). */
async function returnFloorRequisitionStock(id, issueId, quantity) {
  return postDocumentAction(`/floor-requisitions/${id}/returns`, { issue_id: issueId, quantity }, paths(id));
}

export {
  submitFloorRequisition,
  cancelFloorRequisition,
  reviewFloorRequisition,
  issueFloorRequisition,
  rejectFloorRequisition,
  closeFloorRequisition,
  returnFloorRequisitionStock,
};
