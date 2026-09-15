import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { ReportFilters } from "@/components/reports/report-filters";
import { ExportButton } from "@/components/reports/export-button";
import { requestExport } from "./actions";

/** Mirrors `ReportController::run` — the same capped preview the export button is asking for the full version of. */
export default async function ReportDetailPage({ params, searchParams }) {
  const { key } = await params;
  const sp = await searchParams;

  const query = {};
  if (sp.from) query.from = sp.from;
  if (sp.to) query.to = sp.to;
  if (sp.factory_id) query.factory_id = sp.factory_id;
  if (sp.status) query.status = sp.status;

  const search = new URLSearchParams(query);
  const preview = await apiFetch(`/reports/${key}?${search.toString()}`);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Reports", href: "/reports" }, { label: preview.report.title }]} title={preview.report.title} description={preview.report.description} />

      <div className="flex flex-col gap-4">
        <Card>
          <CardBody className="flex flex-wrap items-end justify-between gap-4">
            <ReportFilters
              reportKey={key}
              filters={preview.report.filters}
              factories={preview.factories}
              from={sp.from ?? preview.query.from.slice(0, 10)}
              to={sp.to ?? preview.query.to.slice(0, 10)}
              factoryId={sp.factory_id ?? ""}
              status={sp.status ?? ""}
            />
            <ExportButton reportKey={key} query={query} formats={preview.formats} action={requestExport} />
          </CardBody>
        </Card>

        {preview.truncated ? (
          <Alert variant="warning">Showing the first {preview.rows.length} rows — export the full report to see everything.</Alert>
        ) : null}

        <Card>
          <CardBody>
            {preview.rows.length === 0 ? (
              <p className="text-sm text-foreground-muted">Nothing matches these filters.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border text-xs font-medium text-foreground-muted">
                    <tr>
                      {Object.entries(preview.columns).map(([columnKey, column]) => (
                        <th key={columnKey} className={`px-3 py-2 ${column.numeric ? "text-right" : "text-left"}`}>
                          {column.label}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {preview.rows.map((row, index) => (
                      <tr key={index}>
                        {Object.entries(preview.columns).map(([columnKey, column]) => (
                          <td key={columnKey} className={`px-3 py-2 text-foreground ${column.numeric ? "text-right tabular" : ""}`}>
                            {row[columnKey] ?? "—"}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </>
  );
}
