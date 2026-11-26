import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import {
  YarnSheet,
  ChangeoverBoard,
  TargetSheet,
  AllocationSheet,
  NptSheet,
} from "@/components/workstudy/targets";
import * as actions from "./actions";

/*
 * Work Study → Target & Feeding (AnnotechERP 14/03): Excess Yarn Allowance —
 * Records, the month's Excess Yarn Target sheet and the New form; Style
 * Feeding & Change-over — Records, the Changeover Dashboard and the New form;
 * and Garment Target Setting — Records, the Sewing Target Setting sheet,
 * Production Entry, Production Plans, NPT Entry and the New sewing target form.
 */

const tfOptions = () => apiFetch("/workstudy/targets/options");
const n = (v) => (v === null || v === undefined ? null : String(Number(v)));
const qs = (searchParams, keys) =>
  Object.fromEntries(
    keys.map((k) => [
      k,
      typeof searchParams[k] === "string" ? searchParams[k] : undefined,
    ]),
  );

async function excessYarn({ f, c, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    tfOptions(),
    apiFetch("/erp/ws-yarn-targets"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "yarn", label: f("tabs.yarn"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_yarn"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "company",
      label: f("yarn.company"),
      type: "select",
      required: true,
      options: [...pick, opts.company],
    },
    {
      name: "year",
      label: f("yarn.year"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.years.map((y) => ({ value: String(y), label: String(y) })),
      ],
    },
    {
      name: "month",
      label: f("yarn.month"),
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
      name: "department_id",
      label: f("yarn.col.department"),
      type: "select",
      required: true,
      options: [...pick, ...opts.departments],
    },
    {
      name: "target_qty",
      label: f("yarn.col.target_qty"),
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
            action={actions.createResource.bind(null, "ws-yarn-targets", [])}
            title={f("tabs.new_yarn")}
            description={f("yarn.new_hint")}
            submitLabel={f("yarn.save_new")}
            columns={3}
            successHref="/work-study/excess-yarn-target?tab=records"
          />
        );
      if (tab === "yarn") {
        const period =
          typeof searchParams.period === "string" &&
          /^\d{4}-\d{2}$/.test(searchParams.period)
            ? searchParams.period
            : undefined;
        return (
          <YarnSheet
            options={opts}
            act={actions.tfAction}
            canManage={canManage}
            initialPeriod={period}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="excess-yarn-targets"
          rows={rows.map((r) => ({
            ...r,
            target_qty: n(r.target_qty),
            year: String(r.year),
            month: String(r.month),
            company: opts.company.value,
            company_name: opts.company.label,
          }))}
          columns={[
            { key: "company_name", label: f("yarn.company") },
            { key: "year", label: f("yarn.year") },
            { key: "month_name", label: f("yarn.month") },
            { key: "department", label: f("yarn.col.department") },
            {
              key: "target_qty",
              label: f("yarn.col.target_qty"),
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
                    "ws-yarn-targets",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: f("yarn.save_new"),
                  columns: 3,
                  refKey: "department",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-yarn-targets")
              : undefined
          }
        />
      );
    },
  };
}

async function styleFeeding({ f, te, tab, canManage }) {
  const [opts, rows] = await Promise.all([
    tfOptions(),
    apiFetch("/erp/ws-changeovers"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "dashboard", label: f("tabs.dashboard"), icon: "LayoutDashboard" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_sco"), icon: "Plus" }]
      : []),
  ];
  const linesByFloor = Object.fromEntries(
    opts.floors.map((x) => [
      x.value,
      [
        ...pick,
        ...opts.lines
          .filter((l) => l.floor_id === x.value)
          .map((l) => ({ value: l.value, label: l.label })),
      ],
    ]),
  );
  const orders = [
    ...pick,
    ...opts.orders.map((o) => ({ value: o.value, label: o.label })),
  ];
  const fields = [
    {
      name: "floor_id",
      label: f("sco.new.floor"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.floors.map((x) => ({ value: x.value, label: x.label })),
      ],
    },
    {
      name: "planning_line_id",
      label: f("sco.new.line"),
      type: "select",
      required: true,
      options: pick,
      optionsFrom: { field: "floor_id", map: linesByFloor },
    },
    {
      name: "changeover_date",
      label: f("sco.new.date"),
      type: "date",
      required: true,
    },
    {
      name: "new_buyer_id",
      label: f("sco.new.new_buyer"),
      type: "select",
      options: [...pick, ...opts.buyers],
    },
    {
      name: "new_order",
      label: f("sco.new.new_order"),
      type: "select",
      required: true,
      options: orders,
    },
    { name: "new_gmt_item", label: f("sco.new.new_item") },
    {
      name: "prev_buyer_id",
      label: f("sco.new.prev_buyer"),
      type: "select",
      options: [...pick, ...opts.buyers],
    },
    {
      name: "prev_order",
      label: f("sco.new.prev_order"),
      type: "select",
      options: orders,
    },
    { name: "prev_gmt_item", label: f("sco.new.prev_item") },
    { name: "prev_output_on", label: f("sco.new.prev_output"), type: "date" },
    {
      name: "sco_minutes",
      label: f("sco.new.sco"),
      type: "number",
      required: true,
    },
    { name: "ft_hours", label: f("sco.new.ft"), type: "number" },
    { name: "remarks", label: f("sco.new.remarks"), type: "textarea", span: 3 },
    { name: "finalized", label: f("sco.new.finalize"), type: "checkbox" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-changeovers", [
              "finalized",
            ])}
            title={f("tabs.new_sco")}
            description={f("sco.new.hint")}
            submitLabel={f("sco.new.save")}
            columns={3}
            successHref="/work-study/style-feeding-sco?tab=records"
          />
        );
      if (tab === "dashboard")
        return (
          <ChangeoverBoard
            options={opts}
            act={actions.tfAction}
            canManage={canManage}
          />
        );
      return (
        <RecordsGrid
          exportName="style-change-overs"
          rows={rows.map((r) => ({
            ...r,
            sco_minutes: n(r.sco_minutes),
            ft_hours: n(r.ft_hours),
            status_label: f(`status.${r.status}`),
            finalized: r.finalized ? "1" : "0",
          }))}
          columns={[
            { key: "changeover_date", label: f("sco.col.date"), type: "date" },
            { key: "line", label: f("sco.col.line") },
            { key: "new_order", label: f("sco.col.new_order") },
            { key: "prev_order", label: f("sco.col.previous_order") },
            {
              key: "sco_minutes",
              label: f("sco.col.sco"),
              type: "number",
              align: "right",
            },
            {
              key: "ft_hours",
              label: f("sco.col.ft"),
              type: "number",
              align: "right",
            },
            {
              key: "feeding_start",
              label: f("sco.col.feeding_start"),
              type: "date",
            },
            {
              key: "feeding_end",
              label: f("sco.col.feeding_end"),
              type: "date",
            },
            { key: "status_label", label: f("sco.col.status") },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(null, "ws-changeovers", [
                    "finalized",
                  ]),
                  title: te("edit"),
                  submitLabel: f("sco.new.save"),
                  columns: 3,
                  refKey: "new_order",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-changeovers")
              : undefined
          }
        />
      );
    },
  };
}

async function gmtTarget({ f, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    tfOptions(),
    apiFetch("/erp/ws-sewing-targets"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "sheet", label: f("tabs.sheet"), icon: "CalendarRange" },
    { key: "entry", label: f("tabs.entry"), icon: "CalendarRange" },
    { key: "plans", label: f("tabs.plans"), icon: "CalendarRange" },
    { key: "npt", label: f("tabs.npt"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_target"), icon: "Plus" }]
      : []),
  ];
  const linesByFloor = Object.fromEntries(
    opts.floors.map((x) => [
      x.value,
      [
        ...pick,
        ...opts.lines
          .filter((l) => l.floor_id === x.value)
          .map((l) => ({ value: l.value, label: l.label })),
      ],
    ]),
  );
  const fields = [
    { name: "target_date", label: f("new.date"), type: "date", required: true },
    {
      name: "floor_id",
      label: f("new.floor"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.floors.map((x) => ({ value: x.value, label: x.label })),
      ],
    },
    {
      name: "planning_line_id",
      label: f("new.line"),
      type: "select",
      required: true,
      options: pick,
      optionsFrom: { field: "floor_id", map: linesByFloor },
    },
    {
      name: "buyer_id",
      label: f("new.buyer"),
      type: "select",
      options: [...pick, ...opts.buyers],
    },
    { name: "order_style", label: f("new.order_style") },
    {
      name: "used_op",
      label: f("new.used_op"),
      type: "number",
      required: true,
    },
    {
      name: "used_aso",
      label: f("new.used_aso"),
      type: "number",
      required: true,
    },
    { name: "layout_op", label: f("new.layout_op"), type: "number" },
    { name: "layout_aso", label: f("new.layout_aso"), type: "number" },
    { name: "smv", label: f("new.smv"), type: "number", required: true },
    {
      name: "t_effi",
      label: f("new.t_effi"),
      type: "number",
      required: true,
      suffix: "%",
    },
    { name: "wh", label: f("new.wh"), type: "number", required: true },
    { name: "wh_from", label: f("new.wh_from"), type: "number" },
    { name: "wh_to", label: f("new.wh_to"), type: "number" },
    { name: "wip_rfi", label: f("new.wip_rfi"), type: "number" },
    { name: "prod_gen", label: f("new.prod_gen"), type: "number" },
    { name: "prod_ot", label: f("new.prod_ot"), type: "number" },
    { name: "is_merged", label: f("new.is_merged"), type: "checkbox" },
    { name: "is_single", label: f("new.is_single"), type: "checkbox" },
    { name: "is_off", label: f("new.is_off"), type: "checkbox" },
  ];
  const flags = ["is_merged", "is_single", "is_off"];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(
              null,
              "ws-sewing-targets",
              flags,
            )}
            title={f("new.title")}
            description={f("new.hint")}
            submitLabel={f("new.save")}
            columns={3}
            successHref="/work-study/gmt-target-setting?tab=records"
          />
        );
      if (tab === "sheet")
        return (
          <TargetSheet
            options={opts}
            act={actions.tfAction}
            canManage={canManage}
            initial={qs(searchParams, ["floor", "date"])}
          />
        );
      if (tab === "entry" || tab === "plans")
        return (
          <AllocationSheet
            key={tab}
            mode={tab}
            options={opts}
            act={actions.tfAction}
            canManage={canManage}
            initial={qs(searchParams, ["floor", "line"])}
          />
        );
      if (tab === "npt")
        return (
          <NptSheet
            options={opts}
            act={actions.tfAction}
            canManage={canManage}
            initial={qs(searchParams, ["floor", "date", "line"])}
          />
        );
      return (
        <RecordsGrid
          exportName="sewing-targets"
          rows={rows.map((r) => ({
            ...r,
            used_op: n(r.used_op),
            used_aso: n(r.used_aso),
            t_effi: n(r.t_effi),
            ttl_target: n(r.ttl_target),
            ttl_prod: n(r.ttl_prod),
            status_label: f(`status.${r.status}`),
            ...Object.fromEntries(
              [
                "layout_op",
                "layout_aso",
                "smv",
                "wh",
                "wh_from",
                "wh_to",
                "wip_rfi",
                "prod_gen",
                "prod_ot",
              ].map((k) => [k, n(r[k])]),
            ),
            ...Object.fromEntries(flags.map((k) => [k, r[k] ? "1" : "0"])),
          }))}
          columns={[
            { key: "target_date", label: f("new.col.date"), type: "date" },
            { key: "line", label: f("new.col.line") },
            { key: "order_style", label: f("new.col.order_style") },
            {
              key: "used_op",
              label: f("new.col.op"),
              type: "number",
              align: "right",
            },
            {
              key: "used_aso",
              label: f("new.col.aso"),
              type: "number",
              align: "right",
            },
            {
              key: "t_effi",
              label: f("new.col.t_effi"),
              type: "number",
              align: "right",
            },
            {
              key: "ttl_target",
              label: f("new.col.ttl_target"),
              type: "number",
              align: "right",
            },
            {
              key: "ttl_prod",
              label: f("new.col.ttl_prod"),
              type: "number",
              align: "right",
            },
            { key: "status_label", label: f("new.col.status") },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(
                    null,
                    "ws-sewing-targets",
                    flags,
                  ),
                  title: te("edit"),
                  submitLabel: f("new.save"),
                  columns: 3,
                  refKey: "order_style",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-sewing-targets")
              : undefined
          }
        />
      );
    },
  };
}

const TF_SCREENS = {
  "excess-yarn-target": excessYarn,
  "style-feeding-sco": styleFeeding,
  "gmt-target-setting": gmtTarget,
};
const TF_DEFAULT_TABS = {};

export { TF_SCREENS, TF_DEFAULT_TABS };
