"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/item-master/inspections", `/item-master/inspections/${id}`];

/**
 * Tallying one roll onto the sheet. The whole sheet comes back, because
 * one roll's points move the lot's pooled figure.
 */
async function recordInspectionRoll(id, payload) {
  return postDocumentAction(`/fabric-inspections/${id}/rolls`, payload, paths(id));
}

async function submitInspection(id) {
  return postDocumentAction(`/fabric-inspections/${id}/submit`, {}, paths(id));
}

/** Accepting the lot is what puts its rolls on the shelf. */
async function approveInspection(id) {
  return postDocumentAction(`/fabric-inspections/${id}/approve`, {}, [...paths(id), "/item-master"]);
}

async function rejectInspection(id, reason) {
  return postDocumentAction(`/fabric-inspections/${id}/reject`, { reason }, paths(id));
}

async function escalateInspection(id) {
  return postDocumentAction(`/fabric-inspections/${id}/escalate`, {}, paths(id));
}

async function cancelInspection(id, reason) {
  return postDocumentAction(`/fabric-inspections/${id}/cancel`, { reason }, paths(id));
}

export {
  recordInspectionRoll,
  submitInspection,
  approveInspection,
  rejectInspection,
  escalateInspection,
  cancelInspection,
};
