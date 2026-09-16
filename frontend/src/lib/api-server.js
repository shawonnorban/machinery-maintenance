import "server-only";

import { getSessionToken } from "@/lib/session";
import { ApiError } from "@/lib/api-error";

const BASE_URL = process.env.LARAVEL_API_URL ?? "http://localhost:8000/api/v1";

/**
 * The only way the frontend talks to the Laravel API (see
 * docs/12-Stack-Migration-Implementation-Plan.md Phase C §5): always
 * server-side, so the bearer token — read here from the httpOnly session
 * cookie — never has to exist in a place browser JS can reach. Call this
 * from Server Components, Route Handlers, and Server Actions only; a
 * Client Component that needs data calls a Server Action or Route Handler
 * that in turn calls this, never the Laravel API directly.
 *
 * @param {string} path e.g. "/assets" — joined onto LARAVEL_API_URL.
 * @param {RequestInit & { token?: string | null, includeMeta?: boolean }} [options]
 *   `includeMeta` returns `{ data, meta }` instead of just `data` — needed
 *   for `ApiResponse::paginated()`/`::cursor()` responses, where `meta`
 *   carries `current_page`/`last_page`/`total` (offset) or `next_cursor`/
 *   `has_more` (cursor) alongside the page of rows.
 */
async function apiFetch(path, options = {}) {
  const { token: explicitToken, headers, includeMeta = false, ...rest } = options;
  const token = explicitToken !== undefined ? explicitToken : await getSessionToken();

  // A FormData body (file uploads — checklist photos, work order
  // attachments) must never get a hardcoded `Content-Type: application/
  // json`: the browser/runtime sets `multipart/form-data` with the
  // correct boundary itself only when the header is left unset entirely.
  const isFormData = typeof FormData !== "undefined" && rest.body instanceof FormData;

  let response;

  try {
    response = await fetch(`${BASE_URL}${path}`, {
      ...rest,
      headers: {
        Accept: "application/json",
        ...(isFormData ? {} : { "Content-Type": "application/json" }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...headers,
      },
      cache: "no-store",
    });
  } catch (cause) {
    // `fetch` itself throws on a network-level failure (DNS, connection
    // refused, TLS) rather than returning a Response — left uncaught, that
    // exception crosses a Server Action/Route Handler boundary as an
    // unhandled error, and the client sees a body-less 500 whose
    // `response.json()` fails with a "Unexpected end of JSON input" that
    // hides what actually went wrong. Every caller already knows how to
    // handle an ApiError, so route this through the same path.
    throw new ApiError(502, { message: `Unable to reach the API: ${cause.message}`, code: "API_UNREACHABLE" });
  }

  if (response.status === 204) {
    return null;
  }

  const body = await response.json().catch(() => null);

  if (!response.ok || body?.success === false) {
    throw new ApiError(response.status, body);
  }

  return includeMeta ? { data: body.data, meta: body.meta } : body.data;
}

export { apiFetch, BASE_URL };
