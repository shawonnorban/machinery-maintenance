import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Style & Order Upload → Verify File Data (AnnotechERP 06/01): the buyer's
 * spreadsheet checked against the register, nothing kept. A route handler
 * rather than a server action, because a season's spreadsheet runs past the
 * one megabyte server actions stop at; the body is streamed straight to
 * `OrderUploadApiController::verify()`.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/merchandise/order-uploads/verify`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
