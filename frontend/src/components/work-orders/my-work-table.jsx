"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

/** Mirrors `MyWorkController::index` — this technician's own queue, in the fixed order they'd actually work through it, not a filtered slice of everyone's list. */
function MyWorkTable({ workOrders, meta }) {
  const router = useRouter();

  return (
    <DataTable
      columns={[
        {
          key: "work_order_number",
          header: "Work order",
          render: (row) => (
            <div>
              <Link href={`/work-orders/${row.id}`} className="font-medium text-brand hover:underline">
                {row.work_order_number}
              </Link>
              <div className="text-xs text-foreground-muted">{row.title}</div>
            </div>
          ),
        },
        { key: "asset", header: "Asset", render: (row) => row.asset?.asset_code ?? "—" },
        {
          key: "priority",
          header: "Priority",
          render: (row) => <Badge variant={PRIORITY_TONE[row.priority] ?? "neutral"}>{formatStatus(row.priority)}</Badge>,
        },
        { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
        {
          key: "scheduled_start",
          header: "Scheduled",
          render: (row) => <FormattedDateTime value={row.scheduled_start} mode="date" fallback="—" />,
        },
      ]}
      rows={workOrders}
      rowKey={(row) => row.id}
      emptyTitle="Nothing on your queue."
      emptyDescription="Open jobs assigned to you will show up here."
      rowActions={(row) => [{ label: "View", onSelect: () => router.push(`/work-orders/${row.id}`) }]}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={(nextPage) => router.push(`/work-orders/my-work?page=${nextPage}`)}
    />
  );
}

export { MyWorkTable };
