import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { CompositionForm, CompositionList } from "@/components/merchandise/composition-form";
import { GarmentPartPicker } from "@/components/merchandise/garment-part-picker";
import { PickTicker } from "@/components/merchandise/pick-ticker";
import { SampleMapping } from "@/components/merchandise/sample-mapping";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { GroupedRecords } from "@/components/erp/grouped-records";
import { RateChartEditor } from "@/components/merchandise/rate-chart-editor";
import { TrimSpecEditor } from "@/components/merchandise/trim-spec-editor";
import { TnaRightsMatrix } from "@/components/merchandise/tna-rights-matrix";
import { TnaCalendarEditor } from "@/components/merchandise/tna-calendar-editor";
import { nounLabels } from "@/lib/brickland-labels";
import { findByRef, refsFor } from "@/lib/row-ref";
import { UrlFilters } from "@/components/erp/url-filters";
import * as actions from "./actions";

/*
 * Merchandise Desk → Master Data (AnnotechERP 06/01): the reference lists, each
 * Brickland's Records and New tabs over one screen-kit resource, and the
 * screens with a shape of their own — Fiber Composition, GARM Part List, the
 * buyer mappings, Inquiry Rate Chart, Trims Spec, and the T&A calendar and
 * rights.
 */

const REGIONS = ["ASIAN", "EU", "AMERICAS", "AFRICA", "OCEANIA", "MIDDLE_EAST"];
const FABRIC_GROUPS = ["BODY", "COLLAR", "LINING", "RIB"];

function options(m, group, values) {
  return values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
}

/**
 * Records + New over one resource, as Brickland lays out every master list.
 * With a `noun`, Brickland's words and Edit: "Save colour", Edit opening an
 * "Edit colour" tab (?tab=edit&ref=…) with "Rewriting …", Cancel and "Update
 * colour", and "Remove this colour?" on delete. `newFromSave` names the New
 * tab after its button, as Process Loss's "Save Configuration".
 */
function masterScreen(config) {
  return async function renderMaster({ m, te, tab, canManage, searchParams = {} }) {
    const extra = config.load ? await config.load() : {};
    const decorate = (row) => (config.decorate ? config.decorate(row, { m, ...extra }) : row);
    const rows = (await apiFetch(`/erp/${config.resource}`)).map(decorate);
    const fields = config.fields({ m, ...extra });
    const columns = config.columns ? config.columns({ m, ...extra }) : [];
    const booleans = config.booleans ?? ["active"];
    const action = (verb) => actions[verb].bind(null, config.resource, booleans);
    const extraTabs = config.extraTabs ? config.extraTabs({ m, rows, canManage, searchParams, ...extra }) : [];
    const nameKey = config.nameKey ?? columns[0]?.key;
    const words = config.noun ? nounLabels(te, m(`noun.${config.noun}`), nameKey) : null;
    // The Edit tab names its row by a readable reference (`?tab=edit&ref=…`): the grid's first column, or `refKey`.
    const refKey = config.refKey ?? columns[0]?.key;
    const target = words && (!config.newForm || config.editForm) && tab === "edit" && searchParams.ref && canManage ? findByRef(rows, refKey, searchParams.ref) : null;
    const editing = target ? await apiFetch(`/erp/${config.resource}/${target.id}`).then(decorate).catch(() => null) : null;
    const records = `/merchandise-desk/${config.screen}?tab=records`;
    const newTab = canManage && (config.newLabel || config.newFromSave) ? [{ key: "new", label: config.tabLabel ? m(config.tabLabel) : config.newFromSave && words ? words.save : m(config.newLabel), icon: "Plus" }] : [];

    return {
      tabs: [
        { key: "records", label: te("records"), icon: "Table2", count: rows.length },
        ...extraTabs.map(({ key, label, icon, href }) => ({ key, label, icon, href })),
        ...(editing ? [{ key: "edit", label: words.edit, icon: "Pencil" }] : newTab),
      ],
      body: () => {
        if (editing && config.editForm) {
          // The screen's own form draws Brickland's card: "Edit X", the "Rewriting …" line and Records.
          return config.editForm({ m, te, row: editing, records, heading: { title: words.edit, subtitle: words.rewriting(editing[nameKey] ?? ""), recordsHref: records, recordsLabel: te("records") }, ...extra });
        }
        if (editing) {
          return (
            <EntryForm
              key={editing.id}
              fields={fields}
              initial={editing}
              action={actions.updateResource.bind(null, config.resource, booleans, editing.id)}
              title={words.edit}
              description={words.rewriting(editing[nameKey] ?? "")}
              submitLabel={words.update}
              columns={config.formColumns ?? 2}
              cancelHref={records}
              cancelLabel={te("cancel")}
              successHref={records}
            />
          );
        }
        const custom = extraTabs.find((x) => x.key === tab);
        if (custom) return custom.render();
        if (tab === "new" && canManage) {
          return config.newForm ? (
            config.newForm({ m, te, ...extra })
          ) : (
            <EntryForm
              fields={fields}
              action={action("createResource")}
              title={config.newFromSave && words ? words.save : m(config.newLabel)}
              description={config.description ? m(config.description) : undefined}
              submitLabel={config.submitLabel ? m(config.submitLabel) : words ? words.save : m("save")}
              columns={config.formColumns ?? 2}
              successHref={records}
            />
          );
        }
        if (config.list) return config.list({ m, rows, words, ...extra });
        const filters = config.filters ? config.filters({ m, rows, ...extra }) : [];
        // A filter matches its field exactly, unless it says how (`match`, e.g. a date range's from / to).
        const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
        const grid = (
          <RecordsGrid
            exportName={config.screen}
            rows={shown}
            columns={columns}
            edit={
              canManage
                ? words && !config.newForm
                  ? { href: `/merchandise-desk/${config.screen}?tab=edit&ref=`, refKey }
                  : { fields, action: action("updateResource"), title: m(config.editLabel ?? "edit"), submitLabel: words ? words.update : m("save"), columns: config.formColumns ?? 2 }
                : undefined
            }
            remove={canManage && !config.noDelete ? actions.deleteResource.bind(null, config.resource) : undefined}
            rowAction={canManage && config.rowAction ? config.rowAction({ m }) : undefined}
            deleteDescription={m("delete_hint")}
            deleteLabels={words?.remove}
            // Brickland may name the rows differently from the Save button ("Save Configuration" / "No cutting allowance records yet").
            emptyState={config.emptyNoun ? nounLabels(te, m(`noun.${config.emptyNoun}`)).empty : words?.empty}
          />
        );
        return filters.length ? (
          <div className="flex flex-col gap-3">
            <div className="rounded-lg border border-border bg-surface p-4">
              <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
            </div>
            {grid}
          </div>
        ) : (
          grid
        );
      },
    };
  };
}

const lookups = () => apiFetch("/merchandise/lookups");
const pickOne = (m, list) => [{ value: "", label: m("select") }, ...list];
const SOURCE_MODULES = ["ORDER_CONFIRMATION", "EXPORT_LC", "FABRIC_BOOKING", "FABRIC_RECEIVED", "CUTTING_STARTED", "CUTTING_COMPLETE", "SEWING_STARTED", "SEWING_COMPLETE", "FINISHING_STARTED", "FINISHING_COMPLETE", "PACKING_COMPLETE", "SHIPMENT_COMPLETE"];

const status = (m) => ({ key: "active", label: m("f.status"), type: "status" });
const yesno = (m, key, label) => ({ key, label: m(label ?? `f.${key}`), type: "yesno" });

const countryOrigin = masterScreen({
  resource: "origin-countries",
  screen: "country-origin-master",
  noun: "country",
  newLabel: "new.country",
  fields: ({ m }) => [
    { name: "code", label: m("f.code"), required: true, placeholder: "1000" },
    { name: "name", label: m("f.name"), required: true, placeholder: "Bangladesh" },
    { name: "name_bn", label: m("f.name_bn") },
    { name: "region", label: m("f.region"), type: "select", options: options(m, "region", REGIONS) },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, region_label: row.region ? m(`opt.region.${row.region}`) : null }),
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    yesno(m, "active", "f.active"),
    { key: "region_label", label: m("f.region") },
    status(m),
  ],
});

const countrySetup = masterScreen({
  resource: "country-configs",
  screen: "country-setup",
  submitLabel: "add_setup",
  tabLabel: "add_setup",
  noun: "country_setup",
  newLabel: "new.country_setup",
  booleans: ["active", "is_ship_to"],
  fields: ({ m }) => [
    { name: "code", label: m("f.code"), required: true, placeholder: "BD" },
    { name: "name", label: m("f.name"), required: true, placeholder: "Bangladesh" },
    { name: "region", label: m("f.region"), type: "select", options: options(m, "region", REGIONS) },
    { name: "currency", label: m("f.currency"), placeholder: "BDT" },
    { name: "dial_code", label: m("f.dial_code"), placeholder: "+880" },
    { name: "is_ship_to", label: m("f.is_ship_to"), type: "checkbox" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, region_label: row.region ? m(`opt.region.${row.region}`) : null }),
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "region_label", label: m("f.region") },
    { key: "currency", label: m("f.currency"), type: "mono" },
    { key: "dial_code", label: m("f.dial_code"), type: "mono" },
    yesno(m, "is_ship_to", "f.ship_to"),
    yesno(m, "active", "f.active"),
    status(m),
  ],
});

const colour = masterScreen({
  resource: "colours",
  screen: "colour-master",
  // The url names a row by its name; the first column is a group, shared by many.
  refKey: "name",
  noun: "colour",
  newLabel: "new.colour",
  booleans: ["active", "swatch_provided"],
  load: async () => ({ look: await lookups() }),
  fields: ({ m, look }) => [
    { name: "code", label: m("f.colour_code"), help: m("colour.code_hint") },
    { name: "colour_type", label: m("f.colour_type"), type: "select", required: true, options: options(m, "colour_type", ["SOLID", "MELANGE", "YARN_DYED", "AOP", "STRIPE"]) },
    { name: "name", label: m("f.colour_name"), required: true },
    { name: "pantone_no", label: m("f.pantone_no") },
    { name: "version_no", label: m("f.version_no") },
    { name: "colour_group_id", label: m("f.colour_group"), type: "select", options: pickOne(m, look.colour_groups) },
    { name: "swatch_provided", label: m("f.swatch_provided"), type: "checkbox" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, colour_group_id: row.colour_group_id ?? "", type_label: m(`opt.colour_type.${row.colour_type}`) }),
  columns: ({ m }) => [
    { key: "type_label", label: m("f.colour_type") },
    { key: "code", label: m("f.colour_code"), type: "mono" },
    { key: "name", label: m("f.colour_name_col") },
    { key: "pantone_no", label: m("f.pantone_col"), type: "mono" },
    { key: "version_no", label: m("f.version_col") },
    status(m),
  ],
});

const SIZE_GROUPS = ["LETTER", "NUMERIC", "AGE"];
const size = masterScreen({
  resource: "sizes",
  screen: "size-master",
  // The url names a row by its name; the first column is a group, shared by many.
  refKey: "name",
  noun: "size",
  newLabel: "new.size",
  fields: ({ m }) => [
    { name: "size_group", label: m("f.size_group"), type: "select", options: options(m, "size_group", SIZE_GROUPS) },
    { name: "code", label: m("f.size_code") },
    { name: "name", label: m("f.size_name"), required: true, placeholder: "M" },
    { name: "name_bn", label: m("f.size_name_bn") },
    { name: "comp_size_group", label: m("f.comp_size_group"), type: "select", options: options(m, "size_group", SIZE_GROUPS) },
    { name: "display_order", label: m("f.display_order"), type: "number", default: "0" },
    { name: "comp_display_order", label: m("f.comp_display_order"), type: "number", default: "0" },
    { name: "active", label: m("f.is_active_q"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({
    ...row,
    group_label: row.size_group ? m(`opt.size_group.${row.size_group}`) : null,
    comp_label: row.comp_size_group ? m(`opt.size_group.${row.comp_size_group}`) : null,
    display_order: String(row.display_order ?? 0),
    comp_display_order: String(row.comp_display_order ?? 0),
  }),
  columns: ({ m }) => [
    { key: "group_label", label: m("f.size_group") },
    { key: "comp_label", label: m("f.comp_size_group") },
    { key: "code", label: m("f.code_short"), type: "mono" },
    { key: "name", label: m("f.size_name_col") },
    yesno(m, "active", "f.active"),
    { key: "display_order", label: m("f.display_order"), type: "number" },
    { key: "comp_display_order", label: m("f.comp_display_order"), type: "number" },
    status(m),
  ],
});

const garmentPart = masterScreen({
  resource: "garment-parts",
  screen: "garment-part",
  noun: "part",
  newLabel: "new.part",
  booleans: ["active", "is_circular", "is_process_group", "is_body"],
  fields: ({ m }) => [
    { name: "code", label: m("f.code"), required: true, placeholder: "SLV" },
    { name: "name", label: m("f.name"), required: true, placeholder: "Sleeve" },
    { name: "fabric_group", label: m("f.fabric_group"), type: "select", options: options(m, "fabric_group", FABRIC_GROUPS) },
    { name: "is_circular", label: m("f.is_circular"), type: "checkbox" },
    { name: "is_process_group", label: m("f.is_process_group"), type: "checkbox", help: m("f.is_process_group_hint") },
    { name: "is_body", label: m("f.is_body"), type: "checkbox" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, group_label: row.fabric_group ? m(`opt.fabric_group.${row.fabric_group}`) : null }),
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "group_label", label: m("f.fabric_group") },
    yesno(m, "is_circular", "f.circular"),
    yesno(m, "is_body", "f.body"),
    yesno(m, "is_process_group", "f.process_group"),
    status(m),
  ],
});

const serviceType = masterScreen({
  resource: "service-types",
  screen: "service-type-setup",
  // The url names a row by its name; the first column is a group, shared by many.
  refKey: "name",
  noun: "service_type",
  newLabel: "new.service_type",
  fields: ({ m }) => [
    {
      name: "service_for",
      label: m("f.service_for"),
      type: "select",
      required: true,
      options: options(m, "service_for", ["AOP", "YARN_DYEING", "PRINT", "EMBROIDERY", "GMT_WASH"]),
    },
    { name: "code", label: m("f.code"), required: true },
    { name: "name", label: m("f.name"), required: true },
    { name: "remarks", label: m("f.remarks") },
    { name: "sort_order", label: m("f.order"), type: "number", default: "0" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, service_label: m(`opt.service_for.${row.service_for}`), sort_order: String(row.sort_order ?? 0) }),
  filters: ({ m }) => [{ name: "service_for", field: "service_for", label: m("f.service_for"), options: options(m, "service_for", ["AOP", "YARN_DYEING", "PRINT", "EMBROIDERY", "GMT_WASH"]) }],
  columns: ({ m }) => [
    { key: "service_label", label: m("f.service_for") },
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "remarks", label: m("f.remarks") },
    { key: "sort_order", label: m("f.order"), type: "number" },
    status(m),
  ],
});

const ITEM_GROUPS = ["KNIT_TOP", "KNIT_BOTTOM", "WOVEN_TOP", "WOVEN_BOTTOM"];
const itemGroups = (m) => ITEM_GROUPS.map((v) => ({ value: v, label: m(`ity.groups.${v}`) }));

const itemType = masterScreen({
  resource: "item-types",
  screen: "item-type-master",
  // The url names a row by its name; the first column is a group, shared by many.
  refKey: "name",
  noun: "item_type",
  newLabel: "new.item_type",
  fields: ({ m }) => [
    { name: "item_group", label: m("ity.f.item_group"), type: "select", required: true, default: "KNIT_TOP", options: itemGroups(m), help: m("ity.group_help") },
    { name: "code", label: m("ity.f.code"), required: true, placeholder: "IT-TSH" },
    { name: "name", label: m("ity.f.name"), required: true, placeholder: "T-Shirt", help: m("ity.name_help") },
    { name: "remarks", label: m("ity.f.remarks") },
    { name: "sort_order", label: m("ity.f.sort_order"), type: "number", default: "0" },
    { name: "active", label: m("ity.f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, group_label: m(`ity.groups.${row.item_group}`), sort_order: String(row.sort_order ?? 0) }),
  filters: ({ m }) => [{ name: "item_group", field: "item_group", label: m("ity.f.item_group"), options: itemGroups(m) }],
  columns: ({ m }) => [
    { key: "group_label", label: m("ity.f.item_group") },
    { key: "code", label: m("ity.f.code"), type: "mono" },
    { key: "name", label: m("ity.f.name") },
    { key: "remarks", label: m("ity.f.remarks") },
    { key: "sort_order", label: m("ity.f.sort_order"), type: "number" },
    status(m),
  ],
});

const workNature = masterScreen({
  resource: "work-natures",
  screen: "nature-of-work-master",
  noun: "work_nature",
  newLabel: "new.work_nature",
  fields: ({ m }) => [
    { name: "code", label: m("nwk.f.code"), required: true, placeholder: "NW-CUT" },
    { name: "name", label: m("nwk.f.name"), required: true, placeholder: "Cutting", help: m("nwk.name_help") },
    { name: "remarks", label: m("nwk.f.remarks") },
    { name: "sort_order", label: m("nwk.f.sort_order"), type: "number", default: "0" },
    { name: "active", label: m("nwk.f.active"), type: "checkbox", default: true },
  ],
  decorate: (row) => ({ ...row, sort_order: String(row.sort_order ?? 0) }),
  columns: ({ m }) => [
    { key: "code", label: m("nwk.f.code"), type: "mono" },
    { key: "name", label: m("nwk.f.name") },
    { key: "remarks", label: m("nwk.f.remarks") },
    { key: "sort_order", label: m("nwk.f.sort_order"), type: "number" },
    status(m),
  ],
});

const fabricType = masterScreen({
  resource: "fabric-types",
  screen: "fabric-type-entry",
  noun: "fabric_type",
  newLabel: "new.fabric_type",
  booleans: ["active", "is_base", "is_rib"],
  load: async () => ({ bases: (await apiFetch("/erp/fabric-types")).filter((f) => f.is_base) }),
  fields: ({ m, bases }) => [
    { name: "code", label: m("f.fabric_type_code"), required: true, placeholder: "SJ" },
    { name: "name", label: m("f.fabric_type_name"), required: true, placeholder: "Single Jersey" },
    { name: "short_name", label: m("f.short_name"), required: true, placeholder: "S/J" },
    { name: "fabric_group", label: m("f.fabric_group"), type: "select", options: options(m, "fabric_group", FABRIC_GROUPS) },
    { name: "category", label: m("f.category"), type: "select", options: options(m, "category", ["KNIT", "SWEATER", "WOVEN"]) },
    { name: "construction", label: m("f.construction"), type: "select", required: true, options: options(m, "construction", ["CIRCULAR", "FLAT"]) },
    { name: "is_base", label: m("f.is_base"), type: "checkbox", help: m("f.is_base_hint") },
    { name: "parent_id", label: m("f.parent_type"), type: "select", options: [{ value: "", label: "—" }, ...bases.map((b) => ({ value: b.id, label: `${b.name} (${b.code})` }))] },
    { name: "is_rib", label: m("f.is_rib"), type: "checkbox" },
    { name: "active", label: m("f.is_active_q"), type: "checkbox", default: true },
    { name: "sort_order", label: m("f.display_order"), type: "number", required: true, default: "0" },
  ],
  decorate: (row, { m }) => ({
    ...row,
    flat_cir: row.construction === "FLAT" ? "F" : "C",
    rib_yn: row.is_rib ? "Y" : "N",
    base_yn: row.is_base ? "Y" : "N",
    parts: row.garment_parts?.length ? row.garment_parts.join(", ") : null,
    category_label: row.category ? m(`opt.category.${row.category}`) : null,
    group_label: row.fabric_group ? m(`opt.fabric_group.${row.fabric_group}`) : null,
    parent_id: row.parent_id ?? "",
    sort_order: String(row.sort_order ?? 0),
  }),
  columns: ({ m }) => [
    { key: "code", label: m("f.type_code"), type: "mono" },
    { key: "name", label: m("f.type_name") },
    { key: "short_name", label: m("f.short_name") },
    { key: "flat_cir", label: m("f.flat_cir"), align: "center" },
    { key: "rib_yn", label: m("f.rib"), align: "center" },
    { key: "base_yn", label: m("f.base"), align: "center" },
    { key: "parts", label: m("f.garm_part") },
    { key: "category_label", label: m("f.category_short") },
    { key: "group_label", label: m("f.group") },
    { key: "sort_order", label: m("f.order"), type: "number" },
    status(m),
  ],
  extraTabs: ({ m, rows, canManage }) => [
    {
      key: "garm",
      label: m("garm.tab"),
      icon: "ListChecks",
      render: () => <GarmentPartPicker fabricTypes={rows} load={actions.loadGarmentParts} save={actions.saveGarmentParts} canManage={canManage !== false} />,
    },
  ],
});

const fiber = masterScreen({
  resource: "fibers",
  screen: "fiber-master",
  noun: "fiber",
  newLabel: "new.fiber",
  fields: ({ m }) => [
    { name: "code", label: m("f.fiber_code"), required: true, placeholder: "COT" },
    { name: "name", label: m("f.fiber_name"), required: true, placeholder: "Cotton" },
    { name: "short_name", label: m("f.short_name") },
    { name: "sort_order", label: m("f.display_order"), type: "number", required: true, default: "0" },
    { name: "active", label: m("f.is_active_q"), type: "checkbox", default: true },
  ],
  decorate: (row) => ({ ...row, sort_order: String(row.sort_order ?? 0) }),
  columns: ({ m }) => [
    { key: "code", label: m("f.fiber_code"), type: "mono" },
    { key: "name", label: m("f.fiber_name") },
    { key: "short_name", label: m("f.short_name") },
    { key: "used_by", label: m("f.used_by"), type: "number" },
    { key: "sort_order", label: m("f.order"), type: "number" },
    status(m),
  ],
});

const fiberComposition = masterScreen({
  resource: "fiber-compositions",
  screen: "fiber-composition",
  nameKey: "code",
  refKey: "code",
  noun: "composition",
  newLabel: "new.composition",
  load: async () => ({ fibers: await apiFetch("/erp/fibers") }),
  fields: () => [],
  editForm: ({ te, row, fibers, records, heading }) => <CompositionForm fibers={fibers} initial={row} save={actions.saveComposition} successHref={records} cancelHref={records} cancelLabel={te("cancel")} heading={heading} />,
  // Brickland's New tab: the card titled "New Fiber Composition" with Records, and Save / Cancel.
  newForm: ({ m, te, fibers }) => {
    const records = "/merchandise-desk/fiber-composition?tab=records";
    return <CompositionForm fibers={fibers} save={actions.saveComposition} successHref={records} cancelHref={records} cancelLabel={te("cancel")} heading={{ title: m("new.composition"), recordsHref: records, recordsLabel: te("records") }} />;
  },
  list: ({ m, rows, fibers, words }) => (
    <CompositionList
      rows={rows.map((r) => ({ ...r, total_label: `${r.total}%` }))}
      fibers={fibers}
      columns={[
        { key: "code", label: m("f.comp_code"), type: "mono" },
        { key: "description", label: m("f.comp_desc") },
        { key: "fiber_types", label: m("f.fiber_types") },
        { key: "total_label", label: m("f.total"), align: "right" },
        status(m),
      ]}
      save={actions.saveComposition}
      remove={actions.deleteResource.bind(null, "fiber-compositions")}
      editHref="/merchandise-desk/fiber-composition?tab=edit&ref="
      deleteLabels={words?.remove}
    />
  ),
});

const yarnCount = masterScreen({
  resource: "yarn-count-configs",
  screen: "gsm-wise-count-conf",
  noun: "count",
  newLabel: "new.count",
  booleans: [],
  load: async () => ({ fabricTypes: await apiFetch("/erp/fabric-types") }),
  fields: ({ m, fabricTypes }) => [
    { name: "fabric_type_id", label: m("f.fab_type"), type: "select", required: true, options: fabricTypes.map((f) => ({ value: f.id, label: `${f.name} (${f.code})` })) },
    { name: "template", label: m("f.template"), type: "select", options: options(m, "template", ["COTTON_100", "LYCRA_BLEND", "CVC_BLEND"]) },
    { name: "gauge", label: m("f.gauge"), required: true, placeholder: "24G" },
    { name: "gsm_min", label: m("f.gsm_min"), type: "number", required: true },
    { name: "gsm_max", label: m("f.gsm_max"), type: "number", required: true },
    { name: "count_top", label: m("f.count_top"), placeholder: "30/1" },
    { name: "count_binding", label: m("f.count_binding") },
    { name: "count_back", label: m("f.count_back") },
  ],
  decorate: (row, { m }) => ({ ...row, template_label: row.template ? m(`opt.template.${row.template}`) : null, gsm_min: String(row.gsm_min), gsm_max: String(row.gsm_max) }),
  columns: ({ m }) => [
    { key: "construction", label: m("f.construction_col") },
    { key: "template_label", label: m("f.template_col") },
    { key: "gauge", label: m("f.gauge"), type: "mono" },
    { key: "gsm_min", label: m("f.gsm_min"), type: "number" },
    { key: "gsm_max", label: m("f.gsm_max"), type: "number" },
    { key: "count_top", label: m("f.top"), type: "mono" },
    { key: "count_binding", label: m("f.binding"), type: "mono" },
    { key: "count_back", label: m("f.back"), type: "mono" },
  ],
});

/** The labels a "pick one, tick the rest" panel shows, from `master.pick.<key>.*`. */
function pickLabels(m, key) {
  return { title: m(`pick.${key}.title`), hint: m(`pick.${key}.hint`), select: m(`pick.${key}.select`), placeholder: m(`pick.${key}.placeholder`), choose: m(`pick.${key}.choose`), empty: m(`pick.${key}.empty`) };
}

const OFFICE_TYPES = ["LIAISON", "HEAD_OFFICE", "BUYING_HOUSE", "AGENT"];

const buyerOffice = masterScreen({
  resource: "buyer-offices",
  screen: "buyer-office-master",
  noun: "office",
  newLabel: "new.office",
  load: async () => ({ countries: await apiFetch("/erp/country-configs"), subjects: await apiFetch("/merchandise/mappings/office-buyers/subjects") }),
  fields: ({ m, countries }) => [
    { name: "name", label: m("f.office_name"), required: true, placeholder: "LNDBPL" },
    { name: "office_type", label: m("f.office_type"), type: "select", required: true, options: options(m, "office_type", OFFICE_TYPES) },
    { name: "address", label: m("f.address"), type: "textarea" },
    { name: "country_id", label: m("f.country"), type: "select", options: [{ value: "", label: "—" }, ...countries.map((c) => ({ value: c.id, label: c.name }))] },
    { name: "contact_person", label: m("f.contact_person") },
    { name: "email", label: m("f.email"), type: "email" },
    { name: "phone", label: m("f.phone") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, type_label: m(`opt.office_type.${row.office_type}`), buyers_list: row.buyers?.length ? row.buyers.join(", ") : null, country_id: row.country_id ?? "" }),
  columns: ({ m }) => [
    { key: "name", label: m("f.office_name") },
    { key: "type_label", label: m("f.office_type") },
    { key: "address", label: m("f.address") },
    { key: "country", label: m("f.country") },
    { key: "buyers_list", label: m("f.buyer_acc") },
    yesno(m, "active", "f.active"),
    status(m),
  ],
  extraTabs: ({ m, canManage, subjects }) => [
    { key: "buyers", label: m("pick.office_buyers.tab"), icon: "ListChecks", render: () => <PickTicker kind="office-buyers" subjects={subjects} load={actions.loadMapping} save={actions.saveMapping} canManage={canManage} labels={pickLabels(m, "office_buyers")} /> },
  ],
});

const buyer = masterScreen({
  resource: "buyers",
  screen: "buyer-master",
  noun: "buyer",
  newLabel: "new.buyer",
  load: async () => ({ offices: await apiFetch("/erp/buyer-offices"), subjects: await apiFetch("/merchandise/mappings/buyer-offices/subjects"), look: await lookups() }),
  description: "buyer.hint",
  fields: ({ m, offices, look }) => [
    { name: "code", label: m("f.buyer_code"), required: true, placeholder: "GEB" },
    { name: "name", label: m("f.buyer_name"), required: true, placeholder: "GHFMBH + CO. KG" },
    { name: "name_bn", label: m("f.buyer_name_bn") },
    { name: "short_name", label: m("f.buyer_short_name"), required: true, placeholder: "G HUBER" },
    { name: "profile", label: m("f.buyer_profile"), type: "textarea" },
    { name: "pi_address", label: m("f.pi_address"), type: "textarea", required: true },
    { name: "contact_person", label: m("f.contact_person") },
    { name: "country_id", label: m("f.country"), type: "select", required: true, options: look.countries },
    { name: "registered_from", label: m("f.registered_from"), type: "date", required: true },
    { name: "registered_to", label: m("f.registered_to"), type: "date" },
    { name: "bank_id", label: m("f.advising_bank"), type: "select", options: pickOne(m, look.banks) },
    { name: "supplier_id", label: m("f.supplier"), type: "select", options: pickOne(m, look.suppliers) },
    { name: "display_order", label: m("f.display_order"), type: "number", default: "0" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
    { name: "office_id", label: m("f.link_to_office"), type: "select", required: true, options: offices.map((o) => ({ value: o.id, label: o.name })), help: m("buyer.office_hint") },
  ],
  decorate: (row) => ({ ...row, offices_list: row.offices?.length ? row.offices.join(", ") : null, office_id: row.office_id ?? "", country_id: row.country_id ?? "", bank_id: row.bank_id ?? "", supplier_id: row.supplier_id ?? "", display_order: String(row.display_order ?? 0) }),
  columns: ({ m }) => [
    { key: "code", label: m("f.buyer_code"), type: "mono" },
    { key: "name", label: m("f.buyer_name_col") },
    { key: "short_name", label: m("f.short_name_col") },
    { key: "pi_address", label: m("f.address") },
    { key: "display_order", label: m("f.display_order"), type: "number" },
    { key: "bank", label: m("f.bank_name") },
    { key: "offices_list", label: m("f.offices") },
    status(m),
  ],
  extraTabs: ({ m, canManage, subjects }) => [
    { key: "offices", label: m("pick.buyer_offices.tab"), icon: "ListChecks", render: () => <PickTicker kind="buyer-offices" subjects={subjects} load={actions.loadMapping} save={actions.saveMapping} canManage={canManage} labels={pickLabels(m, "buyer_offices")} /> },
  ],
});

/** Buyer Account Permission and its Marketing twin: who may work which buyers. */
function permissionScreen(kind, key) {
  return async function renderPermission({ m, te, tab, canManage }) {
    const [rows, subjects] = await Promise.all([apiFetch(`/merchandise/mappings/${kind}`), apiFetch(`/merchandise/mappings/${kind}/subjects`)]);
    return {
      tabs: [
        { key: "records", label: te("records"), icon: "Table2", count: rows.length },
        { key: "assign", label: m(`pick.${key}.tab`), icon: "ListChecks" },
      ],
      body: () =>
        tab === "assign" ? (
          <PickTicker kind={kind} subjects={subjects} load={actions.loadMapping} save={actions.saveMapping} canManage={canManage} labels={pickLabels(m, key)} />
        ) : (
          <RecordsGrid
            exportName={kind}
            rows={rows.map((r) => ({ ...r, items_list: r.items.join(", ") }))}
            columns={[
              { key: "subject", label: m("f.user") },
              { key: "count", label: m("f.buyer_count"), type: "number" },
              { key: "items_list", label: m("f.buyers") },
            ]}
          />
        ),
    };
  };
}

async function buyerSampleMapping({ canManage }) {
  const buyers = await apiFetch("/erp/buyers");
  return {
    tabs: [],
    body: () => (
      <SampleMapping
        buyers={buyers.map((b) => ({ value: b.id, label: `${b.short_name} (${b.code})` }))}
        mappedBuyerIds={buyers.filter((b) => b.sample_count > 0).map((b) => b.id)}
        load={actions.loadSamples}
        save={actions.saveSamples}
        canManage={canManage}
      />
    ),
  };
}

async function buyerTestLab({ m, canManage }) {
  const subjects = await apiFetch("/merchandise/mappings/buyer-institutes/subjects");
  const instituteFields = [
    { name: "name", label: m("f.name_en"), required: true },
    { name: "name_bn", label: m("f.name_bn_short") },
    { name: "address", label: m("f.address"), type: "textarea" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [],
    body: () => (
      <PickTicker
        kind="buyer-institutes"
        subjects={subjects}
        load={actions.loadMapping}
        save={actions.saveMapping}
        canManage={canManage}
        labels={pickLabels(m, "buyer_institutes")}
        action={
          canManage ? (
            <DialogFormButton label={m("new.institute")} title={m("new.institute")} fields={instituteFields} action={actions.createResource.bind(null, "test-institutes", ["active"])} submitLabel={m("save")} />
          ) : null
        }
      />
    ),
  };
}

const buyerTrims = masterScreen({
  resource: "buyer-trim-items",
  screen: "buyer-wise-trim-accessories",
  noun: "trim",
  newLabel: "new.trim",
  load: async () => ({ buyers: await apiFetch("/erp/buyers"), look: await lookups() }),
  fields: ({ m, buyers, look }) => [
    { name: "buyer_id", label: m("f.buyer"), type: "select", required: true, options: buyers.map((b) => ({ value: b.id, label: `${b.short_name} (${b.code})` })) },
    { name: "item_category_id", label: m("f.category_plain"), type: "select", options: pickOne(m, look.item_categories) },
    { name: "item_name", label: m("f.item_name"), required: true, placeholder: "Main Label" },
    { name: "item_code", label: m("f.item_code"), required: true, placeholder: "LBL-MAIN" },
    { name: "specification", label: m("f.specification"), placeholder: "Woven, damask, 50 x 20 mm" },
    { name: "material", label: m("f.material"), placeholder: "Polyester" },
    { name: "color", label: m("f.color"), placeholder: "Black" },
    { name: "size", label: m("f.size"), placeholder: "50 x 20 mm" },
    { name: "uom", label: m("f.uom"), type: "uom" },
    { name: "approved_by", label: m("f.approved_by") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
    { name: "description", label: m("f.description"), type: "textarea" },
  ],
  columns: ({ m }) => [
    { key: "item_code", label: m("f.item_code"), type: "mono" },
    { key: "item_name", label: m("f.item_name") },
    { key: "category_name", label: m("f.category_plain") },
    { key: "specification", label: m("f.specification") },
    { key: "material", label: m("f.material") },
    { key: "color", label: m("f.color") },
    { key: "size", label: m("f.size") },
    { key: "uom", label: m("f.uom") },
    { key: "approved_by", label: m("f.approved_by") },
    status(m),
  ],
  extraTabs: ({ m, rows, buyers, searchParams }) => [
    {
      key: "by-buyer",
      label: m("trims.tab"),
      icon: "ListChecks",
      render: () => {
        // A buyer is named in the url by its code, not its id.
        const groups = buyers.map((b) => ({ key: b.code, label: `${b.short_name} (${b.code})`, count: rows.filter((r) => r.buyer_id === b.id).length }));
        const buyer = buyers.find((b) => b.code === searchParams.buyer) ?? null;
        const active = buyer ? buyer.code : null;
        return (
          <GroupedRecords
            groups={groups}
            active={active}
            hrefFor={(id) => `/merchandise-desk/buyer-wise-trim-accessories?tab=by-buyer&buyer=${encodeURIComponent(id)}`}
            groupsTitle={m("trims.buyers")}
            recordsTitle={groups.find((g) => g.key === active)?.label ?? ""}
            emptyText={m("trims.choose")}
          >
            {active ? (
              <RecordsGrid
                exportName="buyer-trims"
                rows={rows.filter((r) => r.buyer_id === buyer.id)}
                columns={[
                  { key: "item_code", label: m("f.item_code"), type: "mono" },
                  { key: "item_name", label: m("f.item_name") },
                  { key: "specification", label: m("f.specification") },
                  { key: "color", label: m("f.color") },
                  { key: "size", label: m("f.size") },
                  status(m),
                ]}
              />
            ) : null}
          </GroupedRecords>
        );
      },
    },
  ],
});

const buyerSupplier = masterScreen({
  resource: "buyer-supplier-configs",
  screen: "buyer-supplier-config",
  noun: "supplier_config",
  newLabel: "new.supplier_config",
  description: "supplier_config.hint",
  formColumns: 3,
  load: async () => ({ buyers: await apiFetch("/erp/buyers"), look: await lookups() }),
  fields: ({ m, buyers, look }) => [
    { name: "buyer_id", label: m("f.buyer"), type: "select", required: true, options: buyers.map((b) => ({ value: b.id, label: `${b.short_name} (${b.code})` })) },
    { name: "item_category_id", label: m("f.item_category"), type: "select", options: pickOne(m, look.item_categories), help: m("supplier_config.category_hint") },
    { name: "nominated_supplier_id", label: m("f.nominated_supplier"), type: "select", options: pickOne(m, look.suppliers) },
    { name: "alternative_supplier_id", label: m("f.alternative_supplier"), type: "select", options: pickOne(m, look.suppliers) },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ],
  decorate: (row) => ({ ...row, item_category_id: row.item_category_id ?? "", nominated_supplier_id: row.nominated_supplier_id ?? "", alternative_supplier_id: row.alternative_supplier_id ?? "" }),
  columns: ({ m }) => [
    { key: "buyer", label: m("f.buyer") },
    { key: "item_category", label: m("f.item_category") },
    { key: "nominated_supplier", label: m("f.nominated_supplier") },
    { key: "alternative_supplier", label: m("f.alternative_supplier") },
    { key: "remarks", label: m("f.remarks") },
    status(m),
  ],
});

const SAMPLE_FLAGS = ["order_required", "po_required", "buyer_approval_required", "qc_required", "material_required", "revision_allowed", "delivery_required"];
const sampleTypes = masterScreen({
  resource: "sample-types",
  screen: "sample-type-configuration",
  noun: "sample_type",
  noDelete: true,
  booleans: ["active", ...SAMPLE_FLAGS],
  fields: ({ m }) => [
    { name: "code", label: m("f.code"), required: true, readOnly: true },
    { name: "name", label: m("f.sample_type"), required: true },
    { name: "stage", label: m("f.sample_stage"), type: "select", required: true, options: options(m, "stage", ["PRE_ORDER", "ORDER", "PRE_PRODUCTION", "BULK", "POST_PRODUCTION"]) },
    { name: "lead_days", label: m("f.lead_days"), type: "number" },
    ...SAMPLE_FLAGS.map((f) => ({ name: f, label: m(`f.${f}`), type: "checkbox" })),
    { name: "active", label: m("f.active"), type: "checkbox" },
  ],
  decorate: (row, { m }) => ({ ...row, stage_label: m(`opt.stage.${row.stage}`), rules: row.customised ? m("sample.configured") : m("sample.default"), lead_days: String(row.lead_days) }),
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.sample_type") },
    { key: "stage_label", label: m("f.sample_stage") },
    ...SAMPLE_FLAGS.map((f) => yesno(m, f)),
    { key: "lead_days", label: m("f.lead_days"), type: "number" },
    { key: "rules", label: m("f.rules") },
    status(m),
  ],
  extraTabs: ({ m, rows }) =>
    [
      ["configured", (r) => r.customised],
      ["defaults", (r) => !r.customised],
    ].map(([key, keep]) => ({
      key,
      label: m(`sample.${key}`),
      icon: "Filter",
      render: () => (
        <RecordsGrid
          exportName={`sample-types-${key}`}
          rows={rows.filter(keep)}
          columns={[
            { key: "code", label: m("f.code"), type: "mono" },
            { key: "name", label: m("f.sample_type") },
            { key: "stage_label", label: m("f.sample_stage") },
            { key: "lead_days", label: m("f.lead_days"), type: "number" },
            status(m),
          ]}
        />
      ),
    })),
});

/* ---- Step C: process-loss norms, inquiry rates, trims specs and T&A. ---- */

const COLOR_GROUPS = ["WHITE", "LIGHT", "MEDIUM", "DARK", "EXTRA_DARK", "MELANGE"];

const dyeingLoss = masterScreen({
  resource: "dyeing-loss-bands",
  screen: "dyeing-process-loss-chart",
  noun: "band",
  newFromSave: true,
  description: "loss.band_hint",
  newLabel: "new.loss_band",
  load: async () => ({ fabricTypes: await apiFetch("/erp/fabric-types"), compositions: await apiFetch("/erp/fiber-compositions") }),
  fields: ({ m, fabricTypes, compositions }) => [
    { name: "fabric_type_id", label: m("f.fabric_type"), type: "select", options: [{ value: "", label: m("loss.any_fabric") }, ...fabricTypes.map((f) => ({ value: f.id, label: `${f.name} (${f.code})` }))] },
    { name: "fiber_composition_id", label: m("f.composition"), type: "select", required: true, options: compositions.map((c) => ({ value: c.id, label: c.description ?? c.code })) },
    { name: "color_group", label: m("f.color_group"), type: "select", required: true, options: options(m, "color_group", COLOR_GROUPS) },
    { name: "weight_low", label: m("f.weight_low"), type: "number", required: true, placeholder: "1" },
    { name: "weight_high", label: m("f.weight_high"), type: "number", required: true, placeholder: "200" },
    { name: "loss_percent", label: m("f.loss_percent"), type: "number", required: true, placeholder: "12" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({
    ...row,
    fabric_type_id: row.fabric_type_id ?? "",
    fabric_type: row.fabric_type ?? m("loss.any_fabric"),
    group_label: m(`opt.color_group.${row.color_group}`),
    band: `${Number(row.weight_low)} – ${Number(row.weight_high)}`,
    loss_label: `${Number(row.loss_percent)}%`,
    weight_low: String(Number(row.weight_low)),
    weight_high: String(Number(row.weight_high)),
    loss_percent: String(Number(row.loss_percent)),
  }),
  columns: ({ m }) => [
    { key: "fabric_type", label: m("f.fab_type") },
    { key: "composition", label: m("f.composition") },
    { key: "group_label", label: m("f.color_group") },
    { key: "band", label: m("f.weight_band"), align: "right" },
    { key: "loss_label", label: m("f.loss_percent"), align: "right" },
    status(m),
  ],
});

/** Process Loss Quantity Setup and Cutting Process Loss Setup: one resource each, the same shape. */
function lossAllowance(resource, screen, emptyNoun) {
  return masterScreen({
    resource,
    screen,
    emptyNoun,
    newLabel: "new.allowance",
    noun: "configuration",
    newFromSave: true,
    description: "loss.allowance_hint",
    booleans: ["add_to_booking"],
    fields: ({ m }) => [
      { name: "qty_low", label: m("f.qty_low"), type: "number", required: true, placeholder: "1" },
      { name: "qty_high", label: m("f.qty_high"), type: "number", required: true, placeholder: "500" },
      { name: "loss_qty", label: m("f.loss_qty"), type: "number", placeholder: "10" },
      { name: "add_to_booking", label: m("f.add_ploss"), type: "checkbox", default: true, help: m("f.add_ploss_hint") },
      { name: "fabric_group", label: m("f.fabric_group"), type: "select", options: [{ value: "", label: m("loss.any") }, ...options(m, "fabric_group", FABRIC_GROUPS)] },
      { name: "colour_type", label: m("f.color_type"), type: "select", options: [{ value: "", label: m("loss.any") }, ...options(m, "colour_type", ["SOLID", "MELANGE", "YARN_DYED", "AOP", "STRIPE"])] },
      { name: "effective_from", label: m("f.effect_date"), type: "date", required: true },
      { name: "effective_to", label: m("f.expire_date"), type: "date" },
    ],
    decorate: (row, { m }) => ({
      ...row,
      fabric_group: row.fabric_group ?? "",
      colour_type: row.colour_type ?? "",
      group_label: row.fabric_group ? m(`opt.fabric_group.${row.fabric_group}`) : m("loss.any"),
      colour_label: row.colour_type ? m(`opt.colour_type.${row.colour_type}`) : m("loss.any"),
      band: `${Number(row.qty_low)} – ${Number(row.qty_high)}`,
      qty_low: String(Number(row.qty_low)),
      qty_high: String(Number(row.qty_high)),
      loss_qty: row.loss_qty === null ? null : String(Number(row.loss_qty)),
      effective_from: row.effective_from?.slice(0, 10) ?? null,
      effective_to: row.effective_to?.slice(0, 10) ?? null,
    }),
    columns: ({ m }) => [
      { key: "band", label: m("f.qty_band"), align: "right" },
      { key: "loss_qty", label: m("f.loss_qty"), type: "number" },
      { key: "group_label", label: m("f.fabric_group") },
      { key: "colour_label", label: m("f.colour_type") },
      yesno(m, "add_to_booking"),
      { key: "effective_from", label: m("f.effective_from"), type: "date" },
      { key: "effective_to", label: m("f.effective_to"), type: "date" },
    ],
  });
}

const RATE_GROUPS = ["YARN", "KNITTING", "YARN_DYEING", "AOP", "DYEING", "DYEING_FINISHING"];

/** The buyer offices down the left of Rate Chart and Rate History, each a link. */
function officePane(m, offices, tab, active, children) {
  // An office is named in the url by its name (`lib/row-ref`), not its id.
  const refs = refsFor(offices, "name");
  const groups = offices.map((o) => ({ key: refs.get(o.id), label: o.name, count: o.count }));
  return (
    <GroupedRecords
      groups={groups}
      active={active}
      hrefFor={(id) => `/merchandise-desk/rate-chart-for-inquiry?tab=${tab}&office=${encodeURIComponent(id)}`}
      groupsTitle={m("rate.offices")}
      recordsTitle={groups.find((g) => g.key === active)?.label ?? ""}
      emptyText={m("rate.choose")}
    >
      {children}
    </GroupedRecords>
  );
}

const rateChart = masterScreen({
  resource: "rate-lines",
  screen: "rate-chart-for-inquiry",
  // The url names a row by its description; the first column is a group, shared by many.
  refKey: "description",
  noun: "rate_line",
  newLabel: "new.rate_line",
  load: async () => ({ offices: await apiFetch("/merchandise/rate-chart") }),
  fields: ({ m }) => [
    { name: "rate_group", label: m("f.rate_group"), type: "select", required: true, options: options(m, "rate_group", RATE_GROUPS) },
    { name: "description", label: m("f.rate_desc"), required: true, placeholder: "100% Cotton Any Count (Combed)" },
    { name: "uom", label: m("f.uom"), type: "uom" },
    { name: "sort_order", label: m("f.display_order"), type: "number", default: "0" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({
    ...row,
    group_label: m(`opt.rate_group.${row.rate_group}`),
    avg_label: row.avg_rate === null ? null : Number(row.avg_rate).toFixed(2),
    sort_order: String(row.sort_order ?? 0),
  }),
  columns: ({ m }) => [
    { key: "group_label", label: m("f.rate_group_short") },
    { key: "description", label: m("f.rate_desc_short") },
    { key: "uom", label: m("f.uom") },
    { key: "offices_quoted", label: m("f.offices_quoted"), type: "number" },
    { key: "avg_label", label: m("f.avg_rate"), align: "right" },
    { key: "sort_order", label: m("f.order"), type: "number" },
    status(m),
  ],
  extraTabs: ({ m, offices, canManage, searchParams }) => {
    const office = findByRef(offices, "name", searchParams.office);
    const active = office?.id ?? null;
    const activeRef = office ? searchParams.office : null;
    return [
      {
        key: "chart",
        label: m("rate.chart"),
        icon: "BadgeDollarSign",
        // Rate Chart and Rate History keep the office chosen in either.
        href: active ? `/merchandise-desk/rate-chart-for-inquiry?tab=chart&office=${encodeURIComponent(activeRef)}` : undefined,
        render: async () =>
          officePane(
            m,
            offices,
            "chart",
            activeRef,
            active ? <RateChartEditor key={active} office={active} rows={await apiFetch(`/merchandise/rate-chart/${active}`)} save={actions.saveRateChart} canManage={canManage} /> : null,
          ),
      },
      {
        key: "history",
        label: m("rate.history"),
        icon: "History",
        href: active ? `/merchandise-desk/rate-chart-for-inquiry?tab=history&office=${encodeURIComponent(activeRef)}` : undefined,
        render: async () =>
          officePane(
            m,
            offices,
            "history",
            activeRef,
            active ? (
              <RecordsGrid
                exportName="rate-history"
                rows={(await apiFetch(`/merchandise/rate-chart/${active}/history`)).map((r) => ({ ...r, group_label: m(`opt.rate_group.${r.rate_group}`), rate_label: `${r.rate.toFixed(2)} ${r.currency}` }))}
                columns={[
                  { key: "group_label", label: m("f.rate_group_short") },
                  { key: "description", label: m("f.rate_desc_short") },
                  { key: "uom", label: m("f.uom") },
                  { key: "rate_label", label: m("f.rate"), align: "right" },
                  { key: "effective_from", label: m("rate.effective_from"), type: "date" },
                  { key: "entered_by", label: m("f.entered_by") },
                  { key: "entered_at", label: m("f.entered_at"), type: "datetime" },
                ]}
              />
            ) : null,
          ),
      },
    ];
  },
});

const trimSpec = masterScreen({
  resource: "trim-spec-templates",
  screen: "trim-accessories-spec-cfg",
  noun: "spec_template",
  newLabel: "new.spec_template",
  load: async () => ({ buyers: await apiFetch("/erp/buyers") }),
  fields: ({ m }) => [
    { name: "code", label: m("f.template_code"), required: true, placeholder: "SPEC-LBL" },
    { name: "name", label: m("f.template_name"), required: true, placeholder: "STOCKMANN - Care Label" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (row) => ({
    ...row,
    applies_to:
      row.item_rules
        .map((r) => [r.item_category, r.item_name, r.buyer].filter(Boolean).join(" / "))
        .filter(Boolean)
        .join("; ") || null,
  }),
  columns: ({ m }) => [
    { key: "code", label: m("f.template_code"), type: "mono" },
    { key: "name", label: m("f.template_name") },
    { key: "applies_to", label: m("f.applies_to") },
    { key: "control_count", label: m("f.controls"), type: "number" },
    status(m),
  ],
  extraTabs: ({ m, rows, buyers, canManage }) => [
    {
      key: "config",
      label: m("spec.tab"),
      icon: "ClipboardList",
      render: () => <TrimSpecEditor templates={rows} buyers={buyers.map((b) => ({ value: b.id, label: `${b.short_name} (${b.code})` }))} save={actions.saveTrimSpec} canManage={canManage} />,
    },
  ],
});

async function tnaRights({ m, te, tab, canManage }) {
  const [rows, users] = await Promise.all([apiFetch("/merchandise/tna-rights"), apiFetch("/merchandise/tna-rights/users")]);
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "assign", label: m("tna.rights_tab"), icon: "KeyRound" },
    ],
    body: () =>
      tab === "assign" ? (
        <TnaRightsMatrix users={users} load={actions.loadTnaRights} save={actions.saveTnaRights} canManage={canManage} />
      ) : (
        <RecordsGrid
          exportName="tna-task-rights"
          rows={rows}
          columns={[
            { key: "user", label: m("f.user") },
            { key: "task", label: m("f.task_name") },
            { key: "group", label: m("f.task_group") },
            { key: "crud", label: "C R U D", type: "mono" },
          ]}
          remove={canManage ? actions.removeTnaRight : undefined}
          deleteLabels={nounLabels(te, m("noun.task_permission"), "user").remove}
        />
      ),
  };
}

const ORDER_TYPES = ["NEW", "REPEAT", "DEVELOPMENT", "PROJECTION"];
const GMT_TYPES = ["BASIC", "SEMI_CRITICAL", "CRITICAL"];
const TEMPLATE_NUMBERS = ["lead_from", "lead_to", "std_days", "before_swing", "after_swing"];

const tnaTemplate = masterScreen({
  resource: "tna-templates",
  screen: "template-for-tna",
  noun: "tna_template",
  newLabel: "new.tna_template",
  submitLabel: "new.tna_template",
  booleans: [],
  load: async () => ({ buyers: await apiFetch("/erp/buyers"), look: await lookups() }),
  description: "tna.template_hint",
  fields: ({ m, buyers, look }) => [
    { name: "code", label: m("f.template_code"), required: true, placeholder: "TNGEN00" },
    { name: "scope", label: m("f.template_type"), type: "radio", required: true, default: "GENERAL", options: options(m, "scope", ["GENERAL", "BUYER"]) },
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: [{ value: "", label: "—" }, ...buyers.map((b) => ({ value: b.id, label: `${b.short_name} (${b.code})` }))], help: m("tna.buyer_hint"), showWhen: { field: "scope", value: "BUYER" } },
    { name: "order_type", label: m("f.order_type"), type: "radio", required: true, default: "NEW", options: options(m, "order_type", ORDER_TYPES) },
    { name: "gmt_type", label: m("f.nature_of_work"), type: "radio", required: true, default: "BASIC", options: options(m, "gmt_type", GMT_TYPES) },
    ...TEMPLATE_NUMBERS.map((name) => ({ name, label: m(`f.bl_${name}`), type: "number", required: true })),
    { name: "description", label: m("f.description"), type: "textarea" },
    { name: "approved_by", label: m("f.approved_by"), type: "select", required: true, options: look.users },
    { name: "prepared_by", label: m("f.prep_by"), type: "select", options: pickOne(m, look.users) },
  ],
  decorate: (row, { m }) => ({
    ...row,
    buyer_id: row.buyer_id ?? "",
    approved_by: row.approved_by ?? "",
    prepared_by: row.prepared_by ?? "",
    buyer_label: row.buyer ?? m("opt.scope.GENERAL"),
    order_label: m(`opt.order_type.${row.order_type}`),
    gmt_label: m(`opt.gmt_type.${row.gmt_type}`),
    ...Object.fromEntries(TEMPLATE_NUMBERS.map((k) => [k, String(row[k])])),
  }),
  columns: ({ m }) => [
    { key: "code", label: m("f.template_code"), type: "mono" },
    { key: "buyer_label", label: m("f.buyer") },
    { key: "order_label", label: m("f.order_type") },
    { key: "gmt_label", label: m("f.gmt_type") },
    { key: "lead_from", label: m("f.lead_from"), type: "number" },
    { key: "lead_to", label: m("f.lead_to"), type: "number" },
    { key: "description", label: m("f.remarks") },
  ],
  rowAction: ({ m }) => ({ label: m("tna.copy"), action: actions.copyTemplate }),
  extraTabs: ({ m, rows, canManage, searchParams }) => {
    // A template is named in the url by its code, not its id.
    const template = findByRef(rows, "code", searchParams.template);
    const active = template?.id ?? null;
    const templateRefs = refsFor(rows, "code");
    return [
      {
        key: "tasks",
        label: m("tna.template_tasks"),
        icon: "CalendarRange",
        render: async () => {
          const groups = rows.map((r) => ({ key: templateRefs.get(r.id), label: r.code, sub: m("tna.template_sub", { type: r.order_label, from: r.lead_from, to: r.lead_to }), count: r.task_count }));
          return (
            <GroupedRecords
              groups={groups}
              active={active ? templateRefs.get(active) : null}
              hrefFor={(ref) => `/merchandise-desk/template-for-tna?tab=tasks&template=${encodeURIComponent(ref)}`}
              groupsTitle={m("tna.templates")}
              recordsTitle={template?.code ?? ""}
              emptyText={m("tna.choose_template")}
            >
              {active ? <TnaCalendarEditor key={active} calendar={await apiFetch(`/merchandise/tna-templates/${active}/tasks`)} save={actions.saveTemplateTasks} canManage={canManage} /> : null}
            </GroupedRecords>
          );
        },
      },
    ];
  },
});

const TASK_FLAGS = ["is_sample", "is_start", "is_end", "is_production"];

const taskAction = masterScreen({
  resource: "tna-tasks",
  screen: "task-action-list",
  // The url names a row by its name; the first column is a group, shared by many.
  refKey: "name",
  noun: "task",
  newLabel: "new.task",
  booleans: ["active", ...TASK_FLAGS],
  load: async () => ({ groups: await apiFetch("/erp/tna-task-groups") }),
  fields: ({ m, groups }) => [
    { name: "tna_task_group_id", label: m("f.task_group"), type: "select", options: [{ value: "", label: m("select") }, ...groups.map((g) => ({ value: g.id, label: g.name }))], help: m("tna.group_hint") },
    { name: "code", label: m("f.task_code"), required: true, placeholder: "TSK-078" },
    { name: "sort_order", label: m("f.task_order"), type: "number", required: true },
    { name: "name", label: m("f.task_name_en_form"), required: true },
    { name: "name_bn", label: m("f.task_name_bn_form") },
    { name: "offset_days", label: m("f.offset_days"), type: "number", help: m("tna.offset_hint") },
    { name: "source_module", label: m("f.source_module"), type: "select", options: pickOne(m, options(m, "source_module", SOURCE_MODULES)), help: m("tna.source_hint") },
    ...TASK_FLAGS.map((f) => ({ name: f, label: m(`f.${f}`), type: "checkbox" })),
    { name: "active", label: m("f.is_active_q"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, tna_task_group_id: row.tna_task_group_id ?? "", source_module: row.source_module ?? "", source_label: SOURCE_MODULES.includes(row.source_module) ? m(`opt.source_module.${row.source_module}`) : row.source_module, sort_order: String(row.sort_order), offset_days: row.offset_days === null ? null : String(row.offset_days) }),
  columns: ({ m }) => [
    { key: "group", label: m("f.task_group_short") },
    { key: "sort_order", label: m("f.task_order_short"), type: "number" },
    { key: "name", label: m("f.task_name_en") },
    { key: "name_bn", label: m("f.task_name_bn") },
    { key: "source_label", label: m("f.source_module") },
    yesno(m, "active", "f.active"),
    yesno(m, "is_sample", "f.sample_q"),
    yesno(m, "is_start", "f.start_q"),
    yesno(m, "is_end", "f.end_q"),
    yesno(m, "is_production", "f.production_q"),
    status(m),
  ],
  extraTabs: ({ m, rows, groups, canManage, searchParams }) => {
    // A task group is named in the url by its code, not its id.
    const group = findByRef(groups, "code", searchParams.group);
    const active = group?.id ?? null;
    const groupRefs = refsFor(groups, "code");
    const groupFields = [
      { name: "name", label: m("f.group_name_en"), required: true },
      { name: "name_bn", label: m("f.group_name_bn") },
      { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
    ];
    return [
      {
        key: "groups",
        label: m("tna.task_groups"),
        icon: "FolderTree",
        render: () => {
          const list = groups.map((g) => ({ key: groupRefs.get(g.id), label: g.name, sub: g.code, count: g.task_count }));
          return (
            <div className="flex flex-col gap-3">
              {canManage ? (
                <div className="flex justify-end">
                  <DialogFormButton label={m("new.task_group")} title={m("new.task_group")} fields={groupFields} action={actions.createResource.bind(null, "tna-task-groups", ["active"])} submitLabel={m("save")} />
                </div>
              ) : null}
              <GroupedRecords
                groups={list}
                active={active ? groupRefs.get(active) : null}
                hrefFor={(ref) => `/merchandise-desk/task-action-list?tab=groups&group=${encodeURIComponent(ref)}`}
                groupsTitle={m("tna.task_groups")}
                recordsTitle={group?.name ?? ""}
                emptyText={m("tna.choose_group")}
              >
                {active ? (
                  <RecordsGrid
                    exportName="tna-group-tasks"
                    rows={rows.filter((r) => r.tna_task_group_id === active)}
                    columns={[
                      { key: "code", label: m("f.task_code"), type: "mono" },
                      { key: "sort_order", label: m("f.task_order_short"), type: "number" },
                      { key: "name", label: m("f.task_name_en") },
                      { key: "name_bn", label: m("f.task_name_bn") },
                      status(m),
                    ]}
                  />
                ) : null}
              </GroupedRecords>
            </div>
          );
        },
      },
    ];
  },
});

const SCREENS = {
  "dyeing-process-loss-chart": dyeingLoss,
  "country-origin-master": countryOrigin,
  "country-setup": countrySetup,
  "colour-master": colour,
  "size-master": size,
  "garment-part": garmentPart,
  "service-type-setup": serviceType,
  "item-type-master": itemType,
  "nature-of-work-master": workNature,
  "fabric-type-entry": fabricType,
  "fiber-master": fiber,
  "fiber-composition": fiberComposition,
  "gsm-wise-count-conf": yarnCount,
  "buyer-office-master": buyerOffice,
  "buyer-master": buyer,
  "buyer-ac-permission": permissionScreen("user-buyers", "user_buyers"),
  "buyer-ac-permission-mkt": permissionScreen("user-buyers-marketing", "user_buyers_mkt"),
  "buyer-sample-mapping": buyerSampleMapping,
  "buyer-test-institute-mapping": buyerTestLab,
  "buyer-wise-trim-accessories": buyerTrims,
  "buyer-supplier-config": buyerSupplier,
  "sample-type-configuration": sampleTypes,
  "process-loss-qty-config": lossAllowance("booking-loss-allowances", "process-loss-qty-config", "booking_allowance"),
  "cutting-process-loss-config": lossAllowance("cutting-loss-allowances", "cutting-process-loss-config", "cutting_allowance"),
  "rate-chart-for-inquiry": rateChart,
  "trim-accessories-spec-cfg": trimSpec,
  "tna-task-permission": tnaRights,
  "template-for-tna": tnaTemplate,
  "task-action-list": taskAction,
};

export { masterScreen, options, SCREENS };
