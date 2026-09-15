"use client";

import { useEffect, useRef, useState } from "react";
import { getEcho } from "@/lib/echo";

/**
 * Subscribes to a private channel for the life of the calling component and
 * leaves cleanly on unmount — Phase D screens call this once per channel
 * they need, never touching `getEcho()` directly.
 *
 * @param {string | null} channelName omit the "private-" prefix; pass null to skip subscribing (e.g. before a companyId is known)
 * @param {string} event the event's `broadcastAs()` name with a leading dot
 *   (e.g. `.breakdown.reported`) if it defines one — that dot tells Echo not
 *   to prefix the name with an assumed App\Events namespace; omit the dot
 *   only for an event with no `broadcastAs()` override, listened to by its
 *   full class name instead
 * @param {(payload: any) => void} callback
 */
function useEcho(channelName, event, callback) {
  const [connectionState, setConnectionState] = useState("initial");
  const callbackRef = useRef(callback);

  useEffect(() => {
    callbackRef.current = callback;
  }, [callback]);

  useEffect(() => {
    if (!channelName) {
      return undefined;
    }

    const echo = getEcho();
    const channel = echo.private(channelName);

    channel.listen(event, (payload) => callbackRef.current(payload));

    const pusher = echo.connector.pusher;
    const onStateChange = (states) => setConnectionState(states.current);
    pusher.connection.bind("state_change", onStateChange);
    // Pusher only fires `state_change` on the *next* transition, not the
    // connection's state as of now — binding alone misses it whenever the
    // singleton connection (getEcho() reuses one per tab) was already
    // connected before this component mounted. Queued as a microtask
    // rather than read synchronously here, which is what React's
    // set-state-in-effect check is warning against.
    queueMicrotask(() => setConnectionState(pusher.connection.state));

    return () => {
      pusher.connection.unbind("state_change", onStateChange);
      echo.leave(channelName);
    };
  }, [channelName, event]);

  return { connectionState };
}

export { useEcho };
