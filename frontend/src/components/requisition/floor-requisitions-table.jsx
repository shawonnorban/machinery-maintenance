"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

// FloorRequisition::STATUSES — the store's own states included, since the
// list is the screen a storekeeper works the queue from.
const STATUSES = [
  "DRAFT",
  "SUBMITTED",
  "UNDER_REVIEW",
  "PARTIALLY_FULFILLED",
  "FULLY_FULFILLED",
  "REJECTED",
  "CLOSED",
  "CANCELLED",
];

function FloorRequisitionsTable({ rows, meta, page, search, status }) {
  const t = useT("requisition");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/requisitions"
      searchPlaceholder={t("search_placeholder")}
      emptyTitle={t("no_documents")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
      columns={[
        {
          key: "fr_no",
          header: t("number"),
          render: (row) => (
            <Link href={`/requisitions/${row.id}`} className="font-medium text-brand hover:underline">
              {row.fr_no}
            </Link>
          ),
        },
        { key: "fr_date", header: t("date"), render: (row) => <FormattedDateTime value={row.fr_date} mode="date" /> },
        { key: "target_item_department", header: t("item_department"), render: (row) => row.target_item_department?.name ?? "—" },
        { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
        { key: "priority", header: t("priority"), render: (row) => t(`priorities.${row.priority}`) },
        { key: "line_count", header: t("lines"), align: "right", render: (row) => row.line_count ?? "—" },
        { key: "requester", header: t("requested_by"), render: (row) => row.requester?.name ?? "—" },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { FloorRequisitionsTable };
