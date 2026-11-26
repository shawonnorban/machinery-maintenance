import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { CatalogCard } from "@/components/erp/catalog-card";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { UrlFilters } from "@/components/erp/url-filters";
import { Projections } from "@/components/planning/projections";
import { MasterPlan } from "@/components/planning/master-plan";
import { MasterPlanList, RevisionRequests, DailyPlanGrid } from "@/components/planning/plan-lists";
import { DailyPlan } from "@/components/planning/daily-plan";
import { PlanVsActual } from "@/components/planning/plan-vs-actual";
import { LineCapacity } from "@/components/planning/line-capacity";
import { PlanTimeline } from "@/components/planning/plan-timeline";
import * as actions from "./actions";

/*
 * Capacity Planning → Master Data (AnnotechERP 12/01): Factory Calendar,
 * Production Line, the machines, manpower and shifts of each line, Efficiency
 * Master, SMV / SAM Master and Other Setup — each laid out as Brickland lays
 * it out. Every screen returns its tabs and its body.
 */

const lookups = () => apiFetch("/planning/lookups");
const options = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const status = (m) => ({ key: "active", label: m("f.status"), type: "status" });
const num = (v) => (v === null || v === undefined ? null : String(Number(v)));

/** Brickland's Records + New over one resource. */
function recordsScreen(config) {
  return async function render({ m, te, tab, canManage }) {
    const extra = config.load ? await config.load() : {};
    const rows = (await apiFetch(`/erp/${config.resource}`)).map((row) => (config.decorate ? config.decorate(row, { m, ...extra }) : row));
    const fields = config.fields({ m, ...extra });
    const booleans = config.booleans ?? ["active"];
    return {
      tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m(config.newLabel), icon: "Plus" }] : [])],
      body: () =>
        tab === "new" && canManage ? (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, config.resource, booleans)}
            title={m(config.newLabel)}
            description={config.hint ? m(config.hint) : undefined}
            submitLabel={m(config.saveLabel ?? "save")}
            columns={3}
            successHref={`/capacity-planning/${config.screen}?tab=records`}
          />
        ) : (
          <RecordsGrid
            exportName={config.screen}
            rows={rows}
            columns={config.columns({ m })}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, config.resource, booleans), title: m(config.editLabel), submitLabel: m("save"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, config.resource) : undefined}
          />
        ),
    };
  };
}

/** The machines, manpower and shifts of each line: a Line filter, a New dialog, and the list. */
function lineScreen(config) {
  return async function render({ m, canManage, searchParams = {} }) {
    const { lines, shifts } = await lookups();
    const all = (await apiFetch(`/erp/${config.resource}`)).map((row) => (config.decorate ? config.decorate(row, { m }) : row));
    // Brickland's Line (and, for machines, Status) filters, applied as they change.
    const rows = all.filter((r) => (!searchParams.line || r.planning_line_id === searchParams.line) && (!searchParams.status || String(r.active) === searchParams.status));
    const fields = config.fields({ m, lines: lines.filter((l) => l.active), shifts });
    const booleans = config.booleans ?? [];
    return {
      tabs: [],
      body: () => (
        <CatalogCard
          title={m(`${config.key}.title`, { count: rows.length })}
          toolbar={
            <UrlFilters
              filters={[
                { name: "line", label: m("f.line"), options: lines, allLabel: m("all") },
                ...(config.statusFilter ? [{ name: "status", label: m("f.status"), options: [{ value: "true", label: m("active") }, { value: "false", label: m("inactive") }], allLabel: m("all") }] : []),
              ]}
              shown={m("shown", { shown: rows.length, total: all.length })}
              clearLabel={m("clear")}
            />
          }
          action={
            canManage ? (
              <DialogFormButton variant="primary" label={m(`${config.key}.new`)} title={m(`${config.key}.new`)} fields={fields} action={actions.createResource.bind(null, config.resource, booleans)} submitLabel={m("save")} />
            ) : null
          }
        >
          <RecordsGrid
            exportName={config.screen}
            rows={rows}
            columns={[{ key: "floor", label: m("f.floor") }, { key: "line", label: m("f.floor_line") }, { key: "planning_line", label: m("f.planning_line") }, ...config.columns({ m })]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, config.resource, booleans), title: m(`${config.key}.edit`), submitLabel: m("save") } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, config.resource) : undefined}
          />
        </CatalogCard>
      ),
    };
  };
}

const productionLine = recordsScreen({
  resource: "planning-lines",
  screen: "planning-line-setup",
  newLabel: "line.new",
  editLabel: "line.edit",
  saveLabel: "line.save",
  hint: "line.hint",
  booleans: ["under_maintenance", "active"],
  load: lookups,
  fields: ({ m, floors, floor_lines }) => [
    { name: "floor_id", label: m("f.floor"), type: "select", required: true, options: floors },
    { name: "code", label: m("f.line_code"), required: true },
    { name: "name", label: m("f.line_name"), required: true },
    { name: "floor_line_id", label: m("f.floor_line_output"), type: "select", options: [{ value: "", label: "—" }, ...floor_lines], help: m("f.floor_line_hint") },
    { name: "std_manpower", label: m("f.std_manpower"), type: "number", required: true, default: "0" },
    { name: "std_machines", label: m("f.std_machines"), type: "number", required: true, default: "0" },
    { name: "std_hours", label: m("f.std_hours"), type: "number", required: true, default: "10" },
    { name: "std_efficiency", label: m("f.std_efficiency"), type: "number", required: true, default: "65" },
    { name: "best_fit", label: m("f.best_fit") },
    { name: "display_order", label: m("f.display_order"), type: "number" },
    { name: "under_maintenance", label: m("f.under_maintenance"), type: "checkbox" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row) => ({
    ...row,
    floor_line_id: row.floor_line_id ?? "",
    std_hours: num(row.std_hours),
    std_efficiency: num(row.std_efficiency),
    std_manpower: num(row.std_manpower),
    std_machines: num(row.std_machines),
    display_order: num(row.display_order),
    efficiency_label: `${Number(row.std_efficiency)}%`,
  }),
  columns: ({ m }) => [
    { key: "unit", label: m("f.unit"), type: "mono" },
    { key: "floor", label: m("f.floor"), type: "mono" },
    { key: "code", label: m("f.line"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "std_manpower", label: m("f.manpower"), type: "number", align: "right" },
    { key: "std_machines", label: m("f.machine"), type: "number", align: "right" },
    { key: "std_hours", label: m("f.hours"), type: "number", align: "right" },
    { key: "efficiency_label", label: m("f.efficiency"), align: "right" },
    { key: "floor_line", label: m("f.floor_line_col"), type: "mono" },
    status(m),
  ],
});

const MACHINE_CATEGORIES = ["SEWING", "SPECIAL", "FINISHING", "OTHER"];
const machines = lineScreen({
  statusFilter: true,
  resource: "planning-line-machines",
  screen: "machine-master",
  key: "machine",
  booleans: ["active"],
  fields: ({ m, lines }) => [
    { name: "planning_line_id", label: m("f.planning_line"), type: "select", required: true, options: lines },
    { name: "machine_type", label: m("f.machine_type"), required: true, placeholder: "Single Needle Lockstitch" },
    { name: "name_code", label: m("f.name_code"), placeholder: "SNLS" },
    { name: "category", label: m("f.category"), type: "select", required: true, options: options(m, "machine_category", MACHINE_CATEGORIES) },
    { name: "installed", label: m("f.installed"), type: "number", required: true },
    { name: "working", label: m("f.working"), type: "number" },
    { name: "standby", label: m("f.standby"), type: "number" },
    { name: "capacity_per_hour", label: m("f.capacity_per_hour"), type: "number" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, category_label: m(`opt.machine_category.${row.category}`), installed: num(row.installed), working: num(row.working), standby: num(row.standby), capacity_per_hour: num(row.capacity_per_hour) }),
  columns: ({ m }) => [
    { key: "machine_type", label: m("f.machine_type") },
    { key: "name_code", label: m("f.name_code"), type: "mono" },
    { key: "category_label", label: m("f.category") },
    { key: "installed", label: m("f.installed"), type: "number", align: "right" },
    { key: "working", label: m("f.working"), type: "number", align: "right" },
    { key: "standby", label: m("f.standby"), type: "number", align: "right" },
    { key: "capacity_per_hour", label: m("f.cap_hour"), type: "number", align: "right" },
    status(m),
  ],
});

const ROLES = ["OPERATOR", "HELPER", "IRONMAN", "QC", "SUPERVISOR", "OTHER"];
const manpower = lineScreen({
  resource: "planning-line-manpower",
  screen: "manpower-master",
  key: "manpower",
  fields: ({ m, lines }) => [
    { name: "planning_line_id", label: m("f.planning_line"), type: "select", required: true, options: lines },
    { name: "role", label: m("f.role"), type: "select", required: true, options: options(m, "role", ROLES) },
    { name: "required", label: m("f.required"), type: "number", required: true },
    { name: "remarks", label: m("f.remarks") },
  ],
  decorate: (row, { m }) => ({ ...row, role_label: m(`opt.role.${row.role}`), required: num(row.required) }),
  columns: ({ m }) => [
    { key: "role_label", label: m("f.role") },
    { key: "required", label: m("f.required"), type: "number", align: "right" },
    { key: "remarks", label: m("f.remarks") },
  ],
});

const shifts = lineScreen({
  resource: "planning-line-shifts",
  screen: "shift-master",
  key: "shift",
  booleans: ["active"],
  fields: ({ m, lines, shifts }) => [
    { name: "planning_line_id", label: m("f.planning_line"), type: "select", required: true, options: lines },
    { name: "hr_shift_id", label: m("f.shift"), type: "select", required: true, options: shifts ?? [], help: m("f.shift_from_hr") },
    { name: "manpower", label: m("f.manpower"), type: "number" },
    { name: "target", label: m("f.target"), type: "number" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row) => ({ ...row, break_minutes: num(row.break_minutes), manpower: num(row.manpower), target: num(row.target), working_hours: num(row.working_hours) }),
  columns: ({ m }) => [
    { key: "shift_name", label: m("f.shift") },
    { key: "time", label: m("f.time"), type: "mono" },
    { key: "break_minutes", label: m("f.break"), type: "number", align: "right" },
    { key: "working_hours", label: m("f.working_hours"), type: "number", align: "right" },
    { key: "manpower", label: m("f.manpower"), type: "number", align: "right" },
    { key: "target", label: m("f.target"), type: "number", align: "right" },
    status(m),
  ],
});

const APPLIES_TO = ["STYLE", "BUYER", "LINE", "UNIT"];
const efficiency = recordsScreen({
  resource: "planning-efficiencies",
  screen: "efficiency-master",
  newLabel: "efficiency.new",
  editLabel: "efficiency.edit",
  saveLabel: "efficiency.save",
  hint: "efficiency.hint",
  load: lookups,
  fields: ({ m, factories, lines, buyers }) => [
    { name: "applies_to", label: m("f.applies_to"), type: "select", required: true, options: options(m, "applies_to", APPLIES_TO), help: m("f.applies_to_hint") },
    { name: "factory_id", label: m("f.unit"), type: "select", options: [{ value: "", label: "—" }, ...factories], help: m("f.for_unit") },
    { name: "planning_line_id", label: m("f.line"), type: "select", options: [{ value: "", label: "—" }, ...lines], help: m("f.for_line") },
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: [{ value: "", label: "—" }, ...buyers], help: m("f.for_buyer") },
    { name: "style_no", label: m("f.style_no"), help: m("f.for_style") },
    { name: "efficiency", label: m("f.efficiency_pct"), type: "number", required: true },
    { name: "effective_from", label: m("f.effective_from"), type: "date", required: true },
    { name: "remarks", label: m("f.remarks") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({
    ...row,
    factory_id: row.factory_id ?? "",
    planning_line_id: row.planning_line_id ?? "",
    buyer_id: row.buyer_id ?? "",
    applies_label: m(`opt.applies_to.${row.applies_to}`),
    efficiency: num(row.efficiency),
    efficiency_label: `${Number(row.efficiency)}%`,
  }),
  columns: ({ m }) => [
    { key: "applies_label", label: m("f.applies_to") },
    { key: "target", label: m("f.applies_for") },
    { key: "efficiency_label", label: m("f.efficiency"), align: "right" },
    { key: "effective_from", label: m("f.effective_from"), type: "date" },
    { key: "remarks", label: m("f.remarks") },
    status(m),
  ],
});

const smv = recordsScreen({
  resource: "planning-style-smvs",
  screen: "smv-sam-master",
  newLabel: "smv.new",
  editLabel: "smv.edit",
  saveLabel: "smv.save",
  fields: ({ m }) => [
    { name: "style_no", label: m("f.style_no"), required: true },
    { name: "smv", label: m("f.smv_sewing"), type: "number" },
    { name: "sam", label: m("f.sam_garment"), type: "number" },
    { name: "remarks", label: m("f.remarks") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({
    ...row,
    smv_label: row.smv === null ? null : Number(row.smv).toFixed(2),
    sam_label: row.sam === null ? null : Number(row.sam).toFixed(2),
    smv: num(row.smv),
    sam: num(row.sam),
    ie_label: row.ie_smv === null ? null : Number(row.ie_smv).toFixed(2),
    uses_label: m(`smv.uses.${row.planning_source}`, { value: Number(row.planning_smv).toFixed(2) }),
  }),
  columns: ({ m }) => [
    { key: "style_no", label: m("f.style_no"), type: "mono" },
    { key: "smv_label", label: m("f.smv"), align: "right" },
    { key: "sam_label", label: m("f.sam"), align: "right" },
    { key: "ie_label", label: m("f.ie_smv"), align: "right" },
    { key: "uses_label", label: m("f.planning_uses") },
    { key: "remarks", label: m("f.remarks") },
    status(m),
  ],
});

const COUNTED_FROM = ["BEFORE_CUTTING", "BEFORE_FIRST_SEWING", "AFTER_LAST_SEWING"];
async function otherSetup({ m, canManage }) {
  const rows = (await apiFetch("/erp/planning-lead-times")).map((row) => ({
    ...row,
    activity_label: m(`opt.activity.${row.activity}`),
    counted_label: m(`opt.counted_from.${row.counted_from}`),
    changed_label: row.changed ? m("setup.changed") : m("setup.default"),
    lead_days: String(row.lead_days),
  }));
  const fields = [
    { name: "lead_days", label: m("f.lead_days"), type: "number", required: true },
    { name: "counted_from", label: m("f.counted_from"), type: "select", required: true, options: options(m, "counted_from", COUNTED_FROM) },
    { name: "remarks", label: m("f.remarks") },
  ];
  return {
    tabs: [],
    body: () => (
      <RecordsGrid
        exportName="planning-setup"
        rows={rows}
        columns={[
          { key: "activity_label", label: m("f.activity") },
          { key: "lead_days", label: m("f.lead_days"), type: "number", align: "right" },
          { key: "counted_label", label: m("f.counted_from") },
          { key: "remarks", label: m("f.remarks") },
          { key: "changed_label", label: m("f.last_changed") },
        ]}
        edit={canManage ? { fields, action: actions.updateResource.bind(null, "planning-lead-times", []), title: m("setup.edit"), submitLabel: m("save") } : undefined}
      />
    ),
  };
}

/* Production Plan (12/02): each screen reads its first view here; everything after happens in place. */
const planPerms = (permissions = []) => ({
  manage: permissions.includes("planning.plan.manage"),
  approve: permissions.includes("planning.plan.approve"),
});
const nowYm = () => {
  const [y, m] = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date()).split("-");
  return [Number(y), Number(m)];
};

async function projections() {
  const [y, m] = nowYm();
  const data = await apiFetch(`/planning/projections?year=${y}&month=${m}`);
  return { tabs: [], body: () => <Projections data={data} act={actions.planAction} /> };
}

/** Opened on an order (`?order=LPP-2026-000025`) or a plan version (`?plan=MP-2026110001&v=1`) — numbers, never keys. */
async function masterPlan({ searchParams = {}, permissions = [] }) {
  const orderNo = typeof searchParams.order === "string" ? searchParams.order : "";
  const planNo = typeof searchParams.plan === "string" ? searchParams.plan : "";
  const v = typeof searchParams.v === "string" ? searchParams.v : "";
  const [plan, order] = await Promise.all([
    planNo ? apiFetch(`/planning/master-plans/${encodeURIComponent(planNo)}${v ? `?v=${encodeURIComponent(v)}` : ""}`).catch(() => null) : null,
    orderNo ? apiFetch(`/planning/master-plans/order/${encodeURIComponent(orderNo)}`).catch(() => null) : null,
  ]);
  // A draft version opens in the editor, through its order.
  const draftOrder = plan?.status === "DRAFT" ? await apiFetch(`/planning/master-plans/order/${encodeURIComponent(plan.order.order_no)}`).catch(() => null) : null;
  const ship = plan?.ship_date ?? order?.order?.ship_date ?? draftOrder?.order?.ship_date;
  const [y, m] = ship ? [Number(ship.slice(0, 4)), Number(ship.slice(5, 7))] : nowYm();
  const [orders, list] = await Promise.all([apiFetch(`/planning/master-plans/orders?year=${y}&month=${m}`), apiFetch("/planning/master-plans")]);
  const initial = { year: y, month: m, orders, list, order: draftOrder ?? order, plan: plan && plan.status !== "DRAFT" ? plan : null };
  return { tabs: [], body: () => <MasterPlan key={`${planNo}${v}${orderNo}`} initial={initial} perms={planPerms(permissions)} act={actions.planAction} /> };
}

async function masterPlanList() {
  const rows = await apiFetch("/planning/master-plans");
  return { tabs: [], body: () => <MasterPlanList rows={rows} act={actions.planAction} /> };
}

async function revisionRequest({ permissions = [] }) {
  const rows = await apiFetch("/planning/revision-requests");
  return { tabs: [], body: () => <RevisionRequests rows={rows} perms={planPerms(permissions)} act={actions.planAction} /> };
}

const today = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
const isDate = (v) => typeof v === "string" && /^\d{4}-\d{2}-\d{2}$/.test(v);

/** Opened on a day and an order (`?date=2026-11-05&order=LPP-2026-000025`) — Daily Plan List's Open, Master Plan's Daily Plan. */
async function dailyPlan({ searchParams = {}, permissions = [] }) {
  const date = isDate(searchParams.date) ? searchParams.date : today();
  const order = typeof searchParams.order === "string" ? searchParams.order : "";
  const [orders, day] = await Promise.all([
    apiFetch("/planning/daily-plans/orders"),
    apiFetch(`/planning/daily-plans/day?${new URLSearchParams(order ? { date, order } : { date })}`).catch(() => apiFetch(`/planning/daily-plans/day?date=${date}`)),
  ]);
  return { tabs: [], body: () => <DailyPlan key={`${date}${order}`} initial={day} orders={orders} perms={planPerms(permissions)} act={actions.planAction} /> };
}

/** Brickland's Date from – to, Line and Status over the saved days, applied as they change; Open goes to the day. */
async function dailyPlanList({ p, searchParams = {} }) {
  const keep = Object.fromEntries(["from", "to", "line", "status"].filter((k) => typeof searchParams[k] === "string" && searchParams[k]).map((k) => [k, searchParams[k]]));
  const [{ rows, lines }, all] = await Promise.all([
    apiFetch(`/planning/daily-plans?${new URLSearchParams(keep)}`),
    Object.keys(keep).length ? apiFetch("/planning/daily-plans").then((d) => d.rows.length) : null,
  ]);
  return {
    tabs: [],
    body: () => (
      <div className="flex flex-col gap-4">
        <section className="rounded-xl border border-border bg-surface p-4">
          <UrlFilters
            filters={[
              { name: "from", label: p("daily.list.date"), type: "date" },
              { name: "to", label: p("daily.list.to"), type: "date" },
              { name: "line", label: p("daily.list.line"), options: lines, allLabel: p("daily.list.all") },
              { name: "status", label: p("daily.list.status"), options: ["PLANNED", "CONFIRMED"].map((v) => ({ value: v, label: p(`daily.row.${v}`) })), allLabel: p("daily.list.all") },
            ]}
            shown={p("daily.list.shown", { shown: rows.length, total: all ?? rows.length })}
            clearLabel={p("daily.list.clear")}
          />
        </section>
        <DailyPlanGrid rows={rows} />
      </div>
    ),
  };
}

/** The tiles and orders, and the last seven days line by line (`?from=&to=&line=`). */
async function planVsActual({ searchParams = {} }) {
  const to = isDate(searchParams.to) ? searchParams.to : today();
  const back = new Date(`${to}T00:00:00Z`);
  back.setUTCDate(back.getUTCDate() - 6);
  const from = isDate(searchParams.from) ? searchParams.from : back.toISOString().slice(0, 10);
  const line = typeof searchParams.line === "string" ? searchParams.line : "";
  const data = await apiFetch(`/planning/plan-vs-actual?${new URLSearchParams(line ? { from, to, line } : { from, to })}`);
  return { tabs: [], body: () => <PlanVsActual data={data} window={{ from, to, line }} act={actions.planAction} /> };
}

/** A month (`?year=&month=`), this month at first. */
const monthQuery = (searchParams) => {
  const [y, m] = nowYm();
  const year = Number(searchParams.year) || y;
  const month = Number(searchParams.month) || m;
  return new URLSearchParams({ year, month });
};

async function lineCapacityView({ searchParams = {} }) {
  const data = await apiFetch(`/planning/line-capacity?${monthQuery(searchParams)}`);
  return { tabs: [], body: () => <LineCapacity data={data} act={actions.planAction} /> };
}

async function planTimeline({ searchParams = {} }) {
  const data = await apiFetch(`/planning/timeline?${monthQuery(searchParams)}`);
  return { tabs: [], body: () => <PlanTimeline data={data} act={actions.planAction} /> };
}

const SCREENS = {
  projections,
  "master-plan": masterPlan,
  "master-plan-list": masterPlanList,
  "revision-request": revisionRequest,
  "daily-plan": dailyPlan,
  "daily-plan-list": dailyPlanList,
  "plan-vs-actual": planVsActual,
  "line-capacity-view": lineCapacityView,
  "plan-timeline": planTimeline,
  "planning-line-setup": productionLine,
  "machine-master": machines,
  "manpower-master": manpower,
  "shift-master": shifts,
  "efficiency-master": efficiency,
  "smv-sam-master": smv,
  "planning-setup": otherSetup,
};

export { SCREENS };
