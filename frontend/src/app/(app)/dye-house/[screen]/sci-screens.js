import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ScQuotationEditor } from "@/components/dyehouse/sc-quotation-editor";
import * as actions from "./actions";

/*
 * Dye House → S/C In-House (AnnotechERP 17/10), each laid out as Brickland
 * lays it out: the Sub-Contract Order Register (programmes taken in from a
 * party), the In-House Fabric Receipt (the party's fabric received against
 * one), and the In-House Party Rate (17/01's quotation, on the side we charge).
 */

const look = () => apiFetch("/dyehouse/sc/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/dye-house/${key}`;
const filterBar = (m, key, q, fields) => <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const blankNulls = (r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v]));
const STATUSES = ["DRAFT", "SUBMITTED", "PARAMS_UPDATED", "DESPATCHED", "COMPLETED", "CANCELLED"];

const orderRegister = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-order-list";
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-sci-programmes"), look()]);
  const fields = [
    { name: "prog_date", label: m("f.prog_date"), type: "date", required: true },
    { name: "supplier_id", label: m("f.party"), type: "select", required: true, options: o.suppliers, placeholder: m("f.select") },
    { name: "buyer_id", label: m("f.buyer_name"), type: "select", options: [{ value: "", label: "—" }, ...o.buyers], placeholder: m("f.select") },
    { name: "order_id", label: m("f.order_no"), type: "select", options: [{ value: "", label: "—" }, ...o.orders], placeholder: m("f.select"), help: m("h.order") },
    { name: "dyeing", label: m("f.dyeing"), type: "checkbox", help: m("h.process") },
    { name: "dyeing_finishing", label: m("f.dyeing_finishing"), type: "checkbox" },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "status", STATUSES) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (!q.supplier || r.supplier_id === q.supplier) && has(r.program_no, q.no) && has(r.order_no, q.order) && within(r.prog_date, q.from, q.to) && (!q.status || r.status === q.status))
    .map((r) => ({
      ...blankNulls(r),
      process: [r.dyeing ? m("f.dyeing") : null, r.dyeing_finishing ? m("f.dyeing_finishing") : null].filter(Boolean).join(", "),
      status_label: m(`opt.status.${r.status}`),
      ref: r.program_no,
    }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_program"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-sci-programmes", ["dyeing", "dyeing_finishing"])} title={m("t.new_programme")} description={m("h.programme_note")} note={m("saved_note")} submitLabel={m("tab.add_program")} columns={3} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
            { name: "no", label: m("f.program_no"), type: "text", placeholder: "DFSCI-…" },
            { name: "order", label: m("f.order_no"), type: "text" },
            { name: "from", label: m("f.prog_from"), type: "date" },
            { name: "to", label: m("f.prog_to"), type: "date" },
            { name: "status", label: m("f.status"), type: "select", options: opts(m, "status", STATUSES) },
          ])}
          <RecordsGrid
            exportName="sc-order-register"
            rows={shown}
            emptyState={{ title: m("records.empty_programme"), description: m("records.empty_programme_hint") }}
            columns={[
              { key: "program_no", label: m("col.program_no"), type: "mono" },
              { key: "supplier", label: m("col.supplier") },
              { key: "order_no", label: m("col.order_no") },
              { key: "style", label: m("col.style_no") },
              { key: "buyer", label: m("col.buyer_name") },
              { key: "process", label: m("col.process_name") },
              { key: "prog_date", label: m("col.prog_date"), type: "date" },
              { key: "received_kg", label: m("col.received_kg"), type: "number", align: "right" },
              { key: "remarks", label: m("col.remarks") },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-sci-programmes", ["dyeing", "dyeing_finishing"]), refKey: "ref", title: m("t.new_programme"), submitLabel: m("tab.add_program"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-sci-programmes") : undefined}
          />
        </>
      ),
  };
};

const fabricReceipt = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-in-house-fabric-receive";
  const [rows, programmes, o] = await Promise.all([apiFetch("/erp/dye-sci-receipts"), apiFetch("/erp/dye-sci-programmes"), look()]);
  const live = programmes.filter((p) => !["CANCELLED", "COMPLETED"].includes(p.status));
  const fields = [
    { name: "challan_no", label: m("f.challan_no"), required: true, help: m("h.challan_no") },
    { name: "challan_date", label: m("f.challan_date"), type: "date", required: true },
    { name: "programme_id", label: m("f.program_no"), type: "select", required: true, options: live.map((p) => ({ value: p.id, label: `${p.program_no} · ${p.supplier ?? ""}` })), placeholder: m("f.select"), help: m("h.programme") },
    { name: "store_id", label: m("f.store"), type: "select", options: [{ value: "", label: "—" }, ...o.stores], placeholder: m("f.select_store") },
    { name: "qty_kg", label: m("f.qty_kg"), type: "number", required: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => has(r.challan_no, q.no) && (!q.supplier || r.supplier_id === q.supplier) && (!q.program || r.programme_id === q.program) && within(r.challan_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), ref: `${r.supplier}-${r.challan_no}` }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-sci-receipts", [])} title={m("t.new_receipt")} description={m("h.receipt_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.challan_no"), type: "text" },
            { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
            { name: "program", label: m("f.program_no"), type: "select", options: programmes.map((p) => ({ value: p.id, label: p.program_no })) },
            { name: "from", label: m("f.challan_from"), type: "date" },
            { name: "to", label: m("f.challan_to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="sc-in-house-fabric-receipt"
            rows={shown}
            emptyState={{ title: m("records.empty_receipt"), description: m("records.empty_receipt_hint") }}
            columns={[
              { key: "challan_no", label: m("col.challan_no"), type: "mono" },
              { key: "challan_date", label: m("col.challan_date"), type: "date" },
              { key: "store", label: m("col.store_name") },
              { key: "supplier", label: m("col.supplier") },
              { key: "program_no", label: m("col.program_no"), type: "mono" },
              { key: "prog_date", label: m("col.prog_date"), type: "date" },
              { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
              { key: "remarks", label: m("col.remarks") },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-sci-receipts", []), refKey: "ref", title: m("t.new_receipt"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-sci-receipts") : undefined}
          />
        </>
      ),
  };
};

const partyRate = async ({ canManage, permissions = [], searchParams: q = {} }) => {
  const [quotations, look2] = await Promise.all([apiFetch("/dyehouse/sc-quotations?side=IN"), apiFetch("/dyehouse/lookups")]);
  return {
    body: () => (
      <ScQuotationEditor
        side="IN"
        quotations={quotations}
        parties={look2.suppliers}
        currencies={look2.currencies}
        loadHeads={actions.loadHeads}
        load={actions.loadQuotation}
        save={actions.saveQuotation}
        canManage={canManage || permissions.includes("dyehouse.master.manage")}
        initialId={q.quotation ?? null}
      />
    ),
  };
};

const SCI_SCREENS = {
  "sc-order-list": orderRegister,
  "sc-in-house-fabric-receive": fabricReceipt,
  "dyeing-sc-party-rate-in-house": partyRate,
};

export { SCI_SCREENS };
