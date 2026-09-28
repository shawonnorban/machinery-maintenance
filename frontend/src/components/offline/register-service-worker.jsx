"use client";

import { useEffect } from "react";

/**
 * Mounted once, in the tenant app shell — registers public/sw.js (docs/12-
 * Stack-Migration-Implementation-Plan.md Phase E). Renders nothing.
 *
 * Production only. The worker's own cache-first strategy for `/_next/
 * static/*` is safe there because production filenames carry a real
 * content hash — a cache hit is guaranteed to be the right file. In `next
 * dev`/Turbopack, chunk URLs are not stably content-hashed the same way,
 * so a tab that registered this worker before an edit (or a `docker
 * compose restart frontend`) goes on serving the *old* cached chunks
 * indefinitely under URLs the dev server has since recompiled — a hard
 * refresh alone doesn't fix it, because an active service worker
 * intercepts fetches regardless of cache-busting headers. Confirmed as
 * the actual cause of this session's recurring "stale tab" hydration-
 * mismatch/script-warning reports, not a one-off Turbopack fluke.
 */
function RegisterServiceWorker() {
  useEffect(() => {
    if (!("serviceWorker" in navigator)) {
      return;
    }

    if (process.env.NODE_ENV === "production") {
      navigator.serviceWorker.register("/sw.js").catch(() => {
        // Same stance as the worker's own install handler: a registration
        // failure leaves the app exactly as usable as it was without one.
      });
      return;
    }

    // A tab that registered the worker before this guard existed keeps
    // running it — stopping the register() call above doesn't retroactively
    // remove an already-installed worker. Clean it up here instead of
    // requiring everyone who hit the stale-chunk symptom to know to open
    // DevTools and unregister it by hand.
    navigator.serviceWorker.getRegistrations().then((registrations) => {
      for (const registration of registrations) {
        registration.unregister().catch(() => {});
      }
    });

    if ("caches" in window) {
      caches.keys().then((keys) => {
        for (const key of keys) {
          if (key.startsWith("mm-assets-")) {
            caches.delete(key).catch(() => {});
          }
        }
      });
    }
  }, []);

  return null;
}

export { RegisterServiceWorker };
