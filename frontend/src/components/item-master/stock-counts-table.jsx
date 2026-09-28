"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["COUNTING", "UNDER_REVIEW", "PENDING_APPROVAL", "APPROVED", "POSTED", "REJECTED", "CANCELLED"];

/** Cycle counts and full stock takes (Inventory spec §12). */
function StockCountsTable({ rows, meta, page, search, status }) {
  const t = useT("itemmaster");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/item-master/stock-counts"
      searchPlaceholder={t("count_search_placeholder")}
      emptyTitle={t("no_counts")}
      statusOptions={[
        { value: "", label: t("all_statuses") },
        ...STATUSES.map((value) => ({ value, label: t(`count_statuses.${value}`) })),
      ]}
      columns={[
        {
          key: "count_no",
          header: t("count_no"),
          render: (row) => (
            <Link href={`/item-master/stock-counts/${row.id}`} className="font-medium text-brand hover:underline">
              {row.count_no}
            </Link>
          ),
        },
        { key: "count_date", header: t("count_date"), render: (row) => <FormattedDateTime value={row.count_date} mode="date" /> },
        { key: "store", header: t("store"), render: (row) => row.store?.name ?? "—" },
        {
          key: "count_type",
          header: t("count_type"),
          render: (row) => (
            <span className="flex flex-wrap items-center gap-1">
              {t(`count_types.${row.count_type}`)}
              {row.is_blind ? <Badge variant="neutral">{t("blind_count")}</Badge> : null}
            </span>
          ),
        },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        {
          key: "variance_value",
          header: t("variance_value"),
          align: "right",
          render: (row) => (
            <span className={Number(row.variance_value) > 0 ? "text-warning" : "text-foreground-muted"}>
              {formatNumber(row.variance_value, { maximumFractionDigits: 2 })}
            </span>
          ),
        },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`count_statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { StockCountsTable };
