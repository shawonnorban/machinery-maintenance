"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { MenuIcon } from "@/components/layout/menu-icon";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * A one-button card for an action with no form ("Backup now", "Purge now"):
 * what it does, the button, and — for anything that removes or overwrites —
 * a confirmation first. The action answers `{status, message?}`; `doneText`
 * is the toast when it says nothing itself, with `:count` from `removed`.
 *
 * @param {{
 *   title: string, description: string, buttonLabel: string, icon?: string, action: Function,
 *   doneText: string, confirm?: { title: string, description: string, confirmLabel: string }, disabled?: boolean, note?: string,
 * }} props
 */
function ActionCard({ title, description, buttonLabel, icon = "Play", action, doneText, confirm, disabled = false, note }) {
  const te = useT("erp");
  const router = useRouter();
  const toastManager = useToastManager();
  const [asking, setAsking] = useState(false);
  const [pending, startTransition] = useTransition();

  function run() {
    startTransition(async () => {
      const result = await action();
      setAsking(false);
      if (result?.status === "success") {
        toastManager.add({ title: result.message ?? doneText.replace(":count", String(result.removed ?? "")), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border bg-surface p-5">
      <div className="flex min-w-0 flex-1 items-start gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-subtle text-brand [&_svg]:size-5">
          <MenuIcon name={icon} />
        </span>
        <div className="min-w-0">
          <h3 className="text-base font-semibold text-foreground">{title}</h3>
          <p className="mt-0.5 text-sm text-foreground-muted">{description}</p>
          {note ? <p className="mt-1 text-xs text-warning">{note}</p> : null}
        </div>
      </div>
      <Button onClick={() => (confirm ? setAsking(true) : run())} loading={pending && !confirm} disabled={disabled || pending}>
        {buttonLabel}
      </Button>
      {confirm ? (
        <ConfirmDialog
          open={asking}
          onOpenChange={setAsking}
          title={confirm.title}
          description={confirm.description}
          confirmLabel={confirm.confirmLabel}
          cancelLabel={te("cancel")}
          loading={pending}
          onConfirm={run}
        />
      ) : null}
    </div>
  );
}

export { ActionCard };
