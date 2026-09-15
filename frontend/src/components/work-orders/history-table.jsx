"use client";

import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

/** Own Client Component for the same reason as every other detail-page table this session: `DataTable`'s column `render` functions can't cross the Server→Client boundary. */
function HistoryTable({ history }) {
  return (
    <DataTable
      columns={[
        {
          key: "changed_at",
          header: "Changed at",
          render: (row) => <FormattedDateTime value={row.changed_at} />,
        },
        {
          key: "transition",
          header: "Transition",
          render: (row) => (
            <span className="flex items-center gap-2">
              {row.from_status ? <StatusBadge status={row.from_status} /> : <span className="text-foreground-muted">—</span>}
              <span className="text-foreground-muted">→</span>
              <StatusBadge status={row.to_status} />
            </span>
          ),
        },
        { key: "changed_by", header: "Changed by", render: (row) => row.changed_by?.name ?? "System" },
        { key: "reason", header: "Reason", render: (row) => row.reason ?? "—" },
      ]}
      rows={history}
      rowKey={(row) => row.id}
      emptyTitle="No status changes recorded yet."
    />
  );
}

export { HistoryTable };
