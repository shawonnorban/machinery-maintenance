"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { SearchingData } from "@/components/erp/searching-data";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { useT } from "@/lib/i18n";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { refOf } from "@/lib/ref-path";

const TYPE_KEYS = { MANUFACTURER: "type_manufacturer", EXTENDED: "type_extended", SERVICE: "type_service_warranty" };

/** Mirrors `warranties/index.blade.php` — the expiring/all pill toggle and the same six columns, row tinted when cover runs out within 30 days. */
function WarrantiesTable({ warranties, meta, page, expiring, addHref }) {
  const t = useT("vendor");
  const tc = useT("common");
  const router = useRouter();

  const scopeOptions = [
    { value: "1", label: t("expiring_soon") },
    { value: "", label: t("all_warranties") },
  ];

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page) });
    const expiringValue = next.expiring ?? expiring;
    if (expiringValue) params.set("expiring", expiringValue);
    router.push(`/vendors/warranties?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <SearchingData
        fields={[{ name: "expiring", label: t("show"), type: "select", options: scopeOptions }]}
        values={{ expiring: expiring ? "1" : "" }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "warranty_number",
            header: t("warranty_no"),
            render: (row) => (
              <Link href={`/vendors/warranties/${refOf(row, "warranty_number")}`} className="font-medium text-brand hover:underline">
                {row.warranty_number}
              </Link>
            ),
          },
          {
            key: "asset",
            header: t("asset"),
            render: (row) => (
              <Link href={`/vendors/warranties/${refOf(row, "warranty_number")}`} className="font-medium text-brand hover:underline">
                {row.asset?.asset_code}
                <div className="text-xs font-normal text-foreground-muted">{row.asset?.name}</div>
              </Link>
            ),
          },
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? t("unnamed_vendor") },
          { key: "warranty_type", header: t("type"), render: (row) => t(TYPE_KEYS[row.warranty_type] ?? row.warranty_type) },
          { key: "end_date", header: t("end_date"), render: (row) => <FormattedDateTime value={row.end_date} mode="date" /> },
          {
            key: "days_remaining",
            header: t("days_remaining"),
            align: "right",
            render: (row) => (row.days_remaining < 0 ? t("expired_days_ago", { days: Math.abs(row.days_remaining) }) : row.days_remaining),
          },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={t(`warranty_status_${row.status?.toLowerCase()}`)} />,
          },
        ]}
        rows={warranties}
        rowKey={(row) => row.id}
        emptyTitle={expiring ? t("nothing_expiring") : t("no_warranties_found")}
        rowActions={(row) => [{ icon: "view", label: tc("view"), onSelect: () => router.push(`/vendors/warranties/${refOf(row, "warranty_number")}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { WarrantiesTable };
