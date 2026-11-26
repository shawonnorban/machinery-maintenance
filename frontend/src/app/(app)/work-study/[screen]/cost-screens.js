import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { SideGrid } from "@/components/workstudy/side-grid";
import { CostSheet, DaySheet } from "@/components/workstudy/costing";
import { itemGroupsGrid } from "./spec-screens";
import * as actions from "./actions";

/*
 * Work Study → Costing (AnnotechERP 14/05): Spec-Wise CM Rate — Records, the
 * item groups beside their categories and the New CM rate form; Overhead
 * Cost Allocation — Records, the Monthly Cost Re-arrange sheet, each
 * section's cost heads and the New cost head form; and the Daily Line Cost
 * Sheet — Records, a floor's day (Order Config Data, Line Wise Prod, Overall
 * Profit Lost) and the New line costing row form.
 */

const costOptions = () => apiFetch("/workstudy/costing/options");
const n = (v) => (v === null || v === undefined ? null : String(Number(v)));
const pick = (c, list) => [{ value: "", label: c("select") }, ...list];

async function cmRate({ m, s, c, te, tab, canManage }) {
  const [opts, rates] = await Promise.all([
    costOptions(),
    apiFetch("/erp/ws-cm-rates"),
  ]);
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rates.length,
    },
    { key: "groups", label: c("tabs.groups"), icon: "ListTree" },
    ...(canManage
      ? [{ key: "new", label: c("tabs.new_cm"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "spec_id",
      label: c("cm.spec"),
      type: "select",
      required: true,
      options: pick(c, opts.specs),
    },
    {
      name: "buyer_id",
      label: c("cm.buyer"),
      type: "select",
      options: pick(c, opts.buyers),
    },
    {
      name: "booking_month",
      label: c("cm.month"),
      required: true,
      placeholder: new Date().toISOString().slice(0, 7),
      help: c("cm.month_help"),
    },
    {
      name: "order_qty",
      label: c("cm.order_qty"),
      type: "number",
      required: true,
    },
    {
      name: "cm_per_dozen",
      label: c("cm.cm"),
      type: "number",
      required: true,
      suffix: c("cm.unit_usd"),
    },
    {
      name: "budget_exp_pct",
      label: c("cm.budget"),
      type: "number",
      suffix: "%",
    },
    {
      name: "lead_time_days",
      label: c("cm.lead"),
      type: "number",
      suffix: c("cm.unit_days"),
    },
    { name: "confirm_date", label: c("cm.confirm"), type: "date" },
    { name: "ship_date", label: c("cm.ship"), type: "date" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-cm-rates", [])}
            title={c("cm.title")}
            description={c("cm.hint")}
            submitLabel={c("cm.save")}
            columns={3}
            successHref="/work-study/item-spec-wise-cm?tab=records"
          />
        );
      if (tab === "groups") {
        const spec = await apiFetch("/workstudy/spec-options");
        return itemGroupsGrid({ s, m, opts: spec, canManage });
      }
      return (
        <RecordsGrid
          exportName="spec-wise-cm-rates"
          rows={rates.map((r) => ({
            ...r,
            order_qty: n(r.order_qty),
            cm_per_dozen: n(r.cm_per_dozen),
            budget_exp_pct: n(r.budget_exp_pct),
            lead_time_days: n(r.lead_time_days),
          }))}
          columns={[
            { key: "spec", label: c("cm.col_spec") },
            { key: "buyer", label: c("cm.buyer") },
            { key: "booking_month", label: c("cm.month") },
            {
              key: "order_qty",
              label: c("cm.order_qty"),
              type: "number",
              align: "right",
            },
            {
              key: "cm_per_dozen",
              label: c("cm.cm"),
              type: "number",
              align: "right",
            },
            {
              key: "budget_exp_pct",
              label: c("cm.col_budget"),
              type: "number",
              align: "right",
            },
            {
              key: "lead_time_days",
              label: c("cm.lead"),
              type: "number",
              align: "right",
            },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(null, "ws-cm-rates", []),
                  title: te("edit"),
                  submitLabel: c("cm.save"),
                  columns: 3,
                  refKey: "spec",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-cm-rates")
              : undefined
          }
        />
      );
    },
  };
}

async function ohAllocation({ m, c, te, tab, canManage, searchParams = {} }) {
  const [opts, costs] = await Promise.all([
    costOptions(),
    apiFetch("/erp/ws-oh-costs"),
  ]);
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: costs.length,
    },
    { key: "monthly", label: c("tabs.monthly"), icon: "CalendarRange" },
    { key: "heads", label: c("tabs.heads"), icon: "ListTree" },
    ...(canManage
      ? [{ key: "new", label: c("tabs.new_head"), icon: "Plus" }]
      : []),
  ];
  const months = Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1),
    label: c(`months.${i + 1}`),
  }));
  const fields = [
    {
      name: "company",
      label: c("head.company"),
      type: "select",
      required: true,
      options: pick(c, [opts.company]),
    },
    {
      name: "year",
      label: c("head.year"),
      type: "select",
      required: true,
      options: pick(
        c,
        opts.years.map((y) => ({ value: String(y), label: String(y) })),
      ),
    },
    {
      name: "month",
      label: c("head.month"),
      type: "select",
      required: true,
      options: pick(c, months),
    },
    {
      name: "head_id",
      label: c("head.head"),
      type: "select",
      required: true,
      options: pick(c, opts.heads),
      span: 3,
    },
    {
      name: "monthly_expense",
      label: c("head.expense"),
      type: "number",
      required: true,
      suffix: c("head.unit_bdt"),
    },
    {
      name: "work_days",
      label: c("head.work_days"),
      type: "number",
      help: c("head.work_days_help"),
    },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-oh-costs", [])}
            title={c("head.title")}
            description={c("head.hint")}
            submitLabel={c("head.save")}
            columns={3}
            successHref="/work-study/oh-cost-allocation?tab=records"
          />
        );
      if (tab === "monthly") {
        const period =
          typeof searchParams.period === "string" &&
          /^\d{4}-\d{2}$/.test(searchParams.period)
            ? searchParams.period
            : undefined;
        return (
          <CostSheet
            options={opts}
            act={actions.costAction}
            canManage={canManage}
            initialPeriod={period}
          />
        );
      }
      if (tab === "heads")
        return (
          <SideGrid
            listTitle={c("sec.sections")}
            items={opts.sections.map((x) => ({
              key: x.key,
              label: x.label,
              hint: x.target ? c("sec.target") : null,
              count: x.count,
            }))}
            listAction={
              canManage ? (
                <DialogFormButton
                  label={c("sec.new")}
                  title={c("sec.new")}
                  fields={[
                    { name: "name", label: c("sec.f_name"), required: true },
                    { name: "name_bn", label: c("sec.f_name_bn") },
                    {
                      name: "sort_order",
                      label: c("sec.f_order"),
                      type: "number",
                      default: "0",
                    },
                    {
                      name: "is_target",
                      label: c("sec.f_target"),
                      type: "checkbox",
                      help: c("sec.f_target_help"),
                    },
                  ]}
                  action={actions.createResource.bind(null, "ws-oh-sections", [
                    "is_target",
                  ])}
                  submitLabel={c("sec.new")}
                />
              ) : null
            }
            title={c("sec.title")}
            hint={c("sec.hint")}
            emptyTitle={c("sec.empty_title")}
            empty={c("sec.empty")}
            gridEmpty={{
              title: c("sec.grid_empty_title"),
              text: m("grid.empty_text"),
            }}
            footerKey="cost.sec.footer"
            columns={[
              { key: "_sl", label: c("sec.sl"), type: "sl" },
              {
                key: "name",
                label: c("sec.name"),
                type: "input",
                width: "w-64",
              },
              {
                key: "name_bn",
                label: c("sec.name_bn"),
                type: "input",
                width: "w-64",
              },
              {
                key: "sort_order",
                label: c("sec.order"),
                type: "number",
                width: "w-24 text-right",
              },
              { key: "active", label: c("sec.active"), type: "check" },
              {
                key: "_remove",
                label: m("grid.action"),
                type: "action",
                blockedBy: "in_use",
              },
            ]}
            blank={{
              name: "",
              name_bn: "",
              sort_order: "",
              active: true,
              in_use: 0,
            }}
            load={actions.loadHeads}
            save={actions.saveHeads}
            canManage={canManage}
            exportName="section-cost-heads"
          />
        );
      return (
        <RecordsGrid
          exportName="overhead-cost-heads"
          rows={costs.map((r) => ({
            ...r,
            monthly_expense: n(r.monthly_expense),
            work_days: n(r.work_days),
            rearranged: n(r.rearranged),
            per_day: n(r.per_day),
            year: String(r.year),
            month: String(r.month),
            company: opts.company.value,
            company_name: opts.company.label,
          }))}
          columns={[
            { key: "company_name", label: c("head.company") },
            { key: "period_label", label: c("head.col_period") },
            { key: "section", label: c("head.head") },
            { key: "particular", label: c("sheet.col.particular") },
            {
              key: "monthly_expense",
              label: c("head.expense"),
              type: "number",
              align: "right",
            },
            {
              key: "work_days",
              label: c("head.work_days"),
              type: "number",
              align: "right",
            },
            {
              key: "rearranged",
              label: c("head.col_rearranged"),
              type: "number",
              align: "right",
            },
            {
              key: "per_day",
              label: c("head.col_per_day"),
              type: "number",
              align: "right",
            },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(null, "ws-oh-costs", []),
                  title: te("edit"),
                  submitLabel: c("head.save"),
                  columns: 3,
                  refKey: "particular",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-oh-costs")
              : undefined
          }
        />
      );
    },
  };
}

async function lineCost({ c, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    costOptions(),
    apiFetch("/erp/ws-line-costings"),
  ]);
  const d = (k, p) => c(`day.${k}`, p);
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "config", label: c("tabs.config"), icon: "CalendarRange" },
    { key: "lines", label: c("tabs.lines"), icon: "CalendarRange" },
    { key: "profit", label: c("tabs.profit"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: c("tabs.new_row"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "floor_id",
      label: d("floor"),
      type: "select",
      required: true,
      options: pick(
        c,
        opts.floors.map((f) => ({ value: f.value, label: f.label })),
      ),
    },
    { name: "cost_date", label: d("date"), type: "date", required: true },
    {
      name: "buyer_id",
      label: d("col.buyer"),
      type: "select",
      options: pick(c, opts.buyers),
    },
    { name: "style_no", label: d("col.style_no"), required: true },
    { name: "order_no", label: d("col.order_no"), required: true },
    { name: "gmt_item", label: d("col.gmt_item") },
    {
      name: "bgt_as_cm_pct",
      label: d("col.bgt_as_cm_pct"),
      type: "number",
      suffix: "%",
    },
    { name: "exc_rate", label: d("col.exc_rate"), type: "number" },
    { name: "smv", label: d("col.smv"), type: "number", required: true },
    {
      name: "fob",
      label: d("col.fob"),
      type: "number",
      suffix: c("cm.unit_usd"),
    },
    { name: "rate_cut", label: d("col.rate_cut"), type: "number" },
    { name: "rate_sew", label: d("col.rate_sew"), type: "number" },
    { name: "rate_fin", label: d("col.rate_fin"), type: "number" },
  ];
  const day = {
    floor:
      typeof searchParams.floor === "string" ? searchParams.floor : undefined,
    date: typeof searchParams.date === "string" ? searchParams.date : undefined,
  };
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-line-costings", [])}
            title={d("new_title")}
            description={d("new_hint")}
            submitLabel={d("save_row")}
            columns={3}
            successHref="/work-study/daily-line-costing-operation?tab=records"
          />
        );
      if (["config", "lines", "profit"].includes(tab))
        return (
          <DaySheet
            key={tab}
            mode={tab}
            floors={opts.floors}
            buyers={opts.buyers}
            act={actions.costAction}
            canManage={canManage}
            initial={day}
          />
        );
      return (
        <RecordsGrid
          exportName="daily-line-costing"
          rows={rows.map((r) => ({
            ...r,
            smv: n(r.smv),
            fob: n(r.fob),
            rate_cut: n(r.rate_cut),
            rate_sew: n(r.rate_sew),
            rate_fin: n(r.rate_fin),
            bgt_as_cm_pct: n(r.bgt_as_cm_pct),
            exc_rate: n(r.exc_rate),
          }))}
          columns={[
            { key: "cost_date", label: d("col.date"), type: "date" },
            { key: "floor", label: d("col.floor") },
            { key: "buyer", label: d("col.buyer") },
            { key: "style_no", label: d("col.style_no") },
            { key: "order_no", label: d("col.order_no") },
            { key: "smv", label: d("col.smv"), type: "number", align: "right" },
            { key: "fob", label: d("col.fob"), type: "number", align: "right" },
            {
              key: "rate_cut",
              label: d("col.rate_cut"),
              type: "number",
              align: "right",
            },
            {
              key: "rate_sew",
              label: d("col.rate_sew"),
              type: "number",
              align: "right",
            },
            {
              key: "rate_fin",
              label: d("col.rate_fin"),
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
                    "ws-line-costings",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: d("save_row"),
                  columns: 3,
                  refKey: "order_no",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-line-costings")
              : undefined
          }
        />
      );
    },
  };
}

const COST_SCREENS = {
  "item-spec-wise-cm": cmRate,
  "oh-cost-allocation": ohAllocation,
  "daily-line-costing-operation": lineCost,
};

export { COST_SCREENS };
