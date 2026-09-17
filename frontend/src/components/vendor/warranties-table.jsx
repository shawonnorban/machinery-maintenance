"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { useT } from "@/lib/i18n";

const TYPE_KEYS = { MANUFACTURER: "type_manufacturer", EXTENDED: "type_extended", SERVICE: "type_service_warranty" };

/** Mirrors `warranties/index.blade.php` — the expiring/all pill toggle and the same six columns, row tinted when cover runs out within 30 days. */
function WarrantiesTable({ warranties, meta, page, expiring }) {
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
      <div className="w-full max-w-[200px]">
        <Select
          options={scopeOptions}
          value={expiring ? "1" : ""}
          onValueChange={(value) => navigate({ expiring: value, page: 1 })}
        />
      </div>

      <DataTable
        columns={[
          {
            key: "asset",
            header: t("asset"),
            render: (row) => (
              <Link href={`/vendors/warranties/${row.id}`} className="font-medium text-brand hover:underline">
                {row.asset?.asset_code}
                <div className="text-xs font-normal text-foreground-muted">{row.asset?.name}</div>
              </Link>
            ),
          },
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? t("unnamed_vendor") },
          { key: "warranty_type", header: t("type"), render: (row) => t(TYPE_KEYS[row.warranty_type] ?? row.warranty_type) },
          { key: "end_date", header: t("end_date"), render: (row) => row.end_date },
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
        rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/vendors/warranties/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { WarrantiesTable };
