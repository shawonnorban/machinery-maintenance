"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { SearchingData } from "@/components/erp/searching-data";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

function WorkOrdersTable({ workOrders, meta, page, open, priority, addHref }) {
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
      <SearchingData
        fields={[
          { name: "open", label: t("status"), type: "select", all: false, options: openOptions },
          { name: "priority", label: t("priority"), type: "select", options: priorityOptions },
        ]}
        values={{ open, priority }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "work_order_number",
            header: t("work_order"),
            render: (row) => (
              <div>
                <Link href={`/work-orders/${refOf(row, "work_order_number")}`} className="font-medium text-brand hover:underline">
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
        rowActions={(row) => [{ icon: "view", label: tc("view"), onSelect: () => router.push(`/work-orders/${refOf(row, "work_order_number")}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { WorkOrdersTable };
