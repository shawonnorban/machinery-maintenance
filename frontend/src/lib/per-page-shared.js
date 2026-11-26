/**
 * Rows-per-page for a list screen (the pager's "Rows" select). The choice is
 * remembered per list — a cookie named after the list's path, so 50 rows on
 * Machines does not also turn Work Orders to 50 — and the server page reads
 * it back (lib/per-page.js) to ask the API for that many.
 */
const PER_PAGE_OPTIONS = [10, 25, 50, 100];

/** "/maintenance/plans" → "pp_maintenance_plans". */
function perPageCookie(pathname) {
  return `pp${String(pathname).replace(/[^a-zA-Z0-9]+/g, "_")}`.replace(/_+$/, "");
}

export { PER_PAGE_OPTIONS, perPageCookie };
