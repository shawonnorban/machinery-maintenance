import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Print Design Sheet → Style & Order Lines → Add line (AnnotechERP 06/10):
 * the line with its artwork image. A route handler rather than a server
 * action, because an artwork runs past the one megabyte server actions stop
 * at; the body is streamed straight through to `PrintLineApiController::add()`.
 */
export async function POST(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { program } = await params;

  const response = await fetch(`${BASE_URL}/merchandise/print-programs/${encodeURIComponent(program)}/lines`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
