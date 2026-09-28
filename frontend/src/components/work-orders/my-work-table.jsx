"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

/** Mirrors `MyWorkController::index` — this technician's own queue, in the fixed order they'd actually work through it, not a filtered slice of everyone's list. */
function MyWorkTable({ workOrders, meta }) {
  const router = useRouter();
  const t = useT("work_order");
  const tc = useT("common");

  return (
    <DataTable
      columns={[
        {
          key: "work_order_number",
          header: t("work_order"),
          render: (row) => (
            <div>
              <Link href={`/work-orders/${row.id}`} className="font-medium text-brand hover:underline">
                {row.work_order_number}
              </Link>
              <div className="text-xs text-foreground-muted">{row.title}</div>
            </div>
          ),
        },
        { key: "asset", header: t("asset"), render: (row) => row.asset?.asset_code ?? "—" },
        {
          key: "priority",
          header: t("priority"),
          render: (row) => (
            <Badge variant={PRIORITY_TONE[row.priority] ?? "neutral"}>{t(`priority_${row.priority?.toLowerCase()}`)}</Badge>
          ),
        },
        {
          key: "status",
          header: t("status"),
          render: (row) => <StatusBadge status={row.status} label={t(`status_${row.status?.toLowerCase()}`)} />,
        },
        {
          key: "scheduled_start",
          header: t("scheduled_start"),
          render: (row) => <FormattedDateTime value={row.scheduled_start} mode="date" fallback="—" />,
        },
      ]}
      rows={workOrders}
      rowKey={(row) => row.id}
      emptyTitle={t("no_my_work")}
      emptyDescription={t("no_my_work_hint")}
      rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/work-orders/${row.id}`) }]}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={(nextPage) => router.push(`/work-orders/my-work?page=${nextPage}`)}
    />
  );
}

export { MyWorkTable };
