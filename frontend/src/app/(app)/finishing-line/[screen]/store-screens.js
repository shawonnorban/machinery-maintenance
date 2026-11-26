import Link from "next/link";
import { Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ScRegister } from "@/components/finishing/sc-process";
import { FinStoreDeliveryEditor, FinCutRequisitionEditor } from "@/components/finishing/store-process";
import * as actions from "./actions";

/*
 * Finishing Line → Fabric Store (AnnotechERP 18/05), each laid out as
 * Brickland lays it out: the Finished Fabric Store Receipt (Delivery List,
 * the entry tab, and the tab that points to it), Roll-Wise Racking &
 * Barcode, Fabric Issue to Cutting Floor (the same three tabs) and Fabric
 * Store Return & Transfer. Brickland left racking and return/transfer as
 * scaffolds; they are built as its other registers are.
 */

const look = () => apiFetch("/finishing/store/options");
const opts = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const all = (m) => ({ value: "", label: m("f.all") });
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/finishing-line/${key}`;
const blankNulls = (r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v]));
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const unique = (list) => list.filter((o, i) => list.findIndex((x) => x.value === o.value) === i);

const tabs3 = (m, kind, count) => [
  { key: "records", label: m(`tab.${kind}_list`), icon: "Table2", count },
  { key: "entry", label: m(`tab.${kind}_entry`), icon: "Layers" },
  { key: "new", label: m(`tab.${kind}_new`), icon: "Plus" },
];

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

function listTab(m, key, q, title, hint, fields, register) {
  return (
    <div className="flex flex-col gap-3">
      <FilterBar basePath={base(key)} keep={{ tab: "records" }} values={q} title={title} hint={hint} labels={{ show: m("list.show"), reset: m("list.reset") }} fields={fields} />
      <div className="flex justify-end">
        <Link href={`${base(key)}?tab=entry`} className={cn(buttonVariants({ size: "sm" }))}>
          <Plus /> {m("list.add_new")}
        </Link>
      </div>
      {register}
    </div>
  );
}

const storeReceipt = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "finish-fabric-store-receive";
  const [rows, o] = await Promise.all([apiFetch("/finishing/store-deliveries"), look()]);
  const shown = rows
    .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.order_id === q.order) && (!q.store || r.store_id === q.store) && (!q.date || r.receive_date === q.date) && has(r.batches, q.batch))
    .map((r) => ({ ...blankNulls(r), status_label: m(`opt.delivery_status.${r.status}`) }));
  return {
    tabs: tabs3(m, "delivery", rows.length),
    body: () =>
      tab === "entry" ? (
        <FinStoreDeliveryEditor key={q.doc ?? "new"} deliveries={rows} options={o} canManage={canManage} load={actions.loadStoreDelivery} save={actions.saveStoreDelivery} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : tab === "new" ? (
        pointer(m, key, "delivery")
      ) : (
        listTab(
          m,
          key,
          q,
          m("list.search"),
          m("delivery.list_hint"),
          [
            { name: "buyer", label: m("f.buyer"), type: "select", options: [all(m), ...o.buyers] },
            { name: "order", label: m("f.order"), type: "select", options: [all(m), ...o.orders] },
            { name: "store", label: m("f.store"), type: "select", options: [{ value: "", label: m("f.select_store") }, ...o.stores] },
            { name: "date", label: m("f.date"), type: "date" },
            { name: "batch", label: m("f.batch_hash"), type: "text" },
          ],
          <ScRegister
            rows={shown}
            exportName="finished-fabric-store-deliveries"
            refKey="delivery_no"
            emptyState={{ title: m("delivery.empty"), text: m("list.empty_hint") }}
            columns={[
              { key: "delivery_no", label: m("col.delivery_no"), type: "mono" },
              { key: "receive_date", label: m("col.date"), type: "date" },
              { key: "store", label: m("col.store") },
              { key: "buyer", label: m("col.buyer") },
              { key: "order_no", label: m("col.order_no") },
              { key: "style", label: m("col.style") },
              { key: "batches", label: m("col.batch_no") },
              { key: "lots", label: m("col.lots"), type: "number", align: "right" },
              { key: "grey_qty", label: m("col.grey_qty"), type: "number", align: "right" },
              { key: "finish_qty", label: m("col.finish_qty"), type: "number", align: "right" },
              { key: "rolls", label: m("col.rolls"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={[
              { key: "open", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` },
              ...(canManage
                ? [
                    { key: "send", label: m("reg.send"), when: { field: "status", in: ["DRAFT"] }, run: actions.sendStoreDelivery, title: m("reg.send_title"), text: m("reg.send_text"), done: m("reg.sent") },
                    { key: "receive", label: m("reg.receive"), when: { field: "status", in: ["SENT"] }, run: actions.receiveStoreDelivery, title: m("reg.receive_title"), text: m("reg.receive_text"), done: m("reg.received") },
                  ]
                : []),
            ]}
          />,
        )
      ),
  };
};

const racking = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "roll-wise-racking-barcode";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-store-rolls"), look()]);
  const fields = (lots) => [
    { name: "lot_id", label: m("f.lot"), type: "select", required: true, options: lots, placeholder: m("f.select"), help: m("h.lot") },
    { name: "roll_no", label: m("f.roll_no"), required: true, help: m("h.roll_no") },
    { name: "weight_kg", label: m("f.weight_kg"), type: "number", step: "0.001", required: true },
    { name: "rack", label: m("f.rack"), placeholder: "R-A1" },
    { name: "bin", label: m("f.bin"), placeholder: "B-03" },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (has(r.barcode, q.no) || has(r.roll_no, q.no) || has(r.lot_code, q.no)) && has(r.rack, q.rack) && (!q.status || r.status === q.status))
    .map((r) => ({ ...blankNulls(r), status_label: m(`opt.roll_status.${r.status}`), ref: r.barcode }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields(o.lots)} action={actions.createResource.bind(null, "fin-store-rolls", [])} title={m("t.new_roll")} description={m("h.roll_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("list.show"), reset: m("list.clear") }} fields={[
            { name: "no", label: m("f.barcode_search"), type: "text", placeholder: m("f.scan") },
            { name: "rack", label: m("f.rack"), type: "text" },
            { name: "status", label: m("f.status"), type: "select", options: [all(m), ...opts(m, "roll_status", ["IN_STORE", "ISSUED"])] },
          ]} />
          <RecordsGrid
            exportName="roll-wise-racking"
            rows={shown}
            emptyState={{ title: m("records.empty_roll"), text: m("records.empty_hint") }}
            columns={[
              { key: "barcode", label: m("col.barcode"), type: "mono" },
              { key: "lot_code", label: m("col.lot_code"), type: "mono" },
              { key: "lot_no", label: m("col.lot_no") },
              { key: "colour", label: m("col.colour") },
              { key: "order_no", label: m("col.order_no") },
              { key: "store", label: m("col.store") },
              { key: "roll_no", label: m("col.roll_no"), type: "mono" },
              { key: "weight_kg", label: m("col.weight_kg"), type: "number", align: "right" },
              { key: "rack", label: m("col.rack") },
              { key: "bin", label: m("col.bin") },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            edit={canManage ? { fields: fields(unique([...o.lots, ...rows.map((r) => ({ value: r.lot_id, label: `${r.lot_code} · ${r.lot_no ?? "—"}` }))])), action: actions.updateResource.bind(null, "fin-store-rolls", []), refKey: "ref", title: m("t.new_roll"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-store-rolls") : undefined}
          />
        </>
      ),
  };
};

const issueToCutting = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "fabric-issue-to-cutting";
  const [rows, o] = await Promise.all([apiFetch("/finishing/cut-requisitions"), look()]);
  const styles = [...new Set(rows.map((r) => r.style).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
  const shown = rows
    .filter((r) => within(r.req_date, q.from, q.to) && (!q.buyer || r.buyer_id === q.buyer) && (!q.style || r.style === q.style) && (!q.store || r.store_id === q.store) && has(r.batch_no, q.batch) && (!q.status || r.status === q.status))
    .map((r) => ({ ...blankNulls(r), status_label: m(`opt.req_status.${r.status}`) }));
  return {
    tabs: tabs3(m, "req", rows.length),
    body: () =>
      tab === "entry" ? (
        <FinCutRequisitionEditor key={q.doc ?? "new"} requisitions={rows} options={o} canManage={canManage} load={actions.loadCutRequisition} save={actions.saveCutRequisition} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : tab === "new" ? (
        pointer(m, key, "req")
      ) : (
        listTab(
          m,
          key,
          q,
          m("list.searching"),
          m("req.list_hint"),
          [
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
            { name: "buyer", label: m("f.buyer"), type: "select", options: [all(m), ...o.buyers] },
            { name: "style", label: m("f.work_style"), type: "select", options: [all(m), ...styles] },
            { name: "store", label: m("f.store"), type: "select", options: [{ value: "", label: m("f.select_store") }, ...o.stores] },
            { name: "batch", label: m("f.batch_no"), type: "text" },
            { name: "status", label: m("f.status"), type: "select", options: [all(m), ...opts(m, "req_status", ["DRAFT", "PARTIAL", "COMPLETE", "CANCELLED"])] },
          ],
          <ScRegister
            rows={shown}
            exportName="fabric-issue-to-cutting"
            refKey="req_no"
            emptyState={{ title: m("req.empty"), text: m("list.empty_hint") }}
            columns={[
              { key: "req_no", label: m("col.req_no"), type: "mono" },
              { key: "req_date", label: m("col.date"), type: "date" },
              { key: "cut_prog_ref", label: m("col.cut_prog_ref") },
              { key: "store", label: m("col.store") },
              { key: "buyer", label: m("col.buyer") },
              { key: "style", label: m("col.style") },
              { key: "colour", label: m("col.colour") },
              { key: "batch_no", label: m("col.batch_no") },
              { key: "req_qty", label: m("col.req_qty"), type: "number", align: "right" },
              { key: "issue_qty", label: m("col.issue_qty"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={[{ key: "open", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` }]}
          />,
        )
      ),
  };
};

const returnTransfer = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "fabric-store-return-transfer";
  const [rows, o, reqs] = await Promise.all([apiFetch("/erp/fin-store-returns"), look(), apiFetch("/finishing/cut-requisitions")]);
  const issued = reqs.filter((r) => ["PARTIAL", "COMPLETE"].includes(r.status)).map((r) => ({ value: r.id, label: `${r.req_no} · ${r.store ?? ""}` }));
  // Fabric by length (2026-11-16): yards or metres, the company's; the kilos follow from the lot when not given.
  const u = m(`len.${o.lots?.[0]?.len_unit ?? rows[0]?.len_unit ?? "YDS"}`);
  const fields = [
    { name: "return_date", label: m("f.return_date"), type: "date", required: true },
    { name: "kind", label: m("f.kind"), type: "select", required: true, options: opts(m, "kind", ["RETURN", "TRANSFER"]), help: m("h.kind") },
    { name: "lot_id", label: m("f.lot"), type: "select", required: true, options: unique([...o.lots, ...rows.map((r) => ({ value: r.lot_id, label: `${r.lot_code} · ${r.lot_no ?? "—"}` }))]), placeholder: m("f.select") },
    { name: "requisition_id", label: m("f.requisition"), type: "select", options: [{ value: "", label: "—" }, ...issued], placeholder: m("f.select"), help: m("h.requisition") },
    { name: "qty_len", label: m("f.qty_len", { u }), type: "number", step: "0.01", help: m("h.qty_len") },
    { name: "qty_kg", label: m("f.qty_kg"), type: "number", step: "0.001" },
    { name: "rolls", label: m("f.rolls"), type: "number" },
    { name: "to_unit", label: m("f.to_unit"), help: m("h.to_unit") },
    { name: "reason", label: m("f.reason") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (has(r.return_no, q.no) || has(r.lot_code, q.no)) && (!q.kind || r.kind === q.kind) && within(r.return_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), kind_label: m(`opt.kind.${r.kind}`), ref: r.return_no }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "fin-store-returns", [])} title={m("t.new_return")} description={m("h.return_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("list.show"), reset: m("list.clear") }} fields={[
            { name: "no", label: m("f.return_search"), type: "text", placeholder: "FSR-… / FSL-…" },
            { name: "kind", label: m("f.kind"), type: "select", options: [all(m), ...opts(m, "kind", ["RETURN", "TRANSFER"])] },
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
          ]} />
          <RecordsGrid
            exportName="fabric-store-returns-transfers"
            rows={shown}
            emptyState={{ title: m("records.empty_return"), text: m("records.empty_hint") }}
            columns={[
              { key: "return_no", label: m("col.return_no"), type: "mono" },
              { key: "return_date", label: m("col.date"), type: "date" },
              { key: "kind_label", label: m("col.kind"), type: "badge" },
              { key: "lot_code", label: m("col.lot_code"), type: "mono" },
              { key: "lot_no", label: m("col.lot_no") },
              { key: "colour", label: m("col.colour") },
              { key: "req_no", label: m("col.req_no") },
              { key: "to_unit", label: m("col.to_unit") },
              { key: "qty_len", label: m("col.qty_len", { u }), type: "number", align: "right" },
              { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
              { key: "rolls", label: m("col.rolls"), type: "number", align: "right" },
              { key: "balance_len", label: m("col.balance_len", { u }), type: "number", align: "right" },
              { key: "balance_kg", label: m("col.balance_kg"), type: "number", align: "right" },
              { key: "reason", label: m("col.reason") },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "fin-store-returns", []), refKey: "ref", title: m("t.new_return"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-store-returns") : undefined}
          />
        </>
      ),
  };
};

const STORE_SCREENS = {
  "finish-fabric-store-receive": storeReceipt,
  "roll-wise-racking-barcode": racking,
  "fabric-issue-to-cutting": issueToCutting,
  "fabric-store-return-transfer": returnTransfer,
};

export { STORE_SCREENS };
