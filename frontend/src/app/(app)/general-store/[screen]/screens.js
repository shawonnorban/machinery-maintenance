import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { UrlSelect } from "@/components/erp/url-select";
import { StoreDocumentScreen } from "@/components/generalstore/document";
import { CurrentStock, StockLedger } from "@/components/generalstore/stock";
import { GsDashboard } from "@/components/generalstore/dashboard";
import {
  NewRequisition,
  RequisitionList,
} from "@/components/generalstore/requisition";
import { AuditLog, StoreSettings } from "@/components/generalstore/admin";
import { GsReport } from "@/components/generalstore/report";
import * as actions from "./actions";

/*
 * General Store → Master Data (AnnotechERP 10/01): Item Category, Item Master,
 * Unit Master, Department, Supplier and Store / Location — each Brickland's
 * one card with its New dialog and Edit on every row. Nothing is deleted; an
 * entry is switched off, and inactive entries are not offered on new ones.
 */

/** A store list over one resource. */
function storeScreen(config) {
  return async function renderStore({ m, canManage, searchParams = {} }) {
    const extra = config.load ? await config.load() : {};
    const query = config.query ? config.query(searchParams) : "";
    const rows = (await apiFetch(`/erp/${config.resource}${query}`)).map(
      (row) => (config.decorate ? config.decorate(row, { m, ...extra }) : row),
    );
    const booleans = config.booleans ?? ["active"];
    const fields = config.fields({ m, ...extra });
    const editFields = config.editFields
      ? config.editFields({ m, ...extra })
      : fields;

    const title = m(`${config.key}.title`, {
      count: rows.length >= 2000 ? "2000+" : rows.length,
    });

    // Brickland's one card: "Item (1)", Excel and New on top, Search (and Category) under it.
    return (
      <RecordsGrid
        exportName={config.screen}
        rows={rows}
        lockedField={config.lockedField}
        header={{
          // The kit lists at most 2000 rows; a longer list says so.
          title,
          subtitle: config.hint ? m(`${config.key}.hint`) : undefined,
          exportLabel: m("excel"),
          searchLabel: m("search"),
          searchPlaceholder: m("search_hint"),
          filters: config.toolbar
            ? config.toolbar({ m, searchParams, ...extra })
            : null,
          action: canManage ? (
            <DialogFormButton
              variant="primary"
              label={m(`${config.key}.new`)}
              title={m(`${config.key}.new`)}
              fields={fields}
              action={actions.createResource.bind(
                null,
                config.resource,
                booleans,
              )}
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
                fields: editFields,
                action: actions.updateResource.bind(
                  null,
                  config.resource,
                  booleans,
                ),
                title: m(`${config.key}.edit`),
                submitLabel: m("save"),
              }
            : undefined
        }
      />
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

const categories = storeScreen({
  resource: "store-categories",
  screen: "gs-categories",
  key: "category",
  // Every module's categories are this one tree (category › sub-category › detail).
  load: async () => ({
    parents: (await apiFetch("/erp/store-categories")).map((c) => ({
      value: c.id,
      label: `${c.code} — ${c.name}`,
    })),
  }),
  fields: ({ m, parents }) => [
    {
      name: "parent_id",
      label: m("f.parent_category"),
      type: "select",
      options: [{ value: "", label: "—" }, ...(parents ?? [])],
      help: m("f.parent_category_hint"),
    },
    code(m, "ELEC"),
    {
      name: "name",
      label: m("f.name"),
      required: true,
      placeholder: "Electrical",
    },
    { name: "name_bn", label: m("f.name_bn") },
    { name: "description", label: m("f.description"), type: "textarea" },
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "parent", label: m("f.parent_category") },
    { key: "items", label: m("f.items"), type: "number", align: "right" },
    { key: "description", label: m("f.description") },
  ],
});

const money = (value) =>
  value === null || value === undefined
    ? null
    : Number(value).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
const qty = (value) =>
  value === null || value === undefined ? null : String(Number(value));

const items = storeScreen({
  resource: "store-items",
  screen: "gs-items",
  key: "item",
  load: async () => {
    const [cats, units, stores] = await Promise.all([
      apiFetch("/erp/store-categories"),
      apiFetch("/erp/store-units"),
      apiFetch("/erp/store-locations"),
    ]);
    return { cats, units, stores };
  },
  editFields: ({ m, cats, units }) => itemFields(m, cats, units),
  fields: ({ m, cats, units, stores }) => {
    const base = itemFields(m, cats, units);
    const defaultStore = stores.find((s) => s.is_default && s.active)?.id ?? "";
    return [
      ...base.slice(0, -1),
      {
        name: "opening_stock",
        label: m("f.opening_stock"),
        type: "number",
        help: m("f.opening_stock_hint"),
      },
      {
        name: "opening_store_id",
        label: m("f.opening_store"),
        type: "select",
        default: defaultStore,
        options: stores
          .filter((s) => s.active)
          .map((s) => ({ value: s.id, label: s.name })),
      },
      base[base.length - 1],
    ];
  },
  decorate: (row, { m }) => ({
    ...row,
    minimum_stock: qty(row.minimum_stock),
    reorder_level: qty(row.reorder_level),
    default_price: row.default_price === null ? null : qty(row.default_price),
    price_label: money(row.default_price),
    stock_label: qty(row.stock),
    status_label: m(`stock_status.${row.stock_status}`),
  }),
  query: (searchParams) =>
    searchParams.category
      ? `?filter[gs_category_id]=${encodeURIComponent(searchParams.category)}`
      : "",
  hint: true,
  toolbar: ({ m, cats, searchParams }) => (
    <div className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold text-foreground">
        {m("f.category")}
      </span>
      <UrlSelect
        basePath="/general-store/gs-items"
        param="category"
        value={searchParams.category}
        options={cats.map((c) => ({ value: c.id, label: c.name }))}
        allLabel={m("item.all_categories")}
      />
    </div>
  ),
  columns: ({ m }) => [
    { key: "item_code", label: m("f.item_code"), type: "mono" },
    { key: "name", label: m("f.item_name") },
    { key: "category", label: m("f.category") },
    { key: "unit", label: m("f.unit") },
    { key: "brand", label: m("f.brand") },
    {
      key: "minimum_stock",
      label: m("f.min_stock"),
      type: "number",
      align: "right",
    },
    { key: "price_label", label: m("f.price"), align: "right" },
    { key: "stock_label", label: m("f.stock"), type: "number", align: "right" },
    { key: "rack_location", label: m("f.rack") },
    { key: "status_label", label: m("f.stock_status"), type: "badge" },
  ],
});

function itemFields(m, cats, units) {
  return [
    {
      name: "item_code",
      label: m("f.item_code"),
      required: true,
      placeholder: "ITM-001",
    },
    { name: "name", label: m("f.item_name"), required: true },
    {
      name: "gs_category_id",
      label: m("f.category"),
      type: "select",
      required: true,
      options: cats
        .filter((c) => c.active)
        .map((c) => ({ value: c.id, label: c.name })),
    },
    { name: "sub_category", label: m("f.sub_category") },
    {
      name: "gs_unit_id",
      label: m("f.unit"),
      type: "select",
      required: true,
      options: units
        .filter((u) => u.active)
        .map((u) => ({ value: u.id, label: `${u.name} (${u.code})` })),
    },
    { name: "brand", label: m("f.brand") },
    {
      name: "minimum_stock",
      label: m("f.minimum_stock"),
      type: "number",
      help: m("f.minimum_stock_hint"),
    },
    { name: "reorder_level", label: m("f.reorder_level"), type: "number" },
    {
      name: "default_price",
      label: m("f.default_price"),
      type: "number",
      help: m("f.default_price_hint"),
    },
    { name: "rack_location", label: m("f.rack_location") },
    { name: "specification", label: m("f.specification"), type: "textarea" },
    active(m),
  ];
}

const units = storeScreen({
  resource: "store-units",
  screen: "gs-units",
  key: "unit",
  lockedField: "platform",
  fields: ({ m }) => [
    code(m, "PCS"),
    { name: "name", label: m("f.name"), required: true, placeholder: "Pieces" },
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "platform", label: m("f.platform_unit"), type: "yesno" },
    { key: "items", label: m("f.items"), type: "number", align: "right" },
  ],
});

const departments = storeScreen({
  resource: "store-departments",
  screen: "gs-departments",
  key: "department",
  fields: ({ m }) => [
    code(m, "MAINT"),
    {
      name: "name",
      label: m("f.name"),
      required: true,
      placeholder: "Maintenance",
    },
    { name: "description", label: m("f.description"), type: "textarea" },
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "description", label: m("f.description") },
  ],
});

const suppliers = storeScreen({
  resource: "store-suppliers",
  screen: "gs-suppliers",
  key: "supplier",
  fields: ({ m }) => [
    code(m, "SUP-001"),
    { name: "name", label: m("f.name"), required: true },
    { name: "contact_person", label: m("f.contact_person") },
    { name: "mobile", label: m("f.mobile"), placeholder: "01XXXXXXXXX" },
    { name: "email", label: m("f.email"), type: "email" },
    { name: "address", label: m("f.address"), type: "textarea" },
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "contact_person", label: m("f.contact_person") },
    { key: "mobile", label: m("f.mobile") },
    { key: "email", label: m("f.email") },
    { key: "address", label: m("f.address") },
  ],
});

const STORE_TYPES = ["GENERAL", "BONDED", "SUB_STORE"];
const stores = storeScreen({
  resource: "store-locations",
  screen: "gs-stores",
  key: "store",
  booleans: ["active", "is_default"],
  load: async () => ({
    warehouses: (await apiFetch("/master-data/warehouses?per_page=200").catch(() => [])).map((w) => ({
      value: w.id,
      label: `${w.code} — ${w.name}`,
    })),
  }),
  fields: ({ m, warehouses }) => [
    code(m, "MAIN"),
    {
      name: "name",
      label: m("f.store_name"),
      required: true,
      placeholder: "Main Store",
    },
    {
      name: "warehouse_id",
      label: m("f.warehouse"),
      type: "select",
      options: [{ value: "", label: "—" }, ...(warehouses ?? [])],
      help: m("f.warehouse_hint"),
    },
    {
      name: "store_type",
      label: m("f.store_type"),
      type: "select",
      options: STORE_TYPES.map((v) => ({ value: v, label: m(`f.store_types.${v}`) })),
      default: "GENERAL",
    },
    { name: "location", label: m("f.location") },
    { name: "in_charge", label: m("f.in_charge") },
    {
      name: "is_default",
      label: m("f.default_store"),
      type: "checkbox",
      help: m("f.default_store_hint"),
    },
    active(m),
  ],
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "warehouse", label: m("f.warehouse") },
    { key: "store_type", label: m("f.store_type"), type: "badge", labels: Object.fromEntries(STORE_TYPES.map((v) => [v, m(`f.store_types.${v}`)])) },
    { key: "location", label: m("f.location") },
    { key: "in_charge", label: m("f.in_charge") },
    { key: "is_default", label: m("f.default"), type: "yesno" },
  ],
});

/** One of the six document screens: its form's options and its list. */
function documentScreen(kind) {
  return async function renderDocuments({
    permissions = [],
    searchParams = {},
  }) {
    // An issue opened from an approved requisition: `?req=REQ-2026-000001`, its number, never its key.
    const req =
      kind === "issue" && typeof searchParams.req === "string"
        ? searchParams.req
        : "";
    const requisition = req
      ? await apiFetch(
          `/general-store/requisitions/${encodeURIComponent(req)}/issuable`,
        ).catch(() => null)
      : null;
    const [options, list] = await Promise.all([
      apiFetch(`/general-store/${kind}/options`),
      apiFetch(`/general-store/${kind}/documents`),
    ]);
    return (
      <StoreDocumentScreen
        kind={kind}
        options={options}
        rows={list.rows}
        canPost={permissions.includes("store.inventory.post")}
        canCancel={permissions.includes("store.inventory.cancel")}
        act={actions.gsAction}
        initialRequisition={requisition}
        key={requisition?.req_no ?? "new"}
      />
    );
  };
}

const reqPerms = (permissions) => ({
  approve: permissions.includes("store.requisition.approve"),
  issue: permissions.includes("store.inventory.post"),
});
const voucherOf = async () =>
  (await apiFetch("/general-store/requisitions/options").catch(() => null))
    ?.voucher ?? null;

async function newRequisition({ permissions = [] }) {
  const [options, list] = await Promise.all([
    apiFetch("/general-store/requisitions/options"),
    apiFetch("/general-store/requisitions?list=mine"),
  ]);
  return (
    <NewRequisition
      options={options}
      rows={list.rows}
      perms={reqPerms(permissions)}
      act={actions.gsAction}
    />
  );
}

function requisitionScreen(list) {
  return async function renderRequisitions({ permissions = [] }) {
    const [rows, voucher] = await Promise.all([
      apiFetch(`/general-store/requisitions?list=${list}`),
      voucherOf(),
    ]);
    return (
      <RequisitionList
        list={list}
        rows={rows.rows}
        perms={reqPerms(permissions)}
        act={actions.gsAction}
        voucher={voucher}
      />
    );
  };
}

/** One of the thirteen reports, with its filters' lists. */
function reportScreen(report) {
  return async function renderReport() {
    const [data, options] = await Promise.all([
      apiFetch(`/general-store/reports/${report}`),
      apiFetch("/general-store/reports/options"),
    ]);
    return (
      <GsReport
        report={report}
        data={data}
        options={options}
        act={actions.gsAction}
      />
    );
  };
}

async function auditLog() {
  return (
    <AuditLog
      rows={(await apiFetch("/general-store/audit-log")).rows}
      act={actions.gsAction}
    />
  );
}

async function storeSettings() {
  return (
    <StoreSettings
      data={await apiFetch("/general-store/settings")}
      act={actions.gsAction}
    />
  );
}

async function currentStock() {
  const [data, options] = await Promise.all([
    apiFetch("/general-store/current-stock"),
    apiFetch("/general-store/receive/options"),
  ]);
  return <CurrentStock data={data} options={options} act={actions.gsAction} />;
}

/** The ledger opens on an item by its code (`?item=ITM-001`, from Current Stock), never its key. */
async function stockLedger({ searchParams = {} }) {
  const item = typeof searchParams.item === "string" ? searchParams.item : "";
  const [data, options] = await Promise.all([
    apiFetch(
      `/general-store/ledger${item ? `?item=${encodeURIComponent(item)}` : ""}`,
    ),
    apiFetch("/general-store/receive/options"),
  ]);
  return (
    <StockLedger
      key={item || "none"}
      data={data}
      options={options}
      initialItem={item}
      act={actions.gsAction}
    />
  );
}

async function dashboard() {
  return <GsDashboard data={await apiFetch("/general-store/dashboard")} />;
}

const SCREENS = {
  "gs-dashboard": dashboard,
  "gs-opening": documentScreen("opening"),
  "gs-receive": documentScreen("receive"),
  "gs-issue": documentScreen("issue"),
  "gs-return": documentScreen("return"),
  "gs-adjustment": documentScreen("adjustment"),
  "gs-transfer": documentScreen("transfer"),
  "gs-current-stock": currentStock,
  "gs-ledger": stockLedger,
  "gs-req-new": newRequisition,
  "gs-req-pending": requisitionScreen("pending"),
  "gs-req-approved": requisitionScreen("approved"),
  "gs-req-issued": requisitionScreen("issued"),
  ...Object.fromEntries(
    [
      "stock",
      "receive",
      "issue",
      "return",
      "adjustment",
      "transfer",
      "department",
      "consumption",
      "category",
      "valuation",
      "low-stock",
      "monthly",
      "history",
    ].map((r) => [`gs-rpt-${r}`, reportScreen(r)]),
  ),
  "gs-audit-log": auditLog,
  "gs-settings": storeSettings,
  "gs-categories": categories,
  "gs-items": items,
  "gs-units": units,
  "gs-departments": departments,
  "gs-suppliers": suppliers,
  "gs-stores": stores,
};

export { SCREENS };
