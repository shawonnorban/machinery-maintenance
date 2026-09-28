"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "UNDER_INSPECTION", "COMPLETED", "CANCELLED"];

function ReceiptsTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/receipts"
      searchPlaceholder={t("search_placeholder")}
      emptyTitle={t("no_documents")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
      columns={[
        {
          key: "grn_no",
          header: t("number"),
          render: (row) => (
            <Link href={`/procurement/receipts/${row.id}`} className="font-medium text-brand hover:underline">
              {row.grn_no}
            </Link>
          ),
        },
        { key: "grn_date", header: t("date"), render: (row) => <FormattedDateTime value={row.grn_date} mode="date" /> },
        { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
        {
          key: "order",
          header: t("purchase_order"),
          render: (row) => (row.order ? <Link href={`/procurement/orders/${row.order.id}`} className="text-brand hover:underline">{row.order.po_no}</Link> : "—"),
        },
        { key: "store", header: t("store"), render: (row) => row.store?.name ?? "—" },
        { key: "gate_pass_no", header: t("gate_pass_no"), render: (row) => row.gate_pass_no ?? "—" },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { ReceiptsTable };
