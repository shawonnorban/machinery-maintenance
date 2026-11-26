import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { LetterheadPreview } from "@/components/settings/letterhead-preview";
import { LabelPreviews } from "@/components/settings/label-previews";
import * as actions from "./actions";
import { HUB_SCREENS } from "./hub-screens";
import { SYSTEM_SCREENS } from "./system-screens";
import { DEVICE_SCREENS } from "./device-screens";

/*
 * Settings → General Settings (AnnotechERP 03/03), in Brickland's layout:
 * a preference form (the company's setting keys) with a Records tab of the
 * same keys, the currency master, the numbering series, the letterhead with a
 * live preview, and the label templates.
 */

const opt = (value, label) => ({ value: String(value), label });

/** Choice labels for the ENUM/LIST keys that have them. */
function choiceLabels(h, key) {
  const maps = {
    "general.working_days": "days",
    "general.fiscal_year_start_month": "months",
    "locale.default": "languages",
    "locale.time_format": "time_formats",
    "number.grouping": "groupings",
  };
  return maps[key] ? (value) => h(`${maps[key]}.${value}`) : (value) => value;
}

/** The setting keys of one form, with their definitions and current values. */
async function loadSettings(keys) {
  const [definitions, settings] = await Promise.all([apiFetch("/settings/definitions"), apiFetch("/settings")]);
  const values = new Map(settings.map((row) => [row.key, row]));
  return keys
    .map((key) => {
      const definition = definitions.find((d) => d.key === key);
      if (!definition) return null;
      const row = values.get(key);
      return { ...definition, value: row?.value ?? definition.default_value, level: row?.level ?? "PLATFORM", updated_at: row?.updated_at ?? null, updated_by: row?.updated_by ?? null };
    })
    .filter(Boolean);
}

function settingFields(items, h) {
  return items.map((item) => {
    const id = item.key.replaceAll(".", "_");
    const label = h(`keys.${id}`);
    // Every key has a help entry; an empty one means no help line.
    const help = h(`help.${id}`) || undefined;
    const labelOf = choiceLabels(h, item.key);
    const base = { name: item.key, label, help };
    if (item.value_type === "BOOL") return { ...base, type: "checkbox" };
    if (item.value_type === "ENUM") return { ...base, type: "select", options: (item.allowed_values ?? []).map((v) => opt(v, labelOf(v))) };
    if (item.value_type === "LIST" && item.allowed_values?.length) return { ...base, type: "multiselect", options: item.allowed_values.map((v) => opt(v, labelOf(v))) };
    if (item.value_type === "INT" || item.value_type === "DECIMAL") return { ...base, type: "number" };
    return { ...base, type: "text" };
  });
}

function settingValues(items) {
  // A free list reads better as "A, B"; a list of fixed choices stays "A,B" for its ticks.
  return Object.fromEntries(items.map((item) => [item.key, item.value_type === "LIST" ? (item.value ?? []).join(item.allowed_values?.length ? "," : ", ") : item.value]));
}

function settingRecords(items, h) {
  return items.map((item) => {
    const labelOf = choiceLabels(h, item.key);
    const text = item.value_type === "BOOL" ? (item.value ? "✓" : "—") : item.value_type === "LIST" ? (item.value ?? []).map(labelOf).join(", ") : labelOf(String(item.value ?? ""));
    return { id: item.key, key: item.key, parameter: h(`keys.${item.key.replaceAll(".", "_")}`), value_text: text || "—", level: item.level, updated_at: item.updated_at };
  });
}

/** A screen that is one preference form plus the Records of its keys. */
function preferencesScreen(keys, { extraTabs: tabsFor = () => [], extraBody = null } = {}) {
  return async ({ h, te, tab, key }) => {
    const items = await loadSettings(keys);
    const extraTabs = tabsFor(h);
    const sources = Object.fromEntries(["COMPANY", "FACTORY", "PLATFORM"].map((v) => [v, h(`sources.${v}`)]));
    return {
      tabs: [{ key: "preferences", label: h("tabs.preferences"), icon: "SlidersHorizontal" }, ...extraTabs, { key: "records", label: te("records"), icon: "Table2", count: items.length }],
      defaultTab: "preferences",
      body: async () => {
        if (extraBody && extraTabs.some((t) => t.key === tab)) return extraBody({ h, te, tab, items });
        if (tab === "records") {
          return (
            <RecordsGrid
              exportName={key}
              rows={settingRecords(items, h)}
              columns={[
                { key: "parameter", label: h("parameter") },
                { key: "key", label: h("key"), type: "mono" },
                { key: "value_text", label: h("value") },
                { key: "level", label: h("source"), type: "badge", labels: sources },
                { key: "updated_at", label: h("updated"), type: "datetime" },
              ]}
            />
          );
        }
        return (
          <EntryForm
            fields={settingFields(items, h)}
            initial={settingValues(items)}
            action={actions.saveSettings.bind(null, keys)}
            title={h(`screens.${key}`)}
            description={h(`descriptions.${key}`)}
            submitLabel={h("save")}
            note={h("form_note")}
            columns={2}
          />
        );
      },
    };
  };
}

const generalPreferences = preferencesScreen(["general.working_days", "general.fiscal_year_start_month", "general.landing_page", "general.page_size"]);

const currencyFields = (h, editing) => [
  { name: "code", label: h("currency.code"), type: "select", required: !editing, readOnly: editing, options: ["BDT", "USD", "EUR", "GBP", "CNY", "INR", "JPY", "HKD", "SGD", "AED", "CAD", "AUD", "TRY", "VND", "PKR", "LKR"].map((c) => opt(c, c)) },
  { name: "name", label: h("currency.name"), required: true, placeholder: "US Dollar" },
  { name: "symbol", label: h("currency.symbol"), placeholder: "$" },
  { name: "decimal_places", label: h("currency.decimal_places"), type: "number", default: 2 },
  { name: "exchange_rate", label: h("currency.exchange_rate"), type: "number", step: "0.000001", required: true, help: h("currency.exchange_rate_help") },
  { name: "rate_date", label: h("currency.rate_date"), type: "date" },
  { name: "active", label: h("currency.active"), type: "checkbox", default: true },
];

const localization = preferencesScreen(["locale.default", "locale.date_format", "locale.time_format", "locale.timezone", "currency.base", "number.decimal_places", "number.grouping"], {
  extraTabs: (h) => [
    { key: "currencies", label: h("tabs.currencies"), icon: "Coins" },
    { key: "new-currency", label: h("tabs.new_currency"), icon: "Plus" },
  ],
  extraBody: async ({ h, tab }) => {
    if (tab === "new-currency") {
      return (
        <EntryForm
          fields={currencyFields(h, false)}
          action={actions.createResource.bind(null, "currencies", ["active"])}
          title={h("tabs.new_currency")}
          description={h("currency.hint")}
          submitLabel={h("currency.save")}
          successHref="/system-settings/localization?tab=currencies"
        />
      );
    }
    const currencies = await apiFetch("/erp/currencies");
    return (
      <RecordsGrid
        exportName="currencies"
        rows={currencies}
        columns={[
          { key: "code", label: h("currency.code"), type: "mono" },
          { key: "name", label: h("currency.name") },
          { key: "symbol", label: h("currency.symbol") },
          { key: "decimal_places", label: h("currency.decimal_places"), type: "number" },
          { key: "exchange_rate", label: h("currency.exchange_rate"), type: "number" },
          { key: "rate_date", label: h("currency.rate_date"), type: "date" },
          { key: "is_base", label: h("currency.is_base"), type: "yesno" },
          { key: "active", label: h("currency.active"), type: "status" },
        ]}
        edit={{ fields: currencyFields(h, true), action: actions.updateResource.bind(null, "currencies", ["active"]), title: h("currency.edit"), submitLabel: h("currency.save") }}
        remove={actions.deleteResource.bind(null, "currencies")}
      />
    );
  },
});

async function documentNumbering({ h, te }) {
  const series = await apiFetch("/numbering");
  const resets = Object.fromEntries(["NEVER", "YEARLY", "MONTHLY"].map((v) => [v, h(`numbering.resets.${v}`)]));
  const rows = series.map((s) => ({ ...s, id: s.document_type, document_label: s.document_type.replaceAll("_", " "), custom: !s.is_default }));
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }],
    body: () => (
      <RecordsGrid
        exportName="numbering"
        rows={rows}
        columns={[
          { key: "document_label", label: h("numbering.document_type") },
          { key: "format", label: h("numbering.format"), type: "mono" },
          { key: "padding", label: h("numbering.padding"), type: "number" },
          { key: "reset", label: h("numbering.reset"), type: "badge", labels: resets },
          { key: "sample", label: h("numbering.sample"), type: "mono" },
          { key: "issued", label: h("numbering.issued"), type: "number" },
          { key: "custom", label: h("numbering.custom"), type: "yesno" },
        ]}
        edit={{
          fields: [
            { name: "document_label", label: h("numbering.document_type"), readOnly: true },
            { name: "format", label: h("numbering.format"), required: true, help: h("numbering.format_help") },
            { name: "padding", label: h("numbering.padding"), type: "number", required: true },
          ],
          action: actions.updateNumbering,
          title: h("numbering.edit"),
          submitLabel: h("numbering.save"),
        }}
        remove={actions.resetNumbering}
        deleteDescription={h("numbering.reset_hint")}
      />
    ),
  };
}

const PRINT_KEYS = ["print.header_title", "print.header_address", "print.header_contact", "print.footer_text", "print.show_logo", "print.show_print_time", "print.signatures", "print.paper_size", "print.margin_mm"];

async function printLetterhead(context) {
  const screen = await preferencesScreen(PRINT_KEYS)(context);
  const [items, me] = await Promise.all([loadSettings(PRINT_KEYS), apiFetch("/auth/me")]);
  const values = Object.fromEntries(items.map((i) => [i.key, i.value]));
  return {
    ...screen,
    body: async () => {
      const form = await screen.body();
      if (context.tab === "records") return form;
      return (
        <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_24rem]">
          <div className="min-w-0">{form}</div>
          <LetterheadPreview values={values} companyName={me.company?.name} logoUrl={me.company?.logo_url} title={context.h("preview")} />
        </div>
      );
    },
  };
}

const labelFields = (h, editing) => [
  { name: "code", label: h("label.code"), required: !editing, readOnly: editing, placeholder: "LBL-ROLL" },
  { name: "name", label: h("label.name"), required: true, placeholder: "Fabric roll label" },
  { name: "usage", label: h("label.usage"), type: "select", required: true, options: ["ITEM", "ROLL", "ASSET", "BUNDLE", "CARTON", "LOCATION"].map((v) => opt(v, h(`label.usages.${v}`))) },
  { name: "symbology", label: h("label.symbology"), type: "select", required: true, options: ["CODE128", "QR", "EAN13", "DATAMATRIX"].map((v) => opt(v, h(`label.symbologies.${v}`))) },
  { name: "width_mm", label: h("label.width_mm"), type: "number", required: true, default: 50 },
  { name: "height_mm", label: h("label.height_mm"), type: "number", required: true, default: 25 },
  { name: "columns", label: h("label.columns"), type: "number", default: 1 },
  { name: "printer_language", label: h("label.printer_language"), type: "select", options: ["PDF", "ZPL"].map((v) => opt(v, h(`label.printer_languages.${v}`))), default: "PDF" },
  {
    name: "fields",
    label: h("label.fields"),
    type: "multiselect",
    options: ["CODE", "NAME", "DESCRIPTION", "QUANTITY", "UOM", "LOT", "COLOR", "SIZE", "BUYER", "ORDER", "DATE", "LOCATION"].map((v) => opt(v, h(`label.fields_values.${v}`))),
    default: "CODE,NAME",
  },
  { name: "active", label: h("label.active"), type: "checkbox", default: true },
];

async function barcodeLabel({ h, te, tab }) {
  const templates = await apiFetch("/erp/label-templates");
  const rows = templates.map((t) => ({ ...t, size: `${Number(t.width_mm)} × ${Number(t.height_mm)} mm`, fields_label: (t.fields ?? []).map((f) => h(`label.fields_values.${f}`)).join(", ") }));
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "preview", label: h("label.preview"), icon: "ScanBarcode" },
      { key: "new", label: h("tabs.new_label"), icon: "Plus" },
    ],
    body: () => {
      if (tab === "new") {
        return (
          <EntryForm
            fields={labelFields(h, false)}
            action={actions.createResource.bind(null, "label-templates", ["active"])}
            title={h("tabs.new_label")}
            description={h("label.hint")}
            submitLabel={h("label.save")}
            successHref="/system-settings/barcode-label?tab=records"
          />
        );
      }
      if (tab === "preview") {
        return <LabelPreviews templates={rows} emptyText={h("label.no_templates")} fieldLabels={Object.fromEntries(["CODE", "NAME", "DESCRIPTION", "QUANTITY", "UOM", "LOT", "COLOR", "SIZE", "BUYER", "ORDER", "DATE", "LOCATION"].map((f) => [f, h(`label.fields_values.${f}`)]))} />;
      }
      return (
        <RecordsGrid
          exportName="label-templates"
          rows={rows}
          columns={[
            { key: "code", label: h("label.code"), type: "mono" },
            { key: "name", label: h("label.name") },
            { key: "usage", label: h("label.usage"), type: "badge", labels: Object.fromEntries(["ITEM", "ROLL", "ASSET", "BUNDLE", "CARTON", "LOCATION"].map((v) => [v, h(`label.usages.${v}`)])) },
            { key: "symbology", label: h("label.symbology"), type: "badge", labels: Object.fromEntries(["CODE128", "QR", "EAN13", "DATAMATRIX"].map((v) => [v, h(`label.symbologies.${v}`)])) },
            { key: "size", label: h("label.size") },
            { key: "columns", label: h("label.columns"), type: "number" },
            { key: "fields_label", label: h("label.fields") },
            { key: "printer_language", label: h("label.printer_language"), type: "badge", labels: { PDF: "PDF", ZPL: "ZPL" } },
            { key: "active", label: h("label.active"), type: "status" },
          ]}
          edit={{ fields: labelFields(h, true), action: actions.updateResource.bind(null, "label-templates", ["active"]), title: h("label.edit"), submitLabel: h("label.save") }}
          remove={actions.deleteResource.bind(null, "label-templates")}
        />
      );
    },
  };
}

const SETTINGS_SCREENS = {
  ...DEVICE_SCREENS,
  ...HUB_SCREENS,
  "general-preferences": generalPreferences,
  localization,
  "document-numbering": documentNumbering,
  "print-letterhead": printLetterhead,
  "barcode-label": barcodeLabel,
  ...SYSTEM_SCREENS,
};

export { SETTINGS_SCREENS };
