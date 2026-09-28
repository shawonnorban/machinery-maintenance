"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = ["/procurement/advances"];

async function createSupplierAdvance(values) {
  return postDocumentAction("/supplier-advances", values, paths);
}

/** Setting part of an advance against a bill that has arrived (spec §13). */
async function adjustSupplierAdvance(id, values) {
  return postDocumentAction(`/supplier-advances/${id}/adjust`, values, paths);
}

/** Money handed back rather than worked off — an order that never came. */
async function refundSupplierAdvance(id, values) {
  return postDocumentAction(`/supplier-advances/${id}/refund`, values, paths);
}

async function cancelSupplierAdvance(id) {
  return postDocumentAction(`/supplier-advances/${id}/cancel`, {}, paths);
}

export { createSupplierAdvance, adjustSupplierAdvance, refundSupplierAdvance, cancelSupplierAdvance };
