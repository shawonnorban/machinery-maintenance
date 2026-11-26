import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Employee Profile (AnnotechERP 24/03): the photograph on an employee's
 * profile, streamed straight to Laravel — a phone photo runs past a server
 * action's one megabyte.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const code = new URL(request.url).searchParams.get("code") ?? "";
  if (!/^[A-Za-z0-9-]{1,24}$/.test(code)) return Response.json({ message: "Not an upload path." }, { status: 404 });

  const response = await fetch(`${BASE_URL}/hr-payroll/profiles/${encodeURIComponent(code)}/photo`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/hr-payroll", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
