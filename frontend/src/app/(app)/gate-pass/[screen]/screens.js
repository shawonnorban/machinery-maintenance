import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { CreateGatePass } from "@/components/gatepass/create";
import {
  GpApproval,
  GateOut,
  GateIn,
  Reconciliation,
  GatePassList,
} from "@/components/gatepass/workflow";
import { GpDashboard } from "@/components/gatepass/dashboard";
import { GpReport } from "@/components/gatepass/report";
import * as actions from "./actions";

/*
 * Gate Pass → Dashboard and Reports (11/02), Create Gate Pass (11/03),
 * Approval, Out, In, Return Reconciliation and List (11/04): each screen's
 * lists and options read here, its steps taken in place.
 *
 * Gate Pass → Master Data (AnnotechERP 11/01): Gate Pass Category, Location
 * Type, Factory / Location, Item / Product and Purpose / Reason — each
 * Brickland's one card with its New dialog and Edit. Nothing is deleted; an
 * entry is switched off, and inactive entries are not offered on new gate
 * passes.
 */

function gateScreen(config) {
  return async function renderGate({ m, canManage }) {
    const extra = config.load ? await config.load() : {};
    const rows = (await apiFetch(`/erp/${config.resource}`)).map((row) =>
      config.decorate ? config.decorate(row, { m, ...extra }) : row,
    );
    const fields = config.fields({ m, ...extra });
    // Brickland's one card: "Factory / Location (0)" and New on top, the plain table under it.
    const list = (
      <RecordsGrid
        exportName={config.screen}
        rows={rows}
        header={{
          title: m(`${config.key}.title`, { count: rows.length }),
          action: canManage ? (
            <DialogFormButton
              variant="primary"
              label={m(`${config.key}.new`)}
              title={m(`${config.key}.new`)}
              fields={fields}
              action={actions.createResource.bind(null, config.resource, [
                "active",
              ])}
              submitLabel={m("save")}
            />
          ) : null,
        }}
        emptyText={m(`${config.key}.empty`)}
        plain
        columns={[
          ...config.columns({ m }),
          { key: "active", label: m("f.status"), type: "status" },
        ]}
        edit={
          canManage
            ? {
                fields,
                action: actions.updateResource.bind(null, config.resource, [
                  "active",
                ]),
                title: m(`${config.key}.edit`),
                submitLabel: m("save"),
              }
            : undefined
        }
      />
    );

    return config.before ? (
      <div className="flex flex-col gap-4">
        {config.before({ m })}
        {list}
      </div>
    ) : (
      list
    );
  };
}

const active = (m) => ({
  name: "active",
  label: m("f.active"),
  type: "checkbox",
  default: true,
  help: m("f.active_hint"),
});
const code = (m, placeholder) => ({
  name: "code",
  label: m("f.code"),
  required: true,
  placeholder,
});
const description = (m) => ({
  name: "description",
  label: m("f.description"),
  type: "textarea",
});
const basic = (m) => [
  { key: "code", label: m("f.code"), type: "mono" },
  { key: "name", label: m("f.name") },
];

/** Brickland's "Gate Pass Types" card: the two types the module's logic fixes. */
function GatePassTypes({ m }) {
  return (
    <div className="rounded-lg border border-border bg-surface px-5 py-4">
      <h2 className="text-base font-semibold text-foreground">
        {m("types.title")}
      </h2>
      <p className="mt-0.5 text-[13px] text-foreground-muted">
        {m("types.hint")}
      </p>
      <div className="mt-3 grid gap-3 md:grid-cols-2">
        {["returnable", "non_returnable"].map((type) => (
          <div key={type} className="rounded-md bg-surface-muted px-4 py-3">
            <p className="text-sm font-semibold text-foreground">
              {m(`types.${type}.title`)}
            </p>
            <p className="mt-1 text-[13px] text-foreground-muted">
              {m(`types.${type}.body`)}
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}

const KINDS = ["MAIN", "ITEM"];
const UNITS = [
  "PCS",
  "DZN",
  "SET",
  "KG",
  "YDS",
  "MTR",
  "ROLL",
  "CTN",
  "BOX",
  "BAG",
  "LTR",
  "NOS",
];
const ITEM_CATEGORIES = [
  "FINISHED_GARMENTS",
  "FABRIC",
  "TRIMS",
  "SAMPLE_GARMENTS",
  "PACKING_MATERIAL",
  "MACHINERY",
  "EQUIPMENT",
  "DOCUMENTS",
  "OTHER",
];

const categories = gateScreen({
  resource: "gatepass-categories",
  screen: "gp-categories",
  key: "category",
  before: ({ m }) => <GatePassTypes m={m} />,
  fields: ({ m }) => [
    code(m, "ITEM"),
    { name: "name", label: m("f.name"), required: true },
    {
      name: "kind",
      label: m("f.kind"),
      type: "select",
      required: true,
      default: "ITEM",
      options: KINDS.map((k) => ({ value: k, label: m(`opt.kind_long.${k}`) })),
    },
    description(m),
    active(m),
  ],
  decorate: (row, { m }) => ({ ...row, kind_label: m(`opt.kind.${row.kind}`) }),
  columns: ({ m }) => [
    ...basic(m),
    { key: "kind_label", label: m("f.kind") },
    { key: "description", label: m("f.description") },
  ],
});

const locationTypes = gateScreen({
  resource: "gatepass-location-types",
  screen: "gp-location-types",
  key: "location_type",
  fields: ({ m }) => [
    code(m, "LT-11"),
    { name: "name", label: m("f.name"), required: true },
    description(m),
    active(m),
  ],
  columns: ({ m }) => [
    ...basic(m),
    {
      key: "locations",
      label: m("f.locations"),
      type: "number",
      align: "right",
    },
    { key: "description", label: m("f.description") },
  ],
});

const locations = gateScreen({
  resource: "gatepass-locations",
  screen: "gp-locations",
  key: "location",
  load: async () => ({ types: await apiFetch("/erp/gatepass-location-types") }),
  fields: ({ m, types }) => [
    code(m, "LOC-001"),
    { name: "name", label: m("f.location_name"), required: true },
    {
      name: "location_type_id",
      label: m("f.location_type"),
      type: "select",
      required: true,
      options: types
        .filter((t) => t.active)
        .map((t) => ({ value: t.id, label: t.name })),
    },
    { name: "contact_person", label: m("f.contact_person") },
    { name: "mobile", label: m("f.mobile"), placeholder: "01XXXXXXXXX" },
    { name: "email", label: m("f.email"), type: "email" },
    { name: "address", label: m("f.address"), type: "textarea" },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.location") },
    { key: "location_type", label: m("f.type") },
    { key: "address", label: m("f.address") },
    { key: "contact_person", label: m("f.contact_person") },
    { key: "mobile", label: m("f.mobile") },
  ],
});

const items = gateScreen({
  resource: "gatepass-items",
  screen: "gp-items",
  key: "item",
  fields: ({ m }) => [
    code(m, "GPI-001"),
    { name: "name", label: m("f.item_name"), required: true },
    {
      name: "item_category",
      label: m("f.item_category"),
      type: "select",
      required: true,
      options: ITEM_CATEGORIES.map((c) => ({
        value: c,
        label: m(`opt.item_category.${c}`),
      })),
    },
    {
      name: "unit",
      label: m("f.unit"),
      type: "select",
      required: true,
      options: UNITS.map((u) => ({ value: u, label: m(`opt.unit.${u}`) })),
    },
    description(m),
    active(m),
  ],
  decorate: (row, { m }) => ({
    ...row,
    category_label: m(`opt.item_category.${row.item_category}`),
    unit_label: m(`opt.unit.${row.unit}`),
  }),
  columns: ({ m }) => [
    ...basic(m),
    { key: "category_label", label: m("f.item_category") },
    { key: "unit_label", label: m("f.unit") },
    { key: "description", label: m("f.description") },
  ],
});

const purposes = gateScreen({
  resource: "gatepass-purposes",
  screen: "gp-purposes",
  key: "purpose",
  fields: ({ m }) => [
    code(m, "PUR-15"),
    { name: "name", label: m("f.name"), required: true },
    description(m),
    active(m),
  ],
  columns: ({ m }) => [
    ...basic(m),
    { key: "description", label: m("f.description") },
  ],
});

const perms = (permissions) => ({
  create: permissions.includes("gatepass.create"),
  approve: permissions.includes("gatepass.approve"),
  gate: permissions.includes("gatepass.gate"),
});
const list = async (name) =>
  (await apiFetch(`/gate-pass/passes?list=${name}`)).rows;
const lists = async (names) =>
  Object.fromEntries(
    await Promise.all(names.map(async (n) => [n, await list(n)])),
  );

async function dashboard({ permissions = [] }) {
  return (
    <GpDashboard
      data={await apiFetch("/gate-pass/dashboard")}
      canCreate={permissions.includes("gatepass.create")}
    />
  );
}

async function reports() {
  const [data, options] = await Promise.all([
    apiFetch("/gate-pass/reports/register"),
    apiFetch("/gate-pass/reports/options"),
  ]);
  return <GpReport data={data} options={options} act={actions.gpAction} />;
}

/** A draft opens to be changed by its number (`?edit=GP-2026-000001`), never its key. */
async function create({ searchParams = {} }) {
  const edit = typeof searchParams.edit === "string" ? searchParams.edit : "";
  const [options, draft] = await Promise.all([
    apiFetch("/gate-pass/options"),
    edit
      ? apiFetch(`/gate-pass/passes/${encodeURIComponent(edit)}`).catch(
          () => null,
        )
      : null,
  ]);
  const usable = draft?.status === "DRAFT" ? draft : null;
  return (
    <CreateGatePass
      key={usable?.gp_no ?? "new"}
      options={options}
      draft={usable}
      act={actions.gpAction}
    />
  );
}

async function approval({ permissions = [] }) {
  return (
    <GpApproval
      lists={await lists(["approval", "approved"])}
      perms={perms(permissions)}
      act={actions.gpAction}
    />
  );
}

/** Gate OUT opens on a pass by its number (`?no=GP-2026-000001`, from Approval). */
async function out({ searchParams = {} }) {
  const no = typeof searchParams.no === "string" ? searchParams.no : "";
  const [all, first] = await Promise.all([
    lists(["approved", "out-today"]),
    no
      ? apiFetch(`/gate-pass/passes/${encodeURIComponent(no)}`).catch(
          () => null,
        )
      : null,
  ]);
  return (
    <GateOut
      key={first?.gp_no ?? "none"}
      lists={all}
      initial={first}
      act={actions.gpAction}
    />
  );
}

async function gateIn() {
  return (
    <GateIn
      lists={await lists(["in-await", "in-today"])}
      act={actions.gpAction}
    />
  );
}

async function reconciliation({ permissions = [] }) {
  const data = await apiFetch("/gate-pass/passes?list=recon");
  return (
    <Reconciliation
      rows={data.rows}
      shortWaiting={data.short_waiting}
      perms={perms(permissions)}
      act={actions.gpAction}
    />
  );
}

async function passList({ permissions = [] }) {
  const [rows, options] = await Promise.all([
    list("all"),
    apiFetch("/gate-pass/reports/options"),
  ]);
  return (
    <GatePassList
      rows={rows}
      options={options}
      perms={perms(permissions)}
      act={actions.gpAction}
    />
  );
}

const SCREENS = {
  "gp-dashboard": dashboard,
  "gp-reports": reports,
  "gp-create": create,
  "gp-approval": approval,
  "gp-out": out,
  "gp-in": gateIn,
  "gp-reconciliation": reconciliation,
  "gp-list": passList,
  "gp-categories": categories,
  "gp-location-types": locationTypes,
  "gp-locations": locations,
  "gp-items": items,
  "gp-purposes": purposes,
};

export { SCREENS };
