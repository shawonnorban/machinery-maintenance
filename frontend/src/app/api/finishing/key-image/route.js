import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Finishing Machine Profile → Key Image (AnnotechERP 18/01): uploading a
 * photograph of a machine's nameplate. A route handler rather than a server
 * action, because a photo runs past the one megabyte server actions stop at;
 * the body is streamed straight through to `FinishingApiController::keyImage()`.
 */
export async function POST(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const response = await fetch(`${BASE_URL}/finishing/key-images`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": request.headers.get("content-type") ?? "" },
    body: request.body,
    duplex: "half",
  });

  return new Response(response.body, { status: response.status, headers: { "Content-Type": response.headers.get("Content-Type") ?? "application/json" } });
}
