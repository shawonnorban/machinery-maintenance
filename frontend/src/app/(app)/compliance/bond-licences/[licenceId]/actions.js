"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/compliance/bond-licences", `/compliance/bond-licences/${id}`];

/**
 * Suspending, restoring or cancelling a licence. All three go through the
 * same endpoint because the consequence is the same one: a licence that
 * is not in force cannot carry a new declaration.
 */
async function setBondLicenceStatus(id, status, reason) {
  return postDocumentAction(`/bond-licences/${id}/status`, { status, reason }, paths(id));
}

export { setBondLicenceStatus };
