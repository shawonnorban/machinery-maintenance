import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import {
  OutputSheet,
  WashList,
  MinuteSheet,
} from "@/components/workstudy/floor-ops";
import * as actions from "./actions";

/*
 * Work Study → Floor Operations (AnnotechERP 14/07): Sewing Output
 * Adjustment — Records, the Sewing Prod Adjustment hour sheet and the New
 * form; Garment Wash Requisition — Records, the Wash Requisition List (Take
 * Action) and the New form; and Standard Minute Adjustment — Records, the
 * Man Minutes Adjustment sheet and the New form.
 */

const foOptions = () => apiFetch("/workstudy/floor-ops/options");
const n = (v) => (v === null || v === undefined ? null : String(Number(v)));
const qs = (searchParams, keys) =>
  Object.fromEntries(
    keys.map((k) => [
      k,
      typeof searchParams[k] === "string" ? searchParams[k] : undefined,
    ]),
  );
const linesOf = (opts, pick) =>
  Object.fromEntries(
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
const floorsOf = (opts, pick) => [
  ...pick,
  ...opts.floors.map((x) => ({ value: x.value, label: x.label })),
];

async function outputAdjustment({ f, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    foOptions(),
    apiFetch("/erp/ws-output-adjustments"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "sheet", label: f("tabs.output"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_output"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "adjust_date",
      label: f("output_new.date"),
      type: "date",
      required: true,
    },
    {
      name: "floor_id",
      label: f("output_new.floor"),
      type: "select",
      required: true,
      options: floorsOf(opts, pick),
    },
    {
      name: "planning_line_id",
      label: f("output_new.line"),
      type: "select",
      options: pick,
      optionsFrom: { field: "floor_id", map: linesOf(opts, pick) },
    },
    {
      name: "buyer_id",
      label: f("output_new.buyer"),
      type: "select",
      options: [...pick, ...opts.buyers],
    },
    { name: "order_no", label: f("output_new.order_no") },
    { name: "item_name", label: f("output_new.item_name") },
    {
      name: "hour",
      label: f("output_new.hour"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.hours.map((h) => ({ value: String(h), label: String(h) })),
      ],
    },
    {
      name: "recorded_qty",
      label: f("output_new.recorded"),
      type: "number",
      required: true,
    },
    {
      name: "adjusted_qty",
      label: f("output_new.adjusted"),
      type: "number",
      required: true,
    },
    {
      name: "reason",
      label: f("output_new.reason"),
      type: "textarea",
      required: true,
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
            action={actions.createResource.bind(
              null,
              "ws-output-adjustments",
              [],
            )}
            title={f("output_new.title")}
            description={f("output_new.hint")}
            submitLabel={f("output_new.save")}
            columns={3}
            successHref="/work-study/sewing-prod-adjustment?tab=records"
          />
        );
      if (tab === "sheet")
        return (
          <OutputSheet
            options={opts}
            act={actions.foAction}
            canManage={canManage}
            initial={qs(searchParams, ["floor", "date", "hour"])}
          />
        );
      return (
        <RecordsGrid
          exportName="production-adjustments"
          rows={rows.map((r) => ({
            ...r,
            hour: String(r.hour),
            recorded_qty: n(r.recorded_qty),
            adjusted_qty: n(r.adjusted_qty),
            status_label: f(`direction.${r.status}`),
          }))}
          columns={[
            {
              key: "adjust_date",
              label: f("output_new.col.date"),
              type: "date",
            },
            { key: "line", label: f("output_new.col.line") },
            { key: "buyer", label: f("output_new.col.buyer") },
            { key: "order_no", label: f("output_new.col.order_no") },
            { key: "item_name", label: f("output_new.col.item_name") },
            {
              key: "hour",
              label: f("output_new.col.hour"),
              align: "right",
            },
            {
              key: "recorded_qty",
              label: f("output_new.col.prod"),
              type: "number",
              align: "right",
            },
            {
              key: "adjusted_qty",
              label: f("output_new.col.adjusted"),
              type: "number",
              align: "right",
            },
            { key: "status_label", label: f("output_new.col.status") },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(
                    null,
                    "ws-output-adjustments",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: f("output_new.save"),
                  columns: 3,
                  refKey: "adjust_date",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-output-adjustments")
              : undefined
          }
        />
      );
    },
  };
}

async function washRequisition({ f, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    foOptions(),
    apiFetch("/erp/ws-wash-requisitions"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "list", label: f("tabs.wash"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_wash"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "requisition_date",
      label: f("wash_new.date"),
      type: "date",
      required: true,
    },
    {
      name: "requested_by",
      label: f("wash_new.requested_by"),
      type: "select",
      options: [...pick, ...opts.employees],
    },
    {
      name: "attn_to",
      label: f("wash_new.attn_to"),
      type: "select",
      options: [...pick, ...opts.users],
    },
    {
      name: "floor_id",
      label: f("wash_new.floor"),
      type: "select",
      required: true,
      options: floorsOf(opts, pick),
    },
    {
      name: "planning_line_id",
      label: f("wash_new.line"),
      type: "select",
      options: pick,
      optionsFrom: { field: "floor_id", map: linesOf(opts, pick) },
    },
    {
      name: "buyer_id",
      label: f("wash_new.buyer"),
      type: "select",
      options: [...pick, ...opts.buyers],
    },
    { name: "style_no", label: f("wash_new.style_no"), required: true },
    { name: "order_no", label: f("wash_new.order_no"), required: true },
    { name: "shipment_date", label: f("wash_new.shipment_date"), type: "date" },
    { name: "order_qty", label: f("wash_new.order_qty"), type: "number" },
    {
      name: "wash_qty",
      label: f("wash_new.wash_qty"),
      type: "number",
      required: true,
    },
    { name: "df_process", label: f("wash_new.df_process") },
    { name: "gmt_category", label: f("wash_new.gmt_category") },
    {
      name: "hourly_production",
      label: f("wash_new.hourly"),
      type: "number",
    },
    {
      name: "daily_production",
      label: f("wash_new.daily"),
      type: "number",
      required: true,
    },
    { name: "start_date", label: f("wash_new.start_date"), type: "date" },
    { name: "close_date", label: f("wash_new.close_date"), type: "date" },
    {
      name: "remarks",
      label: f("wash_new.remarks"),
      type: "textarea",
      span: 3,
    },
    {
      name: "status",
      label: f("wash_new.action"),
      type: "select",
      required: true,
      options: [
        ...pick,
        ...opts.wash_statuses.map((s) => ({
          value: s,
          label: f(`wash.status.${s}`),
        })),
      ],
    },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(
              null,
              "ws-wash-requisitions",
              [],
            )}
            title={f("wash_new.title")}
            description={f("wash_new.hint")}
            submitLabel={f("wash_new.save")}
            columns={3}
            successHref="/work-study/gmt-wash-requisition?tab=records"
          />
        );
      if (tab === "list")
        return (
          <WashList
            options={opts}
            act={actions.foAction}
            canManage={canManage}
            initial={qs(searchParams, [
              "status",
              "buyer",
              "no",
              "style",
              "order",
              "from",
              "to",
            ])}
          />
        );
      return (
        <RecordsGrid
          exportName="wash-requisitions"
          rows={rows.map((r) => ({
            ...r,
            order_qty: n(r.order_qty),
            wash_qty: n(r.wash_qty),
            hourly_production: n(r.hourly_production),
            daily_production: n(r.daily_production),
            status_label: f(`wash.status.${r.status}`),
          }))}
          columns={[
            { key: "requisition_no", label: f("wash.col.requisition_no") },
            {
              key: "requisition_date",
              label: f("wash.col.requisition_date"),
              type: "date",
            },
            { key: "buyer", label: f("wash.col.buyer") },
            { key: "style_no", label: f("wash.col.style_no") },
            { key: "order_no", label: f("wash.col.order_no") },
            {
              key: "shipment_date",
              label: f("wash.col.shipment_date"),
              type: "date",
            },
            {
              key: "order_qty",
              label: f("wash.col.order_qty"),
              type: "number",
              align: "right",
            },
            { key: "remarks", label: f("wash.col.remarks") },
            { key: "status_label", label: f("wash.col.status") },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(
                    null,
                    "ws-wash-requisitions",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: f("wash_new.save"),
                  columns: 3,
                  refKey: "requisition_no",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-wash-requisitions")
              : undefined
          }
        />
      );
    },
  };
}

async function manMinutes({ f, te, tab, canManage, searchParams = {} }) {
  const [opts, rows] = await Promise.all([
    foOptions(),
    apiFetch("/erp/ws-man-minute-adjustments"),
  ]);
  const pick = [{ value: "", label: f("select") }];
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "sheet", label: f("tabs.minutes"), icon: "CalendarRange" },
    ...(canManage
      ? [{ key: "new", label: f("tabs.new_minutes"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "adjust_date",
      label: f("mm_new.date"),
      type: "date",
      required: true,
    },
    {
      name: "floor_id",
      label: f("mm_new.floor"),
      type: "select",
      required: true,
      options: floorsOf(opts, pick),
    },
    {
      name: "planning_line_id",
      label: f("mm_new.line"),
      type: "select",
      required: true,
      options: pick,
      optionsFrom: { field: "floor_id", map: linesOf(opts, pick) },
    },
    {
      name: "reason",
      label: f("mm_new.reason"),
      type: "select",
      required: true,
      span: 3,
      options: [...pick, ...opts.reasons.map((r) => ({ value: r, label: r }))],
    },
    { name: "used_op", label: f("mm_new.op"), type: "number", required: true },
    {
      name: "used_aso",
      label: f("mm_new.aso"),
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
            action={actions.createResource.bind(
              null,
              "ws-man-minute-adjustments",
              [],
            )}
            title={f("mm_new.title")}
            description={f("mm_new.hint")}
            submitLabel={f("mm_new.save")}
            columns={3}
            successHref="/work-study/man-minutes-adjustment?tab=records"
          />
        );
      if (tab === "sheet")
        return (
          <MinuteSheet
            options={opts}
            act={actions.foAction}
            canManage={canManage}
            initial={qs(searchParams, ["floor", "date", "line"])}
          />
        );
      return (
        <RecordsGrid
          exportName="man-minute-adjustments"
          rows={rows.map((r) => ({
            ...r,
            used_op: n(r.used_op),
            used_aso: n(r.used_aso),
            ot_op: n(r.ot_op),
            ot_aso: n(r.ot_aso),
          }))}
          columns={[
            { key: "adjust_date", label: f("mm_new.col.date"), type: "date" },
            { key: "line", label: f("mm_new.col.line") },
            { key: "reason", label: f("mm_new.col.reason") },
            {
              key: "used_op",
              label: f("mm_new.col.op"),
              type: "number",
              align: "right",
            },
            {
              key: "used_aso",
              label: f("mm_new.col.aso"),
              type: "number",
              align: "right",
            },
            {
              key: "ot_op",
              label: f("mm_new.col.ot_op"),
              type: "number",
              align: "right",
            },
            {
              key: "ot_aso",
              label: f("mm_new.col.ot_aso"),
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
                    "ws-man-minute-adjustments",
                    [],
                  ),
                  title: te("edit"),
                  submitLabel: f("mm_new.save"),
                  columns: 3,
                  refKey: "reason",
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-man-minute-adjustments")
              : undefined
          }
        />
      );
    },
  };
}

const FO_SCREENS = {
  "sewing-prod-adjustment": outputAdjustment,
  "gmt-wash-requisition": washRequisition,
  "man-minutes-adjustment": manMinutes,
};

export { FO_SCREENS };
