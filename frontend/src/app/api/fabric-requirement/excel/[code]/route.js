import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Fabric Requirement Reports → Export (AnnotechERP 13/03): a report's rows, by
 * its code, as the workbook the API writes, streamed straight to the browser.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { code } = await params;
  if (!/^[A-Za-z0-9-]+$/.test(code))
    return Response.json({ message: "Not found." }, { status: 404 });

  const response = await fetch(
    `${BASE_URL}/fabric-planning/reports/${encodeURIComponent(code)}/excel`,
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
