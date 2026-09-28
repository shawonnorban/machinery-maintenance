"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (orderId) => [
  `/procurement/orders/${orderId}`,
  `/procurement/orders/${orderId}/import-documents`,
  "/procurement/shipments",
];

/** PATCH, with the error shape the panel already knows how to toast. */
async function patchDocument(path, body, revalidate = []) {
  try {
    const data = await apiFetch(path, { method: "PATCH", body: JSON.stringify(body ?? {}) });
    for (const target of revalidate) revalidatePath(target);
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function createProformaInvoice(orderId, values) {
  return postDocumentAction(`/purchase-orders/${orderId}/proforma-invoices`, values, paths(orderId));
}

async function confirmProformaInvoice(orderId, invoiceId) {
  return postDocumentAction(`/proforma-invoices/${invoiceId}/confirm`, {}, paths(orderId));
}

/** A revision is a new version; the bank opened against the old one. */
async function reviseProformaInvoice(orderId, invoiceId, values) {
  return postDocumentAction(`/proforma-invoices/${invoiceId}/revise`, values, paths(orderId));
}

async function createLetterOfCredit(orderId, values) {
  return postDocumentAction(`/purchase-orders/${orderId}/letters-of-credit`, values, paths(orderId));
}

async function openLetterOfCredit(orderId, letterId) {
  return postDocumentAction(`/letters-of-credit/${letterId}/open`, {}, paths(orderId));
}

async function amendLetterOfCredit(orderId, letterId, values) {
  return postDocumentAction(`/letters-of-credit/${letterId}/amend`, values, paths(orderId));
}

async function closeLetterOfCredit(orderId, letterId) {
  return postDocumentAction(`/letters-of-credit/${letterId}/close`, {}, paths(orderId));
}

async function createShipment(orderId, values) {
  return postDocumentAction(`/purchase-orders/${orderId}/shipments`, values, paths(orderId));
}

async function updateShipment(orderId, shipmentId, values) {
  return patchDocument(`/shipments/${shipmentId}`, values, paths(orderId));
}

async function cancelShipment(orderId, shipmentId, reason) {
  return postDocumentAction(`/shipments/${shipmentId}/cancel`, { reason }, paths(orderId));
}

export {
  createProformaInvoice,
  confirmProformaInvoice,
  reviseProformaInvoice,
  createLetterOfCredit,
  openLetterOfCredit,
  amendLetterOfCredit,
  closeLetterOfCredit,
  createShipment,
  updateShipment,
  cancelShipment,
};
