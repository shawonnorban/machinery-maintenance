"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "PENDING_APPROVAL", "APPROVED", "REJECTED", "PARTIALLY_RECEIVED", "RECEIVED", "CLOSED", "CANCELLED"];

function OrdersTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/orders"
      searchPlaceholder={t("search_placeholder")}
      emptyTitle={t("no_documents")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
      columns={[
        {
          key: "po_no",
          header: t("number"),
          render: (row) => (
            <Link href={`/procurement/orders/${row.id}`} className="font-medium text-brand hover:underline">
              {row.po_no}
              {row.revision_no > 0 ? <span className="ml-1 text-xs text-foreground-muted">{t("revision_no", { number: row.revision_no })}</span> : null}
            </Link>
          ),
        },
        { key: "po_date", header: t("date"), render: (row) => <FormattedDateTime value={row.po_date} mode="date" /> },
        { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
        { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        { key: "subtotal", header: t("subtotal"), align: "right", render: (row) => `${formatNumber(row.subtotal, { maximumFractionDigits: 2 })} ${row.currency}` },
        { key: "delivery_date", header: t("delivery_date"), render: (row) => (row.delivery_date ? <FormattedDateTime value={row.delivery_date} mode="date" /> : "—") },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { OrdersTable };
