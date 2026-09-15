"use client";

import { useCallback } from "react";
import { useRouter } from "next/navigation";
import { useEcho } from "@/lib/use-echo";
import { useToastManager } from "@/components/ui/toast";

/**
 * Phase F's first real Echo/Reverb wiring (docs/12-Stack-Migration-
 * Implementation-Plan.md) — `BreakdownReported`'s own docblock calls this
 * "the event that most justifies having websockets at all," and this list
 * is the screen a maintenance manager is most likely to have open when a
 * line goes down. The company channel, not a single factory's, since this
 * list itself shows "every machine stoppage on the floors you can reach"
 * — someone watching it may reach more than one factory.
 *
 * Renders nothing — it's an effect wired to the page it sits on, not a
 * visible element.
 */
function BreakdownsLiveUpdates({ companyId }) {
  const router = useRouter();
  const toastManager = useToastManager();

  const onReported = useCallback(
    (payload) => {
      toastManager.add({
        title: `New breakdown: ${payload.asset_code ?? "—"}`,
        description: payload.problem,
        type: payload.severity === "CATASTROPHIC" || payload.severity === "MAJOR" ? "danger" : "warning",
      });
      router.refresh();
    },
    [router, toastManager],
  );

  useEcho(companyId ? `company.${companyId}` : null, ".breakdown.reported", onReported);

  return null;
}

export { BreakdownsLiveUpdates };
