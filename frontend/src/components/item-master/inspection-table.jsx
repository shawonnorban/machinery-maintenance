"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["UNDER_INSPECTION", "AVAILABLE", "DAMAGED", "RETURNED"];

/** Mirrors `FabricRollApiController::index` — defaults to the queue, but the full history is one filter away. */
function InspectionTable({ rows, meta, page, status }) {
  const t = useT("inspection");
  const tc = useT("common");
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page), status: next.status ?? status });
    router.push(`/item-master/inspection?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="w-full sm:max-w-[240px]">
        <Select
          options={[{ value: "", label: t("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: t(`statuses.${s}`) }))]}
          value={status}
          onValueChange={(value) => navigate({ status: value, page: 1 })}
        />
      </div>

      <DataTable
        columns={[
          {
            key: "roll_no",
            header: t("roll_no"),
            render: (row) => (
              <Link href={`/item-master/inspection/${row.id}`} className="font-medium text-brand hover:underline">
                {row.roll_no}
              </Link>
            ),
          },
          { key: "item", header: t("item"), render: (row) => (row.item ? `${row.item.name} (${row.item.item_code})` : "—") },
          { key: "goods_receipt", header: t("goods_receipt"), render: (row) => row.goods_receipt?.grn_no ?? "—" },
          { key: "shade", header: t("shade"), render: (row) => row.shade ?? "—" },
          { key: "nominal_width", header: t("nominal_width"), align: "right", render: (row) => (row.nominal_width === null ? "—" : formatNumber(row.nominal_width, { maximumFractionDigits: 2 })) },
          { key: "net_length", header: t("net_length"), align: "right", render: (row) => (row.net_length === null ? "—" : formatNumber(row.net_length, { maximumFractionDigits: 2 })) },
          { key: "net_weight", header: t("net_weight"), align: "right", render: (row) => (row.net_weight === null ? "—" : formatNumber(row.net_weight, { maximumFractionDigits: 2 })) },
          { key: "received_date", header: t("received_date"), render: (row) => <FormattedDateTime value={row.received_date} mode="date" /> },
          { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`statuses.${row.status}`)} /> },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_rolls_pending")}
        rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/item-master/inspection/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { InspectionTable };
