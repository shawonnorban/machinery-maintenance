import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { AuditFilters } from "@/components/audit/audit-filters";
import { AuditLogsTable } from "@/components/audit/audit-logs-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `AuditLogController::index` (SRS 34) — read-only, append-only. */
export default async function AuditLogsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const filters = {
    action: params.action ?? "",
    entity_type: params.entity_type ?? "",
    user_id: params.user_id ?? "",
    from: params.from ?? "",
    to: params.to ?? "",
  };

  const query = new URLSearchParams({ page: String(page) });
  for (const [key, value] of Object.entries(filters)) {
    if (value) query.set(key, value);
  }

  const [logs, t] = await Promise.all([
    apiFetch(`/audit-logs?${query.toString()}`, { includeMeta: true }),
    getT("audit"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("audit_log") }]} title={t("audit_log")} description={t("append_only")} />

      <div className="mb-4">
        <AuditFilters
          actions={logs.meta.filters.actions}
          entityTypes={logs.meta.filters.entity_types}
          users={logs.meta.filters.users}
          filters={filters}
        />
      </div>

      <AuditLogsTable logs={logs.data} meta={logs.meta} filters={filters} />
    </>
  );
}
