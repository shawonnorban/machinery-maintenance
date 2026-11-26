import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Finishing Line → Sub-Contract Party Balance (AnnotechERP 18/03): the open
 * lines as searched, as a workbook written by the API and streamed straight through.
 */
export async function GET(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const query = new URL(request.url).search;
  const response = await fetch(`${BASE_URL}/finishing/sc-balance/export${query}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });
  const headers = { "Content-Type": response.headers.get("Content-Type") ?? "application/octet-stream" };
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers["Content-Disposition"] = disposition;

  return new Response(response.body, { status: response.status, headers });
}
