"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/item-master/stock-counts", `/item-master/stock-counts/${id}`];

/** What the counters found, a batch of shelves at a time. */
async function recordCounts(id, lines) {
  return postDocumentAction(`/stock-counts/${id}/lines`, { lines }, paths(id));
}

/** Stock on a shelf the system had no row for at all. */
async function recordFound(id, values) {
  return postDocumentAction(`/stock-counts/${id}/found`, values, paths(id));
}

async function flagForRecount(id, lineId) {
  return postDocumentAction(`/stock-count-lines/${lineId}/recount`, {}, paths(id));
}

async function submitStockCount(id) {
  return postDocumentAction(`/stock-counts/${id}/submit`, {}, paths(id));
}

/** Writes one adjustment per variance line; it cannot be undone. */
async function postStockCount(id) {
  return postDocumentAction(`/stock-counts/${id}/post`, {}, [...paths(id), "/item-master"]);
}

async function cancelStockCount(id, reason) {
  return postDocumentAction(`/stock-counts/${id}/cancel`, { reason }, paths(id));
}

export { recordCounts, recordFound, flagForRecount, submitStockCount, postStockCount, cancelStockCount };
