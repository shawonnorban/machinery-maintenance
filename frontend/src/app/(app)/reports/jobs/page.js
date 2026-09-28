import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { EmptyState } from "@/components/ui/empty-state";
import { getT } from "@/lib/i18n-server";

/** Mirrors `ReportJobController::index` — this caller's own requested exports, never anyone else's. */
export default async function ReportJobsPage() {
  const [jobs, t] = await Promise.all([
    apiFetch("/report-jobs?per_page=50", { includeMeta: true }),
    getT("report"),
  ]);

  function reportTitle(key) {
    const value = t(`${key}.title`);
    return value === `${key}.title` ? key : value;
  }

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("reports"), href: "/reports" }, { label: t("my_exports") }]} title={t("my_exports")} />

      {jobs.data.length === 0 ? (
        <EmptyState title={t("job.none")} description={t("job.none_hint")} />
      ) : (
        <div className="overflow-hidden rounded-sm border border-border">
          <table className="w-full text-sm">
            <thead className="bg-surface-muted text-xs font-medium text-foreground-muted">
              <tr>
                <th className="px-4 py-3 text-left">{t("report")}</th>
                <th className="px-4 py-3 text-left">{t("job.format")}</th>
                <th className="px-4 py-3 text-left">{t("job.status")}</th>
                <th className="px-4 py-3 text-right">{t("job.rows")}</th>
                <th className="px-4 py-3 text-left">{t("job.requested_at")}</th>
                <th className="px-4 py-3 text-right">{t("job.download")}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {jobs.data.map((job) => (
                <tr key={job.id}>
                  <td className="px-4 py-3 text-foreground">{reportTitle(job.report_type)}</td>
                  <td className="px-4 py-3 text-foreground-muted">{job.format}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={job.status} label={t(`statuses.${job.status}`)} />
                  </td>
                  <td className="px-4 py-3 text-right tabular text-foreground-muted">{job.row_count ?? "—"}</td>
                  <td className="px-4 py-3 text-foreground-muted">
                    <FormattedDateTime value={job.created_at} mode="datetime" />
                  </td>
                  <td className="px-4 py-3 text-right">
                    {job.is_downloadable ? (
                      <a href={`/api/report-jobs/${job.id}/download`} className="font-medium text-brand hover:underline">
                        {t("job.download")}
                      </a>
                    ) : job.status === "FAILED" ? (
                      <span className="text-xs text-danger">{job.error_message ?? t("statuses.FAILED")}</span>
                    ) : (
                      <span className="text-xs text-foreground-muted">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
