import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Sales & Shipment → Export Documentation (AnnotechERP 21/06): a file sent
 * with a paper (`?path=/sales-shipment/…`) — an export document's or a
 * certificate's attachment, a handover's scan, or a shipment attachment with
 * its fields. Streamed straight to Laravel, a scan running past a server
 * action's one megabyte; only the module's own upload paths are let through.
 */
const ULID = "[0-9A-HJKMNP-TV-Z]{26}";
const ALLOWED = [
  new RegExp(`^/sales-shipment/attachments-of/(export-docs|certificates)/${ULID}$`, "i"),
  /^\/sales-shipment\/handovers\/[A-Z]{2,6}-\d{4}-\d{4,8}\/attachment$/,
  new RegExp(`^/sales-shipment/shipment-attachments(/${ULID})?$`, "i"),
];

export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const path = new URL(request.url).searchParams.get("path") ?? "";
  if (!ALLOWED.some((re) => re.test(path))) return Response.json({ message: "Not an upload path." }, { status: 404 });

  const response = await fetch(`${BASE_URL}${path}`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/sales-shipment", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
