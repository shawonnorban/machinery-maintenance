import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UrlFilters } from "@/components/erp/url-filters";
import { buttonVariants } from "@/components/ui/button";
import { AccBookingOrders } from "@/components/merchandise/acc-booking-orders";
import { AccBookedTrims } from "@/components/merchandise/acc-booked-trims";
import { TrimSourcingDetails, TrimSourcingForm } from "@/components/merchandise/trim-sourcing";
import { TrimDyeingForm, TrimDyeingItems } from "@/components/merchandise/trim-dyeing";
import * as actions from "./actions";

/*
 * Merchandise Desk → OPERATION → Accessories & Trims (AnnotechERP 06/08), in
 * Brickland's menu order: Accessories Booking Lookup (Records, Booked Trims),
 * Trims & Accessories Sourcing (Records, Details, New sourcing line), Trims
 * Dyeing Programme (Records, Program Items, New dyeing program), Trims
 * Accessories Booking and the Accessories Booking Register (the confirmed
 * orders; Bookings), and the Accessories Purchase Register (one view).
 * Panels switch without a reload; urls carry booking, order and program
 * numbers, never ids.
 */

const distinct = (rows, key) => [...new Set(rows.map((r) => r[key]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
const shownOf = (rows, filters, searchParams) => rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
const filterBar = (m, filters, shown, total) => (
  <div className="rounded-lg border border-border bg-surface p-4">
    <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown, total })} clearLabel={m("clear")} />
  </div>
);
const header = (m, slug, caption, action = null) => ({ title: m(`screens.${slug}`), subtitle: caption, action, searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") });
const orderTypes = (m, rows, key = "order_type") => [...new Set(rows.map((r) => r[key]).filter(Boolean))].map((v) => ({ value: v, label: m(`ord.type.${v}`) }));
const days = (m, v) => (v === null || v === undefined ? "—" : m("acb.days", { n: v }));

async function bookingLookup({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/acc-bookings");
  const slug = "accessories-booking-view";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "trims", label: m("acb.booked_tab"), icon: "ListChecks" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "trims") {
        const options = await apiFetch("/merchandise/acc-bookings/options");
        const initial = searchParams.booking ? await apiFetch(`/merchandise/acc-bookings/${encodeURIComponent(searchParams.booking)}`).catch(() => null) : null;
        return <AccBookedTrims bookings={rows} options={options} initial={initial} canManage={canManage} actions={{ load: actions.loadAccBooking, add: actions.addAccBookingLine, save: actions.saveAccBookingLines }} />;
      }
      const filters = [
        { name: "buyer", field: "buyer", label: m("acb.f.buyer"), options: distinct(rows, "buyer") },
        { name: "style", label: m("acb.f.style_order"), type: "text", match: (r, v) => has(`${r.style_no} ${r.order_no} ${r.style_desc}`, v) },
        { name: "from", label: m("acb.f.ship_from"), type: "date", match: (r, v) => (r.ship_date ?? "") >= v },
        { name: "to", label: m("acb.f.ship_to"), type: "date", match: (r, v) => (r.ship_date ?? "") <= v },
        { name: "po", label: m("acb.f.po_no"), type: "text", match: (r, v) => has(r.po_no, v) },
        { name: "status", field: "status", label: m("acb.f.status"), options: ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CANCELLED"].map((v) => ({ value: v, label: m(`acb.status.${v}`) })) },
      ];
      const shown = shownOf(rows, filters, searchParams);
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, filters, shown.length, rows.length)}
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, type_label: r.order_type ? m(`ord.type.${r.order_type}`) : null }))}
            rowKey="booking_no"
            header={header(m, slug, m("acb.lookup_caption"))}
            columns={[
              { key: "booking_no", label: m("acb.col.booking_no"), type: "mono" },
              { key: "buyer", label: m("acb.col.buyer") },
              { key: "style_desc", label: m("acb.col.style_desc") },
              { key: "style_no", label: m("acb.col.style_no") },
              { key: "order_no", label: m("acb.col.order_no"), type: "mono" },
              { key: "type_label", label: m("acb.col.order_type") },
              { key: "order_qty", label: m("acb.col.order_qty"), type: "number" },
              { key: "po_no", label: m("acb.col.po_no") },
              { key: "subject", label: m("acb.col.subject") },
              { key: "po_date", label: m("acb.col.po_date"), type: "date" },
              { key: "delivery_date", label: m("acb.col.delivery_date"), type: "date" },
              { key: "supplier", label: m("acb.col.supplier") },
              { key: "items", label: m("acb.col.items"), type: "number" },
              { key: "status", label: m("acb.col.status"), type: "badge", labels: Object.fromEntries(["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CANCELLED"].map((v) => [v, m(`acb.status.${v}`)])) },
            ]}
            emptyText={m("acb.no_match")}
          />
        </div>
      );
    },
  };
}

async function trimSourcing({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/trim-sourcings");
  const slug = "sourcing-of-trims-accessories";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "details", label: m("tsr.details_tab"), icon: "ListChecks" },
    { key: "new", label: m("tsr.new_tab"), icon: "Plus" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "details" || tab === "new") {
        const options = await apiFetch("/merchandise/trim-sourcings/options");
        if (tab === "new") return <TrimSourcingForm options={options} canManage={canManage} save={actions.createTrimSourcing} />;
        const orders = await apiFetch("/merchandise/trim-sourcings/orders");
        const lines = searchParams.order ? await apiFetch(`/merchandise/trim-sourcings/orders/${encodeURIComponent(searchParams.order)}`).catch(() => null) : null;
        return (
          <TrimSourcingDetails
            orders={orders}
            options={options}
            initial={lines ? { order: searchParams.order, lines } : null}
            canManage={canManage}
            actions={{ forOrder: actions.trimSourcingForOrder, update: actions.updateTrimSourcing, remove: actions.deleteTrimSourcing }}
          />
        );
      }
      const filters = [
        { name: "buyer", field: "buyer", label: m("tsr.f.buyer"), options: distinct(rows, "buyer") },
        { name: "style", label: m("tsr.f.work_style"), type: "text", match: (r, v) => has(`${r.work_style} ${r.style_no}`, v) },
        { name: "order", label: m("tsr.f.order_no"), type: "text", match: (r, v) => has(r.order_no, v) },
        { name: "type", field: "order_type", label: m("tsr.f.order_type"), options: orderTypes(m, rows) },
        { name: "from", label: m("tsr.f.shipment_from"), type: "date", match: (r, v) => (r.ship_date ?? "") >= v },
        { name: "to", label: m("tsr.f.shipment_to"), type: "date", match: (r, v) => (r.ship_date ?? "") <= v },
        { name: "supplier", field: "supplier", label: m("tsr.f.supplier"), options: distinct(rows, "supplier") },
      ];
      const shown = shownOf(rows, filters, searchParams);
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, filters, shown.length, rows.length)}
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, type_label: r.order_type ? m(`ord.type.${r.order_type}`) : null, lead_label: days(m, r.lead_time) }))}
            rowKey="id"
            header={header(m, slug, m("tsr.caption"))}
            columns={[
              { key: "buyer", label: m("tsr.col.buyer") },
              { key: "style_desc", label: m("tsr.col.style_desc") },
              { key: "style_no", label: m("tsr.col.style_no") },
              { key: "order_no", label: m("tsr.col.order_no"), type: "mono" },
              { key: "type_label", label: m("tsr.col.order_type") },
              { key: "order_qty", label: m("tsr.col.order_qty"), type: "number" },
              { key: "conf_date", label: m("tsr.col.conf_date"), type: "date" },
              { key: "ship_date", label: m("tsr.col.ship_date"), type: "date" },
              { key: "lead_label", label: m("tsr.col.lead_time") },
              { key: "trim", label: m("tsr.col.trim") },
              { key: "supplier", label: m("tsr.col.supplier") },
              { key: "nominated", label: m("tsr.col.nominated"), type: "yesno" },
            ]}
            rowLink={{ label: m("tsr.edit"), href: `/merchandise-desk/${slug}?tab=details&order=`, refKey: "order_no" }}
            emptyText={m("tsr.none", { order: "" })}
          />
        </div>
      );
    },
  };
}

async function trimDyeing({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/trim-dyeing");
  const slug = "dyeing-program-for-trims";
  const editing = tab === "new" && searchParams.ref;
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "items", label: m("tdp.items_tab"), icon: "ListChecks" },
    { key: "new", label: editing ? m("tdp.edit_tab") : m("tdp.new_tab"), icon: "Plus" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "items" || tab === "new") {
        const options = await apiFetch("/merchandise/trim-dyeing/options");
        if (tab === "new") {
          const initial = editing ? await apiFetch(`/merchandise/trim-dyeing/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
          return <TrimDyeingForm key={initial?.program_no ?? "new"} options={options} initial={initial} canManage={canManage} save={actions.saveTrimDyeing} />;
        }
        const initial = searchParams.program ? await apiFetch(`/merchandise/trim-dyeing/${encodeURIComponent(searchParams.program)}`).catch(() => null) : null;
        return <TrimDyeingItems programs={rows} options={options} initial={initial} canManage={canManage} actions={{ load: actions.loadTrimDyeing, add: actions.addTrimDyeingItem, remove: actions.removeTrimDyeingItem }} />;
      }
      const statuses = ["DRAFT", "SUBMITTED", "IN_PROGRESS", "SENT", "APPROVED", "REJECTED", "CANCELLED"];
      const filters = [
        { name: "type", field: "prod_type", label: m("tdp.f.prod_type"), options: ["BULK", "SAMPLE", "DEVELOPMENT"].map((v) => ({ value: v, label: m(`tdp.prod_types.${v}`) })) },
        { name: "buyer", field: "buyer", label: m("tdp.f.buyer_id"), options: distinct(rows, "buyer") },
        { name: "order", label: m("tdp.f.order_id"), type: "text", match: (r, v) => has(r.order, v) },
        { name: "from", label: m("tdp.f.from"), type: "date", match: (r, v) => r.program_date >= v },
        { name: "to", label: m("tdp.f.to"), type: "date", match: (r, v) => r.program_date <= v },
        { name: "status", field: "status", label: m("tdp.f.status"), options: statuses.map((v) => ({ value: v, label: m(`tdp.status.${v}`) })) },
      ];
      const shown = shownOf(rows, filters, searchParams);
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, filters, shown.length, rows.length)}
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, type_label: m(`tdp.prod_types.${r.prod_type}`) }))}
            rowKey="program_no"
            header={header(
              m,
              slug,
              m("tdp.caption"),
              canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=new`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("tdp.new_tab")}
                </Link>
              ) : null,
            )}
            columns={[
              { key: "program_no", label: m("tdp.col.program_no"), type: "mono" },
              { key: "type_label", label: m("tdp.col.prod_type") },
              { key: "buyer", label: m("tdp.col.buyer") },
              { key: "order", label: m("tdp.col.order") },
              { key: "program_date", label: m("tdp.col.program_date"), type: "date" },
              { key: "items", label: m("tdp.col.items"), type: "number" },
              { key: "remarks", label: m("tdp.col.remarks") },
              { key: "status", label: m("tdp.col.status"), type: "badge", labels: Object.fromEntries(statuses.map((v) => [v, m(`tdp.status.${v}`)])) },
            ]}
            edit={canManage ? { href: `/merchandise-desk/${slug}?tab=new&ref=`, refKey: "program_no" } : undefined}
            emptyText={m("tdp.none", { no: "" })}
          />
        </div>
      );
    },
  };
}

/** Trims Accessories Booking and the Booking Register: the confirmed orders, then their bookings. */
const bookingOrders =
  (slug, register) =>
  async ({ m, te, tab, canManage, searchParams = {} }) => {
    const rows = await apiFetch("/merchandise/acc-bookings/orders");
    const tabs = [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "bookings", label: m("acb.bookings_tab"), icon: "BookMarked" },
    ];
    return {
      tabs,
      body: async () => {
        if (tab === "bookings") {
          const options = await apiFetch("/merchandise/acc-bookings/options");
          const bookings = searchParams.order ? await apiFetch(`/merchandise/acc-bookings/orders/${encodeURIComponent(searchParams.order)}`).catch(() => null) : null;
          return <AccBookingOrders orders={rows} options={options} initial={bookings ? { order: searchParams.order, bookings } : null} canManage={canManage} actions={{ forOrder: actions.accBookingsForOrder, book: actions.bookAccessories }} />;
        }
        const filters = register
          ? [
              { name: "buyer", field: "buyer", label: m("acb.f.buyer"), options: distinct(rows, "buyer") },
              { name: "style", label: m("acb.f.style_order"), type: "text", match: (r, v) => has(`${r.work_style} ${r.style_no} ${r.style_desc}`, v) },
              { name: "order", label: m("acb.f.order_no"), type: "text", match: (r, v) => has(r.order_no, v) },
              { name: "type", field: "order_type", label: m("acb.f.order_type"), options: orderTypes(m, rows) },
              { name: "from", label: m("acb.f.shipment_from"), type: "date", match: (r, v) => (r.ship_date ?? "") >= v },
              { name: "to", label: m("acb.f.shipment_to"), type: "date", match: (r, v) => (r.ship_date ?? "") <= v },
            ]
          : [
              { name: "type", field: "order_type", label: m("acb.f.type"), options: orderTypes(m, rows) },
              { name: "buyer", field: "buyer", label: m("acb.f.buyer"), options: distinct(rows, "buyer") },
              { name: "style", label: m("acb.f.work_style"), type: "text", match: (r, v) => has(`${r.work_style} ${r.style_no}`, v) },
              { name: "order", label: m("acb.f.order_no"), type: "text", match: (r, v) => has(r.order_no, v) },
            ];
        const shown = shownOf(rows, filters, searchParams);
        const columns = [
          { key: "buyer", label: m("acb.col.buyer") },
          { key: "style_desc", label: m("acb.col.style_desc") },
          { key: "style_no", label: m("acb.col.style_no") },
          { key: "order_no", label: m("acb.col.order_no"), type: "mono" },
          { key: "type_label", label: m("acb.col.order_type") },
          { key: "qty", label: m("acb.col.order_qty"), type: "number" },
          ...(register ? [{ key: "conf_date", label: m("acb.col.conf_date"), type: "date" }] : []),
          { key: "ship_date", label: m("acb.col.ship_date"), type: "date" },
          ...(register ? [{ key: "lead_label", label: m("acb.col.lead_time") }] : []),
        ];
        return (
          <div className="flex flex-col gap-3">
            {filterBar(m, filters, shown.length, rows.length)}
            <RecordsGrid
              exportName={slug}
              rows={shown.map((r) => ({ ...r, type_label: r.order_type ? m(`ord.type.${r.order_type}`) : null, lead_label: days(m, r.lead_time) }))}
              rowKey="order_no"
              header={header(m, slug, m("acb.orders_caption"))}
              columns={columns}
              emptyText={m("acb.no_match")}
            />
          </div>
        );
      },
    };
  };

async function purchaseRegister({ m, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/acc-bookings/purchase-register");
  const slug = "accessories-purchase-list";
  return {
    tabs: [],
    body: async () => {
      const filters = [
        { name: "month", field: "req_month", label: m("acb.f.req_month"), options: distinct(rows, "req_month") },
        { name: "buyer", field: "buyer", label: m("acb.f.buyer"), options: distinct(rows, "buyer") },
        { name: "job", label: m("acb.f.job_style"), type: "text", match: (r, v) => has(`${r.job_no} ${r.work_style}`, v) },
        { name: "order", label: m("acb.f.order"), type: "text", match: (r, v) => has(r.order_no, v) },
      ];
      const shown = shownOf(rows, filters, searchParams);
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, filters, shown.length, rows.length)}
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, status_label: m(`apr.status.${r.status}`) }))}
            rowKey="req_no"
            header={header(m, slug, m("acb.purchase_caption"))}
            columns={[
              { key: "req_no", label: m("acb.col.req_no"), type: "mono" },
              { key: "req_date", label: m("acb.col.req_date"), type: "date" },
              { key: "ship_date", label: m("acb.col.req_ship_date"), type: "date" },
              { key: "buyer", label: m("acb.col.req_buyer") },
              { key: "job_no", label: m("acb.col.job_no") },
              { key: "order_no", label: m("acb.col.order"), type: "mono" },
              { key: "items", label: m("acb.col.item_list") },
              { key: "status_label", label: m("acb.col.status") },
            ]}
            emptyText={m("acb.no_match")}
          />
        </div>
      );
    },
  };
}

const ACC_TRIMS_SCREENS = {
  "accessories-booking-view": bookingLookup,
  "sourcing-of-trims-accessories": trimSourcing,
  "dyeing-program-for-trims": trimDyeing,
  "accessories-booking-trims": bookingOrders("accessories-booking-trims", false),
  "accessories-booking-list": bookingOrders("accessories-booking-list", true),
  "accessories-purchase-list": purchaseRegister,
};

const ACC_TRIMS_MANAGE = Object.fromEntries(Object.keys(ACC_TRIMS_SCREENS).map((slug) => [slug, "merchandise.accessories.manage"]));

export { ACC_TRIMS_MANAGE, ACC_TRIMS_SCREENS };
