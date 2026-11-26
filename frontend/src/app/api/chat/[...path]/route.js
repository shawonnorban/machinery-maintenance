import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Chat's own way to Laravel: the chat screen runs in the browser (live, no
 * page loads), and its token lives only in the session cookie — so every
 * chat call (`/api/chat/<path>`) is passed on here to `/api/v1/chat/<path>`
 * with the token attached: JSON, uploads (streamed, a photo runs past a
 * server action's one megabyte) and files / photos back (streamed too).
 * Only the chat's own paths: nothing else of the API is reachable this way.
 */
async function pass(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const { path = [] } = await params;
  if (!path.length || path.some((p) => !/^[A-Za-z0-9_-]{1,80}$/.test(p))) {
    return Response.json({ message: "Not found." }, { status: 404 });
  }
  const url = new URL(request.url);
  const target = `${BASE_URL}/chat/${path.join("/")}${url.search}`;
  const headers = { Authorization: `Bearer ${token}`, Accept: request.headers.get("accept") || "application/json" };
  const type = request.headers.get("content-type");
  if (type) headers["Content-Type"] = type;
  const agent = request.headers.get("user-agent");
  if (agent) headers["User-Agent"] = agent; // which browser took the push subscription
  const hasBody = !["GET", "HEAD"].includes(request.method);

  const response = await fetch(target, {
    method: request.method,
    headers,
    body: hasBody ? request.body : undefined,
    duplex: hasBody ? "half" : undefined,
    cache: "no-store",
  });

  const out = new Headers();
  for (const h of ["content-type", "content-disposition", "cache-control", "content-length", "x-content-type-options"]) {
    const v = response.headers.get(h);
    if (v) out.set(h, v);
  }

  return new Response(response.body, { status: response.status, headers: out });
}

export const GET = pass;
export const POST = pass;
export const PATCH = pass;
export const DELETE = pass;
