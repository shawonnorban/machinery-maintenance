"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ShieldAlert } from "lucide-react";
import { Button } from "@/components/ui/button";

/**
 * Shown for the whole time a platform support session is open — seen by
 * whoever is holding this browser tab, which after `PlatformSupportGrant
 * ApiController::enter()` is platform support, not the account's own owner
 * (SRS 5.4: acting as a customer's user must never look identical to being
 * that user, from the inside). Present whenever `/auth/me` reports
 * `impersonated_by`, i.e. this token was minted by `enter()` rather than an
 * ordinary login.
 *
 * "Leave" reuses the ordinary logout route: revoking this one token and
 * clearing the cookie is the entire session, the same as it is for anyone
 * else's login, since a support session was never a second, nested session
 * layered on a real one — it *is* the token this tab has been using.
 */
function SupportSessionBanner({ actingAsName, actingAsEmail, staffName }) {
  const router = useRouter();
  const [leaving, setLeaving] = useState(false);

  async function leave() {
    setLeaving(true);
    await fetch("/api/auth/logout", { method: "POST" }).catch(() => null);
    router.push("/platform");
    router.refresh();
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 bg-warning px-4 py-2 text-sm font-medium text-white">
      <span className="flex items-center gap-2">
        <ShieldAlert className="size-4" />
        Support session — acting as {actingAsName} {actingAsEmail ? `(${actingAsEmail})` : ""}
        {staffName ? <span className="font-normal text-white/80">· opened by {staffName}</span> : null}
      </span>
      <Button size="sm" variant="outline" className="border-white/40 bg-white/10 text-white hover:bg-white/20" loading={leaving} onClick={leave}>
        Leave
      </Button>
    </div>
  );
}

export { SupportSessionBanner };
