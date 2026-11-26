import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Sample 360° View → Sample Documents (AnnotechERP 07/02): a new document —
 * the next version of its type on the sample — with its path or link, or the
 * file attached. Streamed straight to `SampleDocumentApiController::store()`
 * (a tech pack runs past a server action's one megabyte); the desk's pages
 * are then refreshed.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/prototype/documents`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/prototype-desk", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
