"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";

/**
 * One Echo connection per browser tab, lazily created on first use rather
 * than at module load — importing this file must never open a socket by
 * itself (e.g. from a server-rendered pass that happens to touch the
 * module graph). `authEndpoint` points at this app's own
 * `/api/broadcast-auth` proxy (src/app/api/broadcast-auth/route.js), never
 * at the Laravel API directly — the bearer token never reaches this file.
 */
let echo = null;

/**
 * `null` when there is no Reverb key configured (deliberately blank on a
 * host with nothing to run `reverb:start` — DEPLOYMENT-GUIDE.md §3) rather
 * than a connection to make: `pusher-js` throws synchronously, inside the
 * caller's `useEffect`, the moment it's asked to instantiate without one
 * ("You must pass your app key when you instantiate Pusher"), and an
 * uncaught throw there crashed the whole page, not just the live-update
 * feature — confirmed live on exactly the screens that mount one.
 */
function getEcho() {
  if (echo) {
    return echo;
  }

  if (typeof window === "undefined") {
    throw new Error("getEcho() must only be called in the browser.");
  }

  if (!process.env.NEXT_PUBLIC_REVERB_APP_KEY) {
    return null;
  }

  window.Pusher = Pusher;

  echo = new Echo({
    broadcaster: "reverb",
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST,
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080),
    wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080),
    forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: "/api/broadcast-auth",
  });

  return echo;
}

function disconnectEcho() {
  echo?.disconnect();
  echo = null;
}

export { getEcho, disconnectEcho };
