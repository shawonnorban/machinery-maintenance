import { NextResponse } from "next/server";
import { apiFetch } from "@/lib/api-server";
import { getSessionToken, clearSessionToken } from "@/lib/session";

/**
 * Revokes the token server-side (so it can't be replayed after logout) and
 * always clears the cookie, even if the Laravel call fails — a person
 * clicking "log out" must end up logged out locally regardless.
 */
export async function POST() {
  const token = await getSessionToken();

  if (token) {
    await apiFetch("/auth/logout", { method: "POST" }).catch(() => null);
  }

  await clearSessionToken();

  return NextResponse.json({ success: true });
}
