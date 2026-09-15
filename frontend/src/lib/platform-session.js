import "server-only";

import { cookies } from "next/headers";

/**
 * A platform admin's own door (`PlatformAuthApiController`) mints a token
 * that carries no `company_id` at all — a different credential from a
 * tenant's, on a different guard (`platform.auth`, not `api.auth`) — so it
 * lives in its own cookie rather than sharing `annotech_session`. Sharing
 * one cookie would mean a platform admin who also happens to be signed into
 * a customer's own app in another tab silently clobbers one session with
 * the other.
 */
const COOKIE_NAME = "annotech_platform_session";

async function getPlatformSessionToken() {
  const store = await cookies();
  return store.get(COOKIE_NAME)?.value ?? null;
}

async function setPlatformSessionToken(token, expiresAt) {
  const store = await cookies();
  const expires = expiresAt ? new Date(expiresAt) : undefined;

  store.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    expires,
  });
}

async function clearPlatformSessionToken() {
  const store = await cookies();
  store.delete(COOKIE_NAME);
}

export { COOKIE_NAME, getPlatformSessionToken, setPlatformSessionToken, clearPlatformSessionToken };
