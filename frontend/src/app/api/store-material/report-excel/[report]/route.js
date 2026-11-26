import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Store & Material → Reports → Export Excel (AnnotechERP 09/01): the workbook
 * `TraceApiController::excel()` writes, with the report's filters, streamed
 * straight through to the browser as a download.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { report } = await params;
  const query = new URL(request.url).search;

  const response = await fetch(
    `${BASE_URL}/store-material/reports/${encodeURIComponent(report)}/excel${query}`,
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
