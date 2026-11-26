import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Work Study → Reports (AnnotechERP 14/02): a report's PDF (opened in its own
 * tab to print) or workbook, written by the API and streamed straight through
 * — the parameters as chosen on IE Reports.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { report, format } = await params;
  if (!/^[a-z0-9-]+$/.test(report) || !["pdf", "xlsx"].includes(format))
    return Response.json({ message: "Not found." }, { status: 404 });

  const query = new URL(request.url).search;
  const response = await fetch(
    `${BASE_URL}/workstudy/reports/${report}/export/${format}${query}`,
    {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    },
  );
  const headers = {
    "Content-Type":
      response.headers.get("Content-Type") ?? "application/octet-stream",
  };
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers["Content-Disposition"] = disposition;

  return new Response(response.body, { status: response.status, headers });
}
