"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const OPEN_OPTIONS = [
  { value: "true", label: "Open only" },
  { value: "false", label: "All breakdowns" },
];
const PRIORITY_OPTIONS = [
  { value: "", label: "All priorities" },
  { value: "CRITICAL", label: "Critical" },
  { value: "HIGH", label: "High" },
  { value: "MEDIUM", label: "Medium" },
  { value: "LOW", label: "Low" },
];
const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

function BreakdownsTable({ breakdowns, meta, page, open, priority }) {
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      open: next.open ?? open,
      priority: next.priority ?? priority,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/breakdowns?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-3">
        <div className="w-full max-w-[180px]">
          <Select options={OPEN_OPTIONS} value={open} onValueChange={(value) => navigate({ open: value, page: 1 })} />
        </div>
        <div className="w-full max-w-[180px]">
          <Select options={PRIORITY_OPTIONS} value={priority} onValueChange={(value) => navigate({ priority: value, page: 1 })} placeholder="All priorities" />
        </div>
      </div>

      <DataTable
        columns={[
          {
            key: "breakdown_number",
            header: "Breakdown",
            render: (row) => (
              <Link href={`/breakdowns/${row.id}`} className="font-medium text-brand hover:underline">
                {row.breakdown_number}
              </Link>
            ),
          },
          {
            key: "asset",
            header: "Asset",
            render: (row) => (
              <div>
                {row.asset?.asset_code}
                <div className="text-xs text-foreground-muted">{row.asset?.name}</div>
              </div>
            ),
          },
          { key: "factory", header: "Factory", render: (row) => row.factory?.name ?? "—" },
          {
            key: "priority",
            header: "Priority",
            render: (row) => <Badge variant={PRIORITY_TONE[row.priority] ?? "neutral"}>{formatStatus(row.priority)}</Badge>,
          },
          { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
          {
            key: "reported_at",
            header: "Reported",
            render: (row) => <FormattedDateTime value={row.reported_at} />,
          },
        ]}
        rows={breakdowns}
        rowKey={(row) => row.id}
        emptyTitle={open === "true" ? "No open breakdowns." : "No breakdowns found."}
        emptyDescription={open === "true" ? "Every machine on these floors is running." : undefined}
        rowActions={(row) => [{ label: "View", onSelect: () => router.push(`/breakdowns/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { BreakdownsTable };
