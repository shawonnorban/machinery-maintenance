import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * The one place a file's bytes are ever reached from the browser. Laravel's
 * `GET /files/{id}/download` needs a bearer token (read from the httpOnly
 * cookie, server-side, same as every other call) and answers with a 302 to
 * a short-lived *signed* URL that needs no token at all.
 *
 * That signed URL is fetched here, server-side, and its body streamed
 * straight back — never handed to the browser as a redirect target. Every
 * Laravel response carries `Cross-Origin-Resource-Policy: same-origin`
 * (`SecurityHeaders`, correct hardening for a document the browser is never
 * meant to load from anywhere but this app's own origin), so a browser
 * asked to follow a redirect onto the Laravel origin directly refuses to
 * render what comes back — confirmed live: an attachment's image preview
 * stayed blank despite the URL resolving and returning real bytes to a
 * plain `fetch`/curl, which doesn't enforce that header at all. Streaming
 * the bytes through this route keeps the browser talking to nothing but
 * this app's own origin, the same origin the CORP header already permits.
 */
export async function GET(request, { params }) {
  const { fileId } = await params;
  const token = await getSessionToken();

  const redirectResponse = await fetch(`${BASE_URL}/files/${fileId}/download`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    redirect: "manual",
  });

  const location = redirectResponse.headers.get("location");

  if (!location) {
    return NextResponse.json({ message: "File not available" }, { status: redirectResponse.status || 404 });
  }

  // `location`'s host is whatever Laravel saw on the inbound request above
  // (this container's own `BASE_URL` host) — reachable from here as that
  // same origin, not as the literal string in `location` if it ever
  // differs. `signed.pathname` already carries the full `/api/v1/...`
  // path (it's a named-route URL, not built by appending onto `BASE_URL`),
  // so only `BASE_URL`'s origin is reused here, not the whole thing.
  const signed = new URL(location);
  const internalOrigin = new URL(BASE_URL).origin;
  const fileResponse = await fetch(`${internalOrigin}${signed.pathname}${signed.search}`);

  if (!fileResponse.ok || !fileResponse.body) {
    return NextResponse.json({ message: "File not available" }, { status: fileResponse.status || 404 });
  }

  const headers = {
    "Cache-Control": "private, max-age=0, no-store",
  };
  const contentType = fileResponse.headers.get("content-type");
  const contentLength = fileResponse.headers.get("content-length");
  const contentDisposition = fileResponse.headers.get("content-disposition");
  if (contentType) headers["Content-Type"] = contentType;
  if (contentLength) headers["Content-Length"] = contentLength;
  if (contentDisposition) headers["Content-Disposition"] = contentDisposition;

  return new NextResponse(fileResponse.body, { status: 200, headers });
}
