import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { formatStatus } from "@/components/ui/status-badge";

/** Mirrors `ReportController::index` — one generic catalogue over SRS 32's eighteen reports. */
export default async function ReportsPage() {
  const reports = await apiFetch("/reports");

  const groups = new Map();
  for (const report of reports) {
    const key = report.group ?? "other";
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(report);
  }

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Reports" }]} title="Reports" description="What's running, what it costs, and how the fleet is holding up." />

      {reports.length === 0 ? (
        <EmptyState title="No reports available" description="Nothing in your role's catalogue yet." />
      ) : (
        <div className="flex flex-col gap-8">
          {[...groups.entries()].map(([group, groupReports]) => (
            <section key={group} className="flex flex-col gap-3">
              <h2 className="text-sm font-semibold text-foreground-muted uppercase tracking-wide">{formatStatus(group)}</h2>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {groupReports.map((report) => (
                  <Link key={report.key} href={`/reports/${report.key}`}>
                    <Card className="h-full transition-shadow hover:shadow-sm">
                      <CardBody>
                        <h3 className="text-sm font-semibold text-foreground">{report.title}</h3>
                        <p className="mt-1 text-xs text-foreground-muted">{report.description}</p>
                      </CardBody>
                    </Card>
                  </Link>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </>
  );
}
