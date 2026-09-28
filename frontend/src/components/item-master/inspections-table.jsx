"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CANCELLED"];

/** Four-point inspection sheets (Inventory spec §4.3). */
function InspectionsTable({ rows, meta, page, search, status }) {
  const t = useT("inspection");
  const tc = useT("common");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/item-master/inspections"
      searchPlaceholder={t("inspection_search_placeholder")}
      emptyTitle={t("no_inspections")}
      statusOptions={[
        { value: "", label: tc("all") },
        ...STATUSES.map((value) => ({ value, label: t(`inspection_statuses.${value}`) })),
      ]}
      columns={[
        {
          key: "inspection_no",
          header: t("inspection_no"),
          render: (row) => (
            <div className="flex flex-col">
              <Link href={`/item-master/inspections/${row.id}`} className="font-medium text-brand hover:underline">
                {row.inspection_no}
              </Link>
              <span className="text-xs text-foreground-muted">
                <FormattedDateTime value={row.inspection_date} mode="date" />
              </span>
            </div>
          ),
        },
        {
          key: "item",
          header: t("item"),
          render: (row) => (
            <div className="flex flex-col">
              <span>{row.item?.name ?? "—"}</span>
              <span className="text-xs text-foreground-muted">
                {[row.style_reference, row.colour].filter(Boolean).join(" · ") || row.vendor?.name}
              </span>
            </div>
          ),
        },
        {
          key: "stage",
          header: t("stage"),
          render: (row) => (
            <span className="flex flex-wrap items-center gap-1">
              {t(`stages.${row.stage}`)}
              <Badge variant="neutral">{formatNumber(row.sampling_percent, { maximumFractionDigits: 0 })}%</Badge>
            </span>
          ),
        },
        {
          key: "yards_inspected",
          header: t("yards_inspected"),
          align: "right",
          render: (row) => (
            <span className="tabular-nums">
              {formatNumber(row.yards_inspected)}
              <span className="text-foreground-muted"> / {formatNumber(row.yards_in_lot)}</span>
            </span>
          ),
        },
        {
          key: "penalty_percent",
          header: t("penalty_percent"),
          align: "right",
          render: (row) => (
            <span
              className={`tabular-nums ${
                row.result === "FAIL" ? "font-medium text-danger" : row.result === "PASS" ? "text-success" : ""
              }`}
            >
              {formatNumber(row.penalty_percent, { maximumFractionDigits: 2 })}
              {/* The bar it was judged against, because it is copied per
                  sheet and two rows may not share one. */}
              <span className="text-xs text-foreground-muted">
                {" "}
                / {formatNumber(row.acceptance_threshold, { maximumFractionDigits: 0 })}
              </span>
            </span>
          ),
        },
        {
          key: "status",
          header: t("status"),
          render: (row) => (
            <span className="flex flex-wrap items-center gap-1">
              <StatusBadge status={row.status} label={t(`inspection_statuses.${row.status}`)} />
              {row.result ? (
                <Badge variant={row.result === "FAIL" ? "danger" : "success"}>{t(`results.${row.result}`)}</Badge>
              ) : null}
            </span>
          ),
        },
      ]}
    />
  );
}

export { InspectionsTable };
