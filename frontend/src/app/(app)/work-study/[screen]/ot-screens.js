import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { RateSheet, OtPlanSheet } from "@/components/workstudy/overtime";
import * as actions from "./actions";

/*
 * Work Study → OT & Salary (AnnotechERP 14/06): the Garment Overtime Plan —
 * Records, a floor's OT Plan sheet and the New OT plan row form — and Monthly
 * Salary & OT Rates — Records, the year's Cost Particulars sheet and the New
 * salary and OT rate form.
 */

const otOptions = () => apiFetch("/workstudy/ot/options");
const n = (v) => (v === null || v === undefined ? null : String(Number(v)));

async function otPlan({ o, c, te, tab, canManage, searchParams = {} }) {
  const [opts, plans] = await Promise.all([
    otOptions(),
    apiFetch("/erp/ws-ot-plans"),
  ]);
  const pick = [{ value: "", label: c("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: plans.length,
    },
    { key: "plan", label: o("tabs.plan"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: o("tabs.new_plan"), icon: "Plus" }]
      : []),
  ];
  // The Line box offers the lines of the floor picked.
  const linesByFloor = Object.fromEntries(
    opts.floors.map((f) => [
      f.value,
      [
        ...pick,
        ...opts.lines
          .filter((l) => l.floor_id === f.value)
          .map((l) => ({ value: l.value, label: l.label })),
      ],
    ]),
  );
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={[
              {
                name: "plan_date",
                label: o("new.date"),
                type: "date",
                required: true,
              },
              {
                name: "floor_id",
                label: o("new.floor"),
                type: "select",
                required: true,
                options: [
                  ...pick,
                  ...opts.floors.map((f) => ({
                    value: f.value,
                    label: f.label,
                  })),
                ],
              },
              {
                name: "planning_line_id",
                label: o("new.line"),
                type: "select",
                options: pick,
                optionsFrom: { field: "floor_id", map: linesByFloor },
              },
              {
                name: "department_id",
                label: o("new.department"),
                type: "select",
                options: [...pick, ...opts.departments],
              },
              {
                name: "designation_id",
                label: o("new.designation"),
                type: "select",
                options: [...pick, ...opts.designations],
              },
              {
                name: "mp_gen",
                label: o("new.mp_gen"),
                type: "number",
                required: true,
                default: "0",
              },
              {
                name: "wh_gen",
                label: o("new.wh_gen"),
                type: "number",
                default: "0",
              },
              {
                name: "prev_wh",
                label: o("new.prev_wh"),
                type: "number",
                default: "0",
              },
              {
                name: "hour_count",
                label: o("new.hour_count"),
                type: "number",
                required: true,
                default: "3",
                help: o("new.hour_help"),
              },
              {
                name: "op_per_hour",
                label: o("new.op_per_hour"),
                type: "number",
                default: "0",
              },
              {
                name: "aso_per_hour",
                label: o("new.aso_per_hour"),
                type: "number",
                default: "0",
              },
              { name: "remarks", label: o("new.remarks"), span: 3 },
            ]}
            action={actions.createResource.bind(null, "ws-ot-plans", [])}
            title={o("new.title")}
            description={o("new.hint")}
            submitLabel={o("new.save")}
            columns={3}
            successHref="/work-study/ot-plan-garment-division?tab=records"
          />
        );
      if (tab === "plan") {
        const day = {
          floor:
            typeof searchParams.floor === "string"
              ? searchParams.floor
              : undefined,
          date:
            typeof searchParams.date === "string"
              ? searchParams.date
              : undefined,
        };
        return (
          <OtPlanSheet
            options={opts}
            act={actions.otAction}
            canManage={canManage}
            initial={day}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="garment-overtime-plan"
          rows={plans.map((r) => ({
            ...r,
            mp_gen: n(r.mp_gen),
            wh_gen: n(r.wh_gen),
            hours: n(r.hours),
          }))}
          columns={[
            { key: "plan_date", label: o("new.date"), type: "date" },
            { key: "floor", label: o("new.floor") },
            { key: "line", label: o("new.line") },
            { key: "department", label: o("new.department") },
            { key: "designation", label: o("new.designation") },
            {
              key: "mp_gen",
              label: o("new.mp_gen"),
              type: "number",
              align: "right",
            },
            {
              key: "wh_gen",
              label: o("new.wh_gen"),
              type: "number",
              align: "right",
            },
            {
              key: "hours",
              label: o("new.col_hours"),
              type: "number",
              align: "right",
            },
          ]}
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-ot-plans")
              : undefined
          }
        />
      );
    },
  };
}

async function salaryRates({ o, c, te, tab, canManage, searchParams = {} }) {
  const [opts, rates] = await Promise.all([
    otOptions(),
    apiFetch("/erp/ws-salary-rates"),
  ]);
  const pick = [{ value: "", label: c("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rates.length,
    },
    { key: "rates", label: o("tabs.rates"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: o("tabs.new_rate"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "company",
      label: o("rate.company"),
      type: "select",
      required: true,
      options: [...pick, opts.company],
    },
    {
      name: "year",
      label: o("rate.year"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.years.map((y) => ({ value: String(y), label: String(y) })),
      ],
    },
    {
      name: "month",
      label: o("rate.month"),
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
      name: "op_salary",
      label: o("rate.col.op_salary"),
      type: "number",
      required: true,
    },
    {
      name: "aso_salary",
      label: o("rate.col.aso_salary"),
      type: "number",
      required: true,
    },
    {
      name: "op_ot_rate",
      label: o("rate.col.op_ot_rate"),
      type: "number",
      required: true,
    },
    {
      name: "aso_ot_rate",
      label: o("rate.col.aso_ot_rate"),
      type: "number",
      required: true,
    },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-salary-rates", [])}
            title={o("rate.new_title")}
            description={o("rate.new_hint")}
            submitLabel={o("rate.save_new")}
            columns={3}
            successHref="/work-study/monthly-op-aso-salary-ot-rate?tab=records"
          />
        );
      if (tab === "rates") {
        const year =
          typeof searchParams.year === "string" &&
          /^\d{4}$/.test(searchParams.year)
            ? searchParams.year
            : undefined;
        return (
          <RateSheet
            options={opts}
            act={actions.otAction}
            canManage={canManage}
            initialYear={year}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="monthly-salary-ot-rates"
          rows={rates.map((r) => ({
            ...r,
            company_name: opts.company.label,
            company: opts.company.value,
            year: String(r.year),
            month: String(r.month),
            op_salary: n(r.op_salary),
            aso_salary: n(r.aso_salary),
            op_ot_rate: n(r.op_ot_rate),
            aso_ot_rate: n(r.aso_ot_rate),
          }))}
          columns={[
            { key: "company_name", label: o("rate.col.company") },
            { key: "period_label", label: o("rate.col.month") },
            {
              key: "op_salary",
              label: o("rate.col.op_salary"),
              type: "number",
              align: "right",
            },
            {
              key: "aso_salary",
              label: o("rate.col.aso_salary"),
              type: "number",
              align: "right",
            },
            {
              key: "op_ot_rate",
              label: o("rate.col.op_ot_rate"),
              type: "number",
              align: "right",
            },
            {
              key: "aso_ot_rate",
              label: o("rate.col.aso_ot_rate"),
              type: "number",
              align: "right",
            },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(
                    null,
                    "ws-salary-rates",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: o("rate.save_new"),
                  columns: 3,
                  refKey: "period_label",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-salary-rates")
              : undefined
          }
        />
      );
    },
  };
}

const OT_SCREENS = {
  "ot-plan-garment-division": otPlan,
  "monthly-op-aso-salary-ot-rate": salaryRates,
};

export { OT_SCREENS };
