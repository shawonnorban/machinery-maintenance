import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Development Style wizard → Attachments and Style Image (AnnotechERP 06/07):
 * a view, tech pack, measurement sheet, swatch or artwork for one box. A
 * route handler rather than a server action, because a tech pack runs past
 * the one megabyte server actions stop at; the body is streamed straight
 * through to `DevStyleApiController::attach()`.
 */
export async function POST(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { style } = await params;

  const response = await fetch(`${BASE_URL}/merchandise/styles/${encodeURIComponent(style)}/attachments`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
