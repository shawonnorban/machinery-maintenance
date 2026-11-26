import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Biometric Sync → Import file (AnnotechERP 24/04): a terminal's attendance
 * log off a USB stick, streamed straight to Laravel — a month's attlog.dat
 * runs past a server action's one megabyte.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/hr-payroll/attendance/import`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/hr-payroll", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
