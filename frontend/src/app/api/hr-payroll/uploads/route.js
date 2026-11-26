import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Allowance → Excel Upload, Deduction → Upload (AnnotechERP 24/01): reading
 * an allowance or deduction file. A route handler rather than a server
 * action, because a month's file for a large factory runs past the one
 * megabyte server actions stop at; the body is streamed straight through to
 * `HrPayrollApiController::parseUpload()`.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/hr-payroll/uploads`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
