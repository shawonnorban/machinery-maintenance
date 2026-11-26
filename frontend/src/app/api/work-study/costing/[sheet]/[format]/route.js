import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

const SHEETS = ["oh-sheet", "day-config", "day-lines", "day-profit"];

/**
 * Work Study → Costing (AnnotechERP 14/05): a sheet's PDF (opened in its own
 * tab to print) or workbook, written by the API and streamed straight through
 * — the month (?period=) or the floor and day (?floor=&date=) as on screen.
 */
export async function GET(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { sheet, format } = await params;
  if (!SHEETS.includes(sheet) || !["pdf", "xlsx"].includes(format))
    return Response.json({ message: "Not found." }, { status: 404 });

  const query = new URL(request.url).search;
  const response = await fetch(
    `${BASE_URL}/workstudy/costing/export/${sheet}/${format}${query}`,
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
