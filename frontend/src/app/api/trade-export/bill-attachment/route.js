import { revalidatePath } from "next/cache";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Trade & Export: the document attached to an Export Bill Lodgement (22/02),
 * a Customs Bill of Entry (22/03) or an export L/C's PRC (22/05), streamed
 * straight to Laravel, a scan running past a server action's one megabyte.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const id = new URL(request.url).searchParams.get("id") ?? "";
  if (!/^[0-9a-z]{26}$/i.test(id)) return Response.json({ message: "Not an upload path." }, { status: 404 });

  const kind = new URL(request.url).searchParams.get("kind");
  const path = kind === "prc" ? `lcs/${id}/prc/attachment` : `${kind === "entry" ? "entries" : "lodgements"}/${id}/attachment`;
  const response = await fetch(`${BASE_URL}/tradeexport/ops/${path}`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });
  if (response.ok) revalidatePath("/trade-export", "layout");

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
