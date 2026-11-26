import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Finishing Line → Process-Wise Delivery (AnnotechERP 18/02): the floor as
 * searched, as a PDF (opened in its own tab to print) or a workbook, written
 * by the API and streamed straight through — the search as on screen.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { format } = await params;
  if (!["pdf", "xlsx"].includes(format)) return Response.json({ message: "Not found." }, { status: 404 });

  const query = new URL(request.url).search;
  const response = await fetch(`${BASE_URL}/finishing/delivery-board/export/${format}${query}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });
  const headers = { "Content-Type": response.headers.get("Content-Type") ?? "application/octet-stream" };
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers["Content-Disposition"] = disposition;

  return new Response(response.body, { status: response.status, headers });
}
