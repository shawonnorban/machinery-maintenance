"use client";

import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

/** Own Client Component for the same reason as every other detail-page table this session: `DataTable`'s column `render` functions can't cross the Server→Client boundary. */
function HistoryTable({ history }) {
  const t = useT("work_order");

  return (
    <DataTable
      columns={[
        {
          key: "changed_at",
          header: t("changed_at"),
          render: (row) => <FormattedDateTime value={row.changed_at} />,
        },
        {
          key: "transition",
          header: t("transition"),
          render: (row) => (
            <span className="flex items-center gap-2">
              {row.from_status ? (
                <StatusBadge status={row.from_status} label={t(`status_${row.from_status.toLowerCase()}`)} />
              ) : (
                <span className="text-foreground-muted">—</span>
              )}
              <span className="text-foreground-muted">→</span>
              <StatusBadge status={row.to_status} label={t(`status_${row.to_status.toLowerCase()}`)} />
            </span>
          ),
        },
        { key: "changed_by", header: t("changed_by"), render: (row) => row.changed_by?.name ?? t("system") },
        { key: "reason", header: t("reason"), render: (row) => row.reason ?? "—" },
      ]}
      rows={history}
      rowKey={(row) => row.id}
      emptyTitle={t("no_status_changes")}
    />
  );
}

export { HistoryTable };
