import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UrlFilters } from "@/components/erp/url-filters";
import { FabricBookingSheet } from "@/components/merchandise/fabric-booking-sheet";
import { YarnSpecDesk } from "@/components/merchandise/yarn-spec-desk";
import { RequisitionForm } from "@/components/merchandise/requisition-form";
import { BookingItemsPanel } from "@/components/merchandise/booking-items-panel";
import { ImportBookingDesk } from "@/components/merchandise/import-booking-desk";
import { findByRef } from "@/lib/row-ref";
import * as actions from "./actions";

/*
 * Merchandise Desk → Fabric Booking (AnnotechERP 06/11): Bulk Fabric Booking
 * Entry — Records of the bulk bookings, New Booking, and Edit (a tab, named
 * in the url by the booking's Ref#); Approved Fabric Booking List — the
 * approved bookings of every type, one view, as Brickland shows it;
 * Style-Wise Yarn Spec Confirmation — one working page, the style named in
 * the url by its style no; Additional Fabric Booking Entry — Records, Booking
 * Items (the fabric lines of any booking) and New / Edit requisition.
 */

const STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CANCELLED"];
const TYPES = ["BULK", "ADDITIONAL", "IMPORT", "BUFFER", "PROJECTION", "SAMPLE"];
// Brickland's "29 Sept 2026".
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "short",
  year: "numeric",
  timeZone: "UTC",
});
const day = (value) => (value ? dateFmt.format(new Date(`${value}T00:00:00Z`)) : "—");
const pair = (a, b) => `${a || "—"} | ${b || "—"}`;
const distinct = (rows, key) => [...new Set(rows.map((r) => r[key]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));

async function bulkBooking({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/fabric-bookings?type=BULK");
  const editing = tab === "edit" && searchParams.ref ? await apiFetch(`/merchandise/fabric-bookings/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
  const records = "/merchandise-desk/bulk-fabric-booking?tab=records";
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: rows.length,
    },
    ...(editing
      ? [
          {
            key: "edit",
            label: m("bfb.edit_tab", { no: editing.booking_no }),
            icon: "Pencil",
          },
        ]
      : canManage
        ? [{ key: "new", label: m("bfb.new_tab"), icon: "FilePlus2" }]
        : []),
  ];
  return {
    tabs,
    body: async () => {
      if (editing || (tab === "new" && canManage)) {
        const options = await apiFetch("/merchandise/fabric-bookings/options");
        return (
          <FabricBookingSheet
            key={editing?.booking_no ?? "new"}
            options={options}
            initial={editing}
            save={actions.saveFabricBooking.bind(null, "BULK")}
            submit={actions.submitFabricBooking}
            suggest={actions.suggestFabricBooking}
            canManage={canManage}
            recordsHref={records}
          />
        );
      }
      const has = (value, needle) =>
        String(value ?? "")
          .toLowerCase()
          .includes(needle.toLowerCase());
      const filters = [
        {
          name: "buyer_ac",
          field: "buyer_ac",
          label: m("bfb.f.buyer_ac"),
          options: distinct(rows, "buyer_ac"),
        },
        {
          name: "style",
          label: m("bfb.f.style_filter"),
          type: "text",
          match: (r, v) => has(r.work_style, v),
        },
        {
          name: "order",
          label: m("bfb.f.order"),
          type: "text",
          match: (r, v) => has(r.order_no, v),
        },
        {
          name: "from",
          label: m("bfb.f.date_from"),
          type: "date",
          match: (r, v) => r.booking_date >= v,
        },
        {
          name: "to",
          label: m("bfb.f.date_to"),
          type: "date",
          match: (r, v) => r.booking_date <= v,
        },
        {
          name: "status",
          field: "status",
          label: m("bfb.f.status"),
          options: STATUSES.map((v) => ({
            value: v,
            label: m(`bfb.status.${v}`),
          })),
        },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters
              filters={filters.map(({ match, ...f }) => ({
                ...f,
                allLabel: m("all"),
              }))}
              shown={m("shown", {
                shown: shown.length,
                total: rows.length,
              })}
              clearLabel={m("clear")}
            />
          </div>
          <RecordsGrid
            exportName="bulk-fabric-booking"
            rows={shown}
            rowKey="booking_no"
            header={{
              title: m("screens.bulk-fabric-booking"),
              subtitle: m("bfb.caption"),
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              {
                key: "booking_date",
                label: m("bfb.col.booking_date"),
                type: "date",
              },
              {
                key: "work_style",
                label: m("bfb.col.work_style"),
              },
              { key: "order_no", label: m("bfb.col.order") },
              {
                key: "booking_no",
                label: m("bfb.col.booking_ref"),
                type: "mono",
              },
              {
                key: "revision",
                label: m("bfb.col.rev"),
                type: "number",
              },
              { key: "buyer_ac", label: m("bfb.f.buyer_ac") },
              {
                key: "ship_date",
                label: m("bfb.col.ship_date"),
                type: "date",
              },
              {
                key: "items",
                label: m("bfb.col.items"),
                type: "number",
              },
              {
                // Per unit — kilos and yards are never added (2026-11-16).
                key: "qty_label",
                label: m("bfb.col.qty"),
                align: "right",
              },
              {
                key: "status",
                label: m("bfb.f.status"),
                type: "badge",
                labels: Object.fromEntries(STATUSES.map((v) => [v, m(`bfb.status.${v}`)])),
              },
            ]}
            edit={
              canManage
                ? {
                    href: "/merchandise-desk/bulk-fabric-booking?tab=edit&ref=",
                    refKey: "booking_no",
                  }
                : undefined
            }
            remove={canManage ? actions.deleteFabricBooking : undefined}
            deleteDescription={m("bfb.delete_hint")}
            emptyText={m("bfb.empty")}
          />
        </div>
      );
    },
  };
}

async function approvedList({ m, searchParams = {} }) {
  const rows = (await apiFetch("/merchandise/fabric-bookings/approved")).map((r) => ({
    ...r,
    approval: `${pair(r.approved_at && day(r.approved_at), day(r.rev_date))} (r${r.revision})`,
    buyer_style: pair(r.buyer_ac, r.style),
    job_order: pair(r.job_no, r.order_no),
    ref_sp: pair(r.booking_no, r.sp_no),
    ship: r.ship_date ? day(r.ship_date) : null,
    send_date: r.mail_sent_at ? day(r.mail_sent_at) : null,
  }));
  const typeLabels = Object.fromEntries(TYPES.map((v) => [v, m(`abl.type.${v}`)]));
  return {
    tabs: [],
    body: async () => {
      const filters = [
        {
          name: "type",
          field: "booking_type",
          label: m("abl.f.type"),
          options: TYPES.map((v) => ({
            value: v,
            label: typeLabels[v],
          })),
        },
        {
          name: "buyer_ac",
          field: "buyer_ac",
          label: m("abl.f.buyer_ac"),
          options: distinct(rows, "buyer_ac"),
        },
        {
          name: "style",
          field: "style",
          label: m("abl.f.style"),
          options: distinct(rows, "style"),
        },
        {
          name: "ship_from",
          label: m("abl.f.ship_from"),
          type: "date",
          match: (r, v) => Boolean(r.ship_date) && r.ship_date >= v,
        },
        {
          name: "ship_to",
          label: m("abl.f.ship_to"),
          type: "date",
          match: (r, v) => Boolean(r.ship_date) && r.ship_date <= v,
        },
        {
          name: "approve_from",
          label: m("abl.f.approve_from"),
          type: "date",
          match: (r, v) => Boolean(r.approved_at) && r.approved_at >= v,
        },
        {
          name: "approve_to",
          label: m("abl.f.approve_to"),
          type: "date",
          match: (r, v) => Boolean(r.approved_at) && r.approved_at <= v,
        },
        {
          name: "send_mail",
          label: m("abl.f.send_mail"),
          options: [
            { value: "yes", label: m("abl.yes") },
            { value: "no", label: m("abl.no") },
          ],
          match: (r, v) => r.send_mail === (v === "yes"),
        },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters
              filters={filters.map(({ match, ...f }) => ({
                ...f,
                allLabel: m("all"),
              }))}
              shown={m("shown", {
                shown: shown.length,
                total: rows.length,
              })}
              clearLabel={m("clear")}
            />
          </div>
          <RecordsGrid
            exportName="approved-fabric-booking-list"
            rows={shown}
            rowKey="booking_no"
            header={{
              title: m("screens.fabric-booking-list-approved"),
              subtitle: m("abl.caption"),
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              {
                key: "booking_type",
                label: m("abl.col.type"),
                type: "badge",
                labels: typeLabels,
              },
              { key: "approval", label: m("abl.col.approval") },
              { key: "ship", label: m("abl.col.ship_date") },
              {
                key: "buyer_style",
                label: m("abl.col.buyer_style"),
              },
              { key: "job_order", label: m("abl.col.job_order") },
              {
                key: "ref_sp",
                label: m("abl.col.ref_sp"),
                type: "mono",
              },
              {
                key: "send_mail",
                label: m("abl.col.send_mail"),
                type: "yesno",
              },
              { key: "send_date", label: m("abl.col.send_date") },
            ]}
            emptyText={m("abl.empty")}
          />
        </div>
      );
    },
  };
}

async function yarnSpec({ canManage, searchParams = {} }) {
  const options = await apiFetch("/merchandise/yarn-specs/options");
  const category = options.categories.includes(searchParams.category) ? searchParams.category : "BULK";
  const style = findByRef(options.styles, "label", searchParams.style, "value");
  const rows = style ? await apiFetch(`/merchandise/yarn-specs?style_id=${encodeURIComponent(style.value)}&category=${category}`) : [];
  return {
    tabs: [],
    body: async () => (
      <YarnSpecDesk
        key={`${style?.value ?? ""}-${category}`}
        options={options}
        initialStyleId={style?.value ?? ""}
        initialCategory={category}
        initialRows={rows}
        companyName={options.company}
        canManage={canManage}
        actions={{
          load: actions.loadYarnSpecs,
          save: actions.saveYarnSpec,
          confirm: actions.confirmYarnSpec,
          release: actions.releaseYarnSpec,
          remove: actions.deleteYarnSpec,
          search: actions.searchYarnSpecConfirmation,
        }}
      />
    ),
  };
}

/**
 * A requisition screen — Additional, and Purchase Buffer Booking, whose Records list the buyer, style and
 * reference, have no filters and can remove a buffer order: Records, Booking Items and New / Edit.
 */
function requisitionScreen(type, slug, prefix) {
  const buffer = type === "BUFFER";
  return async function screen({ m, te, tab, canManage, searchParams = {} }) {
    const rows = await apiFetch(`/merchandise/fabric-requisitions?type=${type}`);
    const editing = tab === "edit" && searchParams.ref ? await apiFetch(`/merchandise/fabric-requisitions/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
    const records = `/merchandise-desk/${slug}?tab=records`;
    const tabs = [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: rows.length,
      },
      {
        key: "items",
        label: m(`${prefix}.items_tab`),
        icon: "LayoutList",
      },
      ...(editing
        ? [
            {
              key: "edit",
              label: m(`${prefix}.edit_tab`),
              icon: "Pencil",
            },
          ]
        : canManage
          ? [
              {
                key: "new",
                label: m(`${prefix}.new_tab`),
                icon: "Plus",
              },
            ]
          : []),
    ];
    return {
      tabs,
      body: async () => {
        if (tab === "items") {
          const bookings = await apiFetch("/merchandise/fabric-booking-items");
          const chosen = searchParams.booking && bookings.some((b) => b.booking_no === searchParams.booking) ? searchParams.booking : null;
          const initial = chosen ? await apiFetch(`/merchandise/fabric-booking-items/${encodeURIComponent(chosen)}`) : null;
          return (
            <BookingItemsPanel
              key={chosen ?? "none"}
              bookings={bookings}
              initial={initial}
              canManage={canManage}
              actions={{
                load: actions.loadBookingItems,
                add: actions.addBookingItem,
                save: actions.saveBookingItems,
              }}
            />
          );
        }
        if (editing || (tab === "new" && canManage)) {
          const options = await apiFetch("/merchandise/fabric-requisitions/options");
          return <RequisitionForm key={editing?.booking_no ?? "new"} prefix={prefix} options={options} initial={editing} save={actions.saveFabricRequisition.bind(null, type)} recordsHref={records} />;
        }
        const has = (value, needle) =>
          String(value ?? "")
            .toLowerCase()
            .includes(needle.toLowerCase());
        const filters = buffer
          ? []
          : [
              {
                name: "buyer_ac",
                field: "buyer_ac",
                label: m(`${prefix}.f.buyer_ac`),
                options: distinct(rows, "buyer_ac"),
              },
              {
                name: "style",
                label: m(`${prefix}.f.style_filter`),
                type: "text",
                match: (r, v) => has(r.work_style, v),
              },
            ];
        const statusCol = {
          key: "status",
          label: m(`${prefix}.col.status`),
          type: "badge",
          labels: Object.fromEntries(STATUSES.map((v) => [v, m(`bfb.status.${v}`)])),
        };
        const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
        return (
          <div className="flex flex-col gap-3">
            <div className="rounded-lg border border-border bg-surface p-4">
              <UrlFilters
                filters={filters.map(({ match, ...f }) => ({
                  ...f,
                  allLabel: m("all"),
                }))}
                shown={m("shown", {
                  shown: shown.length,
                  total: rows.length,
                })}
                clearLabel={m("clear")}
              />
            </div>
            <RecordsGrid
              exportName={slug}
              rows={shown.map((r) => ({
                ...r,
                order_type: buffer ? m(`${prefix}.order_type`) : null,
              }))}
              rowKey="booking_no"
              header={{
                title: m(`screens.${slug}`),
                subtitle: m(`${prefix}.caption`),
                searchLabel: m("ldp.search"),
                searchPlaceholder: m("ldp.search"),
                exportLabel: m("ldp.export"),
              }}
              columns={
                buffer
                  ? [
                      {
                        key: "buyer",
                        label: m(`${prefix}.col.buyer`),
                      },
                      {
                        key: "style",
                        label: m(`${prefix}.col.style`),
                      },
                      {
                        key: "order_no",
                        label: m(`${prefix}.col.order`),
                      },
                      {
                        key: "order_type",
                        label: m(`${prefix}.col.order_type`),
                      },
                      {
                        key: "booking_no",
                        label: m(`${prefix}.col.no`),
                        type: "mono",
                      },
                      {
                        key: "booking_date",
                        label: m(`${prefix}.col.date`),
                        type: "date",
                      },
                      statusCol,
                    ]
                  : [
                      {
                        key: "booking_date",
                        label: m(`${prefix}.col.date`),
                        type: "date",
                      },
                      {
                        key: "booking_no",
                        label: m(`${prefix}.col.no`),
                        type: "mono",
                      },
                      {
                        key: "revision",
                        label: m(`${prefix}.col.rev`),
                        type: "number",
                      },
                      {
                        key: "ref_no",
                        label: m(`${prefix}.col.ref`),
                      },
                      {
                        key: "buyer_ac",
                        label: m(`${prefix}.f.buyer_ac`),
                      },
                      {
                        key: "work_style",
                        label: m(`${prefix}.col.work_style`),
                      },
                      {
                        key: "order_no",
                        label: m(`${prefix}.col.order`),
                      },
                      {
                        key: "active",
                        label: m(`${prefix}.col.active`),
                        type: "yesno",
                      },
                      statusCol,
                    ]
              }
              edit={
                canManage
                  ? {
                      href: `/merchandise-desk/${slug}?tab=edit&ref=`,
                      refKey: "booking_no",
                    }
                  : undefined
              }
              remove={canManage && buffer ? actions.deleteFabricRequisition : undefined}
              deleteDescription={buffer ? m(`${prefix}.delete_hint`) : undefined}
              emptyText={m(`${prefix}.empty`)}
            />
          </div>
        );
      },
    };
  };
}

const ORDER_TYPES = ["BULK_NEW", "DEVELOPMENT", "REPEAT", "PROJECTION", "BUFFER", "SPECIAL_SAMPLE"];

/** Imported Fabric Booking: Records — the confirmed orders — and Bookings, the import bookings of the order picked. */
async function importBooking({ m, te, tab, canManage, searchParams = {} }) {
  const orders = await apiFetch("/merchandise/import-bookings/orders");
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: orders.length },
      { key: "bookings", label: m("ifb.bookings_tab"), icon: "LayoutList" },
    ],
    body: async () => {
      if (tab === "bookings") {
        const order = orders.find((o) => o.order_no === searchParams.order) ?? null;
        const rows = order ? await apiFetch(`/merchandise/import-bookings?order_id=${encodeURIComponent(order.id)}`) : [];
        return (
          <ImportBookingDesk
            key={order?.id ?? "none"}
            orders={orders}
            initialOrderId={order?.id ?? ""}
            initialRows={rows}
            canManage={canManage}
            actions={{ load: actions.loadImportBookings, raise: actions.raiseImportBooking, approve: actions.approveImportBooking, remove: actions.removeImportBooking }}
          />
        );
      }
      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const typeLabels = Object.fromEntries(ORDER_TYPES.map((v) => [v, m(`ifb.order_type.${v}`)]));
      const filters = [
        { name: "buyer", field: "buyer", label: m("ifb.f.buyer"), options: distinct(orders, "buyer") },
        { name: "style", label: m("ifb.f.style_order"), type: "text", match: (r, v) => has(r.style, v) || has(r.order_no, v) },
        { name: "order", label: m("ifb.f.order"), type: "text", match: (r, v) => has(r.order_no, v) },
        { name: "type", field: "order_type", label: m("ifb.f.order_type"), options: ORDER_TYPES.map((v) => ({ value: v, label: typeLabels[v] })) },
        { name: "ship_from", label: m("ifb.f.ship_from"), type: "date", match: (r, v) => Boolean(r.ship_date) && r.ship_date >= v },
        { name: "ship_to", label: m("ifb.f.ship_to"), type: "date", match: (r, v) => Boolean(r.ship_date) && r.ship_date <= v },
      ];
      const shown = orders.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: orders.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName="imported-fabric-booking"
            rows={shown}
            rowKey="order_no"
            header={{ title: m("screens.import-fabric-booking-purchase"), subtitle: m("ifb.caption"), searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") }}
            columns={[
              { key: "buyer", label: m("ifb.col.buyer") },
              { key: "style_desc", label: m("ifb.col.style_desc") },
              { key: "style", label: m("ifb.col.style") },
              { key: "order_no", label: m("ifb.col.order"), type: "mono" },
              { key: "order_type", label: m("ifb.col.order_type"), type: "badge", labels: typeLabels },
              { key: "order_qty", label: m("ifb.col.order_qty"), type: "number" },
              { key: "conf_date", label: m("ifb.col.conf_date"), type: "date" },
              { key: "ship_date", label: m("ifb.col.ship_date"), type: "date" },
              { key: "lead_time", label: m("ifb.col.lead_time"), type: "number" },
            ]}
            emptyText={m("ifb.no_orders")}
          />
        </div>
      );
    },
  };
}

const BOOKING_SCREENS = {
  "bulk-fabric-booking": bulkBooking,
  "fabric-booking-list-approved": approvedList,
  "style-wise-yarn-spec-confirm": yarnSpec,
  "additional-fabric-booking": requisitionScreen("ADDITIONAL", "additional-fabric-booking", "afb"),
  "buffer-booking-purchase": requisitionScreen("BUFFER", "buffer-booking-purchase", "pbb"),
  "import-fabric-booking-purchase": importBooking,
};

/** Each Fabric Booking screen is changed under the booking permission. */
const BOOKING_MANAGE = {
  "bulk-fabric-booking": "merchandise.booking.manage",
  "fabric-booking-list-approved": "merchandise.booking.manage",
  "style-wise-yarn-spec-confirm": "merchandise.booking.manage",
  "additional-fabric-booking": "merchandise.booking.manage",
  "buffer-booking-purchase": "merchandise.booking.manage",
  "import-fabric-booking-purchase": "merchandise.booking.manage",
};

export { BOOKING_SCREENS, BOOKING_MANAGE };
