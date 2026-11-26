import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { BatchProduction } from "@/components/dyehouse/batch-production";
import { ChemRegister } from "@/components/dyehouse/chem-store";
import { WashProductionEditor, ManualDailyGrid, ProgrammeEditor } from "@/components/dyehouse/production-board";
import * as actions from "./actions";

/*
 * Dye House → Production Board (AnnotechERP 17/07), each laid out as
 * Brickland lays it out: Bulk Batch Production (the 17/04 sheet with the
 * bulk fields) and its Records, Machine Wash Output, Manual Daily Bulk
 * Production, Direct and General Fabric Booking, the Trims Dyeing Programme,
 * Check Roll Parameter Setup (a scaffold in Brickland, built here) and
 * Dyeing & Finishing Closing. A paper named in the address (?doc=) opens on
 * its editor tab.
 */

const look = () => apiFetch("/dyehouse/production-board/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/dye-house/${key}`;
const yes = (m, v) => (v ? m("opt.yes") : m("opt.no"));
const filterBar = (m, key, q, fields) => <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "CLOSED", "CANCELLED"];
const OPEN = ["DRAFT", "SUBMITTED", "APPROVED"];

const bulkProduction = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, batches, o] = await Promise.all([apiFetch("/dyehouse/productions?scope=BULK"), apiFetch("/dyehouse/production/batches?scope=BULK"), look()]);
  const shown = rows
    .filter((r) => has(r.batch_no, q.batch) && within(r.production_date, q.from, q.to) && (!q.machine || r.machine === q.machine))
    .map((r) => ({ ...r, id: `${r.batch_no}|${r.production_date}`, bypass_label: yes(m, r.bypass_check_roll), scouring_label: yes(m, r.scouring), status_label: r.last_status ? m(`opt.load_status.${r.last_status}`) : null }));
  return {
    tabs: [recordsTab(te, rows.length), { key: "entry", label: m("tab.batch_production"), icon: "Factory" }],
    body: () =>
      tab === "entry" ? (
        <BatchProduction bulk batches={batches} machines={o.machines.filter((x) => !x.is_sample)} canManage={canManage} load={actions.loadProduction} save={actions.saveProduction} title={m("sheet.title")} />
      ) : (
        <>
          {filterBar(m, "batch-production", q, [
            { name: "batch", label: m("f.batch_no"), type: "text", placeholder: "B-26110001" },
            { name: "from", label: m("f.date_from"), type: "date" },
            { name: "to", label: m("f.date_to"), type: "date" },
            { name: "machine", label: m("f.machine"), type: "select", options: o.machines.map((x) => ({ value: x.label, label: x.label })) },
          ])}
          <RecordsGrid
            exportName="bulk-batch-production"
            rows={shown}
            emptyState={{ title: m("records.empty_production"), description: m("records.empty_production_hint") }}
            columns={[
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "production_date", label: m("col.production_date"), type: "date" },
              { key: "lot_no", label: m("col.lot") },
              { key: "machine", label: m("col.machine") },
              { key: "colour", label: m("col.colour") },
              { key: "buyer", label: m("col.buyer") },
              { key: "order_no", label: m("col.order_no") },
              { key: "weight_kg", label: m("col.weight"), type: "number", align: "right" },
              { key: "lab_dip_ref", label: m("col.lab_dip") },
              { key: "liquor_ratio", label: m("col.liquor_ratio") },
              { key: "bypass_label", label: m("col.bypass"), type: "badge" },
              { key: "scouring_label", label: m("col.scouring"), type: "badge" },
              { key: "loads", label: m("col.loads"), type: "number", align: "right" },
              { key: "duration", label: m("col.duration"), align: "right" },
              { key: "status_label", label: m("col.last_status"), type: "badge" },
            ]}
          />
        </>
      ),
  };
};

const washOutput = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/wash-productions"), look()]);
  const key = "machine-wash-production";
  const shown = rows.filter((r) => has(r.req_no, q.no) && within(r.production_date, q.from, q.to) && (!q.machine || r.machine === q.machine));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m("tab.wash"), icon: "Droplets" }] : [])],
    body: () =>
      tab === "entry" && canManage ? (
        <WashProductionEditor productions={rows} options={o} canManage={canManage} load={actions.loadWash} save={actions.saveWash} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.requisition_no"), type: "text", placeholder: "IR-DC-2026110003" },
            { name: "from", label: m("f.date_from"), type: "date" },
            { name: "to", label: m("f.date_to"), type: "date" },
            { name: "machine", label: m("f.machine"), type: "select", options: o.machines.map((x) => ({ value: x.label, label: x.label })) },
          ])}
          <ChemRegister
            exportName={key}
            rows={shown}
            refKey="req_no"
            columns={[
              { key: "req_no", label: m("col.req_no"), type: "mono" },
              { key: "req_date", label: m("col.req_date"), type: "date" },
              { key: "production_date", label: m("col.production_date"), type: "date" },
              { key: "company", label: m("col.company") },
              { key: "department", label: m("col.department") },
              { key: "machine", label: m("col.machine") },
              { key: "requisition_to", label: m("col.req_to") },
              { key: "items", label: m("col.items"), type: "number", align: "right" },
              { key: "total_kg", label: m("col.total_kg"), type: "number", align: "right" },
              { key: "remarks", label: m("col.remarks") },
            ]}
            actions={canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` }] : []}
          />
        </>
      ),
  };
};

const manualDaily = async ({ canManage }) => {
  const rows = await apiFetch("/dyehouse/production-loads");
  return { body: () => <ManualDailyGrid rows={rows} canManage={canManage} save={actions.saveProductionLoads} /> };
};

/** A booking or trim programme screen: its register and the editor tab. */
function programmeScreen(kind, key) {
  return async ({ m, te, tab, canManage, searchParams: q = {} }) => {
    const trim = kind === "TRIM";
    const [rows, o] = await Promise.all([apiFetch(trim ? "/dyehouse/trim-programmes" : `/dyehouse/fabric-bookings?kind=${kind}`), look()]);
    const noKey = trim ? "programme_no" : "booking_no";
    const shown = rows
      .filter((r) => has(r[noKey], q.no) && within(r.prog_date, q.from, q.to) && (!q.type || r.prod_type === q.type) && (!q.status || r.status === q.status))
      .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && has(r.style_order, q.style))
      .map((r) => ({ ...r, status_label: m(`opt.status.${r.status}`), type_label: m(`opt.prod_type.${r.prod_type}`) }));
    const filters = [
      { name: "from", label: m("f.date_from"), type: "date" },
      { name: "to", label: m("f.date_to"), type: "date" },
      { name: "type", label: m("f.prod_type"), type: "select", options: opts(m, "prod_type", ["BULK", "SAMPLE"]) },
      ...(kind === "GENERAL"
        ? []
        : [
            { name: "buyer", label: m("f.buyer"), type: "select", options: o.buyers },
            { name: "style", label: m("f.style_order"), type: "text" },
          ]),
      { name: "status", label: m("f.status"), type: "select", options: opts(m, "status", STATUSES) },
    ];
    const columns = [
      { key: "prog_date", label: m("col.date"), type: "date" },
      { key: noKey, label: m("col.req_hash"), type: "mono" },
      { key: "type_label", label: m("col.prod_type") },
      ...(trim ? [] : [{ key: "req_type", label: m("col.req_type") }]),
      ...(kind === "GENERAL"
        ? []
        : [
            { key: "buyer", label: m("col.buyer_ac") },
            { key: "style_order", label: m("col.style_order") },
          ]),
      { key: "items", label: m("col.items"), type: "number", align: "right" },
      ...(trim ? [{ key: "yds", label: m("col.yds"), type: "number", align: "right" }] : []),
      { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
      { key: "remarks", label: m("col.remarks") },
      { key: "status_label", label: m("col.status"), type: "badge" },
    ];
    return {
      tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m(`tab.pg.${kind}`), icon: trim ? "Ribbon" : "BookOpen" }] : [])],
      body: () =>
        tab === "entry" && canManage ? (
          <ProgrammeEditor kind={kind} papers={rows} options={o} canManage={canManage} load={trim ? actions.loadTrim : actions.loadBooking} save={trim ? actions.saveTrim : actions.saveBooking} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
        ) : (
          <>
            {filterBar(m, key, q, [{ name: "no", label: m("f.req_hash"), type: "text", placeholder: trim ? "TDP-2026110001" : kind === "DIRECT" ? "DFB-2026110001" : "GFB-2026110001" }, ...filters])}
            <ChemRegister exportName={key} rows={shown} refKey={noKey} columns={columns} actions={canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "status", in: OPEN } }] : []} />
          </>
        ),
    };
  };
}

const checkRollParams = async ({ m, te, tab, canManage }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-check-roll-params"), look()]);
  const fields = [
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: [{ value: "", label: m("f.every_buyer") }, ...o.buyers], placeholder: m("f.every_buyer"), help: m("h.param_buyer") },
    { name: "fabric_type_id", label: m("f.fabric_type"), type: "select", options: [{ value: "", label: m("f.every_fabric") }, ...o.fabric_types], placeholder: m("f.every_fabric") },
    { name: "gsm_tol_pct", label: m("f.gsm_tol"), type: "number", required: true, placeholder: "5", help: m("h.gsm_tol") },
    { name: "width_tol_pct", label: m("f.width_tol"), type: "number", required: true, placeholder: "3", help: m("h.width_tol") },
    { name: "shade_delta_e", label: m("f.shade_tol"), type: "number", required: true, placeholder: "1", help: m("h.shade_tol") },
    { name: "shrink_length_pct", label: m("f.shrink_length"), type: "number", placeholder: "5" },
    { name: "shrink_width_pct", label: m("f.shrink_width"), type: "number", placeholder: "5" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows.map((r) => ({
    ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])),
    buyer_label: r.buyer ?? m("f.every_buyer"),
    fabric_label: r.fabric_type ?? m("f.every_fabric"),
    active_label: yes(m, r.active),
    ref: `${r.buyer ?? "all"}-${r.fabric_type ?? "all"}`,
  }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-check-roll-params", ["active"])} title={m("t.new_param")} description={m("h.param_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} />
      ) : (
        <RecordsGrid
          exportName="check-roll-parameters"
          rows={shown}
          emptyState={{ title: m("records.empty_param"), description: m("records.empty_param_hint") }}
          columns={[
            { key: "buyer_label", label: m("col.buyer") },
            { key: "fabric_label", label: m("col.fabric_type") },
            { key: "gsm_tol_pct", label: m("col.gsm_tol"), type: "number", align: "right" },
            { key: "width_tol_pct", label: m("col.width_tol"), type: "number", align: "right" },
            { key: "shade_delta_e", label: m("col.shade_tol"), type: "number", align: "right" },
            { key: "shrink_length_pct", label: m("col.shrink_length"), type: "number", align: "right" },
            { key: "shrink_width_pct", label: m("col.shrink_width"), type: "number", align: "right" },
            { key: "active_label", label: m("col.active"), type: "badge" },
            { key: "remarks", label: m("col.remarks") },
          ]}
          edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-check-roll-params", ["active"]), refKey: "ref", title: m("t.new_param"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
          remove={canManage ? actions.deleteResource.bind(null, "dye-check-roll-params") : undefined}
        />
      ),
  };
};

const dfClosing = async ({ m, permissions = [], searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/df-closings"), look()]);
  const months = [...new Set(rows.map((r) => r.ship_month).filter(Boolean))].sort().map((x) => ({ value: x, label: x }));
  const shown = rows
    .filter((r) => (!q.type || r.scope === q.type) && (!q.buyer || r.buyer_id === q.buyer) && (!q.ship || r.ship_month === q.ship) && (!q.status || r.status === q.status))
    .filter((r) => (has(r.job_no, q.job) || has(r.style, q.job)) && (has(r.order_no, q.order) || has(r.style, q.order)))
    .map((r) => ({
      ...r,
      cat: m(`opt.prod_type.${r.scope}`),
      order_info: [r.job_no, r.style, r.order_no].filter(Boolean).join(" / "),
      type_ship: [r.order_type ? m(`opt.order_type.${r.order_type}`) : null, r.ship_date].filter(Boolean).join(" / "),
      status_label: m(`opt.df_status.${r.status}`),
    }));
  return {
    body: () => (
      <>
        {filterBar(m, "dyeing-and-finishing-close", q, [
          { name: "type", label: m("f.type"), type: "select", options: opts(m, "prod_type", ["BULK", "SAMPLE"]) },
          { name: "buyer", label: m("f.buyer"), type: "select", options: o.buyers },
          { name: "ship", label: m("f.ship_month"), type: "select", options: months },
          { name: "job", label: m("f.job_style"), type: "text" },
          { name: "order", label: m("f.order_style"), type: "text" },
          { name: "status", label: m("f.status"), type: "select", options: opts(m, "df_status", ["RUNNING", "CLOSED"]) },
        ])}
        <ChemRegister
          exportName="dyeing-finishing-closing"
          rows={shown}
          refKey="key"
          labelKey="order_no"
          columns={[
            { key: "cat", label: m("col.cat") },
            { key: "buyer", label: m("col.buyer_ac") },
            { key: "order_info", label: m("col.job_style_order") },
            { key: "type_ship", label: m("col.order_type_ship") },
            { key: "fabrics", label: m("col.fab"), type: "number", align: "right" },
            { key: "booked_kg", label: m("col.bk_rpt"), type: "number", align: "right" },
            { key: "req_qty", label: m("col.req_qty"), type: "number", align: "right" },
            { key: "batches", label: m("col.batches"), type: "number", align: "right" },
            { key: "status_label", label: m("col.status"), type: "badge" },
          ]}
          actions={
            permissions.includes("dyehouse.production.close")
              ? [{ key: "close", label: m("reg.close"), title: m("reg.close_title", { no: ":no" }), text: m("reg.close_text"), done: m("reg.closed"), run: actions.closeDfOrder, when: { field: "status", in: ["RUNNING"] }, destructive: true }]
              : []
          }
        />
      </>
    ),
  };
};

const PROD_SCREENS = {
  "batch-production": bulkProduction,
  "machine-wash-production": washOutput,
  "daily-batch-production-for-bulk-manual": manualDaily,
  "direct-fabric-booking": programmeScreen("DIRECT", "direct-fabric-booking"),
  "general-fabric-booking": programmeScreen("GENERAL", "general-fabric-booking"),
  "dyeing-program-for-trims": programmeScreen("TRIM", "dyeing-program-for-trims"),
  "check-roll-parameter": checkRollParams,
  "dyeing-and-finishing-close": dfClosing,
};

// The screens whose paper opens on its editor when named in the address (?doc=).
const PROD_EDITORS = ["machine-wash-production", "direct-fabric-booking", "general-fabric-booking", "dyeing-program-for-trims"];

export { PROD_SCREENS, PROD_EDITORS };
