import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Sales & Shipment → Shipment → Commercial Document Handover (AnnotechERP
 * 21/05): the scanned papers attached to a handover (`?no=DH-…`). Streamed
 * straight to `ShipmentPaperApiController::handoverAttach()` — a scan runs
 * past a server action's one megabyte; the screen is then refreshed.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const no = new URL(request.url).searchParams.get("no") ?? "";
  if (!/^[A-Z]{2,6}-\d{4}-\d{4,8}$/.test(no)) return Response.json({ message: "Unknown handover." }, { status: 404 });

  const response = await fetch(`${BASE_URL}/sales-shipment/handovers/${encodeURIComponent(no)}/attachment`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/sales-shipment", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
