import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UrlFilters } from "@/components/erp/url-filters";
import { buttonVariants } from "@/components/ui/button";
import { AccRequisitionEntry } from "@/components/merchandise/acc-requisition-entry";
import { AccPoEntry } from "@/components/merchandise/acc-po-entry";
import { AccPoRequisitions } from "@/components/merchandise/acc-po-requisitions";
import { AccReceiptEntry } from "@/components/merchandise/acc-receipt-entry";
import { AccReceivables } from "@/components/merchandise/acc-receivables";
import { AccReturnEntry } from "@/components/merchandise/acc-return-entry";
import { FabricObEntry } from "@/components/merchandise/fabric-ob-entry";
import { FabricObHistory } from "@/components/merchandise/fabric-ob-history";
import { OrderBomViewer } from "@/components/merchandise/order-bom-viewer";
import { PfabIndentEntry } from "@/components/merchandise/pfab-indent-entry";
import { PfabPoEntry } from "@/components/merchandise/pfab-po-entry";
import { PfabMrrEntry } from "@/components/merchandise/pfab-mrr-entry";
import { FfabObEntry } from "@/components/merchandise/ffab-ob-entry";
import { StockClosingExceptions, StockMonthClose } from "@/components/merchandise/stock-closing";
import { findByRef } from "@/lib/row-ref";
import { masterScreen } from "./screens";
import * as actions from "./actions";

/*
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): Accessories
 * Purchase Indent — Records of the requisitions; Accessories Booking BOM for
 * PR, the open orders with their approved BOM, each with Add PR;
 * and Requisition Entry, the order list beside the requisition (named in the
 * url by its PRQ no, or a new one by the order no). Accessories Purchase
 * Order — Records, Purchase Order Entry (named by its PO no, or filled from a
 * requisition by its PRQ no) and the Requisition List. Accessories Receipt
 * Report — Records, Receipt Entry (named by its MRR no, or filled from a
 * purchase order by its PO no) and the Receive List. Procurement Cost Head —
 * the screen kit's Records, New and Edit; and Procurement Posting Setup, the
 * same, one per company. Accessories Supplier Return Challan — Records and the
 * entry with its MRR List (named in the url by its RTN no). Purchased Fabric
 * Opening Balance — Records, Opening Balance Entry (by its OB no) and the
 * Approval History. Dummy Order Accessories Indent — the orders (any, not
 * cancelled) with View BOM, the entry, the PR List and the Order BOM.
 * Purchased Fabric Indent — Records and the Fabric Import Requisition Entry.
 * Purchased Fabric Purchase Order — Records, the entry (by PO no, or filled
 * from an indent by its PRQ no) and the Requisition List. Purchased Fabric
 * Receipt Report — Records and the Fabric MRR with its Receive List. Fabric Supplier Return
 * Challan — the accessories challan's screen on fabric MRRs. Finished Fabric
 * Opening Balance — Records (Remove only, as Brickland) and the entry. The
 * six closings — Records, Show Exception and Month Close.
 */

const STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CLOSED"];
const distinct = (rows, key) => [...new Set(rows.map((r) => r[key]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
const qty = new Intl.NumberFormat("en-US", { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

async function accessoriesPr({ m, te, tab, canManage, can, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/acc-requisitions");
  const slug = "accessories-pr";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "bom", label: m("apr.bom_tab"), icon: "LayoutList" },
    { key: "entry", label: m("apr.entry_tab"), icon: "FilePlus2" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/acc-requisitions/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/acc-requisitions/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        const order = !initial && searchParams.order ? findByRef(options.orders, "order_no", searchParams.order, "value") : null;
        const prefill = order ? { order_id: order.value, buyer_office_id: order.buyer_office_id ?? "", ...(await apiFetch(`/merchandise/acc-requisitions/bom?order_id=${encodeURIComponent(order.value)}`)) } : null;
        return (
          <AccRequisitionEntry
            key={initial?.req_no ?? order?.value ?? "new"}
            options={options}
            initial={initial}
            prefill={prefill}
            canManage={canManage}
            canApprove={can("merchandise.accessories.approve")}
            autoPrint={searchParams.print === "1"}
            actions={{ bom: actions.accRequisitionBom, load: actions.loadAccRequisition, save: actions.saveAccRequisition, transition: actions.transitionAccRequisition, remove: actions.deleteAccRequisition }}
            recordsHref={`/merchandise-desk/${slug}?tab=records`}
            poHref="/merchandise-desk/accessories-po?tab=entry&pr="
          />
        );
      }

      if (tab === "bom") {
        const options = await apiFetch("/merchandise/acc-requisitions/options");
        const types = [...new Set(options.orders.map((o) => o.order_type).filter(Boolean))].sort();
        const orders = options.orders.filter((o) => (!searchParams.type || o.order_type === searchParams.type) && (!searchParams.buyer || o.buyer_office_id === searchParams.buyer) && (!searchParams.order || o.order_no === searchParams.order));
        return (
          <div className="flex flex-col gap-3">
            <div className="rounded-lg border border-border bg-surface p-4">
              <UrlFilters
                filters={[
                  { name: "type", label: m("apr.f.type"), options: types.map((v) => ({ value: v, label: m(`ord.type.${v}`) })), allLabel: m("all") },
                  { name: "buyer", label: m("apr.f.buyer_ac"), options: options.offices, allLabel: m("all") },
                  { name: "order", label: m("apr.f.order"), options: options.orders.map((o) => ({ value: o.order_no, label: o.order_no })), allLabel: m("all") },
                  { name: "req_type", label: m("apr.f.req_type"), options: [{ value: "ACC_PURCHASE", label: m("apr.req_type") }], allLabel: m("all") },
                ]}
                shown={m("shown", { shown: orders.length, total: options.orders.length })}
                clearLabel={m("clear")}
              />
            </div>
            <RecordsGrid
              exportName={`${slug}-bom`}
              rows={orders.map((o) => ({ ...o, order_type_label: o.order_type ? m(`ord.type.${o.order_type}`) : null, qty_label: `${qty.format(o.qty).replace(/\.000$/, "")} ${o.unit ?? ""}`.trim() }))}
              rowKey="value"
              header={{
                title: m("apr.bom_tab"),
                subtitle: m("apr.bom_hint"),
                action: (
                  <Link href={`/merchandise-desk/${slug}?tab=records`} className={buttonVariants({ variant: "outline", size: "sm" })}>
                    {m("apr.pr_list")}
                  </Link>
                ),
                searchLabel: m("ldp.search"),
                searchPlaceholder: m("ldp.search"),
                exportLabel: m("ldp.export"),
              }}
              columns={[
                { key: "buyer", label: m("apr.col.buyer") },
                { key: "style_desc", label: m("apr.col.style_desc") },
                { key: "job", label: m("apr.col.job_hash") },
                { key: "order_no", label: m("apr.col.order_hash") },
                { key: "order_type_label", label: m("apr.col.order_type") },
                { key: "qty_label", label: m("apr.col.order_qty") },
                { key: "ship_date", label: m("apr.col.ship_date"), type: "date" },
                { key: "bom", label: "BOM" },
              ]}
              rowLink={canManage ? { label: m("apr.add_pr"), href: `/merchandise-desk/${slug}?tab=entry&order=`, refKey: "order_no" } : undefined}
              emptyText={m("apr.no_orders")}
            />
          </div>
        );
      }

      const filters = [
        { name: "month", field: "req_month", label: m("apr.f.month"), options: distinct(rows, "req_month") },
        { name: "buyer_ac", field: "buyer_ac", label: m("apr.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
        { name: "order", field: "order_no", label: m("apr.f.order"), options: distinct(rows, "order_no") },
        { name: "supplier", field: "supplier", label: m("apr.f.supplier"), options: distinct(rows, "supplier") },
        { name: "status", field: "status", label: m("apr.f.status"), options: STATUSES.map((v) => ({ value: v, label: m(`apr.status.${v}`) })) },
        { name: "from", label: m("apr.f.from"), type: "date", match: (r, v) => r.req_date >= v },
        { name: "to", label: m("apr.f.to"), type: "date", match: (r, v) => r.req_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown}
            rowKey="req_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("apr.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=bom`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("apr.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "req_no", label: m("apr.col.req_no"), type: "mono" },
              { key: "req_date", label: m("apr.col.req_date"), type: "date" },
              { key: "addition_for", label: m("apr.col.addition_for") },
              { key: "buyer_ac", label: m("apr.col.buyer_grp") },
              { key: "job_no", label: m("apr.col.job") },
              { key: "order_no", label: m("apr.col.order") },
              { key: "supplier", label: m("apr.col.supplier_name") },
              { key: "items", label: m("apr.col.item_list") },
              { key: "status", label: m("apr.col.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((v) => [v, m(`apr.status.${v}`)])) },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "req_no" }}
            rowLink={{ label: m("apr.print"), href: `/merchandise-desk/${slug}?tab=entry&print=1&ref=`, refKey: "req_no" }}
            remove={canManage ? actions.deleteAccRequisition : undefined}
            deleteDescription={m("apr.delete_hint")}
            emptyText={m("apr.empty")}
          />
        </div>
      );
    },
  };
}

async function accessoriesPo({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/acc-purchase-orders");
  const slug = "accessories-po";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("apo.entry_tab"), icon: "Layers" },
    { key: "reqlist", label: m("apo.reqlist_tab"), icon: "LayoutList" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/acc-purchase-orders/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/acc-purchase-orders/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        const prefill = !initial && searchParams.pr ? await apiFetch(`/merchandise/acc-purchase-orders/requisitions/${encodeURIComponent(searchParams.pr)}`).catch(() => null) : null;
        return (
          <AccPoEntry
            key={initial?.po_no ?? prefill?.req_no ?? "new"}
            options={options}
            initial={initial}
            prefill={prefill}
            canManage={canManage}
            actions={{ load: actions.loadAccPurchaseOrder, save: actions.saveAccPurchaseOrder, remove: actions.deleteAccPurchaseOrder }}
          />
        );
      }

      if (tab === "reqlist") {
        const { buyers } = await apiFetch("/merchandise/acc-purchase-orders/requisitions");
        return <AccPoRequisitions buyers={buyers} load={actions.loadPoRequisitions} entryHref={`/merchandise-desk/${slug}?tab=entry&pr=`} canManage={canManage} />;
      }

      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const label = (group, v) => (v ? m(`apo.${group}.${v}`) : null);
      const filters = [
        { name: "po", label: m("apo.po_no_search"), type: "text", match: (r, v) => has(r.po_no, v) },
        { name: "pr", label: m("apo.pr_no_search"), type: "text", match: (r, v) => has(r.pr_numbers, v) },
        { name: "supplier", field: "supplier", label: m("apo.f.supplier"), options: distinct(rows, "supplier") },
        { name: "source", field: "source", label: m("apo.f.source"), options: ["LOCAL", "IMPORT"].map((v) => ({ value: v, label: label("source", v) })) },
        { name: "term", field: "payment_term", label: m("apo.f.payment_term"), options: [...new Set(rows.map((r) => r.payment_term))].map((v) => ({ value: v, label: label("term", v) })) },
        { name: "method", field: "payment_method", label: m("apo.f.payment_method"), options: [...new Set(rows.map((r) => r.payment_method))].map((v) => ({ value: v, label: label("method", v) })) },
        { name: "from", label: m("apo.f.from"), type: "date", match: (r, v) => r.po_date >= v },
        { name: "to", label: m("apo.f.to"), type: "date", match: (r, v) => r.po_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({
              ...r,
              revision_status: r.revision ? m("apo.revised", { n: r.revision }) : m("apo.original"),
              payment_term_label: label("term", r.payment_term),
              payment_method_label: label("method", r.payment_method),
              value_label: `${money.format(r.value)} ${r.currency}`,
            }))}
            rowKey="po_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("apo.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("apo.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "receive_status", label: m("apo.col.receive_status"), type: "badge", labels: Object.fromEntries(["PENDING", "PARTIAL", "RECEIVED"].map((v) => [v, m(`apo.receive.${v}`)])) },
              { key: "revision_status", label: m("apo.col.revision_status") },
              { key: "po_no", label: m("apo.col.po_no"), type: "mono" },
              { key: "po_date", label: m("apo.col.po_date"), type: "date" },
              { key: "pr_numbers", label: m("apo.col.pr_numbers") },
              { key: "mrrs", label: m("apo.col.mrrs") },
              { key: "supplier", label: m("apo.col.supplier") },
              { key: "store", label: m("apo.col.store") },
              { key: "inco_term", label: m("apo.col.inco_term") },
              { key: "payment_term_label", label: m("apo.col.payment_term") },
              { key: "payment_method_label", label: m("apo.col.payment_method") },
              { key: "value_label", label: m("apo.col.value"), align: "right" },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "po_no" }}
            remove={canManage ? actions.deleteAccPurchaseOrder : undefined}
            deleteDescription={m("apo.delete_hint")}
            emptyText={m("apo.empty")}
          />
        </div>
      );
    },
  };
}

async function accessoriesMrr({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/acc-receipts");
  const slug = "accessories-mrr";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("mrr.entry_tab"), icon: "PackageCheck" },
    { key: "receive", label: m("mrr.receive_tab"), icon: "LayoutList" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/acc-receipts/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/acc-receipts/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        const prefill = !initial && searchParams.po ? await apiFetch(`/merchandise/acc-receipts/receivables/${encodeURIComponent(searchParams.po)}`).catch(() => null) : null;
        return (
          <AccReceiptEntry
            key={initial?.mrr_no ?? prefill?.po_no ?? "new"}
            options={options}
            initial={initial}
            prefill={prefill}
            canManage={canManage}
            actions={{ load: actions.loadAccReceipt, save: actions.saveAccReceipt, remove: actions.deleteAccReceipt, finalize: actions.finalizeAccReceipt }}
          />
        );
      }

      if (tab === "receive") {
        const { suppliers } = await apiFetch("/merchandise/acc-receipts/receivables");
        return <AccReceivables suppliers={suppliers} load={actions.loadReceivables} entryHref={`/merchandise-desk/${slug}?tab=entry&po=`} canManage={canManage} />;
      }

      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const filters = [
        { name: "mrr", label: m("mrr.col.mrr_no"), type: "text", match: (r, v) => has(r.mrr_no, v) },
        { name: "po", label: m("mrr.col.po_no"), type: "text", match: (r, v) => has(r.po_no, v) },
        { name: "supplier", field: "supplier", label: m("mrr.col.supplier"), options: distinct(rows, "supplier") },
        { name: "store", field: "store", label: m("mrr.col.store"), options: distinct(rows, "store") },
        { name: "status", field: "status", label: m("mrr.f.status"), options: ["DRAFT", "FINALIZED"].map((v) => ({ value: v, label: m(`mrr.status.${v}`) })) },
        { name: "from", label: m("mrr.f.from"), type: "date", match: (r, v) => r.mrr_date >= v },
        { name: "to", label: m("mrr.f.to"), type: "date", match: (r, v) => r.mrr_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      if (rows.length === 0) {
        return (
          <div className="rounded-xl border border-border bg-surface px-5 py-14 text-center">
            <p className="text-sm font-semibold text-foreground">{m("mrr.empty_title")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("mrr.empty")}</p>
          </div>
        );
      }
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, mrr_type_label: m(`mrr.type.${r.mrr_type}`), landed_label: `${money.format(r.landed_cost)} ${r.currency}`, net_label: `${money.format(r.net_payable)} ${r.currency}` }))}
            rowKey="mrr_no"
            header={{ title: m(`screens.${slug}`), subtitle: m("mrr.caption"), searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") }}
            columns={[
              { key: "mrr_no", label: m("mrr.col.mrr_no"), type: "mono" },
              { key: "mrr_date", label: m("mrr.col.mrr_date"), type: "date" },
              { key: "mrr_type_label", label: m("mrr.col.mrr_type") },
              { key: "supplier", label: m("mrr.col.supplier") },
              { key: "store", label: m("mrr.col.store") },
              { key: "challan_no", label: m("mrr.col.challan_no") },
              { key: "po_no", label: m("mrr.col.po_no") },
              { key: "pr_no", label: m("mrr.col.pr_no") },
              { key: "items", label: m("mrr.col.items"), type: "number" },
              { key: "landed_label", label: m("mrr.col.landed_cost"), align: "right" },
              { key: "net_label", label: m("mrr.col.net_payable"), align: "right" },
              { key: "status", label: m("mrr.col.status"), type: "badge", labels: Object.fromEntries(["DRAFT", "FINALIZED"].map((v) => [v, m(`mrr.status.${v}`)])) },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "mrr_no" }}
            remove={canManage ? actions.deleteAccReceipt : undefined}
            deleteDescription={m("mrr.delete_hint")}
            emptyText={m("mrr.empty")}
          />
        </div>
      );
    },
  };
}

/** Procurement Cost Head: Brickland's Records, New and Edit over the screen kit's resource. */
const costHead = masterScreen({
  resource: "proc-cost-heads",
  screen: "procurement-cost-head",
  noun: "cost_head",
  newLabel: "new.cost_head",
  description: "pch.note",
  booleans: ["active", "landed", "vat_applicable"],
  load: async () => ({ ledgers: await apiFetch("/merchandise/acc-receipts/ledgers") }),
  fields: ({ m, ledgers }) => [
    { name: "code", label: m("pch.f.code"), required: true, placeholder: m("pch.f.code_ph") },
    { name: "name", label: m("pch.f.name"), required: true, placeholder: m("pch.f.name_ph") },
    { name: "landed", label: m("pch.f.landed"), type: "checkbox", default: true, help: m("pch.landed_help") },
    { name: "vat_applicable", label: m("pch.f.vat_applicable"), type: "checkbox", help: m("pch.vat_help") },
    { name: "expense_ledger_id", label: m("pch.f.expense_ledger_id"), type: "select", options: [{ value: "", label: m("select") }, ...ledgers], help: m("pch.ledger_help") },
    { name: "sort_order", label: m("pch.f.sort_order"), type: "number" },
    { name: "active", label: m("pch.f.active"), type: "checkbox", default: true },
  ],
  decorate: (row) => ({ ...row, sort_order: String(row.sort_order ?? "") }),
  columns: ({ m }) => [
    { key: "code", label: m("pch.f.code"), type: "mono" },
    { key: "name", label: m("pch.f.name") },
    { key: "landed", label: m("pch.f.landed"), type: "yesno" },
    { key: "vat_applicable", label: m("pch.f.vat_applicable"), type: "yesno" },
    { key: "expense_ledger", label: m("pch.f.expense_ledger_id") },
    { key: "sort_order", label: m("pch.f.order"), type: "number" },
    { key: "active", label: m("pch.f.status"), type: "status" },
  ],
});

/** Procurement Posting Setup: one per company — the voucher type and the five ledger heads a finalised receipt posts to. */
const POSTING_LEDGERS = [
  ["inventory", "inventory_ledger_id"],
  ["vat", "vat_ledger_id"],
  ["payable", "payable_ledger_id"],
  ["tds", "tds_ledger_id"],
  ["vds", "vds_ledger_id"],
];
const VOUCHER_TYPES = ["BPV", "BRV", "CPV", "CRV", "CV", "BV", "JV"];
const postingSetup = masterScreen({
  resource: "proc-posting-setups",
  screen: "procurement-posting-setup",
  noun: "posting_setup",
  newLabel: "new.posting_setup",
  description: "pps.note",
  nameKey: "company",
  refKey: "company",
  load: async () => {
    const [ledgers, receipt] = await Promise.all([apiFetch("/merchandise/acc-receipts/ledgers"), apiFetch("/merchandise/acc-receipts/options")]);
    return { ledgers, companies: receipt.companies };
  },
  fields: ({ m, ledgers, companies }) => [
    { name: "unit_company_id", label: m("pps.f.company"), type: "select", required: true, options: [{ value: "", label: m("select") }, ...companies] },
    { name: "voucher_type", label: m("pps.f.voucher_type"), type: "select", required: true, options: [{ value: "", label: m("select") }, ...VOUCHER_TYPES.map((v) => ({ value: v, label: m(`pps.vt.${v}`) }))], help: m("pps.voucher_help") },
    ...POSTING_LEDGERS.map(([k, name]) => ({ name, label: m(`pps.f.${k}`), type: "select", required: true, options: [{ value: "", label: m("select") }, ...ledgers], help: m(`pps.${k}_help`) })),
    { name: "active", label: m("pps.f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({ ...row, voucher_label: row.voucher_type ? m(`pps.vt.${row.voucher_type}`) : null }),
  columns: ({ m }) => [
    { key: "company", label: m("pps.f.company") },
    { key: "voucher_label", label: m("pps.f.voucher_type") },
    ...POSTING_LEDGERS.map(([k]) => ({ key: `${k}_ledger`, label: m(`pps.col.${k}`) })),
    { key: "active", label: m("pps.f.status"), type: "status" },
  ],
});

async function supplierReturn({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/acc-returns");
  const slug = "accessories-return-challan-to-supplier";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("rtn.entry_tab"), icon: "Undo2" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/acc-returns/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/acc-returns/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        return (
          <AccReturnEntry
            key={initial?.challan_no ?? "new"}
            options={options}
            initial={initial}
            canManage={canManage}
            actions={{ fromReceipt: actions.returnFromReceipt, load: actions.loadAccReturn, save: actions.saveAccReturn, remove: actions.deleteAccReturn, finalize: actions.finalizeAccReturn }}
            recordsHref={`/merchandise-desk/${slug}?tab=records`}
          />
        );
      }
      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const filters = [
        { name: "challan", label: m("rtn.challan_search"), type: "text", match: (r, v) => has(r.challan_no, v) },
        { name: "mrr", label: m("rtn.mrr_search"), type: "text", match: (r, v) => has(r.mrr_no, v) },
        { name: "type", field: "return_type", label: m("rtn.f.type_filter"), options: [...new Set(rows.map((r) => r.return_type))].map((v) => ({ value: v, label: m(`rtn.type.${v}`) })) },
        { name: "company", field: "company", label: m("rtn.f.unit_company_id"), options: distinct(rows, "company") },
        { name: "supplier", field: "supplier", label: m("rtn.f.supplier_id"), options: distinct(rows, "supplier") },
        { name: "store", field: "store", label: m("rtn.f.store"), options: distinct(rows, "store") },
        { name: "from", label: m("rtn.f.from"), type: "date", match: (r, v) => r.challan_date >= v },
        { name: "to", label: m("rtn.f.to"), type: "date", match: (r, v) => r.challan_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, type_label: m(`rtn.type.${r.return_type}`) }))}
            rowKey="challan_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("rtn.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("rtn.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "finalized", label: m("rtn.col.finalized"), type: "yesno" },
              { key: "mrr_no", label: m("rtn.col.mrr_no"), type: "mono" },
              { key: "challan_no", label: m("rtn.col.challan_no"), type: "mono" },
              { key: "challan_date", label: m("rtn.col.challan_date"), type: "date" },
              { key: "type_label", label: m("rtn.col.return_type") },
              { key: "company", label: m("rtn.col.company") },
              { key: "supplier", label: m("rtn.col.supplier") },
              { key: "store", label: m("rtn.col.store") },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "challan_no" }}
            remove={canManage ? actions.deleteAccReturn : undefined}
            deleteDescription={m("rtn.delete_hint")}
            emptyText={m("rtn.empty")}
          />
        </div>
      );
    },
  };
}

async function fabricOpening({ m, te, tab, canManage, can, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/fabric-obs");
  const slug = "purchase-fabric-opening-balance";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("fob.entry_tab"), icon: "Layers" },
    { key: "history", label: m("fob.history_tab"), icon: "History" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/fabric-obs/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/fabric-obs/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        return (
          <FabricObEntry
            key={initial?.ob_no ?? "new"}
            options={options}
            initial={initial}
            canManage={canManage}
            canApprove={can("merchandise.accessories.approve")}
            actions={{ load: actions.loadFabricOb, save: actions.saveFabricOb, remove: actions.deleteFabricOb, transition: actions.transitionFabricOb }}
            recordsHref={`/merchandise-desk/${slug}?tab=records`}
          />
        );
      }
      if (tab === "history") return <FabricObHistory balances={rows.map(({ ob_no, status, ob_date }) => ({ ob_no, status, ob_date }))} load={actions.loadFabricObHistory} />;

      const STATUSES_OB = ["DRAFT", "SUBMITTED", "APPROVED", "POSTED", "LOCKED"];
      const filters = [
        { name: "status", field: "status", label: m("fob.f.status"), options: STATUSES_OB.map((v) => ({ value: v, label: m(`fob.status.${v}`) })) },
        { name: "store", field: "store", label: m("fob.f.store_id"), options: distinct(rows, "store") },
        { name: "source", field: "source", label: m("fob.f.source"), options: [...new Set(rows.map((r) => r.source))].map((v) => ({ value: v, label: m(`fob.source.${v}`) })) },
        { name: "from", label: m("fob.f.from"), type: "date", match: (r, v) => r.ob_date >= v },
        { name: "to", label: m("fob.f.to"), type: "date", match: (r, v) => r.ob_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, source_label: m(`fob.source.${r.source}`), cost_label: money.format(r.total_cost), conv_label: r.currency === "BDT" ? "—" : String(r.conv_rate), usd_label: r.usd_bdt ? String(r.usd_bdt) : "—" }))}
            rowKey="ob_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("fob.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("fob.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "ob_no", label: m("fob.col.ob_no"), type: "mono" },
              { key: "ob_date", label: m("fob.col.ob_date"), type: "date" },
              { key: "company", label: m("fob.col.company") },
              { key: "store", label: m("fob.col.store") },
              { key: "order", label: m("fob.col.order") },
              { key: "source_label", label: m("fob.col.source") },
              { key: "reference_no", label: m("fob.col.reference") },
              { key: "currency", label: m("fob.col.currency") },
              { key: "conv_label", label: m("fob.col.conv_rate") },
              { key: "usd_label", label: m("fob.col.usd_bdt") },
              { key: "items", label: m("fob.col.items"), type: "number" },
              { key: "rolls", label: m("fob.col.rolls"), type: "number" },
              { key: "total_qty", label: m("fob.col.total_qty"), type: "number" },
              { key: "cost_label", label: m("fob.col.total_cost"), align: "right" },
              { key: "status", label: m("fob.col.status"), type: "badge", labels: Object.fromEntries(STATUSES_OB.map((v) => [v, m(`fob.status.${v}`)])) },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "ob_no" }}
            remove={canManage ? actions.deleteFabricOb : undefined}
            deleteDescription={m("fob.delete_hint")}
            emptyText={m("fob.empty")}
          />
        </div>
      );
    },
  };
}

async function accessoriesPrDummy({ m, te, tab, canManage, can, searchParams = {} }) {
  const options = await apiFetch("/merchandise/acc-requisitions/options?dummy=1");
  const slug = "accessories-pr-dummy-ord";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: options.orders.length },
    { key: "entry", label: m("dum.entry_tab"), icon: "FilePlus2" },
    { key: "prlist", label: m("dum.prlist_tab"), icon: "ListChecks" },
    { key: "bom", label: m("dum.bom_tab"), icon: "LayoutList" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const initial = searchParams.ref ? await apiFetch(`/merchandise/acc-requisitions/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        const order = !initial && searchParams.order ? findByRef(options.orders, "order_no", searchParams.order, "value") : null;
        const prefill = order ? { order_id: order.value, buyer_office_id: order.buyer_office_id ?? "", ...(await apiFetch(`/merchandise/acc-requisitions/bom?order_id=${encodeURIComponent(order.value)}`)) } : null;
        return (
          <AccRequisitionEntry
            key={initial?.req_no ?? order?.value ?? "new"}
            options={options}
            initial={initial}
            prefill={prefill}
            canManage={canManage}
            canApprove={can("merchandise.accessories.approve")}
            autoPrint={searchParams.print === "1"}
            actions={{ bom: actions.accRequisitionBom, load: actions.loadAccRequisition, save: actions.saveAccRequisition, transition: actions.transitionAccRequisition, remove: actions.deleteAccRequisition }}
            recordsHref={`/merchandise-desk/${slug}?tab=prlist`}
            poHref="/merchandise-desk/accessories-po?tab=entry&pr="
            dummy
            title={m("dum.entry_tab")}
            subtitle={m("dum.entry_hint")}
          />
        );
      }

      if (tab === "bom") {
        const order = searchParams.order ? findByRef(options.orders, "order_no", searchParams.order, "value") : null;
        const sheet = order ? await apiFetch(`/merchandise/acc-requisitions/bom?order_id=${encodeURIComponent(order.value)}`) : null;
        return <OrderBomViewer key={order?.value ?? "none"} orders={options.orders} initialOrder={order} initialSheet={sheet} load={actions.accRequisitionBom} />;
      }

      if (tab === "prlist") {
        const rows = await apiFetch("/merchandise/acc-requisitions?dummy=1");
        const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
        const filters = [
          { name: "month", field: "req_month", label: m("apr.f.month"), options: distinct(rows, "req_month") },
          { name: "supplier", field: "supplier", label: m("dum.col.supplier"), options: distinct(rows, "supplier") },
          { name: "buyer", field: "buyer_ac", label: m("dum.col.buyer_grp"), options: distinct(rows, "buyer_ac") },
          { name: "status", field: "status", label: m("apr.f.status"), options: STATUSES.map((v) => ({ value: v, label: m(`apr.status.${v}`) })) },
          { name: "q", label: m("dum.search_box"), type: "text", match: (r, v) => has(`${r.req_no} ${r.job_no} ${r.order_no}`, v) },
        ];
        const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
        return (
          <div className="flex flex-col gap-3">
            <div className="rounded-lg border border-border bg-surface p-4">
              <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
            </div>
            <RecordsGrid
              exportName={`${slug}-pr`}
              rows={shown}
              rowKey="req_no"
              header={{
                title: m("dum.prlist_tab"),
                subtitle: m("dum.prlist_hint"),
                action: (
                  <Link href={`/merchandise-desk/${slug}?tab=records`} className={buttonVariants({ variant: "outline", size: "sm" })}>
                    {m("dum.order_list")}
                  </Link>
                ),
                searchLabel: m("ldp.search"),
                searchPlaceholder: m("ldp.search"),
                exportLabel: m("ldp.export"),
              }}
              columns={[
                { key: "req_no", label: m("dum.col.req_no"), type: "mono" },
                { key: "req_date", label: m("dum.col.req_date"), type: "date" },
                { key: "addition_for", label: m("dum.col.addition_for") },
                { key: "buyer_ac", label: m("dum.col.buyer_grp") },
                { key: "job_no", label: m("dum.col.job") },
                { key: "order_no", label: m("dum.col.order") },
                { key: "supplier", label: m("dum.col.supplier") },
                { key: "items", label: m("dum.col.items") },
                { key: "status", label: m("dum.col.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((v) => [v, m(`apr.status.${v}`)])) },
              ]}
              edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "req_no" }}
              remove={canManage ? actions.deleteAccRequisition : undefined}
              deleteDescription={m("apr.delete_hint")}
              emptyText={m("dum.no_match")}
            />
          </div>
        );
      }

      const types = [...new Set(options.orders.map((o) => o.order_type).filter(Boolean))].sort();
      const orders = options.orders.filter((o) => (!searchParams.type || o.order_type === searchParams.type) && (!searchParams.buyer || o.buyer_office_id === searchParams.buyer) && (!searchParams.order || o.order_no === searchParams.order));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters
              filters={[
                { name: "type", label: m("apr.f.type"), options: types.map((v) => ({ value: v, label: m(`ord.type.${v}`) })), allLabel: m("all") },
                { name: "buyer", label: m("apr.f.buyer_ac"), options: options.offices, allLabel: m("all") },
                { name: "order", label: m("apr.f.order"), options: options.orders.map((o) => ({ value: o.order_no, label: o.order_no })), allLabel: m("all") },
                { name: "req_type", label: m("apr.f.req_type"), options: [{ value: "ACC_PURCHASE", label: m("apr.req_type") }], allLabel: m("all") },
              ]}
              shown={m("shown", { shown: orders.length, total: options.orders.length })}
              clearLabel={m("clear")}
            />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={orders.map((o) => ({ ...o, order_type_label: o.order_type ? m(`ord.type.${o.order_type}`) : null, qty_label: `${qty.format(o.qty).replace(/\.000$/, "")} ${o.unit ?? ""}`.trim() }))}
            rowKey="value"
            header={{ title: m(`screens.${slug}`), subtitle: m("dum.records_caption"), searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") }}
            columns={[
              { key: "buyer", label: m("apr.col.buyer") },
              { key: "style_desc", label: m("apr.col.style_desc") },
              { key: "job", label: m("apr.col.job_hash") },
              { key: "order_no", label: m("apr.col.order_hash") },
              { key: "order_type_label", label: m("apr.col.order_type") },
              { key: "qty_label", label: m("apr.col.order_qty") },
              { key: "ship_date", label: m("apr.col.ship_date"), type: "date" },
            ]}
            rowLink={{ label: m("dum.view_bom"), href: `/merchandise-desk/${slug}?tab=bom&order=`, refKey: "order_no" }}
            emptyText={m("apr.no_orders")}
          />
        </div>
      );
    },
  };
}

async function fabricIndent({ m, te, tab, canManage, can, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/pfab-indents");
  const slug = "purchase-fabric-pr";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("pfi.entry_tab"), icon: "FilePlus2" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/pfab-indents/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/pfab-indents/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        return (
          <PfabIndentEntry
            key={initial?.req_no ?? "new"}
            options={options}
            initial={initial}
            canManage={canManage}
            canApprove={can("merchandise.accessories.approve")}
            autoPrint={searchParams.print === "1"}
            actions={{ fill: actions.fillPfabIndent, load: actions.loadPfabIndent, save: actions.savePfabIndent, transition: actions.transitionPfabIndent, remove: actions.deletePfabIndent }}
            recordsHref={`/merchandise-desk/${slug}?tab=records`}
          />
        );
      }
      const filters = [
        { name: "month", field: "req_month", label: m("apr.f.month"), options: distinct(rows, "req_month") },
        { name: "buyer_ac", field: "buyer_ac", label: m("apr.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
        { name: "order", field: "order_no", label: m("apr.f.order"), options: distinct(rows, "order_no") },
        { name: "supplier", field: "supplier", label: m("apr.f.supplier"), options: distinct(rows, "supplier") },
        { name: "status", field: "status", label: m("apr.f.status"), options: STATUSES.map((v) => ({ value: v, label: m(`apr.status.${v}`) })) },
        { name: "from", label: m("apr.f.from"), type: "date", match: (r, v) => r.req_date >= v },
        { name: "to", label: m("apr.f.to"), type: "date", match: (r, v) => r.req_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown}
            rowKey="req_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("pfi.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("pfi.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "req_no", label: m("apr.col.req_no"), type: "mono" },
              { key: "req_date", label: m("apr.col.req_date"), type: "date" },
              { key: "ship_date", label: m("pfi.col.ship_date"), type: "date" },
              { key: "gsm", label: m("pfi.gsm") },
              { key: "dia", label: m("pfi.dia") },
              { key: "buyer_ac", label: m("apr.col.buyer_grp") },
              { key: "job_no", label: m("apr.col.job") },
              { key: "order_no", label: m("apr.col.order") },
              { key: "supplier", label: m("apr.col.supplier_name") },
              { key: "items", label: m("apr.col.item_list") },
              { key: "status", label: m("apr.col.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((v) => [v, m(`apr.status.${v}`)])) },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "req_no" }}
            rowLink={{ label: m("apr.print"), href: `/merchandise-desk/${slug}?tab=entry&print=1&ref=`, refKey: "req_no" }}
            remove={canManage ? actions.deletePfabIndent : undefined}
            deleteDescription={m("apr.delete_hint")}
            emptyText={m("pfi.empty")}
          />
        </div>
      );
    },
  };
}

async function fabricPo({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/pfab-pos");
  const slug = "purchase-fabric-po";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("fpo.entry_tab"), icon: "Layers" },
    { key: "reqlist", label: m("apo.reqlist_tab"), icon: "LayoutList" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/pfab-pos/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/pfab-pos/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        const prefill = !initial && searchParams.pr ? await apiFetch(`/merchandise/pfab-pos/requisitions/${encodeURIComponent(searchParams.pr)}`).catch(() => null) : null;
        return (
          <PfabPoEntry
            key={initial?.po_no ?? prefill?.req_no ?? "new"}
            options={options}
            initial={initial}
            prefill={prefill}
            canManage={canManage}
            actions={{ fromIndent: actions.pfabPoFromIndent, load: actions.loadPfabPo, save: actions.savePfabPo, remove: actions.deletePfabPo }}
          />
        );
      }
      if (tab === "reqlist") {
        const { buyers } = await apiFetch("/merchandise/pfab-pos/requisitions");
        return <AccPoRequisitions buyers={buyers} load={actions.loadPfabPoRequisitions} entryHref={`/merchandise-desk/${slug}?tab=entry&pr=`} canManage={canManage} />;
      }

      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const label = (group, v) => (v ? m(`apo.${group}.${v}`) : null);
      const filters = [
        { name: "po", label: m("apo.po_no_search"), type: "text", match: (r, v) => has(r.po_no, v) },
        { name: "pr", label: m("apo.pr_no_search"), type: "text", match: (r, v) => has(r.pr_numbers, v) },
        { name: "supplier", field: "supplier", label: m("apo.f.supplier"), options: distinct(rows, "supplier") },
        { name: "source", field: "source", label: m("apo.f.source"), options: ["LOCAL", "IMPORT"].map((v) => ({ value: v, label: label("source", v) })) },
        { name: "term", field: "payment_term", label: m("apo.f.payment_term"), options: [...new Set(rows.map((r) => r.payment_term))].map((v) => ({ value: v, label: label("term", v) })) },
        { name: "method", field: "payment_method", label: m("apo.f.payment_method"), options: [...new Set(rows.map((r) => r.payment_method))].map((v) => ({ value: v, label: label("method", v) })) },
        { name: "from", label: m("apo.f.from"), type: "date", match: (r, v) => r.po_date >= v },
        { name: "to", label: m("apo.f.to"), type: "date", match: (r, v) => r.po_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({
              ...r,
              revision_status: r.revision ? m("apo.revised", { n: r.revision }) : m("apo.original"),
              payment_term_label: label("term", r.payment_term),
              payment_method_label: label("method", r.payment_method),
              value_label: `${money.format(r.value)} ${r.currency}`,
            }))}
            rowKey="po_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("fpo.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("apo.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "receive_status", label: m("apo.col.receive_status"), type: "badge", labels: Object.fromEntries(["PENDING", "PARTIAL", "RECEIVED"].map((v) => [v, m(`apo.receive.${v}`)])) },
              { key: "revision_status", label: m("apo.col.revision_status") },
              { key: "po_no", label: m("apo.col.po_no"), type: "mono" },
              { key: "po_date", label: m("apo.col.po_date"), type: "date" },
              { key: "pr_numbers", label: m("apo.col.pr_numbers") },
              { key: "mrrs", label: m("apo.col.mrrs") },
              { key: "supplier", label: m("apo.col.supplier") },
              { key: "store", label: m("apo.col.store") },
              { key: "inco_term", label: m("apo.col.inco_term") },
              { key: "payment_term_label", label: m("apo.col.payment_term") },
              { key: "payment_method_label", label: m("apo.col.payment_method") },
              { key: "po_group", label: m("fpo.col.po_group") },
              { key: "value_label", label: m("apo.col.value"), align: "right" },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "po_no" }}
            remove={canManage ? actions.deletePfabPo : undefined}
            deleteDescription={m("apo.delete_hint")}
            emptyText={m("apo.empty")}
          />
        </div>
      );
    },
  };
}

async function fabricMrr({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/pfab-mrrs");
  const slug = "purchase-fabric-mrr";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("fmrr.entry_tab"), icon: "PackageOpen" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/pfab-mrrs/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/pfab-mrrs/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        const prefill = !initial && searchParams.po ? await apiFetch(`/merchandise/pfab-mrrs/receivables/${encodeURIComponent(searchParams.po)}`).catch(() => null) : null;
        return (
          <PfabMrrEntry
            key={initial?.mrr_no ?? prefill?.po_no ?? "new"}
            options={options}
            initial={initial}
            prefill={prefill}
            canManage={canManage}
            actions={{ fromOrder: actions.pfabMrrFromOrder, load: actions.loadPfabMrr, save: actions.savePfabMrr, remove: actions.deletePfabMrr, finalize: actions.finalizePfabMrr }}
            recordsHref={`/merchandise-desk/${slug}?tab=records`}
          />
        );
      }
      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const filters = [
        { name: "mrr", label: m("fmrr.col.mrr_no"), type: "text", match: (r, v) => has(r.mrr_no, v) },
        { name: "from", label: m("mrr.f.from"), type: "date", match: (r, v) => r.mrr_date >= v },
        { name: "to", label: m("mrr.f.to"), type: "date", match: (r, v) => r.mrr_date <= v },
        { name: "company", field: "company", label: m("fmrr.col.company"), options: distinct(rows, "company") },
        { name: "store", field: "store", label: m("fmrr.col.store"), options: distinct(rows, "store") },
        { name: "supplier", field: "supplier", label: m("fmrr.col.supplier"), options: distinct(rows, "supplier") },
        { name: "method", field: "payment_method", label: m("mrr.f.payment_method"), options: [...new Set(rows.map((r) => r.payment_method))].map((v) => ({ value: v, label: m(`apo.method.${v}`) })) },
        { name: "pr", label: m("fmrr.f.pr"), type: "text", match: (r, v) => has(r.pr, v) },
        { name: "po", label: m("fmrr.f.po"), type: "text", match: (r, v) => has(r.po, v) },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      const amt = (v, c) => `${money.format(v)} ${c}`;
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({
              ...r,
              method_label: m(`apo.method.${r.payment_method}`),
              source_label: m(`apo.source.${r.source_type}`),
              net_amount_label: amt(r.net_amount, r.currency),
              goods_label: amt(r.goods_value, r.currency),
              vat_label: amt(r.vat, r.currency),
              tds_vds_label: amt(r.tds_vds, r.currency),
              net_payable_label: amt(r.net_payable, r.currency),
            }))}
            rowKey="mrr_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("fmrr.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("apo.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "finalized", label: m("fmrr.col.finalized"), type: "yesno" },
              { key: "accounting", label: m("fmrr.col.accounting"), type: "yesno" },
              { key: "mrr_no", label: m("fmrr.col.mrr_no"), type: "mono" },
              { key: "mrr_date", label: m("fmrr.col.mrr_date"), type: "date" },
              { key: "pr", label: m("fmrr.col.pr") },
              { key: "po", label: m("fmrr.col.po") },
              { key: "company", label: m("fmrr.col.company") },
              { key: "supplier", label: m("fmrr.col.supplier") },
              { key: "store", label: m("fmrr.col.store") },
              { key: "method_label", label: m("fmrr.col.pay_method") },
              { key: "source_label", label: m("fmrr.col.source_type") },
              { key: "lc_no", label: m("fmrr.col.lc_no") },
              { key: "rcv_qty", label: m("fmrr.col.rcv_qty"), type: "number" },
              { key: "ret_qty", label: m("fmrr.col.ret_qty"), type: "number" },
              { key: "net_amount_label", label: m("fmrr.col.net_amount"), align: "right" },
              { key: "remarks", label: m("fmrr.col.remarks") },
              { key: "goods_label", label: m("fmrr.col.goods_value"), align: "right" },
              { key: "vat_label", label: m("fmrr.col.vat"), align: "right" },
              { key: "tds_vds_label", label: m("fmrr.col.tds_vds"), align: "right" },
              { key: "net_payable_label", label: m("fmrr.col.net_payable"), align: "right" },
              { key: "voucher_no", label: m("fmrr.col.voucher") },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "mrr_no" }}
            remove={canManage ? actions.deletePfabMrr : undefined}
            deleteDescription={m("mrr.delete_hint")}
            emptyText={m("mrr.empty_title")}
          />
        </div>
      );
    },
  };
}

async function fabricReturn({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/pfab-returns");
  const slug = "fabric-return-challan-to-supplier";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("frt.entry_tab"), icon: "Undo2" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/pfab-returns/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/pfab-returns/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        return (
          <AccReturnEntry
            key={initial?.challan_no ?? "new"}
            options={options}
            initial={initial}
            canManage={canManage}
            actions={{ fromReceipt: actions.pfabReturnFromReceipt, load: actions.loadPfabReturn, save: actions.savePfabReturn, remove: actions.deletePfabReturn, finalize: actions.finalizePfabReturn }}
            recordsHref={`/merchandise-desk/${slug}?tab=records`}
            labels={{ entry: "master.frt.entry", finalized_toast: "master.frt.finalized_toast", confirm_finalize_hint: "master.frt.confirm_finalize_hint" }}
          />
        );
      }
      const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
      const filters = [
        { name: "challan", label: m("rtn.challan_search"), type: "text", match: (r, v) => has(r.challan_no, v) },
        { name: "mrr", label: m("rtn.mrr_search"), type: "text", match: (r, v) => has(r.mrr_no, v) },
        { name: "type", field: "return_type", label: m("rtn.f.type_filter"), options: [...new Set(rows.map((r) => r.return_type))].map((v) => ({ value: v, label: m(`rtn.type.${v}`) })) },
        { name: "company", field: "company", label: m("rtn.f.unit_company_id"), options: distinct(rows, "company") },
        { name: "supplier", field: "supplier", label: m("rtn.f.supplier_id"), options: distinct(rows, "supplier") },
        { name: "store", field: "store", label: m("rtn.f.store"), options: distinct(rows, "store") },
        { name: "from", label: m("rtn.f.from"), type: "date", match: (r, v) => r.challan_date >= v },
        { name: "to", label: m("rtn.f.to"), type: "date", match: (r, v) => r.challan_date <= v },
      ];
      const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
      return (
        <div className="flex flex-col gap-3">
          <div className="rounded-lg border border-border bg-surface p-4">
            <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
          </div>
          <RecordsGrid
            exportName={slug}
            rows={shown.map((r) => ({ ...r, type_label: m(`rtn.type.${r.return_type}`) }))}
            rowKey="challan_no"
            header={{
              title: m(`screens.${slug}`),
              subtitle: m("frt.caption"),
              action: canManage ? (
                <Link href={`/merchandise-desk/${slug}?tab=entry`} className={buttonVariants({ variant: "primary", size: "sm" })}>
                  {m("rtn.add_new")}
                </Link>
              ) : null,
              searchLabel: m("ldp.search"),
              searchPlaceholder: m("ldp.search"),
              exportLabel: m("ldp.export"),
            }}
            columns={[
              { key: "finalized", label: m("rtn.col.finalized"), type: "yesno" },
              { key: "mrr_no", label: m("rtn.col.mrr_no"), type: "mono" },
              { key: "challan_no", label: m("rtn.col.challan_no"), type: "mono" },
              { key: "challan_date", label: m("rtn.col.challan_date"), type: "date" },
              { key: "type_label", label: m("rtn.col.return_type") },
              { key: "company", label: m("rtn.col.company") },
              { key: "supplier", label: m("rtn.col.supplier") },
              { key: "store", label: m("rtn.col.store") },
            ]}
            edit={{ href: `/merchandise-desk/${slug}?tab=entry&ref=`, refKey: "challan_no" }}
            remove={canManage ? actions.deletePfabReturn : undefined}
            deleteDescription={m("rtn.delete_hint")}
            emptyText={m("rtn.empty")}
          />
        </div>
      );
    },
  };
}

async function finishedFabricOpening({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/ffab-obs");
  const slug = "finish-fabric-opening-balance";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "entry", label: m("ffob.entry_tab"), icon: "Layers" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const options = await apiFetch("/merchandise/ffab-obs/options");
        const initial = searchParams.ref ? await apiFetch(`/merchandise/ffab-obs/${encodeURIComponent(searchParams.ref)}`).catch(() => null) : null;
        return <FfabObEntry key={initial?.ob_no ?? "new"} options={options} initial={initial} canManage={canManage} actions={{ load: actions.loadFfabOb, save: actions.saveFfabOb, remove: actions.deleteFfabOb }} />;
      }
      return (
        <RecordsGrid
          exportName={slug}
          rows={rows}
          rowKey="ob_no"
          header={{
            title: m(`screens.${slug}`),
            subtitle: m("ffob.caption"),
            searchLabel: m("ldp.search"),
            searchPlaceholder: m("ldp.search"),
            exportLabel: m("ldp.export"),
          }}
          columns={[
            { key: "ob_date", label: m("ffob.col.ob_date"), type: "date" },
            { key: "company", label: m("ffob.col.company") },
            { key: "store", label: m("ffob.col.store") },
            { key: "order", label: m("ffob.col.order") },
            { key: "currency", label: m("ffob.col.currency") },
            { key: "conv_rate", label: m("ffob.col.conv_rate"), type: "number" },
            { key: "usd_bdt", label: m("ffob.col.usd_bdt"), type: "number" },
            { key: "items", label: m("ffob.col.items"), type: "number" },
            { key: "total_qty", label: m("ffob.col.total_qty"), type: "number" },
          ]}
          remove={canManage ? actions.deleteFfabOb : undefined}
          deleteDescription={m("ffob.delete_hint")}
          emptyText={m("ffob.empty")}
        />
      );
    },
  };
}

/** The six closings share one screen: Records, Show Exception and Month Close, told apart by their kind. */
const closingScreen =
  (slug, kind) =>
  async ({ m, te, tab, canManage }) => {
    const rows = await apiFetch(`/merchandise/closings/${kind}`);
    const noun = m(`clo.noun.${kind}`);
    const tabs = [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "exceptions", label: m("clo.exceptions_tab"), icon: "ListChecks" },
      { key: "close", label: m("clo.close_tab"), icon: "Plus" },
    ];
    return {
      tabs,
      body: async () => {
        if (tab === "exceptions") return <StockClosingExceptions periods={rows} load={actions.loadClosingExceptions.bind(null, kind)} />;
        if (tab === "close") return <StockMonthClose noun={noun} canManage={canManage} close={actions.closeStockMonth.bind(null, kind)} />;
        return (
          <RecordsGrid
            exportName={slug}
            rows={rows}
            rowKey="period"
            header={{ title: m(`screens.${slug}`), subtitle: m("clo.caption"), searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") }}
            columns={[
              { key: "label", label: m("clo.col.period"), type: "mono" },
              { key: "checked_at", label: m("clo.col.checked_at"), type: "datetime" },
              { key: "exceptions", label: m("clo.col.exceptions"), type: "number" },
              { key: "closed_at", label: m("clo.col.closed_at"), type: "datetime" },
              { key: "closed_by", label: m("clo.col.closed_by") },
              { key: "remarks", label: m("clo.col.remarks") },
              { key: "status", label: m("clo.col.status"), type: "badge", labels: { OPEN: m("clo.status.OPEN"), CLOSED: m("clo.status.CLOSED") } },
            ]}
            emptyText={m("clo.empty", { noun })}
          />
        );
      },
    };
  };

const ACCESSORIES_SCREENS = { "accessories-pr": accessoriesPr, "accessories-po": accessoriesPo, "accessories-mrr": accessoriesMrr, "procurement-cost-head": costHead, "procurement-posting-setup": postingSetup, "accessories-return-challan-to-supplier": supplierReturn, "purchase-fabric-opening-balance": fabricOpening, "accessories-pr-dummy-ord": accessoriesPrDummy, "purchase-fabric-pr": fabricIndent, "purchase-fabric-po": fabricPo, "purchase-fabric-mrr": fabricMrr, "fabric-return-challan-to-supplier": fabricReturn, "finish-fabric-opening-balance": finishedFabricOpening, "purchase-fabric-closing": closingScreen("purchase-fabric-closing", "purchased-fabric"), "finish-fabric-closing": closingScreen("finish-fabric-closing", "finished-fabric"), "trims-closing": closingScreen("trims-closing", "trims"), "batch-fabric-closing": closingScreen("batch-fabric-closing", "batch-fabric"), "gray-fab-left-over-closing": closingScreen("gray-fab-left-over-closing", "grey-leftover"), "finish-fab-left-over-closing": closingScreen("finish-fab-left-over-closing", "finished-leftover") };

/** Requisitions are raised and revised under the accessories permission; approving checks its own. */
const ACCESSORIES_MANAGE = { "accessories-pr": "merchandise.accessories.manage", "accessories-po": "merchandise.accessories.manage", "accessories-mrr": "merchandise.accessories.manage", "procurement-cost-head": "merchandise.accessories.manage", "procurement-posting-setup": "merchandise.accessories.manage", "accessories-return-challan-to-supplier": "merchandise.accessories.manage", "purchase-fabric-opening-balance": "merchandise.accessories.manage", "accessories-pr-dummy-ord": "merchandise.accessories.manage", "purchase-fabric-pr": "merchandise.accessories.manage", "purchase-fabric-po": "merchandise.accessories.manage", "purchase-fabric-mrr": "merchandise.accessories.manage", "fabric-return-challan-to-supplier": "merchandise.accessories.manage", "finish-fabric-opening-balance": "merchandise.accessories.manage", "purchase-fabric-closing": "merchandise.accessories.manage", "finish-fabric-closing": "merchandise.accessories.manage", "trims-closing": "merchandise.accessories.manage", "batch-fabric-closing": "merchandise.accessories.manage", "gray-fab-left-over-closing": "merchandise.accessories.manage", "finish-fab-left-over-closing": "merchandise.accessories.manage" };

export { ACCESSORIES_SCREENS, ACCESSORIES_MANAGE };
