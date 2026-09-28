"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const STATUSES = ["ACTIVE", "SUSPENDED", "CANCELLED"];

/** Customs bond licences (Shared Systems spec §3.1). */
function BondLicencesTable({ rows, meta, page, search, status }) {
  const t = useT("compliance");
  const tc = useT("common");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/compliance/bond-licences"
      searchPlaceholder={t("licence_search_placeholder")}
      emptyTitle={t("no_licences")}
      statusOptions={[
        { value: "", label: tc("all") },
        ...STATUSES.map((value) => ({ value, label: t(`licence_statuses.${value}`) })),
      ]}
      columns={[
        {
          key: "licence_no",
          header: t("licence_no"),
          render: (row) => (
            <div className="flex flex-col">
              <Link href={`/compliance/bond-licences/${row.id}`} className="font-medium text-brand hover:underline">
                {row.licence_no}
              </Link>
              <span className="text-xs text-foreground-muted">{row.issuing_office}</span>
            </div>
          ),
        },
        { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
        {
          key: "period",
          header: t("period"),
          render: (row) => (
            <span className="flex items-center gap-1 text-sm">
              <FormattedDateTime value={row.valid_from} mode="date" />
              <span className="text-foreground-muted">→</span>
              <FormattedDateTime value={row.valid_to} mode="date" />
            </span>
          ),
        },
        { key: "entitlement_count", header: t("entitlements"), align: "right", render: (row) => row.entitlement_count ?? 0 },
        { key: "declaration_count", header: t("declarations"), align: "right", render: (row) => row.declaration_count ?? 0 },
        {
          key: "status",
          header: t("status"),
          render: (row) => (
            <span className="flex flex-wrap items-center gap-1">
              <StatusBadge status={row.status} label={t(`licence_statuses.${row.status}`)} />
              {/* Status alone cannot say whether it applies today. */}
              {row.status === "ACTIVE" && row.has_expired ? (
                <Badge variant="neutral">{t("licence_expired")}</Badge>
              ) : null}
              {row.is_live && row.days_to_expiry <= 60 ? (
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

export { BondLicencesTable };
