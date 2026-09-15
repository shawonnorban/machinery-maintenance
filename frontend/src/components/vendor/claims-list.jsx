"use client";

import { useActionState, useEffect } from "react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { useToastManager } from "@/components/ui/toast";

const STATUS_OPTIONS = [
  { value: "ACKNOWLEDGED", label: "Acknowledged" },
  { value: "APPROVED", label: "Approved" },
  { value: "REJECTED", label: "Rejected" },
  { value: "SETTLED", label: "Settled" },
];

const OPEN_STATUSES = ["SUBMITTED", "ACKNOWLEDGED", "APPROVED"];

/** Mirrors `warranties/show.blade.php`'s claims table — only an open claim gets a decide form. */
function ClaimsList({ claims, canDecide, decideAction }) {
  if (claims.length === 0) {
    return <p className="text-sm text-foreground-muted">No claims filed yet.</p>;
  }

  return (
    <div className="flex flex-col gap-4">
      {claims.map((claim) => (
        <ClaimRow key={claim.id} claim={claim} canDecide={canDecide} decideAction={decideAction} />
      ))}
    </div>
  );
}

function ClaimRow({ claim, canDecide, decideAction }) {
  const [state, dispatch, pending] = useActionState(decideAction.bind(null, claim.id), null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: `Claim ${claim.claim_number} updated`, type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state, claim.claim_number]);

  return (
    <div className="border-b border-border pb-4 last:border-b-0">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-foreground">{claim.claim_number}</p>
          <p className="text-xs text-foreground-muted">{claim.description}</p>
          {claim.resolution ? <p className="text-xs text-foreground-muted">Resolution: {claim.resolution}</p> : null}
        </div>
        <StatusBadge status={claim.status} />
      </div>

      <div className="mt-1 flex gap-4 text-xs text-foreground-muted">
        <span>Claimed: {claim.claimed_amount ?? "—"}</span>
        <span>Settled: {claim.settled_amount ?? "—"}</span>
        <span>{claim.claim_date}</span>
      </div>

      {canDecide && OPEN_STATUSES.includes(claim.status) ? (
        <form action={dispatch} className="mt-3 flex flex-wrap items-end gap-2">
          <div className="w-40">
            <Select name="status" options={STATUS_OPTIONS} defaultValue="APPROVED" />
          </div>
          <div className="w-48">
            <Input name="resolution" placeholder="Resolution" />
          </div>
          <div className="w-32">
            <Input type="number" step="0.01" min="0" name="settled_amount" placeholder="Settled amount" />
          </div>
          <Button type="submit" size="sm" variant="outline" loading={pending}>
            Save
          </Button>
        </form>
      ) : null}
    </div>
  );
}

export { ClaimsList };
