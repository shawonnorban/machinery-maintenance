import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { ReportFilters } from "@/components/reports/report-filters";
import { ExportButton } from "@/components/reports/export-button";
import { getT } from "@/lib/i18n-server";
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
  if (sp.asset_id) query.asset_id = sp.asset_id;
  if (sp.search) query.search = sp.search;
  if (sp.location_id) query.location_id = sp.location_id;
  if (sp.manufacturer_id) query.manufacturer_id = sp.manufacturer_id;

  const search = new URLSearchParams(query);
  const [preview, t] = await Promise.all([
    apiFetch(`/reports/${key}?${search.toString()}`),
    getT("report"),
  ]);

  function translated(path, fallback) {
    const value = t(`${key}.${path}`);
    return value === `${key}.${path}` ? fallback : value;
  }

  function columnLabel(columnKey, fallback) {
    const value = t(`columns.${columnKey}`);
    return value === `columns.${columnKey}` ? fallback : value;
  }

  const title = translated("title", preview.report.title);
  const description = translated("description", preview.report.description);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("reports"), href: "/reports" }, { label: title }]} title={title} description={description} />

      <div className="flex flex-col gap-4">
        <Card>
          <CardBody className="flex flex-wrap items-end justify-between gap-4">
            <ReportFilters
              reportKey={key}
              filters={preview.report.filters}
              factories={preview.factories}
              assets={preview.assets}
              locations={preview.locations}
              manufacturers={preview.manufacturers}
              from={sp.from ?? preview.query.from.slice(0, 10)}
              to={sp.to ?? preview.query.to.slice(0, 10)}
              factoryId={sp.factory_id ?? ""}
              status={sp.status ?? ""}
              assetId={sp.asset_id ?? ""}
              search={sp.search ?? ""}
              locationId={sp.location_id ?? ""}
              manufacturerId={sp.manufacturer_id ?? ""}
            />
            <ExportButton reportKey={key} query={query} formats={preview.formats} action={requestExport} />
          </CardBody>
        </Card>

        {preview.truncated ? (
          <Alert variant="warning">{t("preview_note", { count: preview.rows.length })}</Alert>
        ) : null}

        <Card>
          <CardBody>
            {preview.rows.length === 0 ? (
              <p className="text-sm text-foreground-muted">{t("no_rows")}</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border text-xs font-medium text-foreground-muted">
                    <tr>
                      {Object.entries(preview.columns).map(([columnKey, column]) => (
                        <th key={columnKey} className={`px-3 py-2 ${column.numeric ? "text-right" : "text-left"}`}>
                          {columnLabel(columnKey, column.label)}
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
