"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/procurement/fabric-bookings", `/procurement/fabric-bookings/${id}`];

/** Confirming stakes the claim on the incoming stock (Inventory spec §7.1). */
async function confirmFabricBooking(id) {
  return postDocumentAction(`/fabric-bookings/${id}/confirm`, {}, paths(id));
}

async function cancelFabricBooking(id, reason) {
  return postDocumentAction(`/fabric-bookings/${id}/cancel`, { reason }, paths(id));
}

async function convertFabricBooking(id) {
  return postDocumentAction(`/fabric-bookings/${id}/convert`, {}, [...paths(id), "/procurement/requisitions"]);
}

export { confirmFabricBooking, cancelFabricBooking, convertFabricBooking };
