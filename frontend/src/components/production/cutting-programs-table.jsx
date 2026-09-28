"use client";

import Link from "next/link";
import { DocumentsTable } from "@/components/procurement/documents-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["CONFIRMED", "IN_PROGRESS", "CLOSED", "CANCELLED"];

/** The cutting queue — the batch material is issued against. */
function CuttingProgramsTable({ rows, meta, page, search, status }) {
  const t = useT("production");

  return (
    <DocumentsTable
      rows={rows}
      meta={meta}
      page={page}
      search={search}
      status={status}
      basePath="/production/cutting-programs"
      searchPlaceholder={t("program_no")}
      emptyTitle={t("no_programs")}
      statusOptions={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
      columns={[
        {
          key: "program_no",
          header: t("program_no"),
          render: (row) => (
            <Link href={`/production/cutting-programs/${row.id}`} className="font-medium text-brand hover:underline">
              {row.program_no}
            </Link>
          ),
        },
        { key: "style", header: t("style"), render: (row) => row.style?.style_no ?? "—" },
        { key: "order", header: t("order"), render: (row) => row.style?.order?.order_no ?? "—" },
        { key: "buyer", header: t("buyer"), render: (row) => row.style?.order?.buyer ?? "—" },
        { key: "program_qty", header: t("program_qty"), align: "right", render: (row) => formatNumber(row.program_qty, { maximumFractionDigits: 2 }) },
        { key: "cutting_date", header: t("cutting_date"), render: (row) => <FormattedDateTime value={row.cutting_date} mode="date" /> },
        { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
      ]}
    />
  );
}

export { CuttingProgramsTable };
