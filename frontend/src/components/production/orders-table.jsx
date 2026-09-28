"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["CONFIRMED", "IN_PROGRESS", "CLOSED", "CANCELLED"];

/** The buyer order book (Master Data spec §6) — a minimal one, by design. */
function OrdersTable({ rows, meta, page, search, status }) {
  const t = useT("production");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/production/orders"
      searchPlaceholder={t("search_placeholder")}
      emptyTitle={t("no_orders")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
      columns={[
        {
          key: "order_no",
          header: t("order_no"),
          render: (row) => (
            <Link href={`/production/orders/${row.id}`} className="font-medium text-brand hover:underline">
              {row.order_no}
            </Link>
          ),
        },
        { key: "buyer", header: t("buyer"), render: (row) => row.buyer },
        { key: "order_qty", header: t("order_qty"), align: "right", render: (row) => formatNumber(row.order_qty, { maximumFractionDigits: 2 }) },
        { key: "style_count", header: t("styles"), align: "right", render: (row) => row.style_count ?? "—" },
        { key: "ship_date", header: t("ship_date"), render: (row) => <FormattedDateTime value={row.ship_date} mode="date" /> },
        { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { OrdersTable };
