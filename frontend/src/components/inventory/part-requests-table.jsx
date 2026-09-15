"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { formatStatus } from "@/components/ui/status-badge";
import { formatQuantity } from "@/lib/format";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

/** Mirrors `PartRequestController::index` — what the floor is waiting for, sorted by a stopped machine first, then the oldest wait. */
function PartRequestsTable({ lines }) {
  const router = useRouter();

  return (
    <DataTable
      columns={[
        {
          key: "spare_part",
          header: "Part",
          render: (line) => (
            <div>
              <Link href={`/inventory/parts/${line.spare_part?.id}`} className="font-medium text-brand hover:underline">
                {line.spare_part?.part_number}
              </Link>
              <div className="text-xs text-foreground-muted">{line.spare_part?.name}</div>
            </div>
          ),
        },
        {
          key: "quantity_requested",
          header: "Requested",
          align: "right",
          render: (line) => formatQuantity(line.quantity_requested, line.spare_part?.unit),
        },
        {
          key: "on_hand",
          header: "On hand",
          align: "right",
          render: (line) =>
            line.short ? (
              <span className="font-semibold text-danger">
                {formatQuantity(line.on_hand, line.spare_part?.unit)}
                <div className="text-xs font-normal">Not enough stock</div>
              </span>
            ) : (
              formatQuantity(line.on_hand, line.spare_part?.unit)
            ),
        },
        {
          key: "work_order",
          header: "Work order",
          render: (line) => (
            <div>
              <Link href={`/work-orders/${line.work_order?.id}`} className="font-medium text-brand hover:underline">
                {line.work_order?.work_order_number}
              </Link>
              <div className="text-xs text-foreground-muted">{line.work_order?.title}</div>
            </div>
          ),
        },
        {
          key: "asset",
          header: "Asset",
          render: (line) => (
            <div>
              {line.work_order?.asset?.asset_code}
              <div className="text-xs text-foreground-muted">{line.work_order?.asset?.name}</div>
            </div>
          ),
        },
        {
          key: "priority",
          header: "Priority",
          render: (line) => <Badge variant={PRIORITY_TONE[line.work_order?.priority] ?? "neutral"}>{formatStatus(line.work_order?.priority)}</Badge>,
        },
      ]}
      rows={lines}
      rowKey={(line) => line.id}
      emptyTitle="Nothing outstanding."
      emptyDescription="Every requested part has been issued or the request withdrawn."
      rowActions={(line) => [{ label: "Go and issue", onSelect: () => router.push(`/work-orders/${line.work_order?.id}`) }]}
    />
  );
}

export { PartRequestsTable };
