/**
 * Service worker (docs/12-Stack-Migration-Implementation-Plan.md Phase E,
 * SRS 38) — ported from public/sw.js (the Blade app's own worker) with its
 * one rule preserved exactly: no HTML is ever cached.
 *
 * This is a multi-tenant system where every page is scoped to a company
 * and a person. A cached HTML page is a page that can be shown to the
 * next user of a shared factory tablet, or to somebody since removed from
 * the company — so nothing here stores a navigation response, only the
 * built CSS/JS, which is content-hashed and identical for everybody.
 *
 * Writes are never intercepted. A POST that fails belongs to the offline
 * queue in the page (src/lib/offline/queue.js), which knows the
 * idempotency key; a service worker replaying requests behind the page's
 * back would duplicate them.
 */
const VERSION = "v1";
const ASSETS = `mm-assets-${VERSION}`;
const FALLBACK = "/offline.html";

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(ASSETS)
      .then((cache) => cache.addAll([FALLBACK]))
      // Nothing here is essential. A worker that refuses to install
      // because one file 404'd leaves the app worse than no worker.
      .catch(() => undefined)
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== ASSETS).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

function isBuildAsset(url) {
  // Next.js's own content-hashed output — the direct equivalent of the
  // Blade app's /build/ (Vite's own hashed output directory).
  return url.origin === self.location.origin && url.pathname.startsWith("/_next/static/");
}

self.addEventListener("fetch", (event) => {
  const { request } = event;

  // Only GET. Anything that changes something is the page's business.
  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);

  // Never the API, and never this app's own BFF routes — a cached answer
  // to "what is on hand" is a wrong answer, and a stale one is worse than
  // none because nothing marks it stale.
  if (url.pathname.startsWith("/api/")) {
    return;
  }

  if (isBuildAsset(url)) {
    // Cache first: these filenames contain a content hash, so a hit is
    // always the right file and a miss is always a new deploy.
    event.respondWith(
      caches.match(request).then(
        (hit) =>
          hit ??
          fetch(request).then((response) => {
            if (response.ok) {
              const copy = response.clone();
              caches.open(ASSETS).then((cache) => cache.put(request, copy));
            }
            return response;
          }),
      ),
    );
    return;
  }

  if (request.mode === "navigate") {
    // Network only, with a fallback page. Never a cached copy of somebody
    // else's screen.
    event.respondWith(fetch(request).catch(() => caches.match(FALLBACK).then((hit) => hit ?? new Response("", { status: 504 }))));
  }
});
