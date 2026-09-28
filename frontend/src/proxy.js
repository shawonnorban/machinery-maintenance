import { NextResponse } from "next/server";

/**
 * Next.js 16 renamed `middleware.js` to `proxy.js`. This only checks that
 * the session cookie exists — a coarse, fast redirect so a signed-out visitor
 * never sees a flash of protected UI. It is not the authorization boundary:
 * every actual request still goes through `apiFetch` (src/lib/api-server.js)
 * to the Laravel API, which is the only place that decides what the token
 * may do (UI-DESIGN-SYSTEM.md §4 rule: "the frontend draws, the API decides").
 */
// /sw.js and /offline.html must stay reachable unauthenticated: a browser
// fetches the service worker directly (not through this app's session
// logic), and the offline fallback page's entire purpose is to render
// when nothing else can be reached — including a stale or expired
// session while offline, which is exactly when this redirect would
// otherwise fire.
const PUBLIC_PATHS = ["/login", "/sw.js", "/offline.html"];
const COOKIE_NAME = "annotech_session";
const PLATFORM_COOKIE_NAME = "annotech_platform_session";

export function proxy(request) {
  const { pathname } = request.nextUrl;

  // The platform console is a separate credential on a separate cookie
  // (`lib/platform-session.js`) — checked here against its own login page
  // rather than falling into the tenant check below, which would otherwise
  // redirect every unauthenticated platform visit to the customer's `/login`.
  if (pathname.startsWith("/platform") || pathname.startsWith("/api/platform-auth")) {
    const isPlatformPublic = pathname === "/platform/login" || pathname.startsWith("/api/platform-auth");

    if (isPlatformPublic || request.cookies.has(PLATFORM_COOKIE_NAME)) {
      return NextResponse.next();
    }

    return NextResponse.redirect(new URL("/platform/login", request.url));
  }

  const isPublic =
    PUBLIC_PATHS.some((path) => pathname === path) ||
    pathname.startsWith("/api/auth") ||
    pathname.startsWith("/_next") ||
    pathname === "/favicon.ico";

  if (isPublic) {
    return NextResponse.next();
  }

  const hasSession = request.cookies.has(COOKIE_NAME);

  if (!hasSession) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("from", pathname);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
