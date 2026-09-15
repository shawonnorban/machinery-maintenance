import "server-only";

import { cookies } from "next/headers";

/**
 * The Laravel API is bearer-token-only (see
 * docs/12-Stack-Migration-Implementation-Plan.md Phase C §5) — there is no
 * Sanctum stateful session and no CSRF cookie to lean on. The token itself
 * must never reach browser JS (an XSS in any dependency would be able to
 * read `localStorage` and replay it), so it lives only in this httpOnly
 * cookie, and every read of it happens on the server.
 */
const COOKIE_NAME = "annotech_session";

async function getSessionToken() {
  const store = await cookies();
  return store.get(COOKIE_NAME)?.value ?? null;
}

async function setSessionToken(token, expiresAt) {
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

async function clearSessionToken() {
  const store = await cookies();
  store.delete(COOKIE_NAME);
}

export { COOKIE_NAME, getSessionToken, setSessionToken, clearSessionToken };
