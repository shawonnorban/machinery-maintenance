import Link from "next/link";
import { ArrowRight, BadgeCheck, ClipboardCheck, Factory, Images, ListOrdered, Plus, Shirt, ShoppingBag } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UrlFilters } from "@/components/erp/url-filters";
import { PickPanel } from "@/components/erp/pick-panel";
import { OrderSizes } from "@/components/merchandise/order-sizes";
import { OrderConfirmation } from "@/components/merchandise/order-confirmation";
import { ProjectionCallOffs } from "@/components/merchandise/projection-call-offs";
import { findByRef, refsFor } from "@/lib/row-ref";
import { DevStyleCatalogue } from "@/components/merchandise/dev-style-catalogue";
import { StyleRegistrations } from "@/components/merchandise/style-registrations";
import { StyleSizeRanges } from "@/components/merchandise/style-size-ranges";
import { StyleCatalogueGrid } from "@/components/merchandise/style-catalogue-grid";
import { styleLook } from "./inquiry";
import { masterScreen } from "./screens";
import * as actions from "./actions";

/*
 * Merchandise Desk → Style & Order (AnnotechERP 06/07): Order Register — the
 * order book (Records, New order, Edit order) and each order's colour × size
 * breakdown.
 */

const TYPES = ["BULK_NEW", "DEVELOPMENT", "REPEAT", "PROJECTION", "BUFFER", "SPECIAL_SAMPLE"];
const UNITS = ["PCS", "SET", "DZN"];
const STATUSES = ["DRAFT", "CONFIRMED", "IN_PRODUCTION", "SHIPPED", "CLOSED", "CANCELLED"];

const labelled = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const pickOne = (m, list) => [{ value: "", label: m("select") }, ...list];
const distinct = (rows, key) => [...new Set(rows.map((r) => r[key]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));

const orderRegister = masterScreen({
  resource: "merch-orders",
  screen: "order-register",
  noun: "order",
  nameKey: "order_no",
  refKey: "order_no",
  newLabel: "ord.new_tab",
  description: "ord.new_hint",
  formColumns: 3,
  booleans: [],
  load: async () => ({ look: await apiFetch("/merchandise/orders/lookups") }),
  fields: ({ m, look }) => [
    { name: "order_no", label: m("ord.f.order_no"), placeholder: m("ord.issued"), help: m("ord.order_no_help") },
    { name: "job_no", label: m("ord.f.job_no"), required: true, placeholder: "0036-R26-03" },
    { name: "style_id", label: m("ord.f.style"), type: "select", options: pickOne(m, look.styles.map(({ value, label }) => ({ value, label }))), help: m("ord.style_help") },
    { name: "style_no", label: m("ord.f.style_no"), required: true },
    { name: "work_style_no", label: m("ord.f.work_style_no") },
    { name: "buyer_id", label: m("ord.f.buyer"), type: "select", required: true, options: pickOne(m, look.buyers) },
    { name: "buyer_office_id", label: m("ord.f.buyer_ac"), type: "select", optionsFrom: { field: "buyer_id", map: look.buyer_offices } },
    { name: "factory_id", label: m("ord.f.company"), type: "select", options: pickOne(m, look.factories) },
    { name: "order_type", label: m("ord.f.order_type"), type: "select", default: "BULK_NEW", options: labelled(m, "ord.type", TYPES) },
    { name: "item", label: m("ord.f.item") },
    { name: "gmt_type", label: m("ord.f.gmt_type") },
    { name: "order_date", label: m("ord.f.order_date"), type: "date" },
    { name: "conf_date", label: m("ord.f.conf_date"), type: "date" },
    { name: "ship_date", label: m("ord.f.ship_date"), type: "date" },
    { name: "ship_country_id", label: m("ord.f.ship_country"), type: "select", options: pickOne(m, look.countries) },
    { name: "total_qty", label: m("ord.f.total_qty"), type: "number", required: true, step: "1", help: m("ord.qty_help") },
    { name: "unit", label: m("ord.f.unit"), type: "select", default: "PCS", options: labelled(m, "ord.unit", UNITS) },
    { name: "unit_price", label: m("ord.f.unit_price"), type: "number", step: "0.0001" },
    { name: "currency", label: m("ord.f.currency"), default: "USD", placeholder: "USD" },
    { name: "status", label: m("ord.f.status"), type: "select", default: "CONFIRMED", options: labelled(m, "ord.status", STATUSES) },
  ],
  decorate: (row, { m }) => ({
    ...row,
    total_qty: row.total_qty === null ? null : String(row.total_qty),
    unit_price: row.unit_price === null ? null : String(row.unit_price),
    style_id: row.style_id ?? "",
    buyer_office_id: row.buyer_office_id ?? "",
    factory_id: row.factory_id ?? "",
    ship_country_id: row.ship_country_id ?? "",
    garment_type: row.gmt_type || row.item,
    qty_label: row.total_qty,
    unit_label: row.unit ? m(`ord.unit.${row.unit}`) : null,
    price_label: row.total_price,
    status_label: m(`ord.status.${row.status}`),
  }),
  columns: ({ m }) => [
    { key: "order_no", label: m("ord.f.order_no"), type: "mono" },
    { key: "buyer", label: m("ord.f.buyer") },
    { key: "style_no", label: m("ord.f.style_no") },
    { key: "garment_type", label: m("ord.f.garment_type") },
    { key: "order_date", label: m("ord.f.order_date"), type: "date" },
    { key: "work_style_no", label: m("ord.f.work_style_no") },
    { key: "ship_date", label: m("ord.f.ship_date"), type: "date" },
    { key: "qty_label", label: m("ord.f.total_qty"), type: "number" },
    { key: "unit_label", label: m("ord.f.unit") },
    { key: "price_label", label: m("ord.f.total_price"), type: "number" },
    { key: "currency", label: m("ord.f.currency") },
    { key: "status_label", label: m("ord.f.status"), type: "badge" },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer", field: "buyer", label: m("ord.f.buyer_ac"), options: distinct(rows, "buyer") },
    { name: "style", field: "style_no", label: m("ord.f.style_filter"), options: distinct(rows, "style_no") },
    { name: "job", field: "work_style_no", label: m("ord.f.job_style"), options: distinct(rows, "work_style_no") },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "sizes",
      label: m("ord.tab_sizes"),
      icon: "ListChecks",
      render: async () => {
        // An order is named in the url by its number.
        const picked = rows.find((r) => r.order_no === searchParams.order) ?? null;
        const data = picked ? await apiFetch(`/merchandise/orders/${picked.id}/sizes`) : null;
        return (
          <PickPanel
            title={m("ord.orders")}
            items={rows.map((r) => ({ key: r.order_no, label: r.order_no, sub: `${r.style_no} · ${m("ord.pcs", { qty: r.total_qty })}`, count: r.size_lines }))}
            active={picked?.order_no ?? null}
            hrefBase="/merchandise-desk/order-register?tab=sizes&order="
            searchPlaceholder={m("ord.search")}
            noMatch={m("ord.no_match")}
            panelTitle={picked ? m("ord.sizes_of", { order: picked.order_no }) : m("ord.sizes")}
            panelHint={m("ord.sizes_hint")}
            emptyTitle={m("ord.select_order")}
            emptyText={m("ord.select_order_hint")}
          >
            {data ? <OrderSizes key={picked.id} orderId={picked.id} data={data} save={actions.saveOrderSizes} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

/**
 * Order Confirmation: one screen, no tabs — the quick confirmation of a
 * buyer's approved styles beside what they carry, and the confirmed orders.
 */
async function orderConfirmation({ canManage }) {
  const [look, orders] = await Promise.all([apiFetch("/merchandise/orders/lookups"), apiFetch("/erp/merch-orders")]);
  return {
    tabs: [],
    body: () => (
      <OrderConfirmation
        buyers={look.buyers}
        currencies={["USD", "EUR", "GBP", "CAD", "BDT"]}
        orders={orders}
        loadStyles={actions.loadOrderConfirmationStyles}
        confirm={actions.confirmOrders}
        canManage={canManage}
      />
    ),
  };
}

const STYLE_STATUSES = ["DRAFT", "DEVELOPMENT", "STYLE_APPROVED", "RUNNING", "CLOSED", "ON_HOLD", "CANCELLED"];
const CATALOGUE = "/merchandise-desk/development-style-gallery";

/** Brickland's coloured quick-action tile on the dashboard. */
function ActionTile({ href, icon: Icon, title, sub, tone }) {
  return (
    <Link href={href} className={`flex items-center gap-4 rounded-lg px-5 py-4 text-white shadow-sm transition-opacity hover:opacity-90 ${tone}`}>
      <span className="grid size-11 place-items-center rounded-md bg-white/20">
        <Icon className="size-5" />
      </span>
      <span>
        <span className="block text-base font-semibold">{title}</span>
        <span className="block text-[13px] opacity-90">{sub}</span>
      </span>
    </Link>
  );
}

/** A count card: icon, label, the number and what it counts. */
function CountCard({ icon: Icon, label, value, sub, tone }) {
  return (
    <div className="rounded-lg border border-border bg-surface p-4">
      <div className="flex items-center gap-3">
        <span className={`grid size-9 place-items-center rounded-md ${tone}`}>
          <Icon className="size-4" />
        </span>
        <span className="text-[13px] font-semibold text-foreground">{label}</span>
      </div>
      <div className="mt-3 text-2xl font-bold text-foreground tabular-nums">{Number(value).toLocaleString("en-US")}</div>
      <div className="text-xs text-foreground-muted">{sub}</div>
    </div>
  );
}

/**
 * Style & Order Dashboard: one screen, no tabs — the counts, the four quick
 * actions, and the newest styles and orders.
 */
async function styleOrderDashboard({ m }) {
  const data = await apiFetch("/merchandise/style-order/dashboard");
  const statusLabels = Object.fromEntries(STYLE_STATUSES.map((s) => [s, m(`sty.status.${s}`)]));
  const orderStatus = Object.fromEntries(STATUSES.map((s) => [s, m(`ord.status.${s}`)]));
  const outline = (href, label) => (
    <Link href={href} className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-[13px] font-semibold text-foreground hover:bg-surface-muted">
      {label} <ArrowRight className="size-3.5" />
    </Link>
  );
  const c = data.counts;
  return {
    tabs: [],
    body: () => (
      <div className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <CountCard icon={Shirt} label={m("sod.cards.styles")} value={c.styles} sub={m("sod.cards.styles_sub")} tone="bg-info-subtle text-info" />
          <CountCard icon={BadgeCheck} label={m("sod.cards.approved")} value={c.approved} sub={m("sod.cards.approved_sub")} tone="bg-warning-subtle text-warning" />
          <CountCard icon={ShoppingBag} label={m("sod.cards.orders")} value={c.orders} sub={m("sod.cards.orders_sub")} tone="bg-success-subtle text-success" />
          <CountCard icon={Factory} label={m("sod.cards.in_production")} value={c.in_production} sub={m("sod.cards.in_production_sub")} tone="bg-brand-subtle text-brand" />
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <ActionTile href={`${CATALOGUE}?new=1`} icon={Plus} title={m("sod.actions.new_style")} sub={m("sod.actions.new_style_sub")} tone="bg-success" />
          <ActionTile href="/merchandise-desk/order-confirmation" icon={ClipboardCheck} title={m("sod.actions.confirm")} sub={m("sod.actions.confirm_sub")} tone="bg-danger" />
          <ActionTile href="/merchandise-desk/style-gallery" icon={Images} title={m("sod.actions.catalogue")} sub={m("sod.actions.catalogue_sub")} tone="bg-brand" />
          <ActionTile href="/merchandise-desk/order-register" icon={ListOrdered} title={m("sod.actions.order_list")} sub={m("sod.actions.order_list_sub")} tone="bg-info" />
        </div>
        <RecordsGrid
          exportName="recent-styles"
          rows={data.styles}
          header={{ title: m("sod.recent_styles"), subtitle: m("sod.recent_styles_sub"), action: outline(CATALOGUE, m("sod.all_styles")), searchLabel: m("sod.search"), searchPlaceholder: m("sod.search"), exportLabel: m("sod.export") }}
          columns={[
            { key: "style_no", label: m("sod.col.style_no") },
            { key: "buyer", label: m("sod.col.buyer") },
            { key: "garment_type", label: m("sod.col.garment") },
            { key: "season", label: m("sod.col.season") },
            { key: "status", label: m("sod.col.status"), type: "badge", labels: statusLabels },
            { key: "created_at", label: m("sod.col.created"), type: "date" },
          ]}
          rowLink={{ label: m("sod.open"), href: `${CATALOGUE}?style=`, refKey: "style_no" }}
        />
        <RecordsGrid
          exportName="recent-orders"
          rows={data.orders}
          header={{ title: m("sod.recent_orders"), subtitle: m("sod.recent_orders_sub"), action: outline("/merchandise-desk/order-register", m("sod.order_list")), searchLabel: m("sod.search"), searchPlaceholder: m("sod.search"), exportLabel: m("sod.export") }}
          columns={[
            { key: "order_no", label: m("sod.col.order_no"), type: "mono" },
            { key: "buyer", label: m("sod.col.buyer") },
            { key: "style_no", label: m("sod.col.style_no") },
            { key: "gmt_type", label: m("sod.col.garment") },
            { key: "total_qty", label: m("sod.col.qty"), type: "number" },
            { key: "ship_date", label: m("sod.col.shipment"), type: "date" },
            { key: "status", label: m("sod.col.status"), type: "badge", labels: orderStatus },
          ]}
        />
      </div>
    ),
  };
}

/**
 * Style Progress Board: one row per style, filtered by buyer, season and
 * nature of work in the url; the grid's own search finds a style.
 */
async function styleProgressBoard({ m, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/style-order/progress");
  const filters = [
    { name: "buyer", field: "buyer", label: m("spb.f.buyer_ac"), options: distinct(rows, "buyer") },
    { name: "season", field: "season", label: m("spb.f.season"), options: distinct(rows, "season") },
    { name: "nature", field: "nature_of_work", label: m("spb.f.nature"), options: distinct(rows, "nature_of_work") },
  ];
  const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || String(r[f.field]) === searchParams[f.name]));
  return {
    tabs: [],
    body: () => (
      <div className="flex flex-col gap-3">
        <div className="rounded-lg border border-border bg-surface p-4">
          <UrlFilters filters={filters.map((f) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
        </div>
        <RecordsGrid
          exportName="style-progress-board"
          rows={shown}
          header={{ title: m("screens.style-dashboard"), subtitle: m("spb.caption"), searchLabel: m("sod.search"), searchPlaceholder: m("sod.search"), exportLabel: m("sod.export") }}
          columns={[
            { key: "style_no", label: m("spb.col.style") },
            { key: "buyer", label: m("spb.col.buyer") },
            { key: "season", label: m("spb.col.season") },
            { key: "nature_of_work", label: m("spb.col.nature") },
            { key: "orders", label: m("spb.col.orders"), type: "number" },
            { key: "order_qty", label: m("spb.col.order_qty"), type: "number" },
            { key: "lab_dips", label: m("spb.col.lab_dips"), type: "number" },
            { key: "samples", label: m("spb.col.samples"), type: "number" },
            { key: "bookings", label: m("spb.col.bookings"), type: "number" },
            { key: "budgets", label: m("spb.col.budgets"), type: "number" },
          ]}
        />
      </div>
    ),
  };
}

/**
 * Buyer-Wise Style & Order Follow-up: each order against its shipment, by
 * buyer, shipment window, verification and open/closed (all in the url); the
 * grid's search finds an order or style.
 */
async function buyerWiseFollowUp({ m, searchParams = {} }) {
  const rows = (await apiFetch("/merchandise/style-order/follow-up")).map((r) => ({
    ...r,
    value_label: r.order_value === null ? "—" : `${r.currency} ${Number(r.order_value).toLocaleString("en-US")}`,
    verified_label: r.verified ? m("fu.yes") : m("fu.no"),
  }));
  const filters = [
    { name: "buyer", label: m("fu.f.buyer"), options: distinct(rows, "buyer") },
    { name: "ship_from", label: m("fu.f.ship_from"), type: "date" },
    { name: "ship_to", label: m("fu.f.ship_to"), type: "date" },
    { name: "verified", label: m("fu.f.verified"), options: [{ value: "yes", label: m("fu.opt.verified") }, { value: "no", label: m("fu.opt.not_verified") }] },
    { name: "order", label: m("fu.f.order"), options: [{ value: "open", label: m("fu.opt.open") }, { value: "closed", label: m("fu.opt.closed") }] },
  ];
  const q = searchParams;
  const shown = rows.filter(
    (r) =>
      (!q.buyer || r.buyer === q.buyer) &&
      (!q.ship_from || (r.ship_date && r.ship_date >= q.ship_from)) &&
      (!q.ship_to || (r.ship_date && r.ship_date <= q.ship_to)) &&
      (!q.verified || r.verified === (q.verified === "yes")) &&
      (!q.order || r.open === (q.order === "open")),
  );
  const n = (key) => ({ key, label: m(`fu.col.${key}`), type: "number" });
  return {
    tabs: [],
    body: () => (
      <div className="flex flex-col gap-3">
        <div className="rounded-lg border border-border bg-surface p-4">
          <UrlFilters filters={filters.map((f) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
        </div>
        <RecordsGrid
          exportName="buyer-wise-follow-up"
          rows={shown}
          header={{ title: m("screens.buyer-wise-follow-up"), subtitle: m("fu.caption"), searchLabel: m("sod.search"), searchPlaceholder: m("sod.search"), exportLabel: m("sod.export") }}
          columns={[
            { key: "buyer", label: m("fu.col.buyer") },
            { key: "order_style", label: m("fu.col.order_style") },
            { key: "style_desc", label: m("fu.col.style_desc") },
            n("order_qty"),
            { key: "value_label", label: m("fu.col.order_value"), align: "right" },
            { key: "ship_date", label: m("fu.col.ship"), type: "date" },
            n("cutting"),
            n("ready_input"),
            n("inputed"),
            n("sewing"),
            n("sew_undone"),
            n("iron"),
            n("poly"),
            n("gmt_fin"),
            n("shipment"),
            { key: "verified_label", label: m("fu.col.verified") },
            { key: "status", label: m("fu.col.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((s) => [s, m(`ord.status.${s}`)])) },
          ]}
        />
      </div>
    ),
  };
}

/**
 * Projection & Call-Off Order: the projections with what has been called off
 * against each (Records), and one projection's call-offs with the form that
 * draws the next (Call-Offs, the projection named in the url by its number).
 */
async function projectionCallOff({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/projections");
  const link = "/merchandise-desk/projection-call-off-order?tab=calloffs&projection=";
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "calloffs", label: m("prj.tab_call_offs"), icon: "ListChecks" },
    ],
    body: async () => {
      if (tab === "calloffs") {
        const picked = findByRef(rows, "order_no", searchParams.projection);
        const refs = refsFor(rows, "order_no");
        const [data, look] = picked ? await Promise.all([apiFetch(`/merchandise/projections/${picked.id}/call-offs`), apiFetch("/merchandise/orders/lookups")]) : [null, null];
        return (
          <PickPanel
            title={m("prj.projections")}
            items={rows.map((r) => ({ key: refs.get(r.id), label: r.order_no, sub: `${r.style_no} · ${m("prj.balance")} ${Number(r.balance).toLocaleString("en-US")}`, count: r.call_offs }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase={link}
            searchPlaceholder={m("prj.search")}
            noMatch={m("prj.no_match")}
            panelTitle={picked ? m("prj.call_offs_of", { no: picked.order_no }) : m("prj.call_offs")}
            panelHint={m("prj.call_offs_hint")}
            emptyTitle={m("prj.pick")}
            emptyText={m("prj.pick_hint")}
          >
            {data ? <ProjectionCallOffs key={picked.id} projectionId={picked.id} data={data} countries={look.countries} draw={actions.drawCallOff} canManage={canManage} /> : null}
          </PickPanel>
        );
      }
      return (
        <div className="flex flex-col gap-3">
          <p className="text-[13px] text-foreground-muted">{m("prj.how")}</p>
          <RecordsGrid
            exportName="projections"
            rows={rows}
            columns={[
              { key: "order_no", label: m("prj.col.order_no"), type: "mono" },
              { key: "buyer", label: m("prj.col.buyer") },
              { key: "style_no", label: m("prj.col.style") },
              { key: "total_qty", label: m("prj.col.projected"), type: "number" },
              { key: "called_off", label: m("prj.col.called_off"), type: "number" },
              { key: "balance", label: m("prj.col.balance"), type: "number" },
              { key: "call_offs", label: m("prj.col.call_offs"), type: "number" },
              { key: "ship_date", label: m("prj.col.ship"), type: "date" },
              { key: "status", label: m("prj.col.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((s) => [s, m(`ord.status.${s}`)])) },
            ]}
            rowLink={{ label: m("prj.open"), href: link, refKey: "order_no" }}
            emptyState={{ title: m("prj.empty_title"), text: m("prj.empty_text") }}
          />
        </div>
      );
    },
  };
}

/**
 * Development Style Catalogue (a Style & Order step, not on the menu): the
 * approval trays, the development styles and the style wizard, all on the
 * page. `?new=1` opens the wizard on a new style, `?style=<style no>` on one.
 */
async function developmentStyleCatalogue({ m, canManage, can = () => false, searchParams = {} }) {
  const [index, inbox, sent, look] = await Promise.all([
    apiFetch("/merchandise/dev-styles"),
    apiFetch("/merchandise/dev-styles/inbox"),
    apiFetch("/merchandise/dev-styles/sent"),
    apiFetch("/merchandise/styles/lookups"),
  ]);
  const start = searchParams.style ? findByRef(index.rows, "style_no", searchParams.style) : null;
  return {
    tabs: [],
    body: () => (
      <DevStyleCatalogue
        data={{ ...index, inbox_rows: inbox, sent_rows: sent }}
        look={styleLook(m, look)}
        canManage={canManage}
        canApprove={can("merchandise.style.approve")}
        startNew={searchParams.new === "1"}
        startStyle={start?.id ?? null}
        actions={{
          loadDevStyles: actions.loadDevStyles,
          loadStyle: actions.loadStyle,
          saveStyle: actions.saveStyle,
          loadDevReview: actions.loadDevReview,
          devStyleAct: actions.devStyleAct,
          loadStyleItems: actions.loadStyleItems,
          saveStyleItem: actions.saveStyleItem,
          loadStyleFabrication: actions.loadStyleFabrication,
          saveStyleFabrication: actions.saveStyleFabrication,
          loadStyleColours: actions.loadStyleColours,
          saveStyleColours: actions.saveStyleColours,
          loadStyleServices: actions.loadStyleServices,
          saveStyleServices: actions.saveStyleServices,
          loadStyleBom: actions.loadStyleBom,
          saveStyleBom: actions.saveStyleBom,
          approveStyleBom: actions.approveStyleBom,
          loadStyleFabricMapping: actions.loadStyleFabricMapping,
          saveStyleFabricMapping: actions.saveStyleFabricMapping,
          loadPackingDetails: actions.loadPackingDetails,
          savePackingDetails: actions.savePackingDetails,
          loadStylePacking: actions.loadStylePacking,
          saveStylePacking: actions.saveStylePacking,
          loadStyleAttachments: actions.loadStyleAttachments,
          detachStyleFile: actions.detachStyleFile,
        }}
      />
    ),
  };
}

/**
 * Style Registration Entry (a Style & Order step, not on the menu): the
 * registered styles (Records), the registration requests and their wizard
 * (Registration; `?open=<request no>` opens one), and the size ranges of the
 * styles being registered (Size Range).
 */
async function styleRegistration({ m, te, tab, canManage, can = () => false, searchParams = {} }) {
  const data = await apiFetch("/merchandise/style-registrations");
  const STYLE_STATUSES = ["DRAFT", "DEVELOPMENT", "STYLE_APPROVED", "RUNNING", "CLOSED", "ON_HOLD", "CANCELLED"];
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: data.registered.length },
      { key: "registration", label: m("srq.tab_registration"), icon: "ClipboardCheck" },
      { key: "sizes", label: m("srq.tab_sizes"), icon: "ListChecks" },
    ],
    body: async () => {
      if (tab === "registration") {
        return (
          <StyleRegistrations
            data={data}
            canManage={canManage}
            canApprove={can("merchandise.style.approve")}
            openNo={searchParams.open ?? null}
            actions={{
              loadRegistrations: actions.loadRegistrations,
              loadRegistrationCandidates: actions.loadRegistrationCandidates,
              raiseRegistration: actions.raiseRegistration,
              loadRegistration: actions.loadRegistration,
              saveRegistration: actions.saveRegistration,
              moveRegistration: actions.moveRegistration,
              discardRegistration: actions.discardRegistration,
            }}
          />
        );
      }
      if (tab === "sizes") {
        // The styles being registered, each once: its size sets, as on the development sheet.
        const styles = [...new Map(data.requests.map((r) => [r.style_id, { id: r.style_id, style_no: r.style_no, description: r.style_name }])).values()];
        const refs = refsFor(styles, "style_no");
        const active = findByRef(styles, "style_no", searchParams.style);
        const [lines, look] = active ? await Promise.all([apiFetch(`/merchandise/styles/${active.id}/size-ranges`), apiFetch("/merchandise/styles/lookups")]) : [[], null];
        return (
          <PickPanel
            title={m("srq.styles")}
            items={styles.map((s) => ({ key: refs.get(s.id), label: s.style_no, sub: s.description }))}
            active={active ? refs.get(active.id) : null}
            hrefBase="/merchandise-desk/style-registration?tab=sizes&style="
            searchPlaceholder={m("srq.search")}
            noMatch={m("srq.no_match")}
            panelTitle={m("srq.sizes_title")}
            panelHint={m("srq.sizes_hint")}
            emptyTitle={m("srq.pick_style")}
            emptyText={m("srq.pick_style_hint")}
          >
            {active ? (
              <StyleSizeRanges
                key={active.id}
                style={{ id: active.id, style_no: active.style_no }}
                lines={lines}
                sizes={look.sizes}
                groupLabels={styleLook(m, look).group_labels}
                add={actions.addSizeRange}
                remove={actions.removeSizeRange}
                canManage={canManage}
              />
            ) : null}
          </PickPanel>
        );
      }
      const filters = [
        { name: "buyer_ac", field: "buyer_ac", label: m("srq.filter.buyer_ac"), options: distinct(data.registered, "buyer_ac") },
        { name: "buyer", field: "buyer", label: m("srq.filter.buyer"), options: distinct(data.registered, "buyer") },
        { name: "season", field: "season", label: m("srq.filter.season"), options: distinct(data.registered, "season") },
        { name: "status", field: "status", label: m("srq.filter.status"), options: STYLE_STATUSES.map((s) => ({ value: s, label: m(`sty.status.${s}`) })) },
      ];
      const shown = data.registered.filter((r) => filters.every((f) => !searchParams[f.name] || String(r[f.field]) === searchParams[f.name]));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map((f) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: data.registered.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName="style-registration"
            rows={shown}
            header={{ title: m("screens.style-registration"), subtitle: m("srq.records_caption"), searchLabel: m("sod.search"), searchPlaceholder: m("sod.search"), exportLabel: m("sod.export") }}
            columns={[
              { key: "style_code", label: m("srq.col.style_code"), type: "mono" },
              { key: "registration_no", label: m("srq.col.registration_no") },
              { key: "style_ref", label: m("srq.col.style_ref") },
              { key: "style_desc", label: m("srq.col.style_desc") },
              { key: "buyer", label: m("srq.col.buyer") },
              { key: "buyer_ac", label: m("srq.col.buyer_ac") },
              { key: "inquiry_no", label: m("srq.col.inquiry") },
              { key: "season", label: m("srq.col.season") },
              { key: "qty_unit", label: m("srq.col.qty_unit") },
              { key: "origin", label: m("srq.col.origin") },
              { key: "orders", label: m("srq.col.orders"), type: "number" },
            ]}
            rowLink={{ label: m("srq.open"), href: "/merchandise-desk/style-registration?tab=registration&open=", refKey: "request_no" }}
            emptyText={m("srq.records_empty")}
          />
        </div>
      );
    },
  };
}

/** Style Catalogue (the dashboard's tile, not on the menu): the running styles, a card each. */
async function styleCatalogue() {
  const rows = await apiFetch("/merchandise/style-order/gallery");
  return { tabs: [], body: () => <StyleCatalogueGrid rows={rows} /> };
}

/** Brickland's Style & Order flow: the screens that show the step strip, and where each step goes. */
const STYLE_ORDER_FLOW = {
  steps: [
    { key: "dashboard", href: "/merchandise-desk/style-order-dashboard" },
    { key: "catalogue", href: "/merchandise-desk/development-style-gallery" },
    { key: "registration", href: "/merchandise-desk/style-registration" },
    { key: "confirmation", href: "/merchandise-desk/order-confirmation" },
    { key: "register", href: "/merchandise-desk/order-register" },
  ],
  screens: { "style-order-dashboard": "dashboard", "development-style-gallery": "catalogue", "style-registration": "registration", "order-confirmation": "confirmation", "order-register": "register" },
};

const ORDER_SCREENS = {
  "style-order-dashboard": styleOrderDashboard,
  "development-style-gallery": developmentStyleCatalogue,
  "style-registration": styleRegistration,
  "style-gallery": styleCatalogue,
  "style-dashboard": styleProgressBoard,
  "buyer-wise-follow-up": buyerWiseFollowUp,
  "projection-call-off-order": projectionCallOff,
  "order-confirmation": orderConfirmation,
  "order-register": orderRegister,
};

/** Each Style & Order screen is changed under the order book's permission. */
const ORDER_MANAGE = {
  "style-order-dashboard": "merchandise.order.manage",
  "development-style-gallery": "merchandise.style.manage",
  "style-registration": "merchandise.style.manage",
  "style-gallery": "merchandise.style.manage",
  "style-dashboard": "merchandise.order.manage",
  "buyer-wise-follow-up": "merchandise.order.manage",
  "projection-call-off-order": "merchandise.order.manage",
  "order-confirmation": "merchandise.order.manage",
  "order-register": "merchandise.order.manage",
};

export { ORDER_SCREENS, ORDER_MANAGE, STYLE_ORDER_FLOW };
