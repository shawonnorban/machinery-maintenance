import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Bridges the browser's Echo client to Laravel's private/presence channel
 * authorization. Echo runs in the browser (a WebSocket needs a persistent
 * client-side connection) and calls this URL as its `authEndpoint` — but
 * the bearer token behind that authorization lives only in the httpOnly
 * session cookie (docs/12-Stack-Migration-Implementation-Plan.md Phase C
 * §5), unreachable from the browser JS that would otherwise have to send
 * it. So this route reads the cookie server-side and forwards the request
 * to `POST /api/v1/broadcasting/auth` — a route added specifically to sit
 * behind the bearer-token guard rather than Laravel's session-only default
 * (see app/Modules/Api/Routes/api.php) — with the token attached, and
 * relays the signed response straight back.
 *
 * This is *not* JSON in either direction: Echo's pusher-js transport posts
 * `channel_name` + `socket_id` as form data, and Laravel's broadcaster
 * replies with its own `{ auth, channel_data? }` shape, not this app's
 * `{ success, data }` envelope — so this bypasses `apiFetch` and forwards
 * the body verbatim rather than unwrapping it.
 */
export async function POST(request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: "Not authenticated" }, { status: 401 });
  }

  const body = await request.text();

  const response = await fetch(`${BASE_URL}/broadcasting/auth`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": request.headers.get("content-type") ?? "application/x-www-form-urlencoded",
      Accept: "application/json",
    },
    body,
  });

  const text = await response.text();

  return new NextResponse(text, {
    status: response.status,
    headers: { "Content-Type": response.headers.get("content-type") ?? "application/json" },
  });
}
