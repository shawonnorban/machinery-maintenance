"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "ACTIVE", "CANCELLED"];

/** Long-term prices agreed with a supplier (Procurement spec §14). */
function RateContractsTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/rate-contracts"
      searchPlaceholder={t("contract_search_placeholder")}
      emptyTitle={t("no_contracts")}
      statusOptions={[
        { value: "", label: t("all_statuses") },
        ...STATUSES.map((value) => ({ value, label: t(`contract_statuses.${value}`) })),
      ]}
      columns={[
        {
          key: "contract_no",
          header: t("contract_no"),
          render: (row) => (
            <div className="flex flex-col">
              <Link href={`/procurement/rate-contracts/${row.id}`} className="font-medium text-brand hover:underline">
                {row.contract_no}
              </Link>
              {row.title ? <span className="text-xs text-foreground-muted">{row.title}</span> : null}
            </div>
          ),
        },
        { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
        {
          key: "scope",
          header: t("factory"),
          // Null factory means the price was agreed for the whole group.
          render: (row) => (row.company_wide ? t("company_wide") : (row.factory?.name ?? "—")),
        },
        {
          key: "period",
          header: t("contract_period"),
          render: (row) => (
            <span className="flex items-center gap-1 text-sm">
              <FormattedDateTime value={row.valid_from} mode="date" />
              <span className="text-foreground-muted">→</span>
              <FormattedDateTime value={row.valid_to} mode="date" />
            </span>
          ),
        },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        {
          key: "status",
          header: t("status"),
          render: (row) => (
            <span className="flex flex-wrap items-center gap-1">
              <StatusBadge status={row.status} label={t(`contract_statuses.${row.status}`)} />
              {/* The status alone cannot say whether it applies today. */}
              {row.status === "ACTIVE" && row.has_expired ? (
                <Badge variant="neutral">{t("contract_expired")}</Badge>
              ) : null}
              {row.is_live && row.days_to_expiry <= 30 ? (
                <Badge variant="warning">
                  {t("days_to_expiry")}: {row.days_to_expiry}
                </Badge>
              ) : null}
            </span>
          ),
        },
      ]}
    />
  );
}

export { RateContractsTable };
