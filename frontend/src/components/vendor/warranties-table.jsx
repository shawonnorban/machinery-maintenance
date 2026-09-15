"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";

const SCOPE_OPTIONS = [
  { value: "1", label: "Expiring soon" },
  { value: "", label: "All warranties" },
];

const TYPE_LABELS = { MANUFACTURER: "Manufacturer", EXTENDED: "Extended", SERVICE: "Service" };

/** Mirrors `warranties/index.blade.php` — the expiring/all pill toggle and the same six columns, row tinted when cover runs out within 30 days. */
function WarrantiesTable({ warranties, meta, page, expiring }) {
  const router = useRouter();

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
          options={SCOPE_OPTIONS}
          value={expiring ? "1" : ""}
          onValueChange={(value) => navigate({ expiring: value, page: 1 })}
        />
      </div>

      <DataTable
        columns={[
          {
            key: "asset",
            header: "Asset",
            render: (row) => (
              <Link href={`/vendors/warranties/${row.id}`} className="font-medium text-brand hover:underline">
                {row.asset?.asset_code}
                <div className="text-xs font-normal text-foreground-muted">{row.asset?.name}</div>
              </Link>
            ),
          },
          { key: "vendor", header: "Vendor", render: (row) => row.vendor?.name ?? "Unnamed vendor" },
          { key: "warranty_type", header: "Type", render: (row) => TYPE_LABELS[row.warranty_type] ?? row.warranty_type },
          { key: "end_date", header: "Ends", render: (row) => row.end_date },
          {
            key: "days_remaining",
            header: "Days left",
            align: "right",
            render: (row) => (row.days_remaining < 0 ? `Expired ${Math.abs(row.days_remaining)}d ago` : row.days_remaining),
          },
          { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
        ]}
        rows={warranties}
        rowKey={(row) => row.id}
        emptyTitle={expiring ? "Nothing expiring soon." : "No warranties recorded."}
        rowActions={(row) => [{ label: "View", onSelect: () => router.push(`/vendors/warranties/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { WarrantiesTable };
