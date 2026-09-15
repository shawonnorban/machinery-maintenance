"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Select } from "@/components/ui/select";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "REQUESTED", label: "Requested" },
  { value: "APPROVED", label: "Approved" },
  { value: "IN_TRANSIT", label: "In transit" },
  { value: "RECEIVED", label: "Received" },
  { value: "REJECTED", label: "Rejected" },
];

function TransfersTable({ transfers, meta, page, status }) {
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page) });
    if (next.status ?? status) params.set("status", next.status ?? status);
    router.push(`/inventory/transfers?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="w-full max-w-[200px]">
        <Select options={STATUS_OPTIONS} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
      </div>

      <DataTable
        columns={[
          {
            key: "transfer_number",
            header: "Transfer",
            render: (row) => (
              <Link href={`/inventory/transfers/${row.id}`} className="font-medium text-brand hover:underline">
                {row.transfer_number}
              </Link>
            ),
          },
          {
            key: "route",
            header: "Route",
            render: (row) => (
              <span className="flex items-center gap-2">
                {row.from_factory?.name ?? "—"}
                <span className="text-foreground-muted">→</span>
                {row.to_factory?.name ?? "—"}
              </span>
            ),
          },
          { key: "item_count", header: "Items", align: "right", render: (row) => row.item_count ?? "—" },
          { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
          {
            key: "created_at",
            header: "Requested",
            render: (row) => <FormattedDateTime value={row.created_at} mode="date" />,
          },
        ]}
        rows={transfers}
        rowKey={(row) => row.id}
        emptyTitle="No transfers found."
        rowActions={(row) => [{ label: "View", onSelect: () => router.push(`/inventory/transfers/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { TransfersTable };
