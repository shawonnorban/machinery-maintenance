import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { FilterBar } from "@/components/erp/filter-bar";
import { PickPanel } from "@/components/erp/pick-panel";
import { InquiryQuotation } from "@/components/merchandise/inquiry-quotation";
import { StyleForm } from "@/components/merchandise/style-form";
import { StyleSizeRanges } from "@/components/merchandise/style-size-ranges";
import { StyleSheetDesk } from "@/components/merchandise/style-desk";
import { findByRef, refsFor } from "@/lib/row-ref";
import { nounLabels } from "@/lib/brickland-labels";
import { masterScreen } from "./screens";
import * as actions from "./actions";

/*
 * Merchandise Desk → Inquiry (AnnotechERP 06/05): Price Quotation Entry — the
 * inquiry list, the Quotation panel that moves an inquiry along, and Buyer
 * Rates — and Style Development Sheet — the development styles, their size
 * ranges, and the registration form for a new style, its edit and its sheet.
 */

const INCO_TERMS = ["FOB", "CFR", "CIF", "CPT", "EXW", "DDP"];
const LC_TERMS = ["LC_AT_SIGHT", "LC_30", "LC_60", "LC_90", "TT_ADVANCE", "TT_DELIVERY"];
const CONS_UNITS = ["KG", "LBS", "YDS", "MTR"];
const CALC_UNITS = ["DZ", "PCS", "SET"];
const CURRENCIES = ["USD", "EUR", "GBP", "CAD", "BDT"];
const STYLE_STATUSES = ["DRAFT", "DEVELOPMENT", "STYLE_APPROVED", "RUNNING", "CLOSED", "ON_HOLD", "CANCELLED"];
const FORM_STATUSES = ["DRAFT", "DEVELOPMENT", "STYLE_APPROVED", "ON_HOLD", "CANCELLED"];
const SHEET_STATUSES = ["STYLE_APPROVED", "DEVELOPMENT", "RUNNING", "CLOSED", "ON_HOLD", "CANCELLED", "DRAFT"];

const plain = (values) => values.map((v) => ({ value: v, label: v }));
const labelled = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const money = (currency, value) => (value === null || value === undefined ? null : `${currency ?? ""} ${Number(value).toFixed(2)}`.trim());

const priceQuotation = masterScreen({
  resource: "merch-inquiries",
  screen: "price-quotation",
  noun: "inquiry",
  nameKey: "buyer",
  refKey: "inquiry_no",
  newLabel: "inq.new",
  description: "inq.new_hint",
  formColumns: 3,
  booleans: [],
  load: async () => ({ look: await apiFetch("/merchandise/styles/lookups") }),
  fields: ({ m, look }) => [
    { name: "buyer_id", label: m("inq.f.buyer"), type: "select", required: true, options: look.buyers },
    { name: "inco_term", label: m("inq.f.inco_term"), type: "select", default: "FOB", options: plain(INCO_TERMS) },
    { name: "lc_term", label: m("inq.f.lc_term"), type: "select", options: labelled(m, "inq.opt.lc_term", LC_TERMS) },
    { name: "cons_unit", label: m("inq.f.cons_unit"), type: "select", default: "KG", options: labelled(m, "inq.opt.cons_unit", CONS_UNITS), help: m("inq.cons_hint") },
    { name: "calc_unit", label: m("inq.f.calc_unit"), type: "select", default: "DZ", options: labelled(m, "inq.opt.calc_unit", CALC_UNITS), help: m("inq.calc_hint") },
    { name: "currency", label: m("inq.f.currency"), type: "select", default: "USD", options: plain(CURRENCIES) },
    { name: "exchange_rate", label: m("inq.f.exch_rate"), type: "number", default: "0", step: "0.0001", help: m("inq.rate_hint") },
    { name: "style_no", label: m("inq.f.style_no"), required: true, placeholder: "123" },
    { name: "style_ext", label: m("inq.f.style_ext"), placeholder: "ERRRRA" },
    { name: "style_desc", label: m("inq.f.style_desc"), required: true, placeholder: "Mens T Shirt" },
    { name: "inquiry_date", label: m("inq.f.inquiry_dt"), type: "date", required: true },
    { name: "quoted_fob", label: m("inq.f.quoted_fob"), type: "number", step: "0.0001", suffix: "$" },
    { name: "remarks", label: m("inq.f.remarks"), type: "textarea", span: 3 },
  ],
  decorate: (row, { m }) => ({
    ...row,
    exchange_rate: String(row.exchange_rate ?? 0),
    quoted_fob: row.quoted_fob === null ? null : String(row.quoted_fob),
    lc_term: row.lc_term ?? "",
    fob_label: money(row.currency, row.quoted_fob),
    status_label: m(`inq.status.${row.status}`),
  }),
  columns: ({ m }) => [
    { key: "buyer", label: m("inq.f.buyer") },
    { key: "inquiry_no", label: m("inq.f.inquiry_no"), type: "mono" },
    { key: "style_no", label: m("inq.f.style_no") },
    { key: "style_ext", label: m("inq.f.style_ext") },
    { key: "style_desc", label: m("inq.f.style_desc") },
    { key: "inquiry_date", label: m("inq.f.inquiry_dt"), type: "date" },
    { key: "revision", label: m("inq.f.rev"), type: "number" },
    { key: "fob_label", label: m("inq.f.quoted_fob"), align: "right" },
    { key: "status_label", label: m("inq.f.status"), type: "badge" },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "quotation",
      label: m("inq.tab_quotation"),
      icon: "ListChecks",
      render: async () => {
        const buyers = await apiFetch("/merchandise/inquiries/buyers");
        // A buyer is named in the url by its code, an inquiry by its number.
        const buyer = buyers.find((b) => b.code === searchParams.buyer) ?? null;
        const active = buyer?.code ?? null;
        const list = buyer ? await apiFetch(`/merchandise/inquiries/buyers/${buyer.id}`) : [];
        return (
          <PickPanel
            title={m("inq.buyers")}
            items={buyers.map((b) => ({ key: b.code, label: b.label, sub: b.code, count: b.count }))}
            active={active}
            hrefBase="/merchandise-desk/price-quotation?tab=quotation&buyer="
            searchPlaceholder={m("inq.search")}
            noMatch={m("inq.no_buyer")}
            panelTitle={buyer ? m("inq.inquiries_of", { buyer: buyer.label }) : m("inq.inquiries")}
            panelHint={m("inq.quotation_hint")}
            emptyTitle={m("inq.select_buyer")}
            emptyText={m("inq.select_buyer_hint")}
          >
            <InquiryQuotation key={active} rows={list} move={actions.moveInquiry} canManage={canManage} exportName={`inquiries-${buyer?.code ?? "buyer"}`} />
          </PickPanel>
        );
      },
    },
    {
      key: "rates",
      label: m("inq.tab_rates"),
      icon: "ListChecks",
      render: async () => {
        const picked = rows.find((r) => r.inquiry_no === searchParams.inquiry) ?? null;
        const active = picked?.inquiry_no ?? null;
        const data = picked ? await apiFetch(`/merchandise/inquiries/${picked.id}/rates`) : null;
        return (
          <PickPanel
            title={m("inq.inquiry_list")}
            items={rows.map((r) => ({ key: r.inquiry_no, label: r.inquiry_no, sub: `${r.buyer ?? ""} · ${r.inquiry_date}` }))}
            active={active}
            hrefBase="/merchandise-desk/price-quotation?tab=rates&inquiry="
            searchPlaceholder={m("inq.search")}
            noMatch={m("inq.no_inquiry")}
            panelTitle={data ? m("inq.rates_of", { no: data.inquiry.inquiry_no }) : m("inq.rates")}
            panelHint={m("inq.rates_hint")}
            emptyTitle={m("inq.select_inquiry")}
            emptyText={m("inq.select_inquiry_hint")}
          >
            {data ? (
              <RecordsGrid
                exportName={`buyer-rates-${data.inquiry.inquiry_no}`}
                rowKey="key"
                plain
                emptyText={m("inq.no_rates")}
                rows={data.rates.map((r) => ({
                  ...r,
                  group_label: m(`opt.rate_group.${r.rate_group}`),
                  rate_label: r.rate === null ? null : r.rate.toFixed(4),
                  nominated: r.nominated_supplier ?? m("inq.no_supplier"),
                  state: r.quoted ? m("inq.quoted") : m("inq.not_quoted"),
                }))}
                columns={[
                  { key: "group_label", label: m("inq.f.rate_group") },
                  { key: "description", label: m("inq.f.rate_desc") },
                  { key: "uom", label: m("inq.f.uom") },
                  { key: "rate_label", label: m("inq.f.rate"), align: "right" },
                  { key: "currency", label: m("inq.f.currency") },
                  { key: "effective_from", label: m("inq.f.effective_date"), type: "date" },
                  { key: "buyer_office", label: m("inq.f.buyer_office") },
                  { key: "nominated", label: m("inq.f.nominated") },
                  { key: "alternative_supplier", label: m("inq.f.alternative") },
                  { key: "state", label: m("inq.f.status"), type: "badge" },
                ]}
              />
            ) : null}
          </PickPanel>
        );
      },
    },
  ],
});

/** What StyleForm picks from, labelled. */
function styleLook(m, look) {
  return {
    ...look,
    qty_units: labelled(m, "sty.qty_unit", ["PCS", "SET", "DZN"]),
    form_statuses: labelled(m, "sty.status", FORM_STATUSES),
    sheet_statuses: labelled(m, "sty.status", SHEET_STATUSES),
    seasons: plain(look.seasons),
    group_labels: { LETTER: m("opt.size_group.LETTER"), NUMERIC: m("opt.size_group.NUMERIC"), AGE: m("opt.size_group.AGE") },
  };
}

async function styleDevelopment({ m, te, tab, canManage, can = () => false, searchParams = {} }) {
  const query = new URLSearchParams();
  for (const [param, filter] of [["buyer", "buyer_id"], ["account", "buyer_office_id"], ["ref", "ref"], ["season", "season"], ["status", "status"]]) if (searchParams[param]) query.set(filter, searchParams[param]);
  const [rows, look] = await Promise.all([apiFetch(`/merchandise/styles${query.size ? `?${query}` : ""}`), apiFetch("/merchandise/styles/lookups")]);
  const all = query.size ? await apiFetch("/merchandise/styles") : rows;
  const total = all.length;
  const records = "/merchandise-desk/style-development?tab=records";
  const words = nounLabels(te, m("noun.dev_style"), "style_no");
  // A style is named in the url by its style no (`?tab=sheet&style=MOOD-SHIRT-327632.01`), never its id.
  const target = (tab === "edit" || tab === "sheet") && canManage ? findByRef(all, "style_no", searchParams.style) : null;
  const opened = target ? await apiFetch(`/merchandise/styles/${target.id}`).catch(() => null) : null;
  const formLook = styleLook(m, look);

  /** The sheet: the registration form, Item/Combo/Model (Fresh / Copy) and Style Gallery; the desk opens on the page. */
  async function sheet(row) {
    const items = await apiFetch(`/merchandise/styles/${row.id}/items`);
    const images = items.items.flatMap((i) => [
      i.key_image && { label: `${i.short_name} · ${m("itm.key_image")}`, path: i.key_image },
      i.second_image && { label: `${i.short_name} · ${m("itm.second_image")}`, path: i.second_image },
      i.item_image && { label: `${i.short_name} · ${m("itm.item_image")}`, path: i.item_image },
      ...i.certificates.filter((c) => c.path).map((c, n) => ({ label: `${i.short_name} · ${m("itm.certificate", { n: n + 1 })}`, path: c.path })),
    ]).filter(Boolean);
    return (
      <StyleSheetDesk
        key={`sheet-${row.id}`}
        row={row}
        look={formLook}
        items={items}
        images={images}
        canManage={canManage}
        orders={{ view: can("merchandise.order.view"), manage: can("merchandise.order.manage") }}
        recordsHref={records}
        actions={{
          saveStyle: actions.saveStyle,
          saveStyleItem: actions.saveStyleItem,
          saveStyleColours: actions.saveStyleColours,
          saveStyleFabrication: actions.saveStyleFabrication,
          loadStyleColours: actions.loadStyleColours,
          loadStyleFabrication: actions.loadStyleFabrication,
          saveStyleServices: actions.saveStyleServices,
          loadStyleServices: actions.loadStyleServices,
          saveStyleBom: actions.saveStyleBom,
          approveStyleBom: actions.approveStyleBom,
          loadStyleBom: actions.loadStyleBom,
          saveStyleFabricMapping: actions.saveStyleFabricMapping,
          loadStyleFabricMapping: actions.loadStyleFabricMapping,
          loadStyleOrders: actions.loadStyleOrders,
          createStyleOrder: actions.createStyleOrder,
          updateStyleOrder: actions.updateStyleOrder,
          saveStylePacking: actions.saveStylePacking,
          loadStylePacking: actions.loadStylePacking,
        }}
      />
    );
  }

  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: total },
      { key: "sizes", label: m("sty.tab_sizes"), icon: "ListChecks" },
      ...(opened ? [{ key: tab, label: m(tab === "edit" ? "sty.edit" : "sty.sheet"), icon: tab === "edit" ? "Pencil" : "Layers" }] : canManage ? [{ key: "new", label: m("sty.new"), icon: "Plus" }] : []),
    ],
    body: async () => {
      if (opened && tab === "sheet") return sheet(opened);
      if (opened) return <StyleForm key={`${tab}-${opened.id}`} mode={tab} row={opened} look={formLook} save={actions.saveStyle} recordsHref={records} />;
      if (tab === "new" && canManage) return <StyleForm mode="new" look={formLook} save={actions.saveStyle} recordsHref={records} />;
      if (tab === "sizes") {
        const active = findByRef(all, "style_no", searchParams.style);
        const styleRefs = refsFor(all, "style_no");
        const lines = active ? await apiFetch(`/merchandise/styles/${active.id}/size-ranges`) : [];
        return (
          <PickPanel
            title={m("sty.styles")}
            items={all.map((s) => ({ key: styleRefs.get(s.id), label: s.style_no, sub: s.description, count: s.size_ranges }))}
            active={active ? styleRefs.get(active.id) : null}
            hrefBase="/merchandise-desk/style-development?tab=sizes&style="
            searchPlaceholder={m("inq.search")}
            noMatch={m("sty.no_style")}
            panelTitle={active ? m("sty.size_ranges_of", { style: active.style_no }) : m("sty.size_ranges")}
            panelHint={m("sty.sizes_hint")}
            emptyTitle={m("sty.select_style")}
            emptyText={m("sty.select_style_hint")}
          >
            {active ? (
              <StyleSizeRanges
                key={active.id}
                style={{ id: active.id, style_no: active.style_no }}
                lines={lines}
                sizes={look.sizes}
                groupLabels={formLook.group_labels}
                add={actions.addSizeRange}
                remove={actions.removeSizeRange}
                canManage={canManage}
              />
            ) : null}
          </PickPanel>
        );
      }

      const offices = Object.values(look.buyer_offices).flat();
      return (
        <div className="flex flex-col gap-3">
          <FilterBar
            instant
            basePath="/merchandise-desk/style-development"
            keep={{ tab: "records" }}
            values={searchParams}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "account", label: m("sty.f.buyer_ac"), type: "select", options: [...new Map(offices.map((o) => [o.value, o])).values()], placeholder: m("all") },
              { name: "buyer", label: m("sty.f.buyer"), type: "select", options: look.buyers.map((b) => ({ value: b.value, label: b.short })), placeholder: m("all") },
              { name: "ref", label: m("sty.f.main_ref"), type: "text" },
              { name: "season", label: m("sty.f.season"), type: "select", options: plain(look.seasons), placeholder: m("all") },
              { name: "status", label: m("sty.f.status"), type: "select", options: labelled(m, "sty.status", STYLE_STATUSES), placeholder: m("all") },
            ]}
          />
          <RecordsGrid
            exportName="development-styles"
            rows={rows.map((r) => ({ ...r, qty_label: m(`sty.qty_unit.${r.qty_unit}`), status_label: m(`sty.status.${r.status}`) }))}
            columns={[
              { key: "style_no", label: m("sty.f.main_ref") },
              { key: "description", label: m("sty.f.style_desc") },
              { key: "buyer", label: m("sty.f.buyer") },
              { key: "buyer_office", label: m("sty.f.buyer_ac") },
              { key: "inquiry_no", label: m("sty.f.inquiry"), type: "mono" },
              { key: "season", label: m("sty.f.season") },
              { key: "qty_label", label: m("sty.f.qty_unit") },
              { key: "origin", label: m("sty.f.origin") },
              { key: "orders", label: m("sty.f.orders"), type: "number" },
              { key: "status_label", label: m("sty.f.status"), type: "badge" },
            ]}
            rowLink={canManage ? { label: m("sty.sheet_btn"), href: "/merchandise-desk/style-development?tab=sheet&style=", refKey: "style_no" } : undefined}
            edit={canManage ? { href: "/merchandise-desk/style-development?tab=edit&style=", refKey: "style_no" } : undefined}
            remove={canManage ? actions.deleteStyle : undefined}
            deleteLabels={words.remove}
            emptyState={words.empty}
          />
        </div>
      );
    },
  };
}

const INQUIRY_SCREENS = {
  "price-quotation": priceQuotation,
  "style-development": styleDevelopment,
};

/** Each Inquiry screen is changed under its own permission, not the master data's. */
const INQUIRY_MANAGE = {
  "price-quotation": "merchandise.inquiry.manage",
  "style-development": "merchandise.style.manage",
};

export { INQUIRY_SCREENS, INQUIRY_MANAGE, styleLook };
