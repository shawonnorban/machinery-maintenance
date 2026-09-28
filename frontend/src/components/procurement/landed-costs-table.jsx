"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "POSTED", "CANCELLED"];

/** Freight, duty and clearing, waiting to be spread over a consignment (spec §11). */
function LandedCostsTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/landed-costs"
      searchPlaceholder={t("landed_cost_search_placeholder")}
      emptyTitle={t("no_landed_costs")}
      statusOptions={[
        { value: "", label: t("all_statuses") },
        ...STATUSES.map((value) => ({ value, label: t(`landed_cost_statuses.${value}`) })),
      ]}
      columns={[
        {
          key: "voucher_no",
          header: t("voucher_no"),
          render: (row) => (
            <Link href={`/procurement/landed-costs/${row.id}`} className="font-medium text-brand hover:underline">
              {row.voucher_no}
            </Link>
          ),
        },
        { key: "voucher_date", header: t("voucher_date"), render: (row) => <FormattedDateTime value={row.voucher_date} mode="date" /> },
        { key: "basis", header: t("basis"), render: (row) => t(`bases.${row.basis}`) },
        { key: "receipt_count", header: t("receipts"), align: "right", render: (row) => row.receipt_count ?? "—" },
        {
          key: "total_charges",
          header: t("total_charges"),
          align: "right",
          render: (row) => `${formatNumber(row.total_charges, { maximumFractionDigits: 2 })} ${row.currency}`,
        },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`landed_cost_statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { LandedCostsTable };
