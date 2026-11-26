import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { StatusPills } from "@/components/erp/catalog-card";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { MasterTabs } from "@/components/prebudget/master-tabs";
import { RequisitionColumnSetup, AccBudgetColumnSetup } from "@/components/prebudget/column-setup";
import { pbAction } from "../../pre-budget/[screen]/actions";
import * as actions from "./actions";

/*
 * Pre-Budget → Master Data (AnnotechERP 33/34; menu by the user, 2026-11-19):
 *
 *  - Requisition Column Setup and Acc Budget Column Setup (the BOM
 *    worksheet's columns and its Unit Cost Rules).
 *  - Pre-Budget Master Data: the module's own lists — Accessories Item and
 *    the requisition's (Order Type, Item, Wash Type, Fabric Type, Fabric
 *    Composition, GSM, Sustainability, Parts, Yarn Type, Yarn Count, Yarn
 *    Composition, Additional Process) — one tab each (?tab=). Each is one card: All / Active / Inactive pills,
 *    an Add dialog, Edit, and a Status switch that saves at once. Nothing is
 *    deleted; a value is switched off so a requisition that picked it keeps it.
 */

const STATUSES = ["all", "active", "inactive"];

/** A list screen over one resource. */
function listScreen(config) {
  return async function renderList({ m, canManage, searchParams = {}, hrefFor }) {
    const rows = await apiFetch(`/erp/${config.resource}`);
    const status = STATUSES.includes(searchParams.status) ? searchParams.status : "all";
    const counts = { all: rows.length, active: rows.filter((r) => r.active).length, inactive: rows.filter((r) => !r.active).length };
    const shown = status === "all" ? rows : rows.filter((r) => (status === "active" ? r.active : !r.active));
    const fields = config.fields({ m });

    return (
      <RecordsGrid
        exportName={config.screen}
        rows={shown}
        header={{
          title: m(`${config.key}.title`),
          subtitle: m(`${config.key}.subtitle`, { active: counts.active, count: counts.all }),
          searchInline: true,
          searchPlaceholder: m(`${config.key}.search`),
          filters: <StatusPills filters={STATUSES.map((key) => ({ key, label: m(`status.${key}`), count: counts[key] }))} active={status} hrefFor={(key) => hrefFor(config.screen, key)} />,
          action: canManage ? (
            <DialogFormButton
              // A key of its own: made here on the server, it sits in RecordsGrid's header beside the Excel button.
              key={`${config.screen}-add`}
              variant="primary"
              label={m(`${config.key}.add`)}
              title={m(`${config.key}.new`)}
              description={m(`${config.key}.required`)}
              fields={fields}
              action={actions.createResource.bind(null, config.resource)}
              submitLabel={m("save")}
            />
          ) : null,
        }}
        // Paged and sortable: Norban's lists run to hundreds (Parts 955, Yarn Composition 718).
        columns={[...config.columns({ m }), { key: "active", label: m("f.status"), type: "toggle", action: canManage ? actions.setActive.bind(null, config.resource) : undefined }]}
        edit={canManage ? { fields, action: actions.updateResource.bind(null, config.resource), title: m(`${config.key}.edit`), submitLabel: m("save") } : undefined}
      />
    );
  };
}

const field = (m, name, extra = {}) => ({ name, label: m(`f.${name}`), ...extra });
const active = (m) => ({ name: "active", label: m("f.active"), type: "checkbox", default: true });

/** A Pre-Budget list (Yarn Type, Item, Parts …): a name, a note, a place in the list. */
const simpleList = (resource, screen, key) =>
  listScreen({
    resource,
    screen,
    key,
    fields: ({ m }) => [field(m, "name", { required: true }), field(m, "description", { type: "textarea" }), field(m, "sort_order", { type: "number" }), active(m)],
    columns: ({ m }) => [
      { key: "name", label: m("f.name") },
      { key: "description", label: m("f.description") },
      { key: "sort_order", label: m("f.sort_order"), type: "number", align: "right" },
    ],
  });

const accessoryItems = listScreen({
  resource: "pb-accessory-items",
  screen: "accessory-items",
  key: "acc",
  fields: ({ m }) => [
    field(m, "code", { placeholder: m("ph.code") }),
    field(m, "name", { required: true }),
    field(m, "category", { placeholder: m("ph.category") }),
    field(m, "uom", { type: "uom" }),
    field(m, "description", { type: "textarea" }),
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "category", label: m("f.category") },
    { key: "uom", label: m("f.uom") },
    { key: "description", label: m("f.description") },
  ],
});

/** The lists of Pre-Budget Master Data, in tab order. */
// The requisition's own lists in its column order (Norban's, user 2026-11-20), the Accessories Items first.
const LISTS = {
  "accessory-items": accessoryItems,
  "order-types": simpleList("pb-order-types", "order-types", "order"),
  items: simpleList("pb-items", "items", "item"),
  "wash-types": simpleList("pb-wash-types", "wash-types", "wash"),
  "fabric-types": simpleList("pb-fabric-types", "fabric-types", "ftype"),
  "fabric-compositions": simpleList("pb-fabric-compositions", "fabric-compositions", "fcomp"),
  gsm: simpleList("pb-gsms", "gsm", "gsm"),
  sustainability: simpleList("pb-sustainabilities", "sustainability", "sus"),
  parts: simpleList("pb-parts", "parts", "part"),
  "yarn-types": simpleList("pb-yarn-types", "yarn-types", "yarn"),
  "yarn-counts": simpleList("pb-yarn-counts", "yarn-counts", "ycount"),
  "yarn-compositions": simpleList("pb-yarn-compositions", "yarn-compositions", "ycomp"),
  "additional-processes": simpleList("pb-additional-processes", "additional-processes", "process"),
};

const SCREENS = {
  "requisition-column-setup": async function render() {
    return <RequisitionColumnSetup data={await apiFetch("/pre-budget/columns")} act={pbAction} />;
  },
  "acc-budget-column-setup": async function render() {
    return <AccBudgetColumnSetup data={await apiFetch("/pre-budget/bom-columns")} act={pbAction} />;
  },
  "master-data": async function render({ m, canManage, searchParams = {} }) {
    const tab = Object.hasOwn(LISTS, searchParams.tab) ? searchParams.tab : "accessory-items";
    const hrefFor = (screen, status) => `/pre-budget-master/master-data?tab=${screen}${status === "all" ? "" : `&status=${status}`}`;
    const keys = Object.keys(LISTS);
    // Every list drawn now (the tab's status filter on its own list), so a tab switches without a round trip.
    const rendered = await Promise.all(keys.map((k) => LISTS[k]({ m, canManage, searchParams: k === tab ? searchParams : {}, hrefFor })));
    return <MasterTabs tabs={keys.map((k) => ({ key: k, label: m(`screens.${k}`) }))} initial={tab} panels={Object.fromEntries(keys.map((k, i) => [k, rendered[i]]))} />;
  },
};

export { SCREENS };
