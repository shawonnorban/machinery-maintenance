import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * `ReportJobApiController::download()` streams the file directly (not a
 * redirect to a signed URL, unlike `/files/{id}/download`), so this proxies
 * the bytes through rather than following a `location` header.
 */
export async function GET(request, { params }) {
  const { jobId } = await params;
  const token = await getSessionToken();

  const response = await fetch(`${BASE_URL}/report-jobs/${jobId}/download`, {
    headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });

  if (!response.ok) {
    return new Response(null, { status: response.status });
  }

  return new Response(response.body, {
    headers: {
      "Content-Type": response.headers.get("Content-Type") ?? "application/octet-stream",
      "Content-Disposition": response.headers.get("Content-Disposition") ?? "attachment",
    },
  });
}
