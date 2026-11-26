import "server-only";

import { cookies } from "next/headers";
import { PER_PAGE_OPTIONS, perPageCookie } from "@/lib/per-page-shared";

/** The rows-per-page this viewer chose for the list at `pathname`, or null for the API's default. */
async function perPageFor(pathname) {
  const value = Number((await cookies()).get(perPageCookie(pathname))?.value);
  return PER_PAGE_OPTIONS.includes(value) ? value : null;
}

/** Sets `per_page` on a list query when this viewer chose one for `pathname`. */
async function applyPerPage(params, pathname) {
  const perPage = await perPageFor(pathname);
  if (perPage) params.set("per_page", String(perPage));
  return params;
}

/** The same as a query-string tail: "&per_page=50", or "". */
async function perPageQuery(pathname) {
  const perPage = await perPageFor(pathname);
  return perPage ? `&per_page=${perPage}` : "";
}

export { applyPerPage, perPageFor, perPageQuery };
