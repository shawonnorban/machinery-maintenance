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

function BreakdownsTable({ breakdowns, meta, page, open, priority, addHref }) {
  const t = useT("breakdown");
  const tc = useT("common");
  const router = useRouter();

  const openOptions = [
    { value: "true", label: t("open_only") },
    { value: "false", label: t("all_breakdowns") },
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
    router.push(`/breakdowns?${params.toString()}`);
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
            key: "breakdown_number",
            header: t("breakdown"),
            render: (row) => (
              <Link href={`/breakdowns/${refOf(row, "breakdown_number")}`} className="font-medium text-brand hover:underline">
                {row.breakdown_number}
              </Link>
            ),
          },
          {
            key: "asset",
            header: t("asset"),
            render: (row) => (
              <div>
                {row.asset?.asset_code}
                <div className="text-xs text-foreground-muted">{row.asset?.name}</div>
              </div>
            ),
          },
          { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
          { key: "production_line", header: t("production_line"), render: (row) => row.production_line?.name ?? "—" },
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
            key: "reported_at",
            header: t("reported_at"),
            render: (row) => (
              <div>
                <FormattedDateTime value={row.reported_at} />
                <div className="text-xs text-foreground-muted">{row.reported_by?.name ?? "—"}</div>
              </div>
            ),
          },
        ]}
        rows={breakdowns}
        rowKey={(row) => row.id}
        emptyTitle={t("no_breakdowns")}
        emptyDescription={open === "true" ? t("no_breakdowns_hint") : undefined}
        rowActions={(row) => [{ icon: "view", label: tc("view"), onSelect: () => router.push(`/breakdowns/${refOf(row, "breakdown_number")}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { BreakdownsTable };
