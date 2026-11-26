import Link from "next/link";
import { Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { apiFetch } from "@/lib/api-server";
import { FilterBar } from "@/components/erp/filter-bar";
import { ScChallanEditor, ScReceiptEditor, ScBillEditor, ScRegister, ScPartyBalance } from "@/components/finishing/sc-process";
import * as actions from "./actions";

/*
 * Finishing Line → S/C Process (AnnotechERP 18/03), each laid out as
 * Brickland lays it out: a list tab (Searching Data, Add New, the register),
 * the entry tab where the paper is written, and the third tab Brickland keeps
 * pointing back to it; and the Party Balance, counted as you look.
 */

const look = () => apiFetch("/finishing/sc/options");
const opts = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const any = (m) => ({ value: "", label: m("f.any_status") });
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/finishing-line/${key}`;
const blankNulls = (r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v]));

/** The list tab: the Searching Data card, Add New, and the register beneath. */
function listTab(m, key, q, fields, kind, register) {
  return (
    <div className="flex flex-col gap-3">
      <FilterBar basePath={base(key)} keep={{ tab: "records" }} values={q} title={m("list.search")} hint={m(`${kind}.list_hint`)} labels={{ show: m("list.show"), reset: m("list.reset") }} fields={fields} />
      <div className="flex justify-end">
        <Link href={`${base(key)}?tab=entry`} className={cn(buttonVariants({ size: "sm" }))}>
          <Plus /> {m("list.add_new")}
        </Link>
      </div>
      {register}
    </div>
  );
}

/** Brickland's third tab: it holds no form — it sends you to the entry tab. */
function pointer(m, key, kind) {
  return (
    <div className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-5">
      <h2 className="text-base font-semibold">{m(`tab.${kind}_new`)}</h2>
      <p className="text-[13px] text-foreground-muted">{m(`${kind}.pointer`)}</p>
      <div>
        <Link href={`${base(key)}?tab=entry`} className={cn(buttonVariants({ size: "sm" }))}>
          <Plus /> {m("list.add_new")}
        </Link>
      </div>
    </div>
  );
}

const tabs = (m, kind, count) => [
  { key: "records", label: m(`tab.${kind}_list`), icon: "Table2", count },
  { key: "entry", label: m(`tab.${kind}_entry`), icon: "Layers" },
  { key: "new", label: m(`tab.${kind}_new`), icon: "Plus" },
];

const challan = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-delivery-challan-gatepass";
  const [rows, o] = await Promise.all([apiFetch("/finishing/sc-challans"), look()]);
  const shown = rows
    .filter((r) => (!q.supplier || r.supplier_id === q.supplier) && (!q.store || r.store_id === q.store) && (!q.status || r.status === q.status) && has(r.challan_no, q.no) && has(r.gate_pass_no, q.gate) && within(r.challan_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), status_label: m(`opt.challan_status.${r.status}`) }));
  return {
    tabs: tabs(m, "challan", rows.length),
    body: () =>
      tab === "entry" ? (
        <ScChallanEditor key={q.doc ?? "new"} challans={rows} options={o} canManage={canManage} load={actions.loadScChallan} save={actions.saveScChallan} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : tab === "new" ? (
        pointer(m, key, "challan")
      ) : (
        listTab(
          m,
          key,
          q,
          [
            { name: "company", label: m("f.company"), type: "select", options: o.companies },
            { name: "supplier", label: m("f.supplier"), type: "select", options: [{ value: "", label: m("f.select_supplier") }, ...o.suppliers] },
            { name: "store", label: m("f.store"), type: "select", options: [{ value: "", label: m("f.select_store") }, ...o.stores] },
            { name: "status", label: m("f.status"), type: "select", options: [any(m), ...opts(m, "challan_status", ["DRAFT", "POSTED", "CANCELLED"])] },
            { name: "no", label: m("f.challan_no"), type: "text" },
            { name: "gate", label: m("f.gate_pass_no"), type: "text" },
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
          ],
          "challan",
          <ScRegister
            rows={shown}
            exportName="sc-delivery-challans"
            refKey="challan_no"
            emptyState={{ title: m("challan.empty", { count: 0 }), text: m("list.empty_hint") }}
            columns={[
              { key: "challan_no", label: m("col.challan_no"), type: "mono" },
              { key: "challan_date", label: m("col.challan_date"), type: "date" },
              { key: "supplier", label: m("col.supplier") },
              { key: "store", label: m("col.store") },
              { key: "gate_pass_no", label: m("col.gate_pass_no") },
              { key: "vehicle_no", label: m("col.vehicle_no") },
              { key: "lines", label: m("col.lines"), type: "number", align: "right" },
              { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
              { key: "received_kg", label: m("col.received_kg"), type: "number", align: "right" },
              { key: "balance_kg", label: m("col.balance_kg"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={[
              { key: "open", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` },
              ...(canManage
                ? [
                    { key: "post", label: m("reg.post"), when: { field: "status", in: ["DRAFT"] }, run: actions.postScChallan, title: m("reg.post_challan_title"), text: m("reg.post_challan_text"), done: m("reg.posted") },
                    { key: "cancel", label: m("reg.cancel"), when: { field: "status", in: ["DRAFT", "POSTED"] }, run: actions.cancelScChallan, title: m("reg.cancel_challan_title"), text: m("reg.cancel_challan_text"), done: m("reg.cancelled"), destructive: true },
                  ]
                : []),
            ]}
          />,
        )
      ),
  };
};

const receipt = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-receive-finished";
  const [rows, o] = await Promise.all([apiFetch("/finishing/sc-receipts"), look()]);
  const shown = rows
    .filter((r) => (!q.party || r.supplier_id === q.party) && (!q.store || r.store_id === q.store) && has(r.mrr_no, q.no) && (!q.date || r.receive_date === q.date) && has(r.challan_no, q.challan) && has(r.batches, q.batch))
    .map((r) => ({ ...blankNulls(r), status_label: m(`opt.receipt_status.${r.status}`) }));
  return {
    tabs: tabs(m, "receipt", rows.length),
    body: () =>
      tab === "entry" ? (
        <ScReceiptEditor key={q.doc ?? "new"} receipts={rows} options={o} canManage={canManage} load={actions.loadScReceipt} save={actions.saveScReceipt} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : tab === "new" ? (
        pointer(m, key, "receipt")
      ) : (
        listTab(
          m,
          key,
          q,
          [
            { name: "party", label: m("f.party"), type: "select", options: [{ value: "", label: m("f.select_party_short") }, ...o.suppliers] },
            { name: "store", label: m("f.store"), type: "select", options: [{ value: "", label: m("f.select_store_short") }, ...o.stores] },
            { name: "no", label: m("f.mrr_no"), type: "text" },
            { name: "date", label: m("receipt.date"), type: "date" },
            { name: "challan", label: m("f.challan_no"), type: "text" },
            { name: "batch", label: m("f.batch_no"), type: "text" },
          ],
          "receipt",
          <ScRegister
            rows={shown}
            exportName="sc-finished-receipts"
            refKey="mrr_no"
            emptyState={{ title: m("receipt.empty"), text: m("list.empty_hint") }}
            columns={[
              { key: "mrr_no", label: m("col.mrr_no"), type: "mono" },
              { key: "receive_date", label: m("col.receive_date"), type: "date" },
              { key: "supplier", label: m("col.party") },
              { key: "store", label: m("col.store") },
              { key: "challan_no", label: m("col.challan_no"), type: "mono" },
              { key: "batches", label: m("col.batch_no") },
              { key: "gate_pass_no", label: m("col.gate_pass_no") },
              { key: "recv_kg", label: m("col.received_kg"), type: "number", align: "right" },
              { key: "short_kg", label: m("col.short_kg"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={[
              { key: "open", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` },
              ...(canManage ? [{ key: "post", label: m("reg.post"), when: { field: "status", in: ["DRAFT"] }, run: actions.postScReceipt, title: m("reg.post_receipt_title"), text: m("reg.post_receipt_text"), done: m("reg.posted") }] : []),
            ]}
          />,
        )
      ),
  };
};

const bill = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-process-bill-entry";
  const [rows, o] = await Promise.all([apiFetch("/finishing/sc-bills"), look()]);
  const shown = rows
    .filter((r) => (!q.party || r.supplier_id === q.party) && (!q.status || r.status === q.status) && has(r.bill_no, q.no) && within(r.bill_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), ref: `${r.bill_no}|${r.party_code ?? ""}`, status_label: m(`opt.bill_status.${r.status}`) }));
  const step = (to, k, when, destructive = false) => ({ key: k, label: m(`reg.${k}`), when: { field: "status", in: when }, run: actions.setScBillStatus.bind(null, to), title: m(`reg.${k}_bill_title`), text: m(`reg.${k}_bill_text`), done: m(`reg.${k}_done`), destructive });
  return {
    tabs: tabs(m, "bill", rows.length),
    body: () =>
      tab === "entry" ? (
        <ScBillEditor key={q.doc ?? "new"} bills={rows} options={o} canManage={canManage} load={actions.loadScBill} save={actions.saveScBill} backHref={`${base(key)}?tab=records`} initialNo={q.doc ? `${q.doc}|${q.party ?? ""}` : null} />
      ) : tab === "new" ? (
        pointer(m, key, "bill")
      ) : (
        listTab(
          m,
          key,
          q,
          [
            { name: "party", label: m("f.party"), type: "select", options: [{ value: "", label: m("f.select_party") }, ...o.suppliers] },
            { name: "status", label: m("f.status"), type: "select", options: [any(m), ...opts(m, "bill_status", ["DRAFT", "SUBMITTED", "COMPLETED", "CANCELLED"])] },
            { name: "no", label: m("f.bill_no"), type: "text" },
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
          ],
          "bill",
          <ScRegister
            rows={shown}
            exportName="sc-process-bills"
            refKey="ref"
            labelKey="bill_no"
            emptyState={{ title: m("bill.empty"), text: m("list.empty_hint") }}
            columns={[
              { key: "bill_no", label: m("col.bill_no"), type: "mono" },
              { key: "bill_date", label: m("col.bill_date"), type: "date" },
              { key: "supplier", label: m("col.party") },
              { key: "ref_bill_no", label: m("col.ref_bill_no") },
              { key: "rev_no", label: m("col.rev_no") },
              { key: "lines", label: m("col.lines"), type: "number", align: "right" },
              { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
              { key: "amount", label: m("col.amount"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={[
              { key: "open", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no&party=:party` },
              ...(canManage ? [step("SUBMITTED", "submit", ["DRAFT"]), step("COMPLETED", "complete", ["SUBMITTED"]), step("CANCELLED", "cancel", ["DRAFT", "SUBMITTED"], true)] : []),
            ]}
          />,
        )
      ),
  };
};

const balance = async ({ searchParams: q = {} }) => {
  const params = new URLSearchParams(Object.entries({ party: q.party, challan: q.challan, open: q.open }).filter(([, v]) => v));
  const [rows, o] = await Promise.all([apiFetch(`/finishing/sc-balance${params.toString() ? `?${params}` : ""}`), look()]);
  return { body: () => <ScPartyBalance key={params.toString()} rows={rows} query={q} options={o} base={base("sc-party-wise-balance")} /> };
};

const SC_SCREENS = {
  "sc-delivery-challan-gatepass": challan,
  "sc-receive-finished": receipt,
  "sc-process-bill-entry": bill,
  "sc-party-wise-balance": balance,
};

export { SC_SCREENS };
