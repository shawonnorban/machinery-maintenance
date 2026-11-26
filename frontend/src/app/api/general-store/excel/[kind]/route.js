import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * General Store → Excel (AnnotechERP 10/03): a document list, Current Stock or
 * an item's ledger as the workbook the API writes, with the screen's filters,
 * streamed straight through to the browser as a download.
 */
const DOCUMENTS = [
  "opening",
  "receive",
  "issue",
  "return",
  "adjustment",
  "transfer",
];

export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { kind } = await params;
  // A report: `rpt-<report>`, as Excel, or as a PDF to print with `?as=pdf`.
  const url = new URL(request.url);
  const asPdf = url.searchParams.get("as") === "pdf";
  url.searchParams.delete("as");
  const query = url.search;
  const report = kind.startsWith("rpt-") ? kind.slice(4) : null;
  const path = report
    ? `reports/${encodeURIComponent(report)}/${asPdf ? "pdf" : "excel"}`
    : DOCUMENTS.includes(kind)
      ? `${kind}/documents/excel`
      : {
          "current-stock": "current-stock/excel",
          ledger: "ledger/excel",
          "audit-log": "audit-log/excel",
        }[kind];
  if (!path) return Response.json({ message: "Not found." }, { status: 404 });

  const response = await fetch(`${BASE_URL}/general-store/${path}${query}`, {
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
