/**
 * Service worker (SRS 38).
 *
 * Deliberately small, and deliberately not an offline copy of the application.
 * This is a multi-tenant system where every page is scoped to a company and a
 * person: a cached HTML page is a page that can be shown to the next user of a
 * shared tablet, or to somebody who has since been removed from the company.
 * So no HTML is ever stored.
 *
 * What is cached is what is safe to cache and expensive to fetch: the built
 * CSS and JavaScript, which are content-hashed and identical for everybody.
 * That is enough to make the app start instantly on a slow factory connection,
 * which is the real complaint, and it makes the offline queue reachable — a
 * technician who opens a stale tab with no signal still gets a working page
 * shell rather than the browser's dinosaur.
 *
 * Writes are never intercepted. A POST that fails belongs to the offline
 * queue in the page, which knows the idempotency key; a service worker
 * replaying requests behind the page's back would duplicate them.
 */
const VERSION = 'v1';
const ASSETS = `mm-assets-${VERSION}`;
const FALLBACK = '/offline.html';

self.addEventListener('install', (event) => {
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

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== ASSETS).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

function isBuildAsset(url) {
    return url.origin === self.location.origin && url.pathname.startsWith('/build/');
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only GET. Anything that changes something is the page's business.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Never the API. A cached answer to "what is on hand" is a wrong answer,
    // and a stale one is worse than none because nothing marks it stale.
    if (url.pathname.startsWith('/api/')) return;

    if (isBuildAsset(url)) {
        // Cache first: these filenames contain a content hash, so a hit is
        // always the right file and a miss is always a new deploy.
        event.respondWith(
            caches.match(request).then((hit) => hit ?? fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(ASSETS).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );

        return;
    }

    if (request.mode === 'navigate') {
        // Network only, with a fallback page. Never a cached copy of somebody
        // else's screen.
        event.respondWith(
            fetch(request).catch(() => caches.match(FALLBACK).then(
                (hit) => hit ?? new Response('', { status: 504 }),
            )),
        );
    }
});
