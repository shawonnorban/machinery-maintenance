import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Export Statement ← Excel (View All's Import Excel): the book streamed
 * straight to `StatementApiController::import()` (a book can run past a
 * server action's one megabyte); the answer counts what was added, updated,
 * sent for approval, left alone and skipped.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/export-statement/statements/import`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
