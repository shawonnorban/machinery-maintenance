"use client";

import { useTransition } from "react";
import { RotateCcw } from "lucide-react";
import { Card, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

/**
 * Closed accounts, kept off the main grid and folded away below it — not
 * customers any more, but recoverable for as long as they sit here
 * (mirrors `tenants/index.blade.php`'s own closed-accounts panel).
 */
function ClosedTenantsList({ closed, restoreAction }) {
  if (closed.length === 0) {
    return null;
  }

  return (
    <Card className="mt-6">
      <CardBody className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-foreground">Closed accounts ({closed.length})</h2>
        <div className="flex flex-col divide-y divide-border">
          {closed.map((tenant) => (
            <ClosedRow key={tenant.id} tenant={tenant} restoreAction={restoreAction} />
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

function ClosedRow({ tenant, restoreAction }) {
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function restore() {
    startTransition(async () => {
      const result = await restoreAction(tenant.id);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex items-center justify-between gap-3 py-3">
      <div>
        <p className="text-sm font-medium text-foreground">{tenant.name}</p>
        <p className="text-xs text-foreground-muted">
          {tenant.code} · Closed {tenant.closed_at ? new Date(tenant.closed_at).toLocaleDateString() : "—"}
        </p>
      </div>
      <Button size="sm" variant="outline" loading={pending} onClick={restore}>
        <RotateCcw /> Restore
      </Button>
    </div>
  );
}

export { ClosedTenantsList };
