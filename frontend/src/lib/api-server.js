import "server-only";

import { getSessionToken } from "@/lib/session";
import { ApiError } from "@/lib/api-error";

const BASE_URL = process.env.LARAVEL_API_URL ?? "http://localhost:8000/api/v1";

// Some hosts' own resolver drops lookups for this app's own API subdomain
// intermittently (confirmed live: `getaddrinfo ENOTFOUND` from Node while
// the same hostname resolved fine moments earlier from a plain shell) —
// evidently a quirk of that resolver, not the DNS record. A public resolver
// as a fallback keeps that from taking the API down for this process: this
// only changes which IP the TCP connection dials, so the request's Host
// header and the TLS handshake's SNI are untouched (both still come from
// the original hostname), which is what keeps this safe against a
// certificate mismatch.
//
// Set up lazily, on the first real request, rather than at module load:
// Next's build evaluates every route module to collect its metadata, and
// on a memory-capped shared host that was enough extra weight, loaded
// across dozens of routes in one build worker, to have the build killed
// outright (confirmed live). Nothing at build time ever calls `apiFetch`,
// so lazy setup keeps this code from running until the app is actually
// serving a request.
let dnsFallbackReady = false;

async function ensureDnsFallback() {
  if (dnsFallbackReady) {
    return;
  }

  dnsFallbackReady = true;

  const dns = await import("node:dns");
  const { Agent, setGlobalDispatcher } = await import("undici");

  const publicDnsFallback = new dns.default.promises.Resolver();
  publicDnsFallback.setServers(["1.1.1.1", "8.8.8.8"]);

  setGlobalDispatcher(
    new Agent({
      connect: {
        lookup(hostname, options, callback) {
          dns.default.lookup(hostname, options, (error, address, family) => {
            if (!error) {
              callback(null, address, family);
              return;
            }

            publicDnsFallback
              .resolve4(hostname)
              .then((addresses) => {
                // Node's own `dns.lookup` reports success in one of two
                // shapes depending on `options.all`: a single `(address,
                // family)` pair, or one array of `{ address, family }`
                // objects. The caller here is undici's connector, which
                // picks the shape it wants via that flag — answering with
                // the wrong one is what produced "Invalid IP address:
                // undefined" the one time this path was actually exercised.
                if (options?.all) {
                  callback(
                    null,
                    addresses.map((address) => ({ address, family: 4 })),
                  );
                } else {
                  callback(null, addresses[0], 4);
                }
              })
              .catch(() => callback(error));
          });
        },
      },
    }),
  );
}

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
  await ensureDnsFallback();

  const { token: explicitToken, headers, includeMeta = false, ...rest } = options;
  const token = explicitToken !== undefined ? explicitToken : await getSessionToken();

  // A FormData body (file uploads — checklist photos, work order
  // attachments) must never get a hardcoded `Content-Type: application/
  // json`: the browser/runtime sets `multipart/form-data` with the
  // correct boundary itself only when the header is left unset entirely.
  const isFormData = typeof FormData !== "undefined" && rest.body instanceof FormData;

  const fetchOptions = {
    ...rest,
    headers: {
      Accept: "application/json",
      ...(isFormData ? {} : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    cache: "no-store",
  };

  // A network-level failure (DNS, connection refused, TLS) makes `fetch`
  // itself throw rather than return a Response. A couple of the sites this
  // has run on resolve BASE_URL's own hostname intermittently — a transient
  // blip on hosts otherwise healthy, not a real outage — so it's worth one
  // short retry before giving up. Left uncaught entirely, the exception
  // crosses a Server Action/Route Handler boundary unhandled, and the
  // client sees a body-less 500 whose `response.json()` fails with
  // "Unexpected end of JSON input" that hides what actually went wrong.
  let response;
  let lastError;

  for (let attempt = 0; attempt < 3; attempt++) {
    if (attempt > 0) {
      await new Promise((resolve) => setTimeout(resolve, 200 * attempt));
    }

    try {
      response = await fetch(`${BASE_URL}${path}`, fetchOptions);
      lastError = undefined;
      break;
    } catch (cause) {
      lastError = cause;
    }
  }

  if (lastError) {
    // Every caller already knows how to handle an ApiError, so route this
    // through the same path instead of leaving it to crash unhandled.
    const detail = lastError.cause?.message ?? lastError.message;
    throw new ApiError(502, { message: `Unable to reach the API: ${detail}`, code: "API_UNREACHABLE" });
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

export { apiFetch, BASE_URL, ensureDnsFallback };
