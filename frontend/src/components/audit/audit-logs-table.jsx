"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const ACTION_TONE = {
  DELETED: "danger",
  LOGIN_FAILED: "danger",
  SECURITY_EVENT: "danger",
  CREATED: "success",
  COST_CHANGED: "warning",
  PERMISSION_CHANGED: "warning",
};

const CONTEXT_LABEL = {
  UI: "Web",
  API: "API",
  JOB: "Scheduled job",
  CONSOLE: "Console",
  IMPORT: "Import",
  WEBHOOK: "Webhook",
};

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns the column definitions; the server page just hands
 * over the current page of `logs` as plain data.
 */
function AuditLogsTable({ logs, meta, filters }) {
  const router = useRouter();

  function navigate(page) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value) params.set(key, value);
    }
    params.set("page", String(page));
    router.push(`/audit-logs?${params.toString()}`);
  }

  return (
    <DataTable
      columns={[
        {
          key: "created_at",
          header: "When",
          render: (log) => (
            <Link href={`/audit-logs/${log.id}`} className="text-brand hover:underline">
              <FormattedDateTime value={log.created_at} />
            </Link>
          ),
        },
        {
          key: "who",
          header: "Who",
          // A row written by the scheduler has no user — "System" is more honest than a blank cell.
          render: (log) => log.user?.name ?? "System",
        },
        {
          key: "action",
          header: "Action",
          render: (log) => <Badge variant={ACTION_TONE[log.action] ?? "neutral"}>{log.action}</Badge>,
        },
        {
          key: "entity",
          header: "Entity",
          render: (log) => (
            <div>
              {log.entity_label ?? "—"}
              <div className="text-xs text-foreground-muted">{log.entity_type}</div>
            </div>
          ),
        },
        {
          key: "context",
          header: "Context",
          render: (log) => CONTEXT_LABEL[log.context] ?? log.context,
        },
      ]}
      rows={logs}
      rowKey={(log) => log.id}
      emptyTitle="No entries."
      emptyDescription="Nothing matches this filter yet."
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={navigate}
    />
  );
}

export { AuditLogsTable };
