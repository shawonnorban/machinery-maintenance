"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Select } from "@/components/ui/select";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

function TransfersTable({ transfers, meta, page, status }) {
  const t = useT("inventory");
  const tc = useT("common");
  const router = useRouter();

  const statusOptions = [
    { value: "", label: t("all_statuses") },
    { value: "REQUESTED", label: t("transfer_status_requested") },
    { value: "APPROVED", label: t("transfer_status_approved") },
    { value: "IN_TRANSIT", label: t("transfer_status_in_transit") },
    { value: "RECEIVED", label: t("transfer_status_received") },
    { value: "REJECTED", label: t("transfer_status_rejected") },
  ];

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page) });
    if (next.status ?? status) params.set("status", next.status ?? status);
    router.push(`/inventory/transfers?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="w-full max-w-[200px]">
        <Select options={statusOptions} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
      </div>

      <DataTable
        columns={[
          {
            key: "transfer_number",
            header: t("transfer"),
            render: (row) => (
              <Link href={`/inventory/transfers/${row.id}`} className="font-medium text-brand hover:underline">
                {row.transfer_number}
              </Link>
            ),
          },
          {
            key: "route",
            header: t("route"),
            render: (row) => (
              <span className="flex items-center gap-2">
                {row.from_factory?.name ?? "—"}
                <span className="text-foreground-muted">→</span>
                {row.to_factory?.name ?? "—"}
              </span>
            ),
          },
          { key: "item_count", header: t("items"), align: "right", render: (row) => row.item_count ?? "—" },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={t(`transfer_status_${row.status?.toLowerCase()}`)} />,
          },
          {
            key: "created_at",
            header: t("requested"),
            render: (row) => <FormattedDateTime value={row.created_at} mode="date" />,
          },
        ]}
        rows={transfers}
        rowKey={(row) => row.id}
        emptyTitle={t("no_transfers_found")}
        rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/inventory/transfers/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { TransfersTable };
