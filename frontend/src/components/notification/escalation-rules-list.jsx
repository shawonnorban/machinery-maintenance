"use client";

import { useState, useTransition } from "react";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { formatEventType, formatSeverity } from "./escalation-rule-form";

/** Mirrors `escalations/index.blade.php`'s rules table. */
function EscalationRulesList({ rules, toggleAction, deleteAction }) {
  const t = useT("notification");
  const tc = useT("common");
  const [pendingDelete, setPendingDelete] = useState(null);
  const [deleting, startDeleteTransition] = useTransition();
  const toastManager = useToastManager();

  function handleToggle(rule) {
    startDeleteTransition(async () => {
      const result = await toggleAction(rule.id);
      if (result?.status === "success") {
        toastManager.add({ title: rule.active ? t("pause") : t("resume"), type: "success" });
      }
    });
  }

  function confirmDelete() {
    startDeleteTransition(async () => {
      const result = await deleteAction(pendingDelete.id);
      setPendingDelete(null);
      if (result?.status === "success") {
        toastManager.add({ title: t("rule_removed"), type: "success" });
      }
    });
  }

  return (
    <>
      <DataTable
        columns={[
          { key: "event_type", header: t("event_label"), render: (row) => formatEventType(row.event_type, t) },
          { key: "severity", header: t("severity"), render: (row) => (row.severity ? formatSeverity(row.severity, t) : t("any_severity")) },
          { key: "delay_minutes", header: t("after"), render: (row) => t("minutes_short", { count: row.delay_minutes }) },
          { key: "escalation_level", header: t("level"), render: (row) => row.escalation_level },
          { key: "role", header: t("tell"), render: (row) => row.role?.description ?? "—" },
          { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? t("every_factory") },
          {
            key: "active",
            header: t("status_label"),
            render: (row) => (
              <Badge variant={row.active ? "success" : "neutral"}>{row.active ? t("rule_active") : t("rule_paused")}</Badge>
            ),
          },
        ]}
        rows={rules}
        rowKey={(row) => row.id}
        emptyTitle={t("no_rules")}
        emptyDescription={t("no_rules_hint")}
        rowActions={(row) => [
          { label: row.active ? t("pause") : t("resume"), onSelect: () => handleToggle(row) },
          { label: tc("delete"), destructive: true, onSelect: () => setPendingDelete(row) },
        ]}
      />

      <ConfirmDialog
        open={pendingDelete !== null}
        onOpenChange={(open) => !open && setPendingDelete(null)}
        title={pendingDelete ? t("delete_rule_title", { event: formatEventType(pendingDelete.event_type, t) }) : ""}
        description={t("remove_rule_confirm")}
        confirmLabel={tc("delete")}
        loading={deleting}
        onConfirm={confirmDelete}
      />
    </>
  );
}

export { EscalationRulesList };
