import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { getT } from "@/lib/i18n-server";

/**
 * Mirrors `ReportController::index` — one generic catalogue over SRS 32's
 * eighteen reports. Titles/descriptions come from the API in English; when
 * report.php has a translated entry for the report's own key it's preferred,
 * falling back to the API's own text for anything not yet catalogued there.
 */
export default async function ReportsPage() {
  const [reports, t] = await Promise.all([
    apiFetch("/reports"),
    getT("report"),
  ]);

  function translated(key, path, fallback) {
    const value = t(`${key}.${path}`);
    return value === `${key}.${path}` ? fallback : value;
  }

  const groups = new Map();
  for (const report of reports) {
    const key = report.group ?? "other";
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(report);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("reports") }]}
        title={t("reports")}
        description={t("page_description")}
      />

      {reports.length === 0 ? (
        <EmptyState title={t("no_reports")} description={t("no_reports_hint")} />
      ) : (
        <div className="flex flex-col gap-8">
          {[...groups.entries()].map(([group, groupReports]) => (
            <section key={group} className="flex flex-col gap-3">
              <h2 className="text-sm font-semibold text-foreground-muted uppercase tracking-wide">
                {(() => {
                  const label = t(`groups.${group}`);
                  return label === `groups.${group}` ? group : label;
                })()}
              </h2>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {groupReports.map((report) => (
                  <Link key={report.key} href={`/reports/${report.key}`}>
                    <Card className="h-full transition-shadow hover:shadow-sm">
                      <CardBody>
                        <h3 className="text-sm font-semibold text-foreground">{translated(report.key, "title", report.title)}</h3>
                        <p className="mt-1 text-xs text-foreground-muted">{translated(report.key, "description", report.description)}</p>
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
