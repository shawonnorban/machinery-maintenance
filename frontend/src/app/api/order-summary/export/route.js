import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Order Summary → Excel (View All's Export Excel): the list's own filters,
 * sort and ticked rows passed on to `OrderApiController::export()`, and the
 * .xlsx it writes streamed back as a download.
 */
export async function GET(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const query = new URL(request.url).search;
  const response = await fetch(`${BASE_URL}/order-summary/orders/export${query}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json" },
    cache: "no-store",
  });
  if (!response.ok) {
    return new Response(await response.text(), { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
  }
  const headers = new Headers({ "Content-Type": response.headers.get("Content-Type") ?? "application/octet-stream" });
  const disposition = response.headers.get("Content-Disposition");
  if (disposition) headers.set("Content-Disposition", disposition);

  return new Response(response.body, { status: 200, headers });
}
