"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "PENDING_APPROVAL", "APPROVED", "DISPATCHED", "REJECTED", "CANCELLED"];

/** Goods going back to the supplier (Procurement spec §12). */
function PurchaseReturnsTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/returns"
      searchPlaceholder={t("return_search_placeholder")}
      emptyTitle={t("no_returns")}
      statusOptions={[
        { value: "", label: t("all_statuses") },
        ...STATUSES.map((value) => ({ value, label: t(`return_statuses.${value}`) })),
      ]}
      columns={[
        {
          key: "return_no",
          header: t("return_no"),
          render: (row) => (
            <Link href={`/procurement/returns/${row.id}`} className="font-medium text-brand hover:underline">
              {row.return_no}
            </Link>
          ),
        },
        { key: "return_date", header: t("return_date"), render: (row) => <FormattedDateTime value={row.return_date} mode="date" /> },
        { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
        { key: "goods_receipt", header: t("goods_receipt"), render: (row) => row.goods_receipt?.grn_no ?? "—" },
        { key: "reason", header: t("reason"), render: (row) => t(`return_reasons.${row.reason}`) },
        {
          key: "debit_note_amount",
          header: t("debit_note_amount"),
          align: "right",
          render: (row) => `${formatNumber(row.debit_note_amount, { maximumFractionDigits: 2 })} ${row.currency}`,
        },
        { key: "debit_note_no", header: t("debit_note_no"), render: (row) => row.debit_note_no ?? "—" },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`return_statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { PurchaseReturnsTable };
