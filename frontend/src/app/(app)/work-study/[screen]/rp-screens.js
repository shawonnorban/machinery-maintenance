import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { IeReports } from "@/components/workstudy/reports";
import * as actions from "./actions";

/*
 * Work Study → Reports (AnnotechERP 14/02): Work Study Reports — Records (the
 * report runs), IE Reports (the report set, its parameters and Preview) and
 * Preview (New report run).
 */

async function ieReports({ f, te, tab, canManage }) {
  const [opts, rows] = await Promise.all([
    apiFetch("/workstudy/reports/options"),
    apiFetch("/erp/ws-report-runs"),
  ]);
  const pick = [{ value: "", label: te("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "ie", label: f("tabs.ie"), icon: "FileText" },
    ...(canManage ? [{ key: "new", label: f("tabs.new"), icon: "Plus" }] : []),
  ];
  const fields = [
    {
      name: "report_key",
      label: f("run.report"),
      type: "select",
      required: true,
      span: 3,
      options: [
        ...pick,
        ...opts.reports.map((x) => ({
          value: x.key,
          label: f(`report.${x.key}`),
        })),
      ],
    },
    {
      name: "factory_id",
      label: f("run.unit"),
      type: "select",
      options: [...pick, ...opts.run.units],
    },
    {
      name: "buyer_id",
      label: f("run.buyer"),
      type: "select",
      options: [...pick, ...opts.run.buyers],
    },
    {
      name: "floor_id",
      label: f("run.floors"),
      type: "select",
      options: [...pick, ...opts.run.floors],
    },
    { name: "order_no", label: f("run.order") },
    {
      name: "production_date",
      label: f("run.date"),
      type: "date",
      required: true,
    },
    { name: "export_excel", label: f("run.excel"), type: "checkbox" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-report-runs", [
              "export_excel",
            ])}
            title={f("run.title")}
            description={f("run.hint")}
            submitLabel={f("run.save")}
            columns={3}
            successHref="/work-study/ie-reports?tab=records"
          />
        );
      if (tab === "ie")
        return <IeReports options={opts} act={actions.reportAction} />;
      return (
        <RecordsGrid
          exportName="work-study-report-runs"
          rows={rows.map((r) => ({
            ...r,
            category_label: r.category ? f(`category.${r.category}`) : null,
            rows_count: String(r.rows_count),
            excel_label: r.export_excel ? f("run.yes") : f("run.no"),
            export_excel: r.export_excel ? "1" : "0",
          }))}
          columns={[
            { key: "report", label: f("run.col.report") },
            { key: "category_label", label: f("run.col.category") },
            { key: "unit", label: f("run.col.unit") },
            {
              key: "production_date",
              label: f("run.col.date"),
              type: "date",
            },
            {
              key: "rows_count",
              label: f("run.col.rows"),
              type: "number",
              align: "right",
            },
            { key: "excel_label", label: f("run.col.excel") },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(null, "ws-report-runs", [
                    "export_excel",
                  ]),
                  title: te("edit"),
                  submitLabel: f("run.save"),
                  columns: 3,
                  refKey: "report",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-report-runs")
              : undefined
          }
        />
      );
    },
  };
}

const RP_SCREENS = { "ie-reports": ieReports };

export { RP_SCREENS };
