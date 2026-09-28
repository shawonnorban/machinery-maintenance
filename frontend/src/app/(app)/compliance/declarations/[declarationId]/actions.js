"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => [`/compliance/declarations/${id}`, "/compliance/bond-licences"];

/** Handed to customs — after this the reconciliation has something to measure against. */
async function issueDeclaration(id) {
  return postDocumentAction(`/utilisation-declarations/${id}/issue`, {}, paths(id));
}

async function closeDeclaration(id) {
  return postDocumentAction(`/utilisation-declarations/${id}/close`, {}, paths(id));
}

async function cancelDeclaration(id, reason) {
  return postDocumentAction(`/utilisation-declarations/${id}/cancel`, { reason }, paths(id));
}

export { issueDeclaration, closeDeclaration, cancelDeclaration };
