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

function getEcho() {
  if (echo) {
    return echo;
  }

  if (typeof window === "undefined") {
    throw new Error("getEcho() must only be called in the browser.");
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
