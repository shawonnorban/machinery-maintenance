import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Purchased Fabric Opening Balance → Attach (AnnotechERP 06/06): the count
 * sheet or legacy stock report. A route handler rather than a server action,
 * because a scanned sheet runs past the one megabyte server actions stop at;
 * the body is streamed straight through to `FabricOpeningBalanceApiController::attach()`.
 */
export async function POST(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { balance } = await params;

  const response = await fetch(`${BASE_URL}/merchandise/fabric-obs/${encodeURIComponent(balance)}/attachment`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
