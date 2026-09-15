import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { EmptyState } from "@/components/ui/empty-state";

/** Mirrors `ReportJobController::index` — this caller's own requested exports, never anyone else's. */
export default async function ReportJobsPage() {
  const jobs = await apiFetch("/report-jobs?per_page=50", { includeMeta: true });

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Reports", href: "/reports" }, { label: "My exports" }]} title="My exports" />

      {jobs.data.length === 0 ? (
        <EmptyState title="No exports yet" description="Requested reports appear here once they're ready." />
      ) : (
        <div className="overflow-hidden rounded-sm border border-border">
          <table className="w-full text-sm">
            <thead className="bg-surface-muted text-xs font-medium text-foreground-muted">
              <tr>
                <th className="px-4 py-3 text-left">Report</th>
                <th className="px-4 py-3 text-left">Format</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-right">Rows</th>
                <th className="px-4 py-3 text-left">Requested</th>
                <th className="px-4 py-3 text-right">Download</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {jobs.data.map((job) => (
                <tr key={job.id}>
                  <td className="px-4 py-3 text-foreground">{job.report_type}</td>
                  <td className="px-4 py-3 text-foreground-muted">{job.format}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={job.status} />
                  </td>
                  <td className="px-4 py-3 text-right tabular text-foreground-muted">{job.row_count ?? "—"}</td>
                  <td className="px-4 py-3 text-foreground-muted">
                    <FormattedDateTime value={job.created_at} mode="datetime" />
                  </td>
                  <td className="px-4 py-3 text-right">
                    {job.is_downloadable ? (
                      <a href={`/api/report-jobs/${job.id}/download`} className="font-medium text-brand hover:underline">
                        Download
                      </a>
                    ) : job.status === "FAILED" ? (
                      <span className="text-xs text-danger">{job.error_message ?? "Failed"}</span>
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
