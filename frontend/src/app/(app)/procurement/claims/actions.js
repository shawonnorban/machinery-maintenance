"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = ["/procurement/claims"];

async function createSupplierClaim(values) {
  return postDocumentAction("/supplier-claims", values, paths);
}

async function acceptSupplierClaim(id, reference) {
  return postDocumentAction(`/supplier-claims/${id}/accept`, { reference: reference || undefined }, paths);
}

/** Instalments are normal: a mill rarely pays a claim in one go. */
async function settleSupplierClaim(id, values) {
  return postDocumentAction(`/supplier-claims/${id}/settle`, values, paths);
}

async function rejectSupplierClaim(id, reason) {
  return postDocumentAction(`/supplier-claims/${id}/reject`, { reason }, paths);
}

async function withdrawSupplierClaim(id) {
  return postDocumentAction(`/supplier-claims/${id}/withdraw`, {}, paths);
}

export {
  createSupplierClaim,
  acceptSupplierClaim,
  settleSupplierClaim,
  rejectSupplierClaim,
  withdrawSupplierClaim,
};
