import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * HR & Payroll → Reports (AnnotechERP 24/02): one report as an Excel sheet,
 * with the screen's filters, streamed straight through to the browser.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { key } = await params;
  if (!/^[a-z0-9-]+$/.test(key)) return Response.json({ message: "Not found." }, { status: 404 });
  const query = new URL(request.url).search;

  const response = await fetch(`${BASE_URL}/hr-payroll/reports/${key}/xlsx${query}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });
  const headers = { "Content-Type": response.headers.get("Content-Type") ?? "application/octet-stream" };
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers["Content-Disposition"] = disposition;

  return new Response(response.body, { status: response.status, headers });
}
