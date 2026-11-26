import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * In-House / GRN → Attachments (AnnotechERP 09/01): a delivery challan,
 * packing list or photo, up to five megabytes. A route handler rather than a
 * server action, because a scanned challan runs past the one megabyte server
 * actions stop at; the body is streamed straight through to
 * `GrnApiController::attach()`.
 */
export async function POST(request, { params }) {
  const token = await getSessionToken();
  if (!token)
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const { grn } = await params;

  const response = await fetch(
    `${BASE_URL}/store-material/grns/${encodeURIComponent(grn)}/attachments`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Content-Type": request.headers.get("content-type") ?? "",
      },
      body: request.body,
      duplex: "half",
    },
  );

  return new Response(response.body, {
    status: response.status,
    headers: {
      "Content-Type":
        response.headers.get("Content-Type") ?? "application/json",
    },
  });
}
