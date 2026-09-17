"use client";

import { useActionState, useEffect } from "react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const OPEN_STATUSES = ["SUBMITTED", "ACKNOWLEDGED", "APPROVED"];

/** Mirrors `warranties/show.blade.php`'s claims table — only an open claim gets a decide form. */
function ClaimsList({ claims, canDecide, decideAction }) {
  const t = useT("vendor");

  if (claims.length === 0) {
    return <p className="text-sm text-foreground-muted">{t("no_claims_filed")}</p>;
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
  const t = useT("vendor");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(decideAction.bind(null, claim.id), null);
  const toastManager = useToastManager();

  const STATUS_OPTIONS = [
    { value: "ACKNOWLEDGED", label: t("claim_status_acknowledged") },
    { value: "APPROVED", label: t("claim_status_approved") },
    { value: "REJECTED", label: t("claim_status_rejected") },
    { value: "SETTLED", label: t("claim_status_settled") },
  ];

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("claim_number_updated_toast", { number: claim.claim_number }), type: "success" });
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
          {claim.resolution ? <p className="text-xs text-foreground-muted">{t("resolution_prefix", { text: claim.resolution })}</p> : null}
        </div>
        <StatusBadge status={claim.status} label={t(`claim_status_${claim.status?.toLowerCase()}`)} />
      </div>

      <div className="mt-1 flex gap-4 text-xs text-foreground-muted">
        <span>{t("claimed_amount")}: {claim.claimed_amount ?? "—"}</span>
        <span>{t("settled_amount")}: {claim.settled_amount ?? "—"}</span>
        <span>{claim.claim_date}</span>
      </div>

      {canDecide && OPEN_STATUSES.includes(claim.status) ? (
        <form action={dispatch} className="mt-3 flex flex-wrap items-end gap-2">
          <div className="w-40">
            <Select name="status" options={STATUS_OPTIONS} defaultValue="APPROVED" />
          </div>
          <div className="w-48">
            <Input name="resolution" placeholder={t("resolution")} />
          </div>
          <div className="w-32">
            <Input type="number" step="0.01" min="0" name="settled_amount" placeholder={t("settled_amount")} />
          </div>
          <Button type="submit" size="sm" variant="outline" loading={pending}>
            {tc("save")}
          </Button>
        </form>
      ) : null}
    </div>
  );
}

export { ClaimsList };
