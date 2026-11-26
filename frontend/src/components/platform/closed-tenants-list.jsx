"use client";

import { useTransition } from "react";
import { Archive, RotateCcw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Panel, PTable, fmtDate } from "./ui";

/**
 * Closed accounts, kept off the main list — not customers any more, but
 * recoverable for as long as they sit here.
 */
function ClosedTenantsList({ closed, restoreAction }) {
  if (closed.length === 0) return null;

  return (
    <Panel title="Closed accounts" subtitle={`${closed.length} closed — restore brings one back with its data`} icon={Archive} className="mt-4">
      <PTable
        rows={closed}
        rowKey={(t) => t.id}
        columns={[
          { key: "name", label: "Customer", render: (t) => <b>{t.name}</b> },
          { key: "code", label: "Code" },
          { key: "closed_at", label: "Closed", render: (t) => fmtDate(t.closed_at) },
          { key: "act", label: "", align: "right", render: (t) => <RestoreButton tenant={t} restoreAction={restoreAction} /> },
        ]}
      />
    </Panel>
  );
}

function RestoreButton({ tenant, restoreAction }) {
  const [pending, start] = useTransition();
  const toast = useToastManager();
  const restore = () =>
    start(async () => {
      const result = await restoreAction(tenant.id);
      if (result?.status === "error") toast.add({ title: result.message, type: "danger" });
    });

  return (
    <Button size="sm" variant="outline" loading={pending} onClick={restore}>
      <RotateCcw /> Restore
    </Button>
  );
}

export { ClosedTenantsList };
