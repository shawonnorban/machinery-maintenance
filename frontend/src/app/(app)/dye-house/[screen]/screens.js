import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { GroupedRecords } from "@/components/erp/grouped-records";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { ColourGroupGrid } from "@/components/dyehouse/colour-group-grid";
import { AuditChecks } from "@/components/dyehouse/audit-checks";
import { ScQuotationEditor } from "@/components/dyehouse/sc-quotation-editor";
import { DosingTemplateEditor } from "@/components/dyehouse/dosing-template-editor";
import * as actions from "./actions";

/*
 * Dye House → Master Data (AnnotechERP 17/01): Dye Method Master, Dye Machine
 * Profile (with its reference lists), Finishing & Quality Owner Setup, Dye
 * Chemical Audit Recipe, Colour Group Rate Update and Dye Sub-Contract Party
 * Rate — and Lab-Dip (17/02): Dosing Template Master, Lab Dip Recipe Entry
 * and Lab Dip Recipe Lookup — each laid out as Brickland lays it out. Every
 * screen returns its tabs and its body.
 */

const lookups = () => apiFetch("/dyehouse/lookups");
const options = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const num = (v) => (v === null || v === undefined ? null : String(Number(v)));
const yn = (m, key, label) => ({ key, label: m(label), type: "yesno" });

const methods = async ({ m, te, tab, canManage }) => {
  const rows = (await apiFetch("/erp/dye-methods")).map((r) => ({ ...r, sd_label: m(`opt.sd_short.${r.sd}`) }));
  const fields = [
    { name: "name", label: m("f.method_name"), required: true, placeholder: "Normal Wash" },
    { name: "short_name", label: m("f.method_short"), required: true, placeholder: "N/Wash" },
    { name: "sd", label: m("f.sd"), type: "select", required: true, options: options(m, "sd", ["SINGLE", "DOUBLE"]) },
    { name: "is_default", label: m("f.is_default"), type: "checkbox", help: m("f.is_default_hint") },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  const booleans = ["is_default", "active"];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("method.new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-methods", booleans)} title={m("method.title")} submitLabel={m("save")} columns={3} successHref="/dye-house/dyeing-method-setup?tab=records" />
      ) : (
        <RecordsGrid
          exportName="dye-methods"
          rows={rows}
          columns={[{ key: "name", label: m("f.dye_method") }, { key: "sd_label", label: m("f.sd_col") }, yn(m, "is_default", "f.default_q"), yn(m, "active", "f.active_q")]}
          edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-methods", booleans), title: m("method.edit"), submitLabel: m("save"), columns: 3 } : undefined}
        />
      ),
  };
};

const LISTS = ["TYPE", "BRAND", "LOCATION"];
const PROD = ["FREE", "BUSY", "UNDER_MAINTENANCE", "BREAKDOWN", "RESERVED"];

const machines = async ({ m, te, tab, canManage, searchParams = {} }) => {
  const [rows, lists, look] = await Promise.all([apiFetch("/erp/dye-machines"), apiFetch("/erp/dye-machine-lists"), lookups()]);
  const of = (list) => lists.filter((l) => l.list === list && l.active).map((l) => ({ value: l.id, label: l.name }));
  const fields = [
    { name: "machine_no", label: m("f.machine_no"), required: true, placeholder: "MFL-01", help: m("f.machine_no_hint") },
    { name: "model", label: m("f.model"), placeholder: "FC28" },
    { name: "machine_type_id", label: m("f.machine_type"), type: "select", required: true, options: of("TYPE") },
    { name: "brand_id", label: m("f.brand"), type: "select", required: true, options: of("BRAND") },
    { name: "origin_country_id", label: m("f.country"), type: "select", required: true, options: look.countries, help: m("f.country_hint") },
    { name: "location_id", label: m("f.location"), type: "select", options: [{ value: "", label: "—" }, ...of("LOCATION")], help: m("f.location_hint") },
    { name: "capacity", label: m("f.capacity"), type: "number", placeholder: "1250", help: m("f.capacity_hint") },
    { name: "efficiency", label: m("f.efficiency"), type: "number", placeholder: "85", help: m("f.efficiency_hint") },
    { name: "capacity_unit", label: m("f.mou"), type: "select", required: true, default: "KG", options: options(m, "unit", ["KG", "LBS"]) },
    { name: "active", label: m("f.status"), type: "checkbox", default: true, help: m("f.status_hint") },
    { name: "sl_no", label: m("f.sl_no"), required: true, placeholder: "01", help: m("f.sl_no_hint") },
    { name: "year", label: m("f.year"), type: "number", placeholder: "2010" },
    { name: "rpm", label: m("f.rpm"), type: "number" },
    { name: "temperature", label: m("f.temp"), type: "number", placeholder: "135" },
    { name: "nozzles", label: m("f.nozzles"), type: "number", placeholder: "4" },
    { name: "is_sample", label: m("f.is_sample"), type: "checkbox", help: m("f.is_sample_hint") },
    { name: "floor_id", label: m("f.prod_floor"), type: "select", required: true, options: look.floors },
    { name: "mc_version", label: m("f.mc_version"), required: true, default: "0" },
    { name: "prod_status", label: m("f.prod_status"), type: "select", required: true, default: "FREE", options: options(m, "prod_status", PROD) },
  ];
  const booleans = ["is_sample", "active"];
  const decorated = rows
    .filter((r) => (searchParams.no ? (r.machine_no ?? "").toLowerCase().includes(searchParams.no.toLowerCase()) : true))
    .filter((r) => (searchParams.type ? r.machine_type_id === searchParams.type : true))
    .filter((r) => (searchParams.brand ? r.brand_id === searchParams.brand : true))
    .filter((r) => (searchParams.state ? r.prod_status === searchParams.state : true))
    .filter((r) => (searchParams.status ? String(r.active) === (searchParams.status === "ACTIVE" ? "true" : "false") : true))
    .map((r) => ({
      ...r,
      location_id: r.location_id ?? "",
      capacity: num(r.capacity),
      efficiency: num(r.efficiency),
      year: num(r.year),
      rpm: num(r.rpm),
      temperature: num(r.temperature),
      nozzles: num(r.nozzles),
      capacity_label: r.capacity === null ? null : `${Number(r.capacity)} ${r.capacity_unit}`,
      efficiency_label: r.efficiency === null ? null : `${Number(r.efficiency)}%`,
      state_label: m(`opt.prod_status.${r.prod_status}`),
    }));
  const listGroups = LISTS.map((l) => ({ key: l, label: m(`opt.list.${l}`), sub: m(`opt.list_sub.${l}`), count: lists.filter((x) => x.list === l).length }));
  const activeList = LISTS.includes(searchParams.list) ? searchParams.list : null;
  const listFields = [
    { name: "name", label: m("f.entry_name"), required: true },
    { name: "sort_order", label: m("f.display_order"), type: "number" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];

  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "lists", label: m("machine.lists"), icon: "ListTree" },
      ...(canManage ? [{ key: "new", label: m("machine.new"), icon: "Plus" }] : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-machines", booleans)} title={m("machine.new")} description={m("machine.hint")} submitLabel={m("machine.save")} columns={3} successHref="/dye-house/dyeing-machine-profile?tab=records" />;
      }
      if (tab === "lists") {
        return (
          <GroupedRecords
            groups={listGroups}
            active={activeList}
            hrefFor={(key) => `/dye-house/dyeing-machine-profile?tab=lists&list=${key}`}
            groupsTitle={m("machine.list_title")}
            recordsTitle={activeList ? m(`opt.list.${activeList}`) : ""}
            emptyText={m("machine.list_hint")}
          >
            {activeList ? (
              <div className="flex flex-col gap-3">
                {canManage ? (
                  <div className="flex justify-end">
                    <DialogFormButton
                      variant="primary"
                      label={m("machine.new_entry")}
                      title={m("machine.new_entry")}
                      fields={[{ name: "list", type: "hidden", default: activeList }, ...listFields]}
                      action={actions.createResource.bind(null, "dye-machine-lists", ["active"])}
                      submitLabel={m("save")}
                    />
                  </div>
                ) : null}
                <RecordsGrid
                  exportName={`dye-${activeList.toLowerCase()}`}
                  rows={lists.filter((l) => l.list === activeList).map((l) => ({ ...l, sort_order: String(l.sort_order) }))}
                  columns={[{ key: "name", label: m("f.entry_name") }, { key: "machines", label: m("f.machines"), type: "number", align: "right" }, { key: "sort_order", label: m("f.display_order"), type: "number", align: "right" }, { key: "active", label: m("f.status"), type: "status" }]}
                  edit={canManage ? { fields: listFields, action: actions.updateResource.bind(null, "dye-machine-lists", ["active"]), title: m("machine.edit_entry"), submitLabel: m("save") } : undefined}
                  remove={canManage ? actions.deleteResource.bind(null, "dye-machine-lists") : undefined}
                />
              </div>
            ) : null}
          </GroupedRecords>
        );
      }
      return (
        <>
          <FilterBar
            instant
            basePath="/dye-house/dyeing-machine-profile"
            values={searchParams}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "no", label: m("f.machine_no"), type: "text" },
              { name: "type", label: m("f.machine_type"), type: "select", options: of("TYPE") },
              { name: "brand", label: m("f.brand"), type: "select", options: of("BRAND") },
              { name: "state", label: m("f.prod_status_short"), type: "select", options: options(m, "prod_status", PROD) },
              { name: "status", label: m("f.status"), type: "select", options: [{ value: "ACTIVE", label: m("opt.status.ACTIVE") }, { value: "INACTIVE", label: m("opt.status.INACTIVE") }] },
            ]}
          />
          <RecordsGrid
            exportName="dye-machines"
            rows={decorated}
            columns={[
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "model", label: m("f.model_col") },
              { key: "capacity_label", label: m("f.capacity"), align: "right" },
              { key: "efficiency_label", label: m("f.efficiency"), align: "right" },
              { key: "sl_no", label: m("f.serial") },
              { key: "year", label: m("f.year"), type: "number" },
              { key: "rpm", label: m("f.rpm"), type: "number", align: "right" },
              { key: "nozzles", label: m("f.nozzles"), type: "number", align: "right" },
              { key: "machine_type", label: m("f.type") },
              { key: "brand", label: m("f.brand") },
              { key: "country", label: m("f.country_short") },
              { key: "floor", label: m("f.unit"), type: "mono" },
              { key: "location", label: m("f.location") },
              { key: "state_label", label: m("f.prod_status_short"), type: "badge" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-machines", booleans), title: m("machine.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          />
        </>
      );
    },
  };
};

const EMPLOYEE_TYPES = ["DF_PROCESS_OPERATOR", "DF_QUALITY_INSPECTION", "GFAB_PROCESS_OPERATOR", "GFAB_QUALITY_INSPECTION"];
const owners = async ({ m, te, tab, canManage }) => {
  const [rows, look] = await Promise.all([apiFetch("/erp/dye-responsible-employees"), lookups()]);
  const base = [
    { name: "employee_type", label: m("f.employee_type"), type: "select", required: true, options: options(m, "employee_type", EMPLOYEE_TYPES) },
    { name: "user_id", label: m("f.responsible_employee"), type: "select", required: true, options: look.users },
    { name: "employee_name", label: m("f.employee_name"), help: m("f.employee_name_hint") },
    { name: "department", label: m("f.department") },
    { name: "designation", label: m("f.designation") },
  ];
  const fields = [...base, { name: "pass_code", label: m("f.pass_code"), type: "password", required: true, help: m("f.pass_code_hint") }];
  const editFields = [...base, { name: "pass_code", label: m("f.pass_code"), type: "password", help: m("f.pass_code_keep") }];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("owner.new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-responsible-employees", [])} title={m("owner.new")} submitLabel={m("owner.save")} columns={3} successHref="/dye-house/dyeing-fin-and-g-quality-resp-emploee?tab=records" />
      ) : (
        <RecordsGrid
          exportName="dye-responsible-employees"
          rows={rows.map((r) => ({ ...r, type_label: m(`opt.employee_type.${r.employee_type}`) }))}
          columns={[
            { key: "type_label", label: m("f.employee_type") },
            { key: "employee_name", label: m("f.employee_name") },
            { key: "user", label: m("f.user") },
            { key: "department", label: m("f.department") },
            { key: "designation", label: m("f.designation") },
          ]}
          edit={canManage ? { fields: editFields, action: actions.updateResource.bind(null, "dye-responsible-employees", []), title: m("owner.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          remove={canManage ? actions.deleteResource.bind(null, "dye-responsible-employees") : undefined}
        />
      ),
  };
};

const audit = async ({ canManage }) => {
  const buyers = await apiFetch("/dyehouse/audit-buyers");
  return { tabs: [], body: () => <AuditChecks buyers={buyers} load={actions.loadAuditChecks} save={actions.saveAuditChecks} canManage={canManage} /> };
};

const colourGroups = async ({ m, canManage }) => {
  const rows = await apiFetch("/erp/dye-colour-groups");
  const fields = [
    { name: "code", label: m("f.color_code"), required: true },
    { name: "name", label: m("f.color_name"), required: true },
    { name: "labdip_required", label: m("f.labdip"), type: "checkbox" },
    { name: "water_cost", label: m("f.water_cost"), type: "number" },
    { name: "wash_cost", label: m("f.wash_cost"), type: "number" },
    { name: "display_order", label: m("f.display_order"), type: "number" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [],
    body: () => (
      <ColourGroupGrid
        key={rows.map((r) => r.id).join()}
        rows={rows}
        save={actions.saveColourGroups}
        canManage={canManage}
        action={canManage ? <DialogFormButton label={m("colour.add")} title={m("colour.add")} fields={fields} action={actions.createResource.bind(null, "dye-colour-groups", ["labdip_required", "active"])} submitLabel={m("save")} /> : null}
      />
    ),
  };
};

const RATE_GROUPS = ["KNITTING", "YARN_DYEING", "DYEING", "DYEING_FINISHING", "AOP", "YARN_TWIST"];
const QUOTE_STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CANCELLED"];

const scRates = async ({ m, te, tab, canManage, searchParams = {} }) => {
  const [quotations, look] = await Promise.all([apiFetch("/dyehouse/sc-quotations"), lookups()]);
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: quotations.length },
      { key: "entry", label: m("sc.title"), icon: "Layers" },
    ],
    body: () =>
      tab === "entry" ? (
        <ScQuotationEditor
          quotations={quotations}
          parties={look.suppliers}
          currencies={look.currencies}
          loadHeads={actions.loadHeads}
          load={actions.loadQuotation}
          save={actions.saveQuotation}
          canManage={canManage}
          initialId={searchParams.quotation ?? null}
        />
      ) : (
        <>
          <FilterBar
            instant
            basePath="/dye-house/dyeing-sc-party-rate"
            keep={{ tab: "records" }}
            values={searchParams}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "ref", label: m("f.ref"), type: "text" },
              { name: "group", label: m("f.rate_group"), type: "select", options: options(m, "rate_group", RATE_GROUPS) },
              { name: "party", label: m("f.sc_party"), type: "select", options: look.suppliers },
              { name: "status", label: m("f.status"), type: "select", options: options(m, "quotation_status", QUOTE_STATUSES) },
            ]}
          />
          <RecordsGrid
            exportName="dye-sc-quotations"
            rows={quotations
              .filter((q) => (!searchParams.ref || q.quotation_no.toLowerCase().includes(searchParams.ref.toLowerCase())) && (!searchParams.group || q.rate_group === searchParams.group) && (!searchParams.party || q.party_id === searchParams.party) && (!searchParams.status || q.status === searchParams.status))
              .map((q) => ({ ...q, group_label: m(`opt.rate_group.${q.rate_group}`), status_label: m(`opt.quotation_status.${q.status}`), open: `/dye-house/dyeing-sc-party-rate?tab=entry&quotation=${encodeURIComponent(q.quotation_no)}` }))}
            columns={[
              { key: "quotation_no", label: m("f.ref_no"), type: "mono" },
              { key: "quotation_date", label: m("f.date"), type: "date" },
              { key: "party", label: m("f.party") },
              { key: "group_label", label: m("f.rate_group") },
              { key: "currency", label: m("f.currency"), type: "mono" },
              { key: "lines", label: m("f.rates"), type: "number", align: "right" },
              { key: "docs", label: m("f.docs"), type: "number", align: "right" },
              { key: "status_label", label: m("f.status"), type: "badge" },
              { key: "open", label: "", type: "link", sortable: false },
            ]}
          />
        </>
      ),
  };
};

// ── Lab-Dip (AnnotechERP 17/02) ─────────────────────────────────────────

const labDip = () => apiFetch("/dyehouse/lab-dip/options");

const dosingTemplates = async ({ m, te, tab, canManage, searchParams = {} }) => {
  const [templates, look] = await Promise.all([apiFetch("/dyehouse/dosing-templates"), labDip()]);
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: templates.length },
      { key: "detail", label: m("tpl.detail_tab"), icon: "Layers" },
    ],
    body: () =>
      tab === "detail" ? (
        <DosingTemplateEditor templates={templates} options={look} load={actions.loadTemplate} save={actions.saveTemplate} remove={actions.deleteTemplate} canManage={canManage} initialCode={searchParams.code ?? null} />
      ) : (
        <RecordsGrid
          exportName="dosing-templates"
          rows={templates.map((r) => ({ ...r, id: r.code, type_label: m(`opt.type.${r.template_type}`), status_label: m(`opt.status.${r.is_finalized ? "FINALISED" : "DRAFT"}`) }))}
          columns={[
            { key: "code", label: m("f.code"), type: "mono" },
            { key: "name", label: m("f.name") },
            { key: "short_name", label: m("f.short_name") },
            { key: "dye_method", label: m("f.dyeing_method") },
            { key: "colour_group", label: m("f.colour_group") },
            { key: "type_label", label: m("f.template_type") },
            { key: "agents", label: m("col.agents"), type: "number", align: "right" },
            { key: "status_label", label: m("col.status"), type: "badge" },
          ]}
          emptyState={{ title: m("tpl.empty"), text: m("tpl.empty_hint") }}
        />
      ),
  };
};

/** A recipe as its grids and its form read it. */
const recipeRows = async (m) =>
  (await apiFetch("/erp/dye-lab-dip-recipes")).map((r) => ({
    ...r,
    option_no: String(r.option_no),
    style_id: r.style_id ?? "",
    fabric_type_id: r.fabric_type_id ?? "",
    dye_method_id: r.dye_method_id ?? "",
    colour_type_label: r.colour_type ? m(`opt.colour_type.${r.colour_type}`) : null,
  }));

/** Brickland's filter row: Buyer A/C, Style # and Recipe #. */
const recipeFilter = (m, look, basePath, searchParams, keep) => (
  <FilterBar
    instant
    basePath={basePath}
    keep={keep}
    values={searchParams}
    labels={{ show: m("filter.show"), reset: m("filter.clear") }}
    fields={[
      { name: "buyer", label: m("f.buyer"), type: "select", options: look.buyers },
      { name: "style", label: m("f.style"), type: "select", options: look.styles },
      { name: "recipe", label: m("f.recipe_no"), type: "text", placeholder: "LD-..." },
    ]}
  />
);
const filterRecipes = (rows, q) =>
  rows.filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.style || r.style_id === q.style) && (!q.recipe || (r.recipe_no ?? "").toLowerCase().includes(q.recipe.trim().toLowerCase())));

const recipes = async ({ m, te, tab, canManage, searchParams = {} }) => {
  const [rows, look] = await Promise.all([recipeRows(m), labDip()]);
  const none = (list) => [{ value: "", label: "—" }, ...list];
  const fields = [
    { name: "recipe_no", label: m("f.recipe_no"), required: true, placeholder: "LD-2026-0001", help: m("f.recipe_no_hint") },
    { name: "option_no", label: m("f.option_no"), type: "number", required: true, default: "1" },
    { name: "buyer_id", label: m("f.buyer"), type: "select", required: true, options: look.buyers },
    { name: "style_id", label: m("f.style"), type: "select", options: none(look.styles) },
    { name: "colour_id", label: m("f.colour"), type: "select", required: true, options: look.colours, help: m("f.colour_hint") },
    { name: "fabric_type_id", label: m("f.fabric_type"), type: "select", options: none(look.fabric_types) },
    { name: "dye_method_id", label: m("f.dyeing_method"), type: "select", options: none(look.methods) },
    { name: "fiber_composition", label: m("f.fiber_comp"), placeholder: "100% Cotton" },
  ];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("btn.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-lab-dip-recipes", [])} title={m("recipe.new_title")} description={m("recipe.hint")} note={m("recipe.note")} submitLabel={m("btn.add_new")} columns={3} />
      ) : (
        <>
          {recipeFilter(m, look, "/dye-house/lab-dip-recipe", searchParams, { tab: "records" })}
          <RecordsGrid
            exportName="lab-dip-recipes"
            rows={filterRecipes(rows, searchParams)}
            columns={[
              { key: "buyer", label: m("col.buyer_name") },
              { key: "style", label: m("col.style_no") },
              { key: "colour_type_label", label: m("col.colour_type") },
              { key: "colour", label: m("col.colour") },
              { key: "fabric_type", label: m("f.fabric_type") },
              { key: "colour_group", label: m("f.colour_group") },
              { key: "dye_method", label: m("f.dyeing_method") },
              { key: "fiber_composition", label: m("f.fiber_comp") },
              { key: "recipe_no", label: m("col.recipe_no"), type: "mono" },
              { key: "option_no", label: m("col.option_no"), type: "number", align: "right" },
              { key: "created_by", label: m("col.created_by") },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-lab-dip-recipes", []), refKey: "ref", title: m("recipe.new_title"), submitLabel: m("btn.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-lab-dip-recipes") : undefined}
            emptyState={{ title: m("recipe.empty"), text: m("recipe.empty_hint") }}
          />
        </>
      ),
  };
};

const recipeLookup = async ({ m, searchParams = {} }) => {
  const [rows, look] = await Promise.all([recipeRows(m), labDip()]);
  return {
    tabs: [],
    body: () => (
      <>
        {recipeFilter(m, look, "/dye-house/lab-dip-recipe-view", searchParams)}
        <RecordsGrid
          exportName="lab-dip-recipe-lookup"
          rows={filterRecipes(rows, searchParams)}
          columns={[
            { key: "buyer", label: m("col.buyer_name") },
            { key: "style", label: m("col.style_no") },
            { key: "colour", label: m("col.colour") },
            { key: "fabric_type", label: m("f.fabric_type") },
            { key: "fiber_composition", label: m("f.fiber_comp") },
            { key: "recipe_no", label: m("col.recipe_no"), type: "mono" },
            { key: "option_no", label: m("col.option_no"), type: "number", align: "right" },
          ]}
          emptyText={m("recipe.empty")}
        />
      </>
    ),
  };
};

const SCREENS = {
  "dyeing-method-setup": methods,
  "dyeing-machine-profile": machines,
  "dyeing-fin-and-g-quality-resp-emploee": owners,
  "dyes-chemical-audit-recipe-setup": audit,
  "color-group-wise-rate-update": colourGroups,
  "dyeing-sc-party-rate": scRates,
  "dosing-template": dosingTemplates,
  "lab-dip-recipe": recipes,
  "lab-dip-recipe-view": recipeLookup,
};

export { SCREENS };
