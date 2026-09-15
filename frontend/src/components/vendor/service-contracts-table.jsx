"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";

const SCOPE_OPTIONS = [
  { value: "1", label: "Expiring soon" },
  { value: "", label: "All contracts" },
];

/** Mirrors `contracts/index.blade.php`. */
function ServiceContractsTable({ contracts, meta, page, expiring }) {
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page) });
    const expiringValue = next.expiring ?? expiring;
    if (expiringValue) params.set("expiring", expiringValue);
    router.push(`/vendors/service-contracts?${params.toString()}`);
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
            key: "contract_number",
            header: "Contract",
            render: (row) => (
              <Link href={`/vendors/service-contracts/${row.id}`} className="font-medium text-brand hover:underline">
                {row.contract_number}
                <div className="text-xs font-normal text-foreground-muted">{row.contract_type}</div>
              </Link>
            ),
          },
          { key: "vendor", header: "Vendor", render: (row) => row.vendor?.name ?? "—" },
          {
            key: "scope",
            header: "Scope",
            render: (row) => row.asset?.asset_code ?? row.factory?.name ?? "Several machines",
          },
          { key: "end_date", header: "Ends", render: (row) => row.end_date },
          { key: "value", header: "Value", align: "right", render: (row) => (row.value === null ? "—" : row.value) },
          { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
        ]}
        rows={contracts}
        rowKey={(row) => row.id}
        emptyTitle={expiring ? "Nothing expiring soon." : "No service contracts recorded."}
        rowActions={(row) => [{ label: "View", onSelect: () => router.push(`/vendors/service-contracts/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { ServiceContractsTable };
