import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { StatusPills } from "@/components/erp/catalog-card";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import * as actions from "./actions";

/*
 * Material Procurement → Master Data (AnnotechERP 08/01): Fabric Master,
 * Trims & Accessories Master and Supplier Master, each Brickland's one card —
 * All / Active / Inactive, an Add dialog, Edit, and a Status switch that saves
 * at once. Nothing here is deleted; an item or supplier is switched off.
 */

const STATUSES = ["all", "active", "inactive"];

/** A catalogue screen over one resource. */
function catalogScreen(config) {
  return async function renderCatalog({ m, canManage, searchParams = {} }) {
    const rows = (await apiFetch(`/erp/${config.resource}`)).map((row) => (config.decorate ? config.decorate(row, { m }) : row));
    const status = STATUSES.includes(searchParams.status) ? searchParams.status : "all";
    const counts = { all: rows.length, active: rows.filter((r) => r.active).length, inactive: rows.filter((r) => !r.active).length };
    const shown = status === "all" ? rows : rows.filter((r) => (status === "active" ? r.active : !r.active));
    const fields = config.fields({ m });

    // Brickland's one card: title, "N active of M", Add on top; pills and search, then the plain table.
    return (
      <RecordsGrid
        exportName={config.screen}
        rows={shown}
        header={{
          title: m(`${config.key}.title`),
          subtitle: m(`${config.key}.subtitle`, { active: counts.active, count: counts.all }),
          searchInline: true,
          searchPlaceholder: m(`${config.key}.search`),
          filters: <StatusPills filters={STATUSES.map((key) => ({ key, label: m(`status.${key}`), count: counts[key] }))} active={status} hrefFor={(key) => `/material-procurement/${config.screen}${key === "all" ? "" : `?status=${key}`}`} />,
          action: canManage ? (
            <DialogFormButton
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
        plain
        columns={[...config.columns({ m }), { key: "active", label: m("f.status"), type: "toggle", action: canManage ? actions.setActive.bind(null, config.resource) : undefined }]}
        edit={canManage ? { fields, action: actions.updateResource.bind(null, config.resource), title: m(`${config.key}.edit`), submitLabel: m("save") } : undefined}
      />
    );
  };
}

const text = (m, name, extra = {}) => ({ name, label: m(`f.${name}`), ...extra });
const active = (m) => ({ name: "active", label: m("f.active"), type: "checkbox", default: true });

const fabric = catalogScreen({
  resource: "material-fabrics",
  screen: "fabric-master",
  key: "fabric",
  fields: ({ m }) => [
    text(m, "item_code", { required: true, placeholder: "FAB-001" }),
    text(m, "fabric_name", { name: "name", required: true, placeholder: "Cotton Jersey" }),
    text(m, "category", { required: true, placeholder: "Knit / Single Jersey" }),
    text(m, "sub_category"),
    text(m, "gsm", { placeholder: "160" }),
    text(m, "width", { placeholder: '60"' }),
    text(m, "composition", { placeholder: "100% Cotton" }),
    text(m, "colour", { placeholder: "White" }),
    text(m, "uom", { required: true, type: "uom" }),
    text(m, "specification"),
    text(m, "description", { type: "textarea" }),
    text(m, "remarks"),
    active(m),
  ],
  columns: ({ m }) => [
    { key: "item_code", label: m("f.item_code"), type: "mono" },
    { key: "name", label: m("f.fabric_name") },
    { key: "category", label: m("f.category") },
    { key: "gsm", label: m("f.gsm"), align: "right" },
    { key: "width", label: m("f.width") },
    { key: "composition", label: m("f.composition") },
    { key: "colour", label: m("f.colour") },
    { key: "uom", label: m("f.uom") },
  ],
});

const trims = catalogScreen({
  resource: "material-trims",
  screen: "accessories-master",
  key: "trims",
  fields: ({ m }) => [
    text(m, "item_code", { required: true, placeholder: "ACC-001" }),
    text(m, "item_name", { name: "name", required: true, placeholder: "Main Label" }),
    text(m, "category", { required: true, placeholder: "Label" }),
    text(m, "sub_category"),
    text(m, "size", { placeholder: "50 x 20 mm" }),
    text(m, "colour", { placeholder: "Black" }),
    text(m, "uom", { required: true, type: "uom" }),
    text(m, "specification", { placeholder: "Woven, damask" }),
    text(m, "description", { type: "textarea" }),
    text(m, "remarks"),
    active(m),
  ],
  columns: ({ m }) => [
    { key: "item_code", label: m("f.item_code"), type: "mono" },
    { key: "name", label: m("f.item_name") },
    { key: "category", label: m("f.category") },
    { key: "size", label: m("f.size") },
    { key: "specification", label: m("f.specification") },
    { key: "colour", label: m("f.colour") },
    { key: "uom", label: m("f.uom") },
  ],
});

const SUPPLIER_TYPES = ["LOCAL_NON_EPZ", "LOCAL_EPZ", "FOREIGN"];

const supplier = catalogScreen({
  resource: "material-suppliers",
  screen: "supplier-master",
  key: "supplier",
  fields: ({ m }) => [
    text(m, "supplier_code", { name: "code", required: true, placeholder: "SUP-001" }),
    text(m, "supplier_name", { name: "name", required: true }),
    text(m, "short_name"),
    text(m, "supplier_type", { type: "select", required: true, default: "LOCAL_NON_EPZ", options: SUPPLIER_TYPES.map((v) => ({ value: v, label: m(`opt.supplier_type.${v}`) })) }),
    text(m, "owner_name"),
    text(m, "goods_service", { placeholder: "Trims, Fabric…" }),
    text(m, "vat_reg_no"),
    text(m, "tin_no"),
    text(m, "address", { type: "textarea" }),
    active(m),
  ],
  decorate: (row, { m }) => ({ ...row, type_label: row.supplier_type ? m(`opt.supplier_type.${row.supplier_type}`) : null }),
  columns: ({ m }) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.supplier") },
    { key: "type_label", label: m("f.type") },
    { key: "goods_service", label: m("f.goods_service") },
    { key: "owner_name", label: m("f.owner") },
    { key: "portal_logins", label: m("f.portal_logins"), type: "number", align: "right" },
  ],
});

const SCREENS = {
  "fabric-master": fabric,
  "accessories-master": trims,
  "supplier-master": supplier,
};

export { SCREENS };
