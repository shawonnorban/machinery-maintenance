"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** The last 50 attempts to reach this endpoint (`WebhookEndpointApiController::show`). */
function DeliveriesTable({ deliveries, endpointId, redeliver }) {
  const t = useT("webhook");
  const router = useRouter();
  const [, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runRedeliver(delivery) {
    startTransition(async () => {
      const result = await redeliver(delivery.id, endpointId);
      if (result?.status === "success") {
        toastManager.add({ title: t("redelivery_queued"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <DataTable
      columns={[
        { key: "event_type", header: t("event") },
        { key: "status", header: t("status"), render: (d) => <StatusBadge status={d.status} label={t(`delivery_statuses.${d.status}`)} /> },
        { key: "attempt_count", header: t("attempts"), align: "right" },
        { key: "response_status", header: t("response"), align: "right", render: (d) => d.response_status ?? "—" },
        { key: "last_attempted_at", header: t("last_attempt"), render: (d) => <FormattedDateTime value={d.last_attempted_at} mode="datetime" fallback="—" /> },
      ]}
      rows={deliveries}
      rowKey={(d) => d.id}
      emptyTitle={t("no_deliveries_found")}
      rowActions={(d) => [{ label: t("redeliver"), onSelect: () => runRedeliver(d) }]}
    />
  );
}

export { DeliveriesTable };
