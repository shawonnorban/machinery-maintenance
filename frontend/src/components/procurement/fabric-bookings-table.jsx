"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "CONFIRMED", "CONVERTED", "CANCELLED"];

/** Fabric held with a mill before there is a purchase order (spec §3). */
function FabricBookingsTable({ rows, meta, page, search, status }) {
  const t = useT("procurement");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/procurement/fabric-bookings"
      searchPlaceholder={t("booking_search_placeholder")}
      emptyTitle={t("no_bookings")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`booking_statuses.${s}`) }))]}
      columns={[
        {
          key: "booking_no",
          header: t("booking_no"),
          render: (row) => (
            <Link href={`/procurement/fabric-bookings/${row.id}`} className="font-medium text-brand hover:underline">
              {row.booking_no}
            </Link>
          ),
        },
        { key: "booking_date", header: t("booking_date"), render: (row) => <FormattedDateTime value={row.booking_date} mode="date" /> },
        { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
        { key: "style", header: t("style"), render: (row) => row.style?.style_no ?? "—" },
        { key: "required_date", header: t("required_date"), render: (row) => <FormattedDateTime value={row.required_date} mode="date" /> },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`booking_statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { FabricBookingsTable };
