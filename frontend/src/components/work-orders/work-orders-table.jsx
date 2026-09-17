"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

function WorkOrdersTable({ workOrders, meta, page, open, priority }) {
  const t = useT("work_order");
  const tc = useT("common");
  const router = useRouter();

  const openOptions = [
    { value: "true", label: t("open_only") },
    { value: "false", label: t("all_work_orders") },
  ];
  const priorityOptions = [
    { value: "", label: t("all_priorities") },
    { value: "CRITICAL", label: t("priority_critical") },
    { value: "HIGH", label: t("priority_high") },
    { value: "MEDIUM", label: t("priority_medium") },
    { value: "LOW", label: t("priority_low") },
  ];

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      open: next.open ?? open,
      priority: next.priority ?? priority,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/work-orders?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-3">
        <div className="w-full max-w-[180px]">
          <Select options={openOptions} value={open} onValueChange={(value) => navigate({ open: value, page: 1 })} />
        </div>
        <div className="w-full max-w-[180px]">
          <Select options={priorityOptions} value={priority} onValueChange={(value) => navigate({ priority: value, page: 1 })} placeholder={t("all_priorities")} />
        </div>
      </div>

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
          {
            key: "asset",
            header: t("asset"),
            render: (row) => row.asset?.asset_code ?? "—",
          },
          { key: "factory", header: tc("factory_scope"), render: (row) => row.factory?.name ?? "—" },
          {
            key: "priority",
            header: t("priority"),
            render: (row) => <Badge variant={PRIORITY_TONE[row.priority] ?? "neutral"}>{t(`priority_${row.priority?.toLowerCase()}`)}</Badge>,
          },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={t(`status_${row.status?.toLowerCase()}`)} />,
          },
          {
            key: "scheduled_start",
            header: t("scheduled_start"),
            render: (row) => <FormattedDateTime value={row.scheduled_start} mode="date" />,
          },
        ]}
        rows={workOrders}
        rowKey={(row) => row.id}
        emptyTitle={t("no_work_orders")}
        rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/work-orders/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { WorkOrdersTable };
