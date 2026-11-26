import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ChemRegister } from "@/components/dyehouse/chem-store";
import { ScProgrammeEditor, ScBatchProgramme, ScChallanEditor, ScReceiptEditor } from "@/components/dyehouse/sc-out-house";
import * as actions from "./actions";

/*
 * Dye House → S/C Dyeing (AnnotechERP 17/09), each laid out as Brickland
 * lays it out: the Out-House Programme, the Dye Batch Programme and the Grey
 * Fabric Indent List, the Delivery Challan, the Floor and Challan Receipts
 * and the Process Bill. A paper named in the address (?doc=) opens on its
 * editor tab.
 */

const look = () => apiFetch("/dyehouse/sc/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/dye-house/${key}`;
const filterBar = (m, key, q, fields) => <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const can = (permissions, p) => permissions.includes(`dyehouse.sc.${p}`);
const STATUSES = ["DRAFT", "SUBMITTED", "PARAMS_UPDATED", "DESPATCHED", "COMPLETED", "CANCELLED"];
const BILL = ["DRAFT", "WAITING_STORE", "APPROVED", "COMPLETED", "CANCELLED"];

const programme = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-out-house-program";
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/sc-programmes"), look()]);
  const shown = rows
    .filter((r) => has(r.program_no, q.no) && within(r.issue_date, q.from, q.to) && (!q.supplier || r.supplier_id === q.supplier) && (!q.store || r.store_id === q.store) && (!q.status || r.status === q.status))
    .map((r) => ({ ...r, status_label: m(`opt.status.${r.status}`) }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m("tab.programme"), icon: "Send" }] : [])],
    body: () =>
      tab === "entry" && canManage ? (
        <ScProgrammeEditor programmes={rows} options={o} canManage={canManage} load={actions.loadScProgramme} save={actions.saveScProgramme} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "from", label: m("f.issue_from"), type: "date" },
            { name: "to", label: m("f.issue_to"), type: "date" },
            { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
            { name: "store", label: m("f.store"), type: "select", options: o.stores },
            { name: "status", label: m("f.status"), type: "select", options: opts(m, "status", STATUSES) },
            { name: "no", label: m("f.program_no"), type: "text", placeholder: "DF-SC-OH-…" },
          ])}
          <ChemRegister
            exportName={key}
            rows={shown}
            refKey="program_no"
            columns={[
              { key: "program_no", label: m("col.program_no"), type: "mono" },
              { key: "issue_date", label: m("col.issue_date"), type: "date" },
              { key: "exp_delivery_date", label: m("col.exp_delivery"), type: "date" },
              { key: "challan_no", label: m("col.challan_no"), type: "mono" },
              { key: "challan_date", label: m("col.challan_date"), type: "date" },
              { key: "company", label: m("col.company") },
              { key: "supplier", label: m("col.supplier") },
              { key: "store", label: m("col.store") },
              { key: "gate_pass_no", label: m("col.gate_pass") },
              { key: "req_kg", label: m("col.req_kg"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "status", in: ["DRAFT", "SUBMITTED", "PARAMS_UPDATED", "DESPATCHED"] } }] : []}
          />
        </>
      ),
  };
};

const batchProgramme = async ({ canManage }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/sc-requisitions"), look()]);
  return { body: () => <ScBatchProgramme requisitions={rows} options={o} canManage={canManage} load={actions.loadScRequisition} save={actions.saveScRequisition} /> };
};

const indentList = async ({ m, permissions = [], searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/sc-requisitions"), look()]);
  const shown = rows
    .filter((r) => (!q.buyer || r.buyer_ids.includes(q.buyer)) && (!q.order || r.order_ids.includes(q.order)) && (!q.supplier || r.supplier_id === q.supplier))
    .filter((r) => !q.status || (q.status === "ISSUED" ? r.status === "ISSUED" : r.status !== "ISSUED"))
    .map((r) => ({ ...r, status_label: m(`opt.req_status.${r.status}`) }));
  return {
    body: () => (
      <>
        {filterBar(m, "grey-fab-requisition-list-dyeing-sc", q, [
          { name: "buyer", label: m("f.buyer"), type: "select", options: o.buyers },
          { name: "order", label: m("f.order_style"), type: "select", options: o.orders },
          { name: "status", label: m("f.status"), type: "select", options: [{ value: "WAITING", label: m("opt.indent.WAITING") }, { value: "ISSUED", label: m("opt.indent.ISSUED") }] },
          { name: "supplier", label: m("f.sc_factory"), type: "select", options: o.suppliers },
        ])}
        <ChemRegister
          exportName="sc-grey-fabric-indent-list"
          rows={shown}
          refKey="req_no"
          columns={[
            { key: "req_no", label: m("col.req_no"), type: "mono" },
            { key: "supplier", label: m("col.sc_factory") },
            { key: "req_date", label: m("col.req_date"), type: "date" },
            { key: "req_kg", label: m("col.req_kg"), type: "number", align: "right" },
            { key: "issued_kg", label: m("col.issue_kg"), type: "number", align: "right" },
            { key: "batch_no", label: m("col.batch_no"), type: "mono" },
            { key: "req_type", label: m("col.req_type") },
            { key: "status_label", label: m("col.status"), type: "badge" },
          ]}
          actions={can(permissions, "issue") ? [{ key: "issue", label: m("reg.issue"), dialog: "issue", when: { field: "status", in: ["WAITING", "PARTIAL"] } }] : []}
          loadIssue={actions.loadScIssue}
          issueStock={actions.issueScRequisition}
        />
      </>
    ),
  };
};

const challan = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "sc-out-house-challan";
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/sc-challans"), look()]);
  const shown = rows
    .filter((r) => within(r.challan_date, q.from, q.to) && (!q.party || (r.party_id ?? q.party) === q.party) && (!q.supplier || r.supplier_id === q.supplier) && (!q.store || r.store_id === q.store))
    .map((r) => ({ ...r, programmes_label: r.programmes.join(", "), open: r.received ? "N" : "Y" }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m("tab.challan"), icon: "Truck" }] : [])],
    body: () =>
      tab === "entry" && canManage ? (
        <ScChallanEditor challans={rows} options={o} canManage={canManage} load={actions.loadScChallan} save={actions.saveScChallan} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "from", label: m("f.challan_from"), type: "date" },
            { name: "to", label: m("f.challan_to"), type: "date" },
            { name: "party", label: m("f.company"), type: "select", options: o.companies },
            { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
            { name: "store", label: m("f.store"), type: "select", options: o.stores },
          ])}
          <ChemRegister
            exportName={key}
            rows={shown}
            refKey="challan_no"
            columns={[
              { key: "challan_no", label: m("col.challan_no"), type: "mono" },
              { key: "challan_date", label: m("col.challan_date"), type: "date" },
              { key: "company", label: m("col.company") },
              { key: "supplier", label: m("col.supplier") },
              { key: "store", label: m("col.store") },
              { key: "gate_pass_no", label: m("col.gate_pass") },
              { key: "programmes_label", label: m("col.programmes") },
            ]}
            actions={canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "open", in: ["Y"] } }] : []}
          />
        </>
      ),
  };
};

function receiptScreen(kind, key) {
  return async ({ m, te, tab, canManage, searchParams: q = {} }) => {
    const [rows, challans, o] = await Promise.all([apiFetch(`/dyehouse/sc-receipts?kind=${kind}`), apiFetch("/dyehouse/sc-challans"), look()]);
    const floor = kind === "FLOOR";
    const shown = rows
      .filter((r) => (!q.supplier || r.supplier_id === q.supplier) && (!q.transfer || r.transfer_party_id === q.transfer) && has(r.challan_no, q.challan) && (!q.program || r.programmes.some((p) => has(p, q.program))))
      .map((r) => ({ ...r, programmes_label: r.programmes.join(", ") }));
    return {
      tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m(`tab.rc.${kind}`), icon: floor ? "ClipboardCheck" : "PackageCheck" }] : [])],
      body: () =>
        tab === "entry" && canManage ? (
          <ScReceiptEditor kind={kind} receipts={rows} challans={challans} options={o} canManage={canManage} load={actions.loadScReceipt} save={actions.saveScReceipt} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
        ) : (
          <>
            {filterBar(m, key, q, [
              { name: "supplier", label: m("f.sc_party"), type: "select", options: o.suppliers },
              { name: "transfer", label: m("f.transfer_party"), type: "select", options: o.suppliers },
              { name: "challan", label: m("f.challan_hash"), type: "text" },
              { name: "program", label: m("f.program_hash"), type: "text" },
            ])}
            <ChemRegister
              exportName={key}
              rows={shown}
              refKey="mrr_no"
              columns={[
                { key: "mrr_no", label: m("col.mrr_no"), type: "mono" },
                { key: "issue_date", label: m("col.issue_date"), type: "date" },
                floor ? { key: "qc_no", label: m("col.qc_no") } : { key: "batch_no", label: m("col.batch_no") },
                { key: "challan_no", label: m("col.challan_no"), type: "mono" },
                { key: "challan_date", label: m("col.challan_date"), type: "date" },
                { key: "supplier", label: m("col.supplier") },
                { key: "transfer_party", label: m("col.transfer_party") },
                { key: "store", label: m("col.store") },
                ...(floor ? [{ key: "programmes_label", label: m("col.program_list") }] : []),
                { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
              ]}
              actions={canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` }] : []}
            />
          </>
        ),
    };
  };
}

const bill = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-sc-bills"), look()]);
  const fields = [
    { name: "supplier_id", label: m("f.party"), type: "select", required: true, options: o.suppliers, placeholder: m("f.select") },
    { name: "bill_date", label: m("f.date"), type: "date", required: true },
    { name: "amount", label: m("f.amount"), type: "number", required: true },
    { name: "ref_bill_id", label: m("f.ref_bill"), type: "select", options: [{ value: "", label: "—" }, ...rows.map((b) => ({ value: b.id, label: `${b.bill_no} · ${b.supplier ?? ""}` }))], placeholder: m("f.select"), help: m("h.ref_bill") },
    { name: "revision_descr", label: m("f.rev_descr"), placeholder: m("h.rev_descr_ph") },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "bill", BILL) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (!q.supplier || r.supplier_id === q.supplier) && within(r.bill_date, q.from, q.to) && (!q.status || r.status === q.status))
    .map((r) => ({ ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])), status_label: m(`opt.bill.${r.status}`), ref: r.bill_no }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.bill"), icon: "ReceiptText" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-sc-bills", [])} title={m("tab.bill")} description={m("h.bill_note")} note={m("saved_note")} submitLabel={m("btn.save_bill")} columns={3} />
      ) : (
        <>
          {filterBar(m, "sc-out-house-bill", q, [
            { name: "supplier", label: m("f.party"), type: "select", options: o.suppliers },
            { name: "from", label: m("f.date_from"), type: "date" },
            { name: "to", label: m("f.date_to"), type: "date" },
            { name: "status", label: m("f.status"), type: "select", options: opts(m, "bill", BILL) },
          ])}
          <RecordsGrid
            exportName="sc-out-house-bill"
            rows={shown}
            emptyState={{ title: m("records.empty_bill"), description: m("records.empty_bill_hint") }}
            columns={[
              { key: "bill_date", label: m("col.date"), type: "date" },
              { key: "bill_no", label: m("col.bill_no"), type: "mono" },
              { key: "ref_bill_no", label: m("col.ref_bill"), type: "mono" },
              { key: "revision_no", label: m("col.rev_no"), type: "number", align: "right" },
              { key: "revision_descr", label: m("col.rev_descr") },
              { key: "supplier", label: m("col.supplier") },
              { key: "amount", label: m("col.amount"), type: "number", align: "right" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-sc-bills", []), refKey: "ref", title: m("tab.bill"), submitLabel: m("btn.save_bill"), columns: 3 } : undefined}
          />
        </>
      ),
  };
};

const SC_SCREENS = {
  "sc-out-house-program": programme,
  "dyeing-sc-program-batch": batchProgramme,
  "grey-fab-requisition-list-dyeing-sc": indentList,
  "sc-out-house-challan": challan,
  "sc-out-house-challan-receive": receiptScreen("STORE", "sc-out-house-challan-receive"),
  "sc-out-house-floor-receive": receiptScreen("FLOOR", "sc-out-house-floor-receive"),
  "sc-out-house-bill": bill,
};

// The screens whose paper opens on its editor when named in the address (?doc=).
const SC_EDITORS = ["sc-out-house-program", "sc-out-house-challan", "sc-out-house-challan-receive", "sc-out-house-floor-receive"];

export { SC_SCREENS, SC_EDITORS };
