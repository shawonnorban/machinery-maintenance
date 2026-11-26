import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Print Design Sheet → Style & Order Lines: a new artwork image for one line
 * (AnnotechERP 06/10), streamed straight through to
 * `PrintLineApiController::artwork()` — an image can run past the one
 * megabyte server actions stop at.
 */
export async function POST(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { program, line } = await params;

  const response = await fetch(`${BASE_URL}/merchandise/print-programs/${encodeURIComponent(program)}/lines/${encodeURIComponent(line)}/artwork`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
