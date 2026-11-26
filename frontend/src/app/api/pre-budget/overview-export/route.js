import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Pre-Budget → Budget Overview → Excel: `?budget=PB-…` passed on to
 * `BudgetApiController::overviewExport()`, and the .xlsx it writes (the
 * artwork in its cells) streamed back as a download.
 */
export async function GET(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const no = new URL(request.url).searchParams.get("budget") ?? "";
  if (!/^PB-[A-Z0-9-]+$/.test(no)) return Response.json({ message: "Not found." }, { status: 404 });
  const response = await fetch(`${BASE_URL}/pre-budget/budgets/${encodeURIComponent(no)}/overview/export`, {
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
