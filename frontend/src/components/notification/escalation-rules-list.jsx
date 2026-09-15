"use client";

import { useState, useTransition } from "react";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { formatEventType } from "./escalation-rule-form";

/** Mirrors `escalations/index.blade.php`'s rules table. */
function EscalationRulesList({ rules, toggleAction, deleteAction }) {
  const [pendingDelete, setPendingDelete] = useState(null);
  const [deleting, startDeleteTransition] = useTransition();
  const toastManager = useToastManager();

  function handleToggle(rule) {
    startDeleteTransition(async () => {
      const result = await toggleAction(rule.id);
      if (result?.status === "success") {
        toastManager.add({ title: rule.active ? "Rule paused" : "Rule resumed", type: "success" });
      }
    });
  }

  function confirmDelete() {
    startDeleteTransition(async () => {
      const result = await deleteAction(pendingDelete.id);
      setPendingDelete(null);
      if (result?.status === "success") {
        toastManager.add({ title: "Rule deleted", type: "success" });
      }
    });
  }

  return (
    <>
      <DataTable
        columns={[
          { key: "event_type", header: "Event", render: (row) => formatEventType(row.event_type) },
          { key: "severity", header: "Severity", render: (row) => (row.severity ? formatEventType(row.severity) : "Any severity") },
          { key: "delay_minutes", header: "After", render: (row) => `${row.delay_minutes} min` },
          { key: "escalation_level", header: "Level", render: (row) => row.escalation_level },
          { key: "role", header: "Tell", render: (row) => row.role?.description ?? "—" },
          { key: "factory", header: "Factory", render: (row) => row.factory?.name ?? "Every factory" },
          {
            key: "active",
            header: "Status",
            render: (row) => <Badge variant={row.active ? "success" : "neutral"}>{row.active ? "Active" : "Paused"}</Badge>,
          },
        ]}
        rows={rules}
        rowKey={(row) => row.id}
        emptyTitle="No escalation rules configured."
        rowActions={(row) => [
          { label: row.active ? "Pause" : "Resume", onSelect: () => handleToggle(row) },
          { label: "Delete", destructive: true, onSelect: () => setPendingDelete(row) },
        ]}
      />

      <ConfirmDialog
        open={pendingDelete !== null}
        onOpenChange={(open) => !open && setPendingDelete(null)}
        title={pendingDelete ? `Delete the ${formatEventType(pendingDelete.event_type)} rule?` : ""}
        description="Nothing else references a rule by id, so this is unconditional — the notification history stays exactly as it was sent."
        confirmLabel="Delete"
        loading={deleting}
        onConfirm={confirmDelete}
      />
    </>
  );
}

export { EscalationRulesList };
