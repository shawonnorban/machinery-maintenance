import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import {
  TargetConfigSheet,
  ResultSheet,
} from "@/components/workstudy/section-profit";
import * as actions from "./actions";

/*
 * Work Study → Section Profitability (AnnotechERP 14/08): Section Profit &
 * Loss Setup — Records, the Income Target Config sheet and the New income
 * target form; Section Profit & Loss Entry — Records, the SecWise
 * Profit/Loss-Trans sheet and the New section result form.
 */

const spOptions = () => apiFetch("/workstudy/section-pl/options");
const n = (v) => (v === null || v === undefined ? null : String(Number(v)));

async function profitSetup({ f, c, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    spOptions(),
    apiFetch("/erp/ws-section-targets"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "config", label: f("tabs.cfg"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_cfg"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "company",
      label: f("company"),
      type: "select",
      required: true,
      options: [...pick, opts.company],
    },
    {
      name: "year",
      label: f("year"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.years.map((y) => ({ value: String(y), label: String(y) })),
      ],
    },
    {
      name: "month",
      label: f("month"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...Array.from({ length: 12 }, (_, i) => ({
          value: String(i + 1),
          label: c(`months.${i + 1}`),
        })),
      ],
    },
    {
      name: "section_id",
      label: f("cfg_new.section"),
      type: "select",
      required: true,
      options: [...pick, ...opts.sections],
    },
    { name: "sub_section", label: f("cfg_new.sub_section") },
    {
      name: "daily_target",
      label: f("cfg_new.daily_target"),
      type: "number",
      required: true,
    },
    {
      name: "daily_target_inhouse",
      label: f("cfg_new.inhouse"),
      type: "number",
    },
    {
      name: "daily_target_subcon",
      label: f("cfg_new.subcon"),
      type: "number",
    },
    {
      name: "avg_rate",
      label: f("cfg_new.avg_rate"),
      type: "number",
      required: true,
    },
    {
      name: "work_days",
      label: f("cfg_new.work_days"),
      type: "number",
      required: true,
    },
    { name: "oh_calculated", label: f("cfg_new.oh"), type: "number" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-section-targets", [])}
            title={f("cfg_new.title")}
            description={f("cfg_new.hint")}
            submitLabel={f("cfg_new.save")}
            columns={3}
            successHref="/work-study/sec-wise-profit-loss-config?tab=records"
          />
        );
      if (tab === "config")
        return (
          <TargetConfigSheet
            options={opts}
            act={actions.spAction}
            canManage={canManage}
            initialPeriod={
              typeof searchParams.period === "string"
                ? searchParams.period
                : undefined
            }
          />
        );
      return (
        <RecordsGrid
          exportName="section-income-targets"
          rows={rows.map((r) => ({
            ...r,
            year: String(r.year),
            month: String(r.month),
            ...Object.fromEntries(
              [
                "daily_target",
                "daily_target_inhouse",
                "daily_target_subcon",
                "avg_rate",
                "work_days",
                "target_income",
                "oh_calculated",
              ].map((k) => [k, n(r[k])]),
            ),
          }))}
          columns={[
            { key: "section", label: f("cfg.col.section") },
            { key: "sub_section", label: f("cfg.col.sub_section") },
            { key: "period_label", label: f("cfg.col.period") },
            ...[
              "daily_target",
              "daily_target_inhouse",
              "daily_target_subcon",
              "avg_rate",
              "work_days",
              "target_income",
              "oh_calculated",
            ].map((k) => ({
              key: k,
              label: f(`cfg.col.${k}`),
              type: "number",
              align: "right",
            })),
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(
                    null,
                    "ws-section-targets",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: f("cfg_new.save"),
                  columns: 3,
                  refKey: "section",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-section-targets")
              : undefined
          }
        />
      );
    },
  };
}

async function profitEntry({ f, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    spOptions(),
    apiFetch("/erp/ws-section-results"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "entry", label: f("tabs.trans"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_trans"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "company",
      label: f("company"),
      type: "select",
      required: true,
      options: [...pick, opts.company],
    },
    {
      name: "section_id",
      label: f("trans_new.section"),
      type: "select",
      required: true,
      options: [...pick, ...opts.sections],
    },
    {
      name: "result_date",
      label: f("trans_new.date"),
      type: "date",
      required: true,
    },
    ...[
      "income_target",
      "income",
      "prod_target",
      "prod_qty",
      "oh_cost",
      "mat_cost",
      "expense",
    ].map((k) => ({ name: k, label: f(`trans_new.${k}`), type: "number" })),
    {
      name: "comments",
      label: f("trans_new.comments"),
      type: "textarea",
      span: 3,
    },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-section-results", [])}
            title={f("trans_new.title")}
            description={f("trans_new.hint")}
            submitLabel={f("trans_new.save")}
            columns={3}
            successHref="/work-study/sec-wise-profit-loss-trans?tab=records"
          />
        );
      if (tab === "entry")
        return (
          <ResultSheet
            options={opts}
            act={actions.spAction}
            canManage={canManage}
            initialDate={
              typeof searchParams.date === "string"
                ? searchParams.date
                : undefined
            }
          />
        );
      return (
        <RecordsGrid
          exportName="section-results"
          rows={rows.map((r) => ({
            ...r,
            standing_label: f(`standing.${r.standing}`),
            ...Object.fromEntries(
              [
                "income_target",
                "income",
                "prod_target",
                "prod_qty",
                "oh_cost",
                "mat_cost",
                "expense",
                "total_cost",
                "profit",
              ].map((k) => [k, n(r[k])]),
            ),
          }))}
          columns={[
            { key: "section", label: f("trans_new.col.section") },
            {
              key: "result_date",
              label: f("trans_new.col.date"),
              type: "date",
            },
            ...[
              "income_target",
              "income",
              "prod_target",
              "prod_qty",
              "total_cost",
              "profit",
            ].map((k) => ({
              key: k,
              label: f(`trans_new.col.${k}`),
              type: "number",
              align: "right",
            })),
            { key: "standing_label", label: f("trans_new.col.status") },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(
                    null,
                    "ws-section-results",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: f("trans_new.save"),
                  columns: 3,
                  refKey: "section",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-section-results")
              : undefined
          }
        />
      );
    },
  };
}

const SP_SCREENS = {
  "sec-wise-profit-loss-config": profitSetup,
  "sec-wise-profit-loss-trans": profitEntry,
};

export { SP_SCREENS };
