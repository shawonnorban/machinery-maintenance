import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { SideGrid } from "@/components/workstudy/side-grid";
import * as actions from "./actions";

/*
 * Finishing Line → Master Data (AnnotechERP 18/01): Finishing Process Types,
 * Finishing Machine Profile, Machine-Process Mapping and Machine-Wise Target
 * Setup — each with Brickland's tabs and filters.
 */

const STAGES = ["AWAITING_LOAD", "DYEING_RUNNING", "DEWATERING", "DRYING", "HEAT_SET", "STENTERING", "COMPACTING", "CALENDERING", "BRUSHING", "QC_INSPECTION", "READY_FOR_DELIVERY", "DELIVERED", "ON_HOLD", "AT_SUB_CONTRACT"];
const TYPES = ["STENTER", "COMPACTOR", "DRYER", "SLITTING", "HYDRO_EXTRACTOR", "SQUEEZER", "BRUSH", "CALENDER", "TUBE_HEAT_SET"];
const STATUSES = ["RUNNING", "IDLE", "UNDER_MAINTENANCE", "BREAKDOWN"];
const UNITS = ["BAG", "COIL", "CTN", "DZN", "GRS", "KG", "LTR", "MTR", "PCS", "ROLL", "SET", "YDS"];
const DIAS = ["OPEN", "TUBE", "BOTH"];

const lookups = () => apiFetch("/finishing/lookups");
const options = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const all = (m) => ({ value: "", label: m("all") });
const num = (v) => (v === null || v === undefined || v === "" ? null : String(Number(v)));

/** Rows narrowed to the filter row's choice (it lives in the url). */
function filtered(rows, query, specs) {
  return rows.filter((r) => specs.every(({ name, key = name }) => !query[name] || String(r[key] ?? "") === query[name]));
}

function filterBar(m, screen, query, fields) {
  return <FilterBar basePath={`/finishing-line/${screen}`} keep={{ tab: "records" }} values={query} fields={fields} labels={{ show: m("filter.show"), reset: m("filter.clear") }} instant />;
}

const activeFilter = (m) => ({ name: "active", label: m("f.active_q"), type: "select", options: [all(m), { value: "true", label: m("yes") }, { value: "false", label: m("no") }] });

async function processTypes({ m, te, tab, canManage, searchParams: query = {} }) {
  const [rows, groups, look] = await Promise.all([apiFetch("/erp/fin-process-types"), apiFetch("/finishing/groups"), lookups()]);
  const fields = [
    { name: "code", label: m("f.code"), required: true, placeholder: "DF-HS", help: m("process.code_hint") },
    { name: "name", label: m("f.process_name"), required: true, placeholder: "Heat-Set" },
    { name: "short_name", label: m("f.short_name"), required: true, placeholder: "HS", help: m("process.short_hint") },
    { name: "group_id", label: m("f.group"), type: "select", required: true, options: look.groups, placeholder: m("process.select_group") },
    { name: "subgroup_id", label: m("f.subgroup"), type: "select", optionsFrom: { field: "group_id", map: look.subgroups }, placeholder: m("process.select_subgroup") },
    { name: "link_process_id", label: m("f.link_process"), type: "select", options: [{ value: "", label: m("process.select_link") }, ...look.processes], placeholder: m("process.select_link"), help: m("process.link_hint") },
    { name: "wip_stage", label: m("f.wip_stage"), type: "select", required: true, options: options(m, "stage", STAGES), default: "STENTERING", help: m("process.wip_hint") },
    { name: "rate", label: m("f.rate"), type: "number", required: true, default: "0", step: "0.01" },
    { name: "loss_pct", label: m("f.loss_pct"), type: "number", required: true, default: "0", step: "0.01", suffix: "%", help: m("process.loss_hint") },
    { name: "display_order", label: m("f.display_order"), type: "number", required: true, default: "0" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [{ key: "records", label: m("process.records"), icon: "Table2", count: rows.length }, { key: "groups", label: m("process.groups_tab"), icon: "ListChecks" }, ...(canManage ? [{ key: "new", label: m("process.new"), icon: "Plus" }] : [])],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} title={m("process.new")} description={m("process.hint")} action={actions.createResource.bind(null, "fin-process-types", ["active"])} submitLabel={m("process.save")} columns={3} successHref="/finishing-line/process-types?tab=records" />;
      }
      if (tab === "groups") {
        return (
          <SideGrid
            ns="finishing"
            listTitle={m("groups.title")}
            items={groups.map((g) => ({ key: g.key, label: g.label, hint: m("groups.processes", { count: g.processes }), count: g.count }))}
            listAction={<DialogFormButton label={m("groups.new")} title={m("groups.new")} fields={[{ name: "code", label: m("f.group_code"), required: true }, { name: "name", label: m("f.group_name"), required: true }, { name: "sort_order", label: m("f.order"), type: "number" }]} action={actions.createGroup} submitLabel={m("save")} />}
            title={m("groups.subgroups")}
            hint={m("groups.hint")}
            emptyTitle={m("groups.empty_title")}
            empty={m("groups.empty")}
            gridEmpty={{ title: m("groups.no_subgroups_title"), text: m("groups.no_subgroups") }}
            footerKey="master.groups.footer"
            addLabel={m("groups.add")}
            columns={[
              { key: "_sl", label: m("f.sl"), type: "sl" },
              { key: "code", label: m("f.subgroup_code"), type: "input", width: "w-40" },
              { key: "name", label: m("f.subgroup_name"), type: "input", width: "w-48" },
              { key: "sort_order", label: m("f.order"), type: "number" },
              { key: "active", label: m("f.active_q"), type: "check" },
              { key: "processes", label: m("f.processes"), type: "text" },
              { key: "_remove", label: m("f.action"), type: "action", blockedBy: "processes" },
            ]}
            blank={{ code: "", name: "", sort_order: "", active: true, processes: 0 }}
            load={actions.loadSubgroups}
            save={actions.saveSubgroups}
            canManage={canManage}
            exportName="process-subgroups"
          />
        );
      }
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, "process-types", query, [
            { name: "group", label: m("f.group_short"), type: "select", options: [all(m), ...look.groups] },
            { name: "stage", label: m("f.wip_stage"), type: "select", options: [all(m), ...options(m, "stage", STAGES)] },
            activeFilter(m),
          ])}
          <RecordsGrid
            exportName="finishing-process-types"
            rows={filtered(rows, query, [{ name: "group", key: "group_id" }, { name: "stage", key: "wip_stage" }, { name: "active" }]).map((r) => ({
              ...r, subgroup_id: r.subgroup_id ?? "", link_process_id: r.link_process_id ?? "", rate: Number(r.rate).toFixed(2), loss_pct: num(r.loss_pct), display_order: String(r.display_order), stage_label: m(`opt.stage.${r.wip_stage}`),
            }))}
            columns={[
              { key: "code", label: m("f.code"), type: "mono" },
              { key: "name", label: m("f.process_name") },
              { key: "short_name", label: m("f.short_name") },
              { key: "subgroup", label: m("f.subgroup_col") },
              { key: "link_process", label: m("f.link_process") },
              { key: "display_order", label: m("f.display_order"), type: "number", align: "right" },
              { key: "rate", label: m("f.rate"), type: "number", align: "right" },
              { key: "loss_pct", label: m("f.apl"), type: "number", align: "right" },
              { key: "stage_label", label: m("f.wip_stage") },
              { key: "active", label: m("f.status"), type: "status" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fin-process-types", ["active"]), title: m("process.edit"), submitLabel: m("save"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-process-types") : undefined}
          />
        </div>
      );
    },
  };
}

async function machines({ m, te, tab, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/fin-machines"), lookups()]);
  const fields = [
    { name: "machine_no", label: m("f.machine_no"), required: true, placeholder: "MFL-01", help: m("machine.no_hint") },
    { name: "name", label: m("f.machine_name"), required: true, placeholder: "Stenter-1 (Bruckner, MFL)" },
    { name: "model", label: m("f.model") },
    { name: "origin_country_id", label: m("f.country"), type: "select", required: true, options: look.countries, placeholder: m("machine.select_country") },
    { name: "brand_id", label: m("f.brand"), type: "select", required: true, options: look.brands, placeholder: m("machine.select_brand") },
    { name: "capacity", label: m("f.capacity"), type: "number", step: "0.01" },
    { name: "location_id", label: m("f.location"), type: "select", options: [{ value: "", label: m("machine.select_location") }, ...look.locations], placeholder: m("machine.select_location") },
    { name: "mou", label: m("f.mou"), type: "select", required: true, options: options(m, "unit", UNITS), placeholder: m("machine.select_mou") },
    { name: "efficiency", label: m("f.efficiency"), type: "number", step: "0.01", suffix: "%" },
    { name: "sl_no", label: m("f.sl_no") },
    { name: "machine_type", label: m("f.machine_type"), type: "select", required: true, options: options(m, "type", TYPES), placeholder: m("machine.select_type") },
    { name: "rpm", label: m("f.rpm"), type: "number" },
    { name: "year", label: m("f.year"), type: "number" },
    { name: "status", label: m("f.machine_status"), type: "select", required: true, options: options(m, "status", STATUSES), default: "IDLE" },
    { name: "temperature", label: m("f.temp"), type: "number", suffix: "°C" },
    { name: "floor_id", label: m("f.floor"), type: "select", required: true, options: look.floors, placeholder: m("select_dash") },
    { name: "is_sample", label: m("f.sample"), type: "checkbox" },
    { name: "key_image", label: m("f.key_image"), type: "filepath", upload: "/api/finishing/key-image", placeholder: m("machine.key_image_placeholder"), help: m("machine.key_image_hint"), span: 3 },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [{ key: "records", label: m("machine.records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("machine.new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} title={m("machine.new")} description={m("machine.hint")} action={actions.createResource.bind(null, "fin-machines", ["is_sample", "active"])} submitLabel={m("machine.save")} columns={3} successHref="/finishing-line/machine-profile?tab=records" />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, "machine-profile", query, [
            { name: "type", label: m("f.type"), type: "select", options: [all(m), ...options(m, "type", TYPES)] },
            { name: "status", label: m("f.machine_status"), type: "select", options: [all(m), ...options(m, "status", STATUSES)] },
            activeFilter(m),
          ])}
          <RecordsGrid
            exportName="finishing-machines"
            rows={filtered(rows, query, [{ name: "type", key: "machine_type" }, { name: "status" }, { name: "active" }]).map((r) => ({
              ...r, location_id: r.location_id ?? "", capacity: num(r.capacity), efficiency: num(r.efficiency), rpm: r.rpm === null ? null : String(r.rpm), year: r.year === null ? null : String(r.year), temperature: r.temperature === null ? null : String(r.temperature),
              type_label: m(`opt.type.${r.machine_type}`),
            }))}
            columns={[
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "name", label: m("f.machine_name") },
              { key: "model", label: m("f.model_col") },
              { key: "capacity", label: m("f.cap"), type: "number", align: "right" },
              { key: "efficiency", label: m("f.efficiency"), type: "number", align: "right" },
              { key: "sl_no", label: m("f.serial") },
              { key: "year", label: m("f.year") },
              { key: "rpm", label: m("f.rpm"), type: "number", align: "right" },
              { key: "type_label", label: m("f.type") },
              { key: "brand", label: m("f.brand") },
              { key: "country", label: m("f.country_short") },
              { key: "mou", label: m("f.unit"), type: "mono" },
              { key: "location", label: m("f.location") },
              { key: "is_sample", label: m("f.sample"), type: "yesno" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fin-machines", ["is_sample", "active"]), title: m("machine.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          />
        </div>
      ),
  };
}

async function machineProcesses({ m, te, tab, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/fin-machine-processes"), lookups()]);
  const fields = [
    { name: "dia_type", label: m("f.dia_type"), type: "select", required: true, options: options(m, "dia", DIAS), default: "OPEN" },
    { name: "machine_id", label: m("f.machine"), type: "select", required: true, options: look.machines, placeholder: m("mapping.select_machine") },
    { name: "process_id", label: m("f.process"), type: "select", required: true, options: look.processes, placeholder: m("mapping.select_process") },
    { name: "sequence", label: m("f.sequence"), type: "number", required: true, default: "1", help: m("mapping.sequence_hint") },
    { name: "batch_status", label: m("f.batch_status"), type: "select", required: true, options: options(m, "stage", STAGES), default: "AWAITING_LOAD" },
    { name: "next_status", label: m("f.next_status"), type: "select", required: true, options: options(m, "stage", STAGES), default: "STENTERING" },
    { name: "is_default", label: m("f.is_default"), type: "checkbox" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [{ key: "records", label: m("mapping.records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("mapping.new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} title={m("mapping.new")} description={m("mapping.hint")} action={actions.createResource.bind(null, "fin-machine-processes", ["is_default", "active"])} submitLabel={m("mapping.save")} columns={3} successHref="/finishing-line/machine-process-mapping?tab=records" />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, "machine-process-mapping", query, [
            { name: "dia", label: m("f.dia_type"), type: "select", options: [all(m), ...options(m, "dia", DIAS)] },
            { name: "process", label: m("f.process"), type: "select", options: [all(m), ...look.processes] },
            activeFilter(m),
          ])}
          <RecordsGrid
            exportName="machine-process-mapping"
            rows={filtered(rows, query, [{ name: "dia", key: "dia_type" }, { name: "process", key: "process_id" }, { name: "active" }]).map((r) => ({ ...r, sequence: String(r.sequence), dia_label: m(`opt.dia.${r.dia_type}`), batch_label: m(`opt.stage.${r.batch_status}`), next_label: m(`opt.stage.${r.next_status}`) }))}
            columns={[
              { key: "process", label: m("f.process") },
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "machine_name", label: m("f.machine_name") },
              { key: "dia_label", label: m("f.dia_type") },
              { key: "batch_label", label: m("f.batch_status") },
              { key: "next_label", label: m("f.next_status") },
              { key: "sequence", label: m("f.sequence_col"), type: "number", align: "right" },
              { key: "is_default", label: m("f.default_q"), type: "yesno" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fin-machine-processes", ["is_default", "active"]), title: m("mapping.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          />
        </div>
      ),
  };
}

async function targets({ m, te, tab, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/fin-machine-targets"), lookups()]);
  const fields = [
    { name: "machine_id", label: m("f.machine"), type: "select", required: true, options: look.machines, placeholder: m("mapping.select_machine") },
    { name: "process_id", label: m("f.process"), type: "select", options: [{ value: "", label: m("target.any_process") }, ...look.processes], placeholder: m("target.any_process"), help: m("target.process_hint") },
    { name: "dia_type", label: m("f.dia_type"), type: "select", required: true, options: options(m, "dia", DIAS) },
    { name: "effective_from", label: m("f.effective_from"), type: "date", required: true },
    { name: "target_qty", label: m("f.target_qty"), type: "number", required: true, step: "0.01" },
    { name: "shift_hours", label: m("f.shift_hours"), type: "number", required: true, step: "0.01" },
    { name: "remarks", label: m("f.remarks"), type: "textarea", span: 3 },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [{ key: "records", label: m("target.records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("target.new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} title={m("target.new")} description={m("target.hint")} action={actions.createResource.bind(null, "fin-machine-targets", ["active"])} submitLabel={m("target.save")} columns={3} successHref="/finishing-line/machine-target-setup?tab=records" />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, "machine-target-setup", query, [
            { name: "machine", label: m("f.machine"), type: "select", options: [all(m), ...look.machines] },
            { name: "dia", label: m("f.dia_type"), type: "select", options: [all(m), ...options(m, "dia", DIAS)] },
            activeFilter(m),
          ])}
          <RecordsGrid
            exportName="machine-targets"
            rows={filtered(rows, query, [{ name: "machine", key: "machine_id" }, { name: "dia", key: "dia_type" }, { name: "active" }]).map((r) => ({ ...r, process_id: r.process_id ?? "", process: r.process ?? m("target.any"), target_qty: num(r.target_qty), shift_hours: num(r.shift_hours), per_hour: num(r.per_hour), dia_label: m(`opt.dia.${r.dia_type}`) }))}
            columns={[
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "machine_name", label: m("f.machine_name") },
              { key: "process", label: m("f.process") },
              { key: "dia_label", label: m("f.dia_type") },
              { key: "effective_from", label: m("f.effective_from"), type: "date" },
              { key: "target_qty", label: m("f.target_shift"), type: "number", align: "right" },
              { key: "shift_hours", label: m("f.shift_hours"), type: "number", align: "right" },
              { key: "per_hour", label: m("f.per_hour"), type: "number", align: "right" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fin-machine-targets", ["active"]), title: m("target.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          />
        </div>
      ),
  };
}

const SCREENS = {
  "process-types": processTypes,
  "machine-profile": machines,
  "machine-process-mapping": machineProcesses,
  "machine-target-setup": targets,
};

/** Every Finishing Line screen opens on Records, as Brickland's do. */
const DEFAULT_TABS = {};

export { SCREENS, DEFAULT_TABS };
