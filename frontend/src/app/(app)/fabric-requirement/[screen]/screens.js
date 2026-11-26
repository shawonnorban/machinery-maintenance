import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import * as actions from "./actions";

/*
 * Fabric Requirement → Master Data (AnnotechERP 13/01): Batch Lead Days
 * Before PCD, Standard Batch Hour Norms and Dye Machine Route Master — each
 * Brickland's Records and New. A criterion left to Any shows as Any.
 */

const options = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const select = (m, name, group, values, extra = {}) => ({ name, label: m(`f.${name}`), type: "select", options: [{ value: "", label: "—" }, ...options(m, group, values)], ...extra });
const check = (m, name, extra = {}) => ({ name, label: m(`f.${name}`), type: "checkbox", ...extra });
const num = (v) => (v === null || v === undefined ? null : String(Number(v)));
const orAny = (m, group, value) => (value ? m(`opt.${group}.${value}`) : m("any"));

/** Records + New over one resource. */
function recordsScreen(config) {
  return async function render({ m, te, tab, canManage }) {
    const extra = config.load ? await config.load() : {};
    const rows = (await apiFetch(`/erp/${config.resource}`)).map((row) => config.decorate(row, { m, ...extra }));
    const fields = config.fields({ m, ...extra });
    return {
      tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m(`${config.key}.new`), icon: "Plus" }] : [])],
      body: () =>
        tab === "new" && canManage ? (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, config.resource, config.booleans)}
            title={m(`${config.key}.new`)}
            submitLabel={m(`${config.key}.save`)}
            columns={3}
            successHref={`/fabric-requirement/${config.screen}?tab=records`}
          />
        ) : (
          <RecordsGrid
            exportName={config.screen}
            rows={rows}
            columns={config.columns({ m })}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, config.resource, config.booleans), title: m(`${config.key}.edit`), submitLabel: m("save"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, config.resource) : undefined}
          />
        ),
    };
  };
}

const COLOURS = ["SOLID", "MELANGE", "YARN_DYED", "AOP"];
/** Dye House's colour groups, as Brickland lists them: "Name (CODE)". */
const colourGroups = async () => (await apiFetch("/erp/dye-colour-groups").catch(() => [])).filter((g) => g.active !== false).map((g) => ({ value: g.id, label: `${g.name} (${g.code})` }));
const FINISHES = ["NORMAL", "PEACH", "BRUSH", "SILICONE"];
const WASHES = ["NO_WASH", "ENZYME", "STONE", "GARMENT"];
const TEMPLATES = ["COTTON_100", "LYCRA_BLEND", "CVC_BLEND"];

const leadDays = recordsScreen({
  resource: "fp-batch-lead-rules",
  screen: "batch-plan-date-before-shipment-pcd",
  key: "lead",
  booleans: ["is_embellishment", "all_colours", "any_finish", "any_wash"],
  fields: ({ m }) => [
    select(m, "colour_type", "colour_type", COLOURS),
    check(m, "all_colours"),
    select(m, "finish_type", "finish_type", FINISHES),
    check(m, "any_finish"),
    select(m, "wash_type", "wash_type", WASHES),
    check(m, "any_wash"),
    { name: "lead_from", label: m("f.lead_from"), type: "number", required: true },
    { name: "lead_to", label: m("f.lead_to"), type: "number", required: true },
    { name: "volume_from", label: m("f.volume_from"), type: "number", required: true },
    { name: "volume_to", label: m("f.volume_to"), type: "number", required: true },
    { name: "production_lead_time", label: m("f.production_lead_time"), type: "number", required: true },
    { name: "basis", label: m("f.basis"), type: "select", required: true, options: options(m, "basis", ["SHIPMENT", "PCD"]) },
    { name: "days_before_shipment", label: m("f.days_before_shipment"), type: "number" },
    { name: "days_before_pcd", label: m("f.days_before_pcd"), type: "number" },
    check(m, "is_embellishment"),
  ],
  decorate: (r, { m }) => ({
    ...r,
    colour_type: r.colour_type ?? "",
    finish_type: r.finish_type ?? "",
    wash_type: r.wash_type ?? "",
    ...Object.fromEntries(["lead_from", "lead_to", "volume_from", "volume_to", "production_lead_time", "days_before_shipment", "days_before_pcd"].map((k) => [k, num(r[k])])),
    colour_label: orAny(m, "colour_type", r.colour_type),
    finish_label: orAny(m, "finish_type", r.finish_type),
    wash_label: orAny(m, "wash_type", r.wash_type),
    lead_band: `${r.lead_from}–${r.lead_to}`,
    volume_band: `${Number(r.volume_from).toLocaleString("en-US")}–${Number(r.volume_to).toLocaleString("en-US")}`,
    basis_label: m(`opt.basis.${r.basis}`),
    days_label: r.basis === "SHIPMENT" ? r.days_before_shipment : r.days_before_pcd,
  }),
  columns: ({ m }) => [
    { key: "colour_label", label: m("f.colour_type") },
    { key: "finish_label", label: m("f.finish_type") },
    { key: "wash_label", label: m("f.wash_type") },
    { key: "lead_band", label: m("f.lead_band"), align: "right" },
    { key: "volume_band", label: m("f.volume_band"), align: "right" },
    { key: "production_lead_time", label: m("f.production_lead_time"), type: "number", align: "right" },
    { key: "basis_label", label: m("f.basis") },
    { key: "days_label", label: m("f.days"), type: "number", align: "right" },
    { key: "is_embellishment", label: m("f.is_embellishment"), type: "yesno" },
  ],
});

const batchHours = recordsScreen({
  resource: "fp-batch-hours",
  screen: "standard-batch-hour-setup",
  key: "hours",
  booleans: ["any_template", "any_category"],
  load: async () => ({ groups: await colourGroups() }),
  fields: ({ m, groups }) => [
    select(m, "fiber_template", "template", TEMPLATES),
    check(m, "any_template"),
    select(m, "buyer_category", "buyer_category", ["A", "B", "C"]),
    check(m, "any_category"),
    { name: "colour_group_id", label: m("f.colour_group"), type: "select", required: true, options: groups },
    { name: "dyeing_method", label: m("f.dyeing_method"), type: "select", required: true, options: options(m, "method", ["EXHAUST", "COLD_PAD_BATCH", "CONTINUOUS", "PIGMENT"]) },
    { name: "batch_hours", label: m("f.batch_hours"), type: "number", required: true },
  ],
  decorate: (r, { m }) => ({
    ...r,
    fiber_template: r.fiber_template ?? "",
    buyer_category: r.buyer_category ?? "",
    batch_hours: num(r.batch_hours),
    colour_group_id: r.colour_group_id ?? "",
    template_label: orAny(m, "template", r.fiber_template),
    category_label: orAny(m, "buyer_category", r.buyer_category),
    method_label: m(`opt.method.${r.dyeing_method}`),
  }),
  columns: ({ m }) => [
    { key: "template_label", label: m("f.fiber_template") },
    { key: "category_label", label: m("f.buyer_category") },
    { key: "colour_group", label: m("f.colour_group") },
    { key: "method_label", label: m("f.dyeing_method") },
    { key: "batch_hours", label: m("f.batch_hours"), type: "number", align: "right" },
  ],
});

const machineRoutes = recordsScreen({
  resource: "fp-machine-routes",
  screen: "dyeing-machine-routing-setup",
  key: "route",
  booleans: ["active", "any_fabric_group", "any_template", "any_buyer", "any_gsm"],
  load: async () => {
    const [machines, buyers, groups] = await Promise.all([apiFetch("/erp/dye-machines").catch(() => []), apiFetch("/erp/buyers").catch(() => []), colourGroups()]);
    return { machines, buyers, groups };
  },
  fields: ({ m, machines, buyers, groups }) => [
    { name: "colour_group_id", label: m("f.colour_group_route"), type: "select", required: true, options: groups },
    select(m, "fabric_group", "fabric_group", ["BODY", "COLLAR", "LINING", "RIB"]),
    check(m, "any_fabric_group"),
    select(m, "fiber_template", "template", TEMPLATES),
    check(m, "any_template"),
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: [{ value: "", label: "—" }, ...buyers.map((b) => ({ value: b.id, label: `${b.short_name} (${b.code})` }))] },
    check(m, "any_buyer"),
    { name: "dye_machine_id", label: m("f.dye_machine"), type: "select", required: true, options: machines.filter((x) => x.active).map((x) => ({ value: x.id, label: `${x.machine_no} — ${x.machine_type ?? ""}` })) },
    check(m, "active", { default: true }),
    { name: "gsm_from", label: m("f.gsm_from"), type: "number" },
    { name: "gsm_to", label: m("f.gsm_to"), type: "number" },
    check(m, "any_gsm"),
  ],
  decorate: (r, { m }) => ({
    ...r,
    colour_group_id: r.colour_group_id ?? "",
    fabric_group: r.fabric_group ?? "",
    fiber_template: r.fiber_template ?? "",
    buyer_id: r.buyer_id ?? "",
    gsm_from: num(r.gsm_from),
    gsm_to: num(r.gsm_to),
    group_label: orAny(m, "fabric_group", r.fabric_group),
    template_label: orAny(m, "template", r.fiber_template),
    buyer_label: r.buyer ?? m("any"),
    gsm_label: r.gsm_from === null ? m("any") : `${r.gsm_from}–${r.gsm_to}`,
  }),
  columns: ({ m }) => [
    { key: "colour_group", label: m("f.colour_group_route") },
    { key: "group_label", label: m("f.fabric_group") },
    { key: "template_label", label: m("f.fiber_template") },
    { key: "buyer_label", label: m("f.buyer") },
    { key: "gsm_label", label: m("f.gsm_band"), align: "right" },
    { key: "machine", label: m("f.dye_machine"), type: "mono" },
    { key: "active", label: m("f.status"), type: "status" },
  ],
});

const SCREENS = {
  "batch-plan-date-before-shipment-pcd": leadDays,
  "standard-batch-hour-setup": batchHours,
  "dyeing-machine-routing-setup": machineRoutes,
};

export { SCREENS };
