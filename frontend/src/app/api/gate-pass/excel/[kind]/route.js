import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Gate Pass → Excel (AnnotechERP 11/02–11/04): Gate Pass List (`list`) or a
 * report (`rpt-<report>`, as a PDF to print with `?as=pdf`) as the file the
 * API writes, with the screen's filters, streamed straight to the browser.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { kind } = await params;
  const url = new URL(request.url);
  const asPdf = url.searchParams.get("as") === "pdf";
  url.searchParams.delete("as");
  const report = kind.startsWith("rpt-") ? kind.slice(4) : null;
  const path = report
    ? `reports/${encodeURIComponent(report)}/${asPdf ? "pdf" : "excel"}`
    : kind === "list"
      ? "passes/excel"
      : null;
  if (!path) return Response.json({ message: "Not found." }, { status: 404 });

  const response = await fetch(`${BASE_URL}/gate-pass/${path}${url.search}`, {
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
