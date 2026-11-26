import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * The QC tablet's own way to Laravel (Quality Tally → Tally Entry): a tap
 * must land in well under a second, which a server action in `next dev`
 * does not, and the offline queue replays from the browser. So the tally
 * screen calls `/api/quality-tally/tally/...` and this passes it on to
 * `/api/v1/quality-tally/tally/...` with the session's token. Only the tally
 * paths: nothing else of the API is reachable this way.
 */
const ALLOWED = new Set(["tally", "tally/context", "tally/summary", "tally/bulk", "tally/undo", "cut-bundles", "cut-bundles/scan"]);

async function pass(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const { path = [] } = await params;
  const joined = path.join("/");
  if (!ALLOWED.has(joined)) return Response.json({ message: "Not found." }, { status: 404 });

  const url = new URL(request.url);
  const headers = { Authorization: `Bearer ${token}`, Accept: "application/json" };
  const hasBody = !["GET", "HEAD"].includes(request.method);
  if (hasBody) headers["Content-Type"] = "application/json";

  const response = await fetch(`${BASE_URL}/quality-tally/${joined}${url.search}`, {
    method: request.method,
    headers,
    body: hasBody ? await request.text() : undefined,
    cache: "no-store",
  });

  return new Response(response.body, { status: response.status, headers: { "content-type": response.headers.get("content-type") ?? "application/json" } });
}

export const GET = pass;
export const POST = pass;
