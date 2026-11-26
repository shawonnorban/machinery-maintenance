import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Capacity Planning → Export (AnnotechERP 12/02): Master Plan List
 * (`master-plans`) or Plan vs Actual (`plan-vs-actual`) as the workbook the API
 * writes, with the screen's filters, streamed straight to the browser.
 */
const PATHS = {
  "master-plans": "master-plans/excel",
  "plan-vs-actual": "plan-vs-actual/excel",
};

export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { kind } = await params;
  const path = PATHS[kind];
  if (!path) return Response.json({ message: "Not found." }, { status: 404 });

  const url = new URL(request.url);
  const response = await fetch(`${BASE_URL}/planning/${path}${url.search}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });
  const headers = {
    "Content-Type":
      response.headers.get("Content-Type") ?? "application/octet-stream",
  };
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers["Content-Disposition"] = disposition;

  return new Response(response.body, { status: response.status, headers });
}
