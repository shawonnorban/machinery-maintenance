import { NextResponse } from "next/server";
import { BASE_URL } from "@/lib/api-server";
import { getSessionToken } from "@/lib/session";

/**
 * The one door the offline queue (src/lib/offline/queue.js) is allowed to
 * knock on. Every endpoint a draft can target must be listed here
 * explicitly — an open relay to *any* Laravel path would let injected
 * page script replay the visitor's session against endpoints that have
 * nothing to do with offline drafts. Add to this list only alongside the
 * screen that starts queuing drafts for that endpoint.
 */
const ALLOWED_ENDPOINTS = [
  // Breakdown reporting (frontend/src/components/breakdowns/report-breakdown-form.jsx)
  // — the one write in the product where losing what was typed means a
  // line stays down while it's typed again.
  /^\/breakdowns$/,
];

function isAllowed(endpoint) {
  return ALLOWED_ENDPOINTS.some((pattern) => pattern.test(endpoint));
}

/**
 * Reads the httpOnly session cookie server-side and forwards the draft
 * with `Idempotency-Key` attached — the queue itself never holds the
 * bearer token (docs/12-Stack-Migration-Implementation-Plan.md Phase C
 * §5). Relays the API's status code, body, and `idempotent-replay` header
 * back verbatim, since the queue's own 401/409 handling depends on all
 * three exactly as Laravel sent them.
 */
export async function POST(request) {
  const { endpoint, payload, idempotencyKey } = await request.json();

  if (typeof endpoint !== "string" || !isAllowed(endpoint)) {
    return NextResponse.json({ message: "Endpoint not allowed", code: "ENDPOINT_NOT_ALLOWED" }, { status: 400 });
  }

  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: "Not authenticated" }, { status: 401 });
  }

  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
      "Idempotency-Key": idempotencyKey,
    },
    body: JSON.stringify(payload),
  });

  const text = await response.text();
  const replay = response.headers.get("idempotent-replay");

  return new NextResponse(text, {
    status: response.status,
    headers: {
      "Content-Type": response.headers.get("content-type") ?? "application/json",
      ...(replay ? { "idempotent-replay": replay } : {}),
    },
  });
}
