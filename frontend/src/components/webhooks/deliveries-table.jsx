"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";

/** The last 50 attempts to reach this endpoint (`WebhookEndpointApiController::show`). */
function DeliveriesTable({ deliveries, endpointId, redeliver }) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runRedeliver(delivery) {
    startTransition(async () => {
      const result = await redeliver(delivery.id, endpointId);
      if (result?.status === "success") {
        toastManager.add({ title: "Redelivery queued", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <DataTable
      columns={[
        { key: "event_type", header: "Event" },
        { key: "status", header: "Status", render: (d) => <StatusBadge status={d.status} /> },
        { key: "attempt_count", header: "Attempts", align: "right" },
        { key: "response_status", header: "Response", align: "right", render: (d) => d.response_status ?? "—" },
        { key: "last_attempted_at", header: "Last attempt", render: (d) => <FormattedDateTime value={d.last_attempted_at} mode="datetime" fallback="—" /> },
      ]}
      rows={deliveries}
      rowKey={(d) => d.id}
      emptyTitle="No deliveries yet."
      rowActions={(d) => [{ label: "Redeliver", onSelect: () => runRedeliver(d) }]}
    />
  );
}

export { DeliveriesTable };
