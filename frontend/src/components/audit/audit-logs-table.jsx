"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const ACTION_TONE = {
  DELETED: "danger",
  LOGIN_FAILED: "danger",
  SECURITY_EVENT: "danger",
  CREATED: "success",
  COST_CHANGED: "warning",
  PERMISSION_CHANGED: "warning",
};

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns the column definitions; the server page just hands
 * over the current page of `logs` as plain data.
 */
function AuditLogsTable({ logs, meta, filters }) {
  const t = useT("audit");
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
          header: t("when"),
          render: (log) => (
            <Link href={`/audit-logs/${log.id}`} className="text-brand hover:underline">
              <FormattedDateTime value={log.created_at} />
            </Link>
          ),
        },
        {
          key: "who",
          header: t("who"),
          // A row written by the scheduler has no user — "System" is more honest than a blank cell.
          render: (log) => log.user?.name ?? t("system"),
        },
        {
          key: "action",
          header: t("action"),
          render: (log) => <Badge variant={ACTION_TONE[log.action] ?? "neutral"}>{t(`actions.${log.action}`)}</Badge>,
        },
        {
          key: "entity",
          header: t("entity"),
          render: (log) => (
            <div>
              {log.entity_label ?? "—"}
              <div className="text-xs text-foreground-muted">{log.entity_type}</div>
            </div>
          ),
        },
        {
          key: "context",
          header: t("context"),
          render: (log) => t(`contexts.${log.context}`),
        },
      ]}
      rows={logs}
      rowKey={(log) => log.id}
      emptyTitle={t("no_entries")}
      emptyDescription={t("no_entries_hint")}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={navigate}
    />
  );
}

export { AuditLogsTable };
