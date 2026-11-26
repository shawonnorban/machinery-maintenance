import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Re-Joining (AnnotechERP 24/07): the scanned re-join letter, streamed
 * straight to Laravel — a phone scan runs past a server action's one megabyte.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const ref = new URL(request.url).searchParams.get("ref") ?? "";
  if (!/^RJN-\d{6}$/.test(ref)) return Response.json({ message: "Not an upload path." }, { status: 404 });

  const response = await fetch(`${BASE_URL}/hr-payroll/rejoins/${encodeURIComponent(ref)}/doc`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/hr-payroll", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
