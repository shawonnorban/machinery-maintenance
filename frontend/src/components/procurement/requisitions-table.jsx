"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "PENDING_APPROVAL", "APPROVED", "REJECTED", "PARTIALLY_CONVERTED", "FULLY_CONVERTED", "CANCELLED"];

function RequisitionsTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/requisitions"
      searchPlaceholder={t("search_placeholder")}
      emptyTitle={t("no_documents")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
      columns={[
        {
          key: "pr_no",
          header: t("number"),
          render: (row) => (
            <Link href={`/procurement/requisitions/${row.id}`} className="font-medium text-brand hover:underline">
              {row.pr_no}
            </Link>
          ),
        },
        { key: "pr_date", header: t("date"), render: (row) => <FormattedDateTime value={row.pr_date} mode="date" /> },
        { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
        { key: "priority", header: t("priority"), render: (row) => t(`priorities.${row.priority}`) },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        { key: "estimated_total", header: t("estimated_total"), align: "right", render: (row) => `${formatNumber(row.estimated_total, { maximumFractionDigits: 2 })} ${row.currency}` },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
        { key: "requester", header: t("requested_by"), render: (row) => row.requester?.name ?? "—" },
      ]}
    />
  );
}

export { RequisitionsTable };
