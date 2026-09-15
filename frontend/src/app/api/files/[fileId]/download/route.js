import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * The one place a file's bytes are ever reached from the browser. Laravel's
 * `GET /files/{id}/download` needs a bearer token (this route reads it from
 * the httpOnly cookie, server-side, same as every other call) and answers
 * with a 302 to a short-lived *signed* URL that needs no token at all —
 * the only way to hand a photo to something that can't send an
 * Authorization header, like an `<a>` tag or an `<img>` src. This route
 * follows that redirect manually and hands the signed URL on to the
 * browser, rather than using `apiFetch` (which would follow it
 * automatically via `fetch`'s default behavior and then try to parse a
 * binary/streamed response as JSON).
 */
export async function GET(request, { params }) {
  const { fileId } = await params;
  const token = await getSessionToken();

  const response = await fetch(`${BASE_URL}/files/${fileId}/download`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    redirect: "manual",
  });

  const location = response.headers.get("location");

  if (!location) {
    return NextResponse.json({ message: "File not available" }, { status: response.status || 404 });
  }

  return NextResponse.redirect(location);
}
