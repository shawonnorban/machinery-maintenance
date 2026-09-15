"use client";

import { useEffect, useState } from "react";
import { startOfflineQueue, discardDraft } from "@/lib/offline/queue";

/**
 * Subscribes to the offline queue's `offline-queue:changed` event (fired
 * by src/lib/offline/queue.js on every save/send/discard) and starts the
 * queue's background flush loop on first use — `AppShell`'s sync
 * indicator is the one place this needs calling; a technician screen that
 * saves drafts calls `saveDraft()` directly and this hook picks up the
 * change through the same event, no prop drilling required.
 */
function useOfflineQueue() {
  const [state, setState] = useState({ pending: [], failed: [] });

  useEffect(() => {
    startOfflineQueue();

    function onChange(event) {
      setState(event.detail);
    }

    window.addEventListener("offline-queue:changed", onChange);
    return () => window.removeEventListener("offline-queue:changed", onChange);
  }, []);

  return { ...state, discardDraft };
}

export { useOfflineQueue };
