import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Attendance & Shift → Export (AnnotechERP 24/04): Daily Attendance and the
 * Monthly Attendance Summary as Excel or PDF (the shared TablePdf), with the
 * screen's filters, streamed straight through to the browser.
 */
const REPORTS = { daily: "daily", summary: "summary" };

export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { report, format } = await params;
  if (!REPORTS[report] || !["pdf", "xlsx"].includes(format)) return Response.json({ message: "Not found." }, { status: 404 });
  const query = new URL(request.url).search;

  const response = await fetch(`${BASE_URL}/hr-payroll/attendance/${REPORTS[report]}/${format}${query}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });
  const headers = { "Content-Type": response.headers.get("Content-Type") ?? "application/octet-stream" };
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers["Content-Disposition"] = disposition;

  return new Response(response.body, { status: response.status, headers });
}
