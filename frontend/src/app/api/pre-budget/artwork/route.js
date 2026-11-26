import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Pre-Budget (AnnotechERP 33/34): a requisition's artwork (CAD) image, streamed straight
 * to `RequisitionApiController::artwork()` (past a server action's one megabyte).
 * The answer is the stored file's id and address; the sheet puts the id in
 * the Artwork cell and saves it with the row.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/pre-budget/requisitions/artwork`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
