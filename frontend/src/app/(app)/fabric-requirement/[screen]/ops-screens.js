import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { GroupedRecords } from "@/components/erp/grouped-records";
import { StatusAreasEditor, StatusRulesEditor, SectionDaysEditor, ScheduleBoardEditor } from "@/components/fabricplanning/ops-editors";
import { BatchPlanner } from "@/components/fabricplanning/batch-planner";
import * as actions from "./actions";

/*
 * Fabric Requirement → Operations (AnnotechERP 13/02): Planning Constraint
 * Ruleset, Fabric Batch Plan Entry, Factory Calendar & Holidays, Dye Machine
 * Capacity Board and Batch Schedule Board (13/02), and Fabric Requirement
 * Reports (13/03) — each Brickland's Records, its own
 * middle tab (a group chosen on the left, its editor beside it; or the batch
 * planner) and its New form. A chosen group travels in the address by its
 * number or code, never its key.
 */

const options = () => apiFetch("/fabric-planning/options");
const opt = (o, group, values) => values.map((v) => ({ value: v, label: o(`opt.${group}.${v}`) }));
const num = (v) => (v === null || v === undefined ? null : String(Number(v)));
const kg = (v) => (v === null || v === undefined ? null : Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 }));
const labelOf = (list, value) => list.find((x) => x.value === value)?.label ?? null;
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });

/** Brickland's two-pane tab: the groups on the left, the chosen group's editor beside it. */
function twoPane({ groups, active, base, tab, title, pick, children }) {
  return (
    <GroupedRecords
      groups={groups}
      active={active}
      hrefFor={(key) => `${base}?tab=${tab}&group=${encodeURIComponent(key)}`}
      groupsTitle={title}
      recordsTitle=""
      emptyText={pick}
    >
      {children}
    </GroupedRecords>
  );
}

/* ---------------- Planning Constraint Ruleset ---------------- */

async function constraints({ o, te, tab, canManage, searchParams = {} }) {
  const base = "/fabric-requirement/planning-constraints-rules";
  const [opts, rows] = await Promise.all([options(), apiFetch("/erp/fp-status-areas")]);
  const groups = opts.groups.map((g) => ({ key: g.key, label: g.label, sub: o("area.group_sub", { areas: g.areas, rules: g.rules }), count: g.areas + g.rules }));
  const groupChoices = opts.groups.map((g) => ({ value: g.id, label: g.label }));
  const fields = (edit) => [
    { name: "group_id", label: o("f.group"), type: "select", required: true, options: groupChoices },
    { name: "sl", label: o("f.sl"), type: "number", required: true, default: "1" },
    { name: "code", label: o("f.code"), required: true, readOnly: edit },
    { name: "name", label: o("f.area"), required: true },
    ...(edit ? [{ name: "parent_id", label: o("f.parent"), type: "select", options: [{ value: "", label: "—" }, ...rows.map((r) => ({ value: r.id, label: `${r.code} — ${r.name}` }))], help: o("f.parent_help") }] : []),
    { name: "wt_factor", label: o("f.wt"), type: "number", required: true, default: "1" },
    { name: "active", label: o("f.active"), type: "checkbox", default: true },
  ];
  const active = typeof searchParams.group === "string" ? searchParams.group : null;
  const group = groups.find((g) => g.key === active) ?? null;
  const tabs = [
    recordsTab(te, rows.length),
    { key: "areas", label: o("tabs.areas"), icon: "ListTree" },
    { key: "rules", label: o("tabs.rules"), icon: "ListChecks" },
    ...(canManage ? [{ key: "new", label: o("tabs.new_area"), icon: "Plus" }] : []),
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return <EntryForm fields={fields(false)} action={actions.createResource.bind(null, "fp-status-areas", ["active"])} title={o("tabs.new_area")} submitLabel={o("save")} columns={3} successHref={`${base}?tab=records`} />;
      if (tab === "areas" || tab === "rules") {
        const sl = group?.key;
        const data = sl ? await apiFetch(`/fabric-planning/status-groups/${sl}/${tab}`) : null;
        return twoPane({
          groups,
          active: group?.key ?? null,
          base,
          tab,
          title: o("area.groups"),
          pick: `${o("area.pick")} — ${o(tab === "areas" ? "area.pick_hint" : "rule.pick_hint")}`,
          children: group ? (
            tab === "areas" ? (
              <StatusAreasEditor key={`a${sl}`} group={group} rows={data} save={actions.opsAction.bind(null, "saveAreas", sl)} canManage={canManage} />
            ) : (
              <StatusRulesEditor key={`r${sl}`} group={group} rows={data} save={actions.opsAction.bind(null, "saveRules", sl)} canManage={canManage} />
            )
          ) : null,
        });
      }
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">{o("area.records_title")}</p>
          <RecordsGrid
            exportName="planning-constraints"
            rows={rows.map((r) => ({ ...r, sl: num(r.sl), wt_factor: num(r.wt_factor), parent_id: r.parent_id ?? "", parent_label: r.parent }))}
            columns={[
              { key: "group", label: o("f.group") },
              { key: "sl", label: o("f.sl"), type: "number", align: "right" },
              { key: "code", label: o("f.code"), type: "mono" },
              { key: "parent_label", label: o("f.parent"), type: "mono" },
              { key: "name", label: o("f.area") },
              { key: "wt_factor", label: o("f.wt"), type: "number", align: "right" },
              { key: "active", label: o("f.status"), type: "status" },
            ]}
            edit={canManage ? { fields: fields(true), action: actions.updateResource.bind(null, "fp-status-areas", ["active"]), title: te("edit"), submitLabel: o("save"), columns: 3, refKey: "code" } : undefined}
          />
        </div>
      );
    },
  };
}

/* ---------------- Fabric Batch Plan Entry ---------------- */

const COLOUR_TYPES = ["SOLID", "MELANGE", "YARN_DYED", "AOP"];
const METHODS = ["EXHAUST", "COLD_PAD_BATCH", "CONTINUOUS", "PIGMENT"];
const STATUSES = ["DRAFT", "PLANNED", "SCHEDULED", "RELEASED", "CANCELLED"];

async function batchPlanning({ o, te, tab, canManage, searchParams = {} }) {
  const base = "/fabric-requirement/batch-planning";
  const [opts, plans] = await Promise.all([options(), apiFetch("/fabric-planning/batch-plans")]);
  const tabs = [recordsTab(te, plans.length), { key: "planning", label: o("tabs.planning"), icon: "Layers" }, ...(canManage ? [{ key: "new", label: o("tabs.new_plan"), icon: "Plus" }] : [])];
  return {
    tabs,
    body: async () => {
      if (tab === "planning") {
        const bp = typeof searchParams.bp === "string" ? searchParams.bp : "";
        const initial = bp ? await apiFetch(`/fabric-planning/batch-plans/${encodeURIComponent(bp)}`).catch(() => null) : null;
        return <BatchPlanner key={bp} initial={initial} plans={plans} options={opts} act={actions.opsAction} canManage={canManage} />;
      }
      if (tab === "new" && canManage) {
        const select = (name, label, choices, extra = {}) => ({ name, label, type: "select", options: [{ value: "", label: o("select") }, ...choices], ...extra });
        return (
          <EntryForm
            fields={[
              select("week_start", o("f.week"), opts.weeks, { required: true }),
              select("buyer_id", o("f.buyer"), opts.buyers),
              select("colour_type", o("f.colour_type"), opt(o, "colour_type", COLOUR_TYPES)),
              select("dyeing_method", o("f.method"), opt(o, "method", METHODS), { required: true }),
              select("dye_machine_id", o("f.machine"), opts.machines),
              { name: "style_job", label: o("f.style_job") },
              { name: "order_no", label: o("f.order_no") },
              { name: "order_cat", label: o("f.order_cat") },
              { name: "shipment_date", label: o("f.shipment"), type: "date" },
              { name: "booking_confirm_date", label: o("f.booking_confirm"), type: "date" },
              { name: "production_hours", label: o("f.prod_hr"), type: "number" },
              { name: "pending_qty", label: o("f.pending"), type: "number" },
              { name: "batches", label: o("f.batches"), type: "number", required: true, default: "1" },
              { name: "bp_qty", label: o("f.bp_qty"), type: "number", required: true, suffix: "kg" },
              select("status", o("f.plan_status"), opt(o, "status", STATUSES), { required: true, default: "DRAFT" }),
            ]}
            action={actions.createBatchPlan}
            title={o("tabs.new_plan")}
            submitLabel={o("bp.save")}
            columns={3}
            successHref={`${base}?tab=records`}
          />
        );
      }
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">{o("bp.records_title")}</p>
          <RecordsGrid
            exportName="batch-plans"
            rowKey="bp_no"
            rows={plans.map((p) => ({
              ...p,
              colour_type_label: p.colour_type ? o(`opt.colour_type.${p.colour_type}`) : null,
              method_label: o(`opt.method.${p.dyeing_method}`),
              status_label: o(`opt.status.${p.status}`),
              qty_label: kg(p.bp_qty),
            }))}
            columns={[
              { key: "bp_no", label: o("f.bp_no"), type: "mono" },
              { key: "week", label: o("f.week") },
              { key: "buyer", label: o("f.buyer") },
              { key: "style_job", label: o("f.style_job") },
              { key: "order_no", label: o("f.order_no") },
              { key: "colour_type_label", label: o("f.colour_type") },
              { key: "colour_group", label: o("f.colour_group") },
              { key: "method_label", label: o("f.method") },
              { key: "machine", label: o("f.machine"), type: "mono" },
              { key: "shipment_date", label: o("f.shipment") },
              { key: "batches", label: o("f.batches"), type: "number", align: "right" },
              { key: "qty_label", label: o("f.bp_qty"), align: "right" },
              { key: "status_label", label: o("f.plan_status"), type: "badge" },
            ]}
            rowLink={{ label: te("edit"), href: `${base}?tab=planning&bp=`, refKey: "bp_no" }}
          />
        </div>
      );
    },
  };
}

/* ---------------- Factory Calendar & Holidays ---------------- */

const DAY_TYPES = ["WEEKLY_OFF", "GOVT_HOLIDAY", "COMPANY_HOLIDAY"];

async function holidays({ o, te, tab, canManage, searchParams = {} }) {
  const base = "/fabric-requirement/factory-holiday-model";
  const [opts, rows] = await Promise.all([options(), apiFetch("/erp/fp-holidays")]);
  const fields = [
    { name: "company", label: o("f.company"), type: "select", required: true, options: [{ value: "", label: o("select") }, opts.company] },
    { name: "factory_id", label: o("f.office"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...opts.factories] },
    { name: "day", label: o("f.date"), type: "date", required: true },
    { name: "day_type", label: o("f.day_type"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...opt(o, "day_type", DAY_TYPES)] },
    { name: "name", label: o("f.name") },
  ];
  const tabs = [recordsTab(te, rows.length), { key: "sections", label: o("tabs.sections"), icon: "ListTree" }, ...(canManage ? [{ key: "new", label: o("tabs.new_holiday"), icon: "Plus" }] : [])];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "fp-holidays", [])} title={o("tabs.new_holiday")} submitLabel={o("save")} columns={3} successHref={`${base}?tab=records`} />;
      if (tab === "sections") {
        const sections = await apiFetch("/fabric-planning/sections");
        const groups = sections.map((s) => ({ key: s.key, label: s.label, sub: o("hol.sub", { n: s.count }), count: s.count }));
        const active = groups.find((g) => g.key === searchParams.group) ?? null;
        const days = active ? await apiFetch(`/fabric-planning/sections/${encodeURIComponent(active.key)}/days`) : null;
        return twoPane({
          groups,
          active: active?.key ?? null,
          base,
          tab,
          title: o("hol.sections"),
          pick: `${o("hol.pick")} — ${o("hol.pick_hint")}`,
          children: active ? <SectionDaysEditor key={active.key} section={active} rows={days} options={opts} save={actions.opsAction.bind(null, "saveDays", active.key)} canManage={canManage} /> : null,
        });
      }
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">{o("hol.records_title")}</p>
          <RecordsGrid
            exportName="factory-holidays"
            rows={rows.map((r) => ({
              ...r,
              section_label: labelOf(opts.sections, r.section_id) ?? o("hol.all_sections"),
              office_label: labelOf(opts.factories, r.factory_id),
              type_label: o(`opt.day_type.${r.day_type}`),
              name: r.name ?? "",
            }))}
            columns={[
              { key: "day", label: o("f.date") },
              { key: "section_label", label: o("f.section") },
              { key: "office_label", label: o("f.office") },
              { key: "type_label", label: o("f.day_type"), type: "badge" },
              { key: "name", label: o("f.name") },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fp-holidays", []), title: te("edit"), submitLabel: o("save"), columns: 3, refKey: "day" } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fp-holidays") : undefined}
          />
        </div>
      );
    },
  };
}

/* ---------------- Dye Machine Capacity Board ---------------- */

async function machineCapacity({ o, te, tab, canManage, searchParams = {} }) {
  const base = "/fabric-requirement/dyeing-machine-capacity-dashboard";
  const [opts, rows] = await Promise.all([options(), apiFetch("/erp/fp-machine-capacities")]);
  const fields = [
    { name: "dye_machine_id", label: o("f.cap_machine"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...opts.machines] },
    { name: "week_start", label: o("f.cap_week"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...opts.weeks] },
    { name: "capacity_kg", label: o("f.capacity"), type: "number", required: true, suffix: "kg" },
    { name: "working_days", label: o("f.wdays"), type: "number", required: true, default: "6" },
    { name: "holidays", label: o("f.holidays"), type: "number", required: true, default: "1" },
    { name: "max_load_pct", label: o("f.max_load"), type: "number", required: true, suffix: "%", default: "100" },
    { name: "capacity_hours", label: o("f.cap_hours"), type: "number", required: true, suffix: "hrs" },
    { name: "avg_batch_qty", label: o("f.avg_batch"), type: "number", suffix: "kg" },
  ];
  const tabs = [recordsTab(te, rows.length), { key: "machines", label: o("tabs.machines"), icon: "Gauge" }, ...(canManage ? [{ key: "new", label: o("tabs.new_capacity"), icon: "Plus" }] : [])];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "fp-machine-capacities", [])} title={o("tabs.new_capacity")} description={o("cap.form_hint")} submitLabel={o("save")} columns={3} successHref={`${base}?tab=records`} />;
      if (tab === "machines") {
        const weeks = await apiFetch("/fabric-planning/capacity-weeks");
        const groups = weeks.map((w) => ({ key: w.key, label: w.label, sub: o("cap.sub", { n: w.count }), count: w.count }));
        const active = groups.find((g) => g.key === searchParams.group) ?? null;
        const data = active ? await apiFetch(`/fabric-planning/capacity-weeks/${active.key}`) : null;
        return twoPane({
          groups,
          active: active?.key ?? null,
          base,
          tab,
          title: o("cap.weeks"),
          pick: groups.length ? `${o("cap.pick")} — ${o("cap.pick_hint")}` : o("cap.no_weeks"),
          children: data ? (
            <section className="min-w-0 overflow-hidden rounded-lg border border-border bg-surface">
              <div className="px-5 pt-5 pb-4">
                <h2 className="text-base font-semibold text-foreground">{o("cap.title_of", { week: data.week })}</h2>
                <p className="text-[13px] text-foreground-muted">{o("cap.hint")}</p>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1100px] text-sm">
                  <thead className="bg-brand text-brand-foreground">
                    <tr>
                      {["cap_machine", "capacity", "wdays", "holidays", "max_load", "max_load_kg", "cap_hours", "avg_batch", "planned_batches", "planned_kg", "free_kg", "load"].map((k, i) => (
                        <th key={k} className={`px-3 py-2.5 text-xs font-semibold whitespace-nowrap ${i === 0 ? "text-left" : "text-right"}`}>
                          {o(`f.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {data.rows.map((r) => (
                      <tr key={r.id} className="border-t border-border">
                        <td className="px-3 py-2.5 font-mono text-[13px] font-semibold">{r.machine}</td>
                        {[kg(r.capacity_kg), r.working_days, r.holidays, `${num(r.max_load_pct)}%`, kg(r.max_load_kg), num(r.capacity_hours), kg(r.avg_batch_qty) ?? "—", r.planned_batches, kg(r.planned_kg)].map((v, i) => (
                          <td key={i} className="px-3 py-2.5 text-right tabular-nums">
                            {v}
                          </td>
                        ))}
                        <td className={`px-3 py-2.5 text-right tabular-nums ${r.free_kg < 0 ? "text-danger" : ""}`}>{kg(r.free_kg)}</td>
                        <td className="px-3 py-2.5">
                          <div className="ml-auto flex w-32 items-center gap-2">
                            <div className="h-1.5 flex-1 rounded-full bg-surface-muted">
                              <div className={`h-1.5 rounded-full ${r.load_pct > 100 ? "bg-danger" : r.load_pct >= 85 ? "bg-warning" : "bg-success"}`} style={{ width: `${Math.min(100, r.load_pct)}%` }} />
                            </div>
                            <span className="w-10 text-right text-xs tabular-nums">{r.load_pct}%</span>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          ) : null,
        });
      }
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">{o("cap.records_title")}</p>
          <RecordsGrid
            exportName="machine-capacity"
            rows={rows.map((r) => ({
              ...r,
              machine: labelOf(opts.machines, r.dye_machine_id),
              ...Object.fromEntries(["capacity_kg", "max_load_pct", "capacity_hours", "avg_batch_qty"].map((k) => [k, num(r[k])])),
              working_days: num(r.working_days),
              holidays: num(r.holidays),
            }))}
            columns={[
              { key: "machine", label: o("f.cap_machine"), type: "mono" },
              { key: "week", label: o("f.cap_week") },
              { key: "capacity_kg", label: o("f.capacity"), type: "number", align: "right" },
              { key: "working_days", label: o("f.wdays"), type: "number", align: "right" },
              { key: "holidays", label: o("f.holidays"), type: "number", align: "right" },
              { key: "max_load_pct", label: o("f.max_load"), type: "number", align: "right" },
              { key: "max_load_kg", label: o("f.max_load_kg"), type: "number", align: "right" },
              { key: "capacity_hours", label: o("f.cap_hours"), type: "number", align: "right" },
              { key: "avg_batch_qty", label: o("f.avg_batch"), type: "number", align: "right" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fp-machine-capacities", []), title: te("edit"), submitLabel: o("save"), columns: 3, refKey: "machine" } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fp-machine-capacities") : undefined}
          />
        </div>
      );
    },
  };
}

/* ---------------- Batch Schedule Board ---------------- */

async function batchScheduling({ o, te, tab, canManage, searchParams = {} }) {
  const base = "/fabric-requirement/batch-scheduling";
  const [opts, rows] = await Promise.all([options(), apiFetch("/erp/fp-schedule-plans")]);
  const fields = [
    { name: "name", label: o("f.plan_name"), required: true },
    { name: "party_id", label: o("f.party"), type: "select", options: [{ value: "", label: o("select") }, opts.company] },
    { name: "basis", label: o("f.basis"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...opt(o, "basis", ["SHIPMENT", "BOOKING"])], help: o("f.basis_help") },
    { name: "scale", label: o("f.scale"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...opt(o, "scale", ["DAY", "WEEK", "MONTH"])] },
    { name: "cell_width", label: o("f.cell_width"), type: "number", required: true, suffix: "%" },
    { name: "active", label: o("f.board_active"), type: "checkbox", default: true },
  ];
  const label = (r) => `${r.plan_no} — ${r.name}`;
  const tabs = [recordsTab(te, rows.length), { key: "board", label: o("tabs.board"), icon: "ListOrdered" }, ...(canManage ? [{ key: "new", label: o("tabs.new_board"), icon: "Plus" }] : [])];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "fp-schedule-plans", ["active"])} title={o("tabs.new_board")} description={o("sb.form_hint")} submitLabel={o("tabs.new_board")} columns={3} successHref={`${base}?tab=records`} />;
      if (tab === "board") {
        const groups = rows.map((r) => ({
          key: r.plan_no,
          label: label(r),
          sub: [r.party_id ? opts.company.label : o("sb.all_parties"), o(`opt.scale.${r.scale}`).toUpperCase(), o(`opt.basis.${r.basis}`)].join(" · "),
          count: r.batches,
        }));
        const active = groups.find((g) => g.key === searchParams.group) ?? null;
        const data = active ? await apiFetch(`/fabric-planning/schedule-plans/${encodeURIComponent(active.key)}/batches`) : null;
        return twoPane({
          groups,
          active: active?.key ?? null,
          base,
          tab,
          title: o("sb.plans"),
          pick: groups.length ? `${o("sb.pick")} — ${o("sb.pick_hint")}` : o("sb.no_plans"),
          children: data ? <ScheduleBoardEditor key={active.key} board={active} data={data} options={opts} save={actions.opsAction.bind(null, "saveBoard", active.key)} canManage={canManage} /> : null,
        });
      }
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">{o("sb.records_title")}</p>
          <RecordsGrid
            exportName="schedule-plans"
            rows={rows.map((r) => ({
              ...r,
              party_id: r.party_id ?? "",
              party_label: r.party_id ? opts.company.label : o("sb.all"),
              basis_label: o(`opt.basis.${r.basis}`),
              scale_label: r.scale,
              cell_width: num(r.cell_width),
            }))}
            columns={[
              { key: "plan_no", label: o("f.plan_no"), type: "mono" },
              { key: "name", label: o("f.search_plan") },
              { key: "party_label", label: o("f.party") },
              { key: "basis_label", label: o("f.basis") },
              { key: "scale_label", label: o("f.scale") },
              { key: "cell_width", label: o("f.cell_width"), type: "number", align: "right" },
              { key: "batches", label: o("f.board_batches"), type: "number", align: "right" },
              { key: "active", label: o("f.status"), type: "status" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fp-schedule-plans", ["active"]), title: te("edit"), submitLabel: o("save"), columns: 3, refKey: "plan_no" } : undefined}
          />
        </div>
      );
    },
  };
}

/* ---------------- Fabric Requirement Reports (13/03) ---------------- */

const REPORT_RIGHT = new Set(["batches", "qty", "max_load", "planned", "used", "over_by", "seq", "batch_hour"]);
const REPORT_TONE = {
  OVER: "bg-danger-subtle text-danger",
  WITHIN: "bg-success-subtle text-success",
  CANCELLED: "bg-danger-subtle text-danger",
  INACTIVE: "bg-surface-muted text-foreground-muted",
  DRAFT: "bg-surface-muted text-foreground-muted",
};

/** A report's cell as shown: the status pill, a quantity with its thousands, a percentage. */
function reportCell(row, k) {
  if (k === "status") return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${REPORT_TONE[row.tone] ?? "bg-info-subtle text-info"}`}>{row.status}</span>;
  if (typeof row[k] === "number" && k !== "seq") return k === "used" ? `${row[k]}%` : kg(row[k]);
  return row[k] ?? "—";
}

async function reports({ o, te, tab, canManage, searchParams = {} }) {
  const base = "/fabric-requirement/fabric-planning-reports";
  const [opts, catalogue, runs] = await Promise.all([options(), apiFetch("/fabric-planning/reports"), apiFetch("/erp/fp-report-runs")]);
  const r = (k, x) => o(`rpt.${k}`, x);
  const fields = [
    { name: "report_id", label: r("f.report"), type: "select", required: true, options: [{ value: "", label: o("select") }, ...catalogue.map((c) => ({ value: c.id, label: c.name }))] },
    { name: "factory_id", label: r("f.unit"), type: "select", options: [{ value: "", label: o("select") }, ...opts.factories] },
    { name: "buyer_id", label: r("f.buyer"), type: "select", options: [{ value: "", label: o("select") }, ...opts.buyers] },
    { name: "week_start", label: r("f.week"), type: "select", options: [{ value: "", label: o("select") }, ...opts.weeks] },
    { name: "export_excel", label: r("f.export_excel"), type: "checkbox" },
  ];
  const tabs = [recordsTab(te, runs.length), { key: "preview", label: o("tabs.preview"), icon: "ListChecks" }, ...(canManage ? [{ key: "new", label: o("tabs.new_run"), icon: "Plus" }] : [])];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "fp-report-runs", ["export_excel"])} title={o("tabs.new_run")} description={r("form_hint")} submitLabel={r("f.save")} columns={3} successHref={`${base}?tab=records`} />;
      if (tab === "preview") {
        const groups = catalogue.map((c) => ({ key: c.code, label: c.name, sub: `${r(`categories.${c.category}`)} · ${c.code}`, count: "▶" }));
        const active = groups.find((g) => g.key === searchParams.group) ?? null;
        const data = active ? await apiFetch(`/fabric-planning/reports/${encodeURIComponent(active.key)}`) : null;
        return twoPane({
          groups,
          active: active?.key ?? null,
          base,
          tab,
          title: r("groups"),
          pick: `${r("pick")} — ${r("pick_hint")}`,
          children: data ? (
            <section className="min-w-0 overflow-hidden rounded-lg border border-border bg-surface">
              <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 pb-4">
                <div>
                  <h2 className="text-base font-semibold text-foreground">{r("title_of", { report: data.name })}</h2>
                  <p className="text-[13px] text-foreground-muted">{r("hint")}</p>
                </div>
                <a href={`/api/fabric-requirement/excel/${data.code}`} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border-strong px-3 text-xs font-medium text-foreground hover:bg-surface-muted">
                  {r("export")}
                </a>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-sm">
                  <thead className="bg-brand text-brand-foreground">
                    <tr>
                      {data.columns.map((k) => (
                        <th key={k} className={`px-3 py-2.5 text-xs font-semibold whitespace-nowrap ${REPORT_RIGHT.has(k) ? "text-right" : "text-left"}`}>
                          {r(`col.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {data.rows.length === 0 ? (
                      <tr>
                        <td colSpan={data.columns.length} className="px-3 py-10 text-center text-[13px] text-foreground-muted">
                          {r("empty")}
                        </td>
                      </tr>
                    ) : null}
                    {data.rows.map((row, i) => (
                      <tr key={i} className="border-t border-border">
                        {data.columns.map((k) => (
                          <td key={k} className={`px-3 py-2.5 ${REPORT_RIGHT.has(k) ? "text-right tabular-nums" : ""} ${["bp_no", "machine", "routed_machine"].includes(k) ? "font-mono text-[13px]" : ""}`}>
                            {reportCell(row, k)}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="border-t border-border px-5 py-4 text-[13px] text-foreground-muted">{r("foot", { n: data.rows.length })}</p>
            </section>
          ) : null,
        });
      }
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">{r("records_title")}</p>
          <RecordsGrid
            exportName="fabric-requirement-report-runs"
            rows={runs.map((x) => ({
              ...x,
              factory_id: x.factory_id ?? "",
              buyer_id: x.buyer_id ?? "",
              week_start: x.week_start ?? "",
              category_label: x.category ? r(`categories.${x.category}`) : null,
              unit_label: labelOf(opts.factories, x.factory_id) ?? r("all"),
              buyer_label: labelOf(opts.buyers, x.buyer_id) ?? r("all"),
              week_label: x.week ?? r("all"),
              excel_label: r(x.export_excel ? "yes" : "no"),
            }))}
            columns={[
              { key: "report", label: r("f.report") },
              { key: "category_label", label: r("f.category") },
              { key: "unit_label", label: r("f.unit") },
              { key: "buyer_label", label: r("f.buyer_col") },
              { key: "week_label", label: r("f.week") },
              { key: "rows", label: r("f.rows"), type: "number", align: "right" },
              { key: "excel_label", label: r("f.export_excel") },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fp-report-runs", ["export_excel"]), title: te("edit"), submitLabel: r("f.save"), columns: 3, refKey: "report" } : undefined}
          />
        </div>
      );
    },
  };
}

const OPS_SCREENS = {
  "planning-constraints-rules": constraints,
  "batch-planning": batchPlanning,
  "factory-holiday-model": holidays,
  "dyeing-machine-capacity-dashboard": machineCapacity,
  "batch-scheduling": batchScheduling,
  "fabric-planning-reports": reports,
};

export { OPS_SCREENS };
