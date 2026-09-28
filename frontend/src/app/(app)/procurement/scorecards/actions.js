"use server";

import { postDocumentAction } from "@/components/procurement/document-action-helpers";

/**
 * Re-scores every supplier over the default three-month window. A POST,
 * not a side effect of opening the page: it writes stored snapshots and
 * moves each supplier's rating.
 */
async function calculateScorecards() {
  return postDocumentAction("/supplier-scorecards/calculate", {}, ["/procurement/scorecards", "/vendors"]);
}

export { calculateScorecards };
