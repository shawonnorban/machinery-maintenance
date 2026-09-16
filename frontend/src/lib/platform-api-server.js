import "server-only";

import { getPlatformSessionToken } from "@/lib/platform-session";
import { ApiError } from "@/lib/api-error";
import { BASE_URL } from "@/lib/api-server";

/**
 * The platform console's own `apiFetch` (see `lib/api-server.js`'s docblock
 * for the reasoning this mirrors) — every call goes to `/platform/*` under
 * the same Laravel API, authenticated with the platform admin's own bearer
 * token rather than a tenant's.
 *
 * @param {string} path e.g. "/tenants" — joined onto `${BASE_URL}/platform`.
 */
async function platformApiFetch(path, options = {}) {
  const { token: explicitToken, headers, includeMeta = false, ...rest } = options;
  const token = explicitToken !== undefined ? explicitToken : await getPlatformSessionToken();

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

  // See `apiFetch` in `lib/api-server.js` for why this retries on a
  // network-level failure rather than letting it crash unhandled.
  let response;
  let lastError;

  for (let attempt = 0; attempt < 3; attempt++) {
    if (attempt > 0) {
      await new Promise((resolve) => setTimeout(resolve, 200 * attempt));
    }

    try {
      response = await fetch(`${BASE_URL}/platform${path}`, fetchOptions);
      lastError = undefined;
      break;
    } catch (cause) {
      lastError = cause;
    }
  }

  if (lastError) {
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

export { platformApiFetch };
