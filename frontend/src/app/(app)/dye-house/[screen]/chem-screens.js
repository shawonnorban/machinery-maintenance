import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ChemReceiptEditor, ChemReturnEditor, ChemIssueEditor, ChemCsEditor, ChemRegister } from "@/components/dyehouse/chem-store";
import * as actions from "./actions";

/*
 * Dye House → Chemical Store (AnnotechERP 17/06), each laid out as Brickland
 * lays it out: the stock and loan opening balances, the receipts (bought,
 * borrowed, a loan coming back), the return to supplier, Chemical Item Stock,
 * every issue paper (transfer, print, machine wash & finishing, batch
 * programme, sales, loan issue, loan return), the comparative statement and
 * the month closing. A paper named in the address (?doc=) opens on its
 * editor tab.
 */

const look = () => apiFetch("/dyehouse/chem-store/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/dye-house/${key}`;
const yes = (m, v) => (v ? m("opt.yes") : m("opt.no"));
const filterBar = (m, key, q, fields) => <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const can = (permissions, p) => permissions.includes(`dyehouse.chemstore.${p}`);
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const unique = (rows, value, label) => [...new Map(rows.filter((r) => r[value]).map((r) => [r[value], { value: r[value], label: r[label] ?? r[value] }])).values()];

/* ── Opening balances ─────────────────────────────────────────────── */

const openingBalance = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-chem-openings"), look()]);
  const fields = [
    { name: "party_id", label: m("f.company"), type: "select", required: true, options: o.companies, default: o.companies[0]?.value, placeholder: m("f.select_company") },
    { name: "store_id", label: m("f.receiving_store"), type: "select", required: true, options: o.stores, placeholder: m("f.select_store") },
    { name: "ob_date", label: m("f.ob_date"), type: "date", required: true },
    { name: "category_id", label: m("f.item_category"), type: "select", options: o.categories, placeholder: m("f.select_category") },
    { name: "usd_rate", label: m("f.usd_rate"), type: "number", placeholder: "123" },
    { name: "item_id", label: m("f.item_name"), type: "select", required: true, options: o.chemicals, placeholder: m("f.select_item") },
    { name: "source", label: m("f.source"), type: "select", required: true, options: opts(m, "source", ["LOCAL", "IMPORT"]) },
    { name: "auto_lot", label: m("f.auto_lot"), type: "checkbox", help: m("h.auto_lot") },
    { name: "lot_no", label: m("f.lot"), placeholder: "1102250014", help: m("h.lot_once") },
    { name: "exp_date", label: m("f.exp_date"), type: "date" },
    { name: "pack_qty", label: m("f.pack_qty"), type: "number" },
    { name: "pack_uom", label: m("f.pack_uom"), type: "uom", placeholder: m("f.select_pack_uom") },
    { name: "qty_per_pack", label: m("f.qty_per_pack"), type: "number" },
    { name: "loose_qty", label: m("f.loose_qty"), type: "number" },
    { name: "total_qty", label: m("f.total_qty"), type: "number", help: m("h.total_qty") },
    { name: "uom", label: m("f.uom"), type: "uom", placeholder: m("f.select_uom") },
    { name: "rate_bdt", label: m("f.rate_bdt"), type: "number", help: m("h.ob_rate") },
  ];
  const shown = rows
    .filter((r) => (!q.store || r.store_id === q.store) && has(r.item, q.item) && has(r.lot_no, q.lot) && within(r.ob_date, q.from, q.to))
    .map((r) => ({ ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])), source_label: m(`opt.source.${r.source}`), ref: `${r.lot_no}` }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.save"), icon: "Save" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-chem-openings", ["auto_lot"])} title={m("t.new_ob")} description={m("h.ob_note")} note={m("saved_note")} submitLabel={m("tab.save")} columns={3} />
      ) : (
        <>
          {filterBar(m, "stock-opening-balance", q, [
            { name: "store", label: m("f.receiving_store"), type: "select", options: o.stores },
            { name: "item", label: m("f.item_name"), type: "text", placeholder: "Caustic Soda" },
            { name: "lot", label: m("f.lot"), type: "text", placeholder: "1102250014" },
            { name: "from", label: m("f.ob_from"), type: "date" },
            { name: "to", label: m("f.ob_to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="chemical-opening-balance"
            rows={shown}
            columns={[
              { key: "store", label: m("col.store") },
              { key: "ob_date", label: m("col.ob_date"), type: "date" },
              { key: "category", label: m("col.category") },
              { key: "item", label: m("col.item") },
              { key: "source_label", label: m("col.source") },
              { key: "lot_no", label: m("col.lot"), type: "mono" },
              { key: "exp_date", label: m("col.exp_date"), type: "date" },
              { key: "pack_qty", label: m("col.pack_qty"), type: "number", align: "right" },
              { key: "qty_per_pack", label: m("col.qty_per_pack"), type: "number", align: "right" },
              { key: "loose_qty", label: m("col.loose_qty"), type: "number", align: "right" },
              { key: "total_qty", label: m("col.total_qty"), type: "number", align: "right" },
              { key: "uom", label: m("col.uom") },
              { key: "rate_bdt", label: m("col.rate_bdt"), type: "number", align: "right" },
              { key: "value", label: m("col.value"), type: "number", align: "right" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-chem-openings", ["auto_lot"]), refKey: "ref", title: m("t.new_ob"), submitLabel: m("tab.save"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-chem-openings") : undefined}
          />
        </>
      ),
  };
};

const loanOpening = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-chem-loan-openings"), look()]);
  const fields = [
    { name: "ob_date", label: m("f.ob_date"), type: "date", required: true },
    { name: "supplier_id", label: m("f.supplier_name"), type: "select", required: true, options: o.suppliers, placeholder: m("f.select_supplier") },
    { name: "category_id", label: m("f.category"), type: "select", options: o.categories, placeholder: m("f.select_category") },
    { name: "item_id", label: m("f.dyes_chemical"), type: "select", required: true, options: o.chemicals, placeholder: m("f.select_item") },
    { name: "loan_received", label: m("f.loan_received"), type: "number", help: m("h.loan_received") },
    { name: "loan_issued", label: m("f.loan_issued"), type: "number", help: m("h.loan_issued") },
    { name: "uom", label: m("f.uom"), type: "uom", placeholder: m("f.select_uom") },
    { name: "is_final", label: m("f.is_final"), type: "checkbox", help: m("h.is_final") },
  ];
  const shown = rows
    .filter((r) => (!q.supplier || r.supplier_id === q.supplier) && has(r.item, q.item) && within(r.ob_date, q.from, q.to))
    .map((r) => ({ ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])), final_label: yes(m, r.is_final), ref: `${r.supplier}-${r.item}` }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.save"), icon: "Save" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-chem-loan-openings", ["is_final"])} title={m("t.new_loan_ob")} description={m("h.loan_ob_note")} note={m("saved_note")} submitLabel={m("tab.save")} columns={3} />
      ) : (
        <>
          {filterBar(m, "loan-opening-balance", q, [
            { name: "supplier", label: m("f.supplier_name"), type: "select", options: o.suppliers },
            { name: "item", label: m("f.dyes_chemical"), type: "text", placeholder: "Caustic Soda" },
            { name: "from", label: m("f.ob_from"), type: "date" },
            { name: "to", label: m("f.ob_to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="chemical-loan-opening-balance"
            rows={shown}
            columns={[
              { key: "ob_date", label: m("col.ob_date"), type: "date" },
              { key: "supplier", label: m("col.supplier") },
              { key: "item", label: m("col.item") },
              { key: "uom", label: m("col.uom") },
              { key: "loan_received", label: m("col.loan_received"), type: "number", align: "right" },
              { key: "loan_issued", label: m("col.loan_issued"), type: "number", align: "right" },
              { key: "final_label", label: m("col.is_final"), type: "badge" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-chem-loan-openings", ["is_final"]), refKey: "ref", title: m("t.new_loan_ob"), submitLabel: m("tab.save"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-chem-loan-openings") : undefined}
          />
        </>
      ),
  };
};

/* ── Receipts ─────────────────────────────────────────────────────── */

const RC_STATUS = ["DRAFT", "RECEIVED", "VERIFIED", "CANCELLED"];

function receiptScreen(kind, key) {
  return async ({ m, te, tab, canManage, permissions = [], searchParams: q = {} }) => {
    const [rows, o] = await Promise.all([apiFetch(`/dyehouse/chem-receipts?kind=${kind}`), look()]);
    const back = kind === "LOAN_BACK";
    const shown = rows
      .filter((r) => has(r.doc_no, q.no) && within(r.doc_date, q.from, q.to) && (!q.supplier || r.supplier_id === q.supplier) && (!q.store || r.store_id === q.store) && (!q.status || r.status === q.status))
      .filter((r) => (!q.source || r.source === q.source) && has(r.challan_no, q.challan) && has(r.lc_no, q.lc) && has(r.pr_no, q.pr) && has(r.po_no, q.po) && (!q.pay || r.pay_method === q.pay) && (!q.reqtype || r.req_type === q.reqtype))
      .map((r) => ({ ...r, status_label: m(`opt.rc_status.${r.status}`), finalized_label: yes(m, r.finalized), accounted_label: yes(m, r.accounted), source_label: r.source ? m(`opt.source.${r.source}`) : null, return_label: r.return_type ? m(`opt.loan_back.${r.return_type}`) : null }));
    const issue = can(permissions, "issue") || canManage;
    const actionsFor = [
      ...(canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "status", in: ["DRAFT"] } }] : []),
      ...(issue ? [{ key: "finalise", label: m("reg.finalise"), title: m("reg.finalise_title", { no: ":no" }), text: back ? m("reg.finalise_back_text") : m("reg.finalise_text"), done: m("reg.finalised", { no: ":no" }), run: actions.finaliseChemReceipt, when: { field: "status", in: ["DRAFT"] } }] : []),
      ...(kind === "PURCHASE" && can(permissions, "approve") ? [{ key: "verify", label: m("reg.verify"), title: m("reg.verify_title", { no: ":no" }), text: m("reg.verify_text"), done: m("reg.verified", { no: ":no" }), run: actions.verifyChemReceipt, when: { field: "status", in: ["RECEIVED"] } }] : []),
      ...(issue ? [{ key: "cancel", label: m("reg.cancel"), title: m("reg.cancel_title", { no: ":no" }), text: m("reg.cancel_rc_text"), done: m("reg.cancelled", { no: ":no" }), run: actions.cancelChemReceipt, when: { field: "status", in: ["DRAFT"] }, destructive: true }] : []),
    ];
    const columns = {
      PURCHASE: [
        { key: "doc_no", label: m("col.mrr_no"), type: "mono" },
        { key: "doc_date", label: m("col.mrr_dt"), type: "date" },
        { key: "challan_no", label: m("col.challan_no") },
        { key: "company", label: m("col.company") },
        { key: "supplier", label: m("col.supplier") },
        { key: "store", label: m("col.store") },
        { key: "status_label", label: m("col.req_status"), type: "badge" },
        { key: "finalized_label", label: m("col.is_finalized"), type: "badge" },
        { key: "accounted_label", label: m("col.is_accounting"), type: "badge" },
        { key: "items", label: m("col.items"), type: "number", align: "right" },
      ],
      LOAN_IN: [
        { key: "doc_no", label: m("col.mrr_no"), type: "mono" },
        { key: "doc_date", label: m("col.mrr_date"), type: "date" },
        { key: "company", label: m("col.company") },
        { key: "store", label: m("col.rcv_store") },
        { key: "supplier", label: m("col.supplier") },
        { key: "pay_method", label: m("col.pay_method") },
        { key: "source_label", label: m("col.source_type") },
        { key: "challan_no", label: m("col.challan_no") },
        { key: "lc_no", label: m("col.lc") },
        { key: "status_label", label: m("col.status"), type: "badge" },
      ],
      LOAN_BACK: [
        { key: "doc_no", label: m("col.rtn_challan_no"), type: "mono" },
        { key: "doc_date", label: m("col.rtn_date"), type: "date" },
        { key: "company", label: m("col.company") },
        { key: "store", label: m("col.store") },
        { key: "supplier", label: m("col.supplier_name") },
        { key: "return_label", label: m("col.return_type") },
        { key: "received_by", label: m("col.received_by") },
        { key: "status_label", label: m("col.status"), type: "badge" },
      ],
    }[kind];
    const filters = {
      PURCHASE: [
        { name: "no", label: m("f.mrr_no"), type: "text", placeholder: "MRR-2026080001" },
        { name: "from", label: m("f.mrr_from"), type: "date" },
        { name: "to", label: m("f.mrr_to"), type: "date" },
        { name: "reqtype", label: m("f.req_type"), type: "select", options: unique(rows, "req_type", "req_type") },
        { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
        { name: "pay", label: m("f.pay_method"), type: "select", options: unique(rows, "pay_method", "pay_method") },
        { name: "store", label: m("f.store"), type: "select", options: o.stores },
        { name: "source", label: m("f.source_type"), type: "select", options: opts(m, "source", ["LOCAL", "IMPORT"]) },
        { name: "challan", label: m("f.challan_no"), type: "text" },
        { name: "lc", label: m("f.lc_no"), type: "text" },
        { name: "pr", label: m("f.pr"), type: "text" },
        { name: "po", label: m("f.po"), type: "text" },
        { name: "status", label: m("f.status"), type: "select", options: opts(m, "rc_status", RC_STATUS) },
      ],
      LOAN_IN: [
        { name: "no", label: m("f.mrr_no"), type: "text", placeholder: "MRR-DC-2026080001" },
        { name: "from", label: m("f.month_from"), type: "date" },
        { name: "to", label: m("f.month_to"), type: "date" },
        { name: "pay", label: m("f.pay_method"), type: "select", options: unique(rows, "pay_method", "pay_method") },
        { name: "source", label: m("f.source_type"), type: "select", options: opts(m, "source", ["LOCAL", "IMPORT"]) },
        { name: "challan", label: m("f.challan_no"), type: "text" },
        { name: "lc", label: m("f.lc_no"), type: "text" },
      ],
      LOAN_BACK: [
        { name: "no", label: m("f.challan_hash"), type: "text", placeholder: "RTN-LP-2026080001" },
        { name: "from", label: m("f.month_from"), type: "date" },
        { name: "to", label: m("f.month_to"), type: "date" },
        { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
        { name: "store", label: m("f.store"), type: "select", options: o.stores },
      ],
    }[kind];
    return {
      tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m(`tab.rc.${kind}`), icon: "PackagePlus" }] : [])],
      body: () =>
        tab === "entry" && canManage ? (
          <ChemReceiptEditor kind={kind} receipts={rows} options={o} canManage={canManage} load={actions.loadChemReceipt} save={actions.saveChemReceipt} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
        ) : (
          <>
            {filterBar(m, key, q, filters)}
            <ChemRegister exportName={key} rows={shown} columns={columns} refKey="doc_no" actions={actionsFor} />
          </>
        ),
    };
  };
}

const receiptReturn = async ({ m, te, tab, canManage, permissions = [], searchParams: q = {} }) => {
  const [rows, receipts, o] = await Promise.all([apiFetch("/dyehouse/chem-returns"), apiFetch("/dyehouse/chem-receipts?kind=PURCHASE"), look()]);
  const counted = receipts.filter((r) => r.finalized);
  const issue = can(permissions, "issue") || canManage;
  const key = "daily-dyes-chemical-receive-return";
  const shown = rows
    .filter((r) => has(r.return_no, q.no) && has(r.mrr_no, q.mrr) && (!q.type || r.return_type === q.type) && (!q.supplier || r.supplier_id === q.supplier) && (!q.store || r.store_id === q.store) && within(r.return_date, q.from, q.to))
    .map((r) => ({ ...r, type_label: m(`opt.rt_type.${r.return_type}`), finalized_label: yes(m, r.finalized), status_label: m(`opt.rt_status.${r.status}`) }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m("tab.return"), icon: "Undo2" }] : [])],
    body: () =>
      tab === "entry" && canManage ? (
        <ChemReturnEditor returns={rows} receipts={counted} options={o} canManage={canManage} load={actions.loadChemReturn} save={actions.saveChemReturn} loadSource={actions.loadChemReturnSource} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.challan_no"), type: "text", placeholder: "RTN-2026080001" },
            { name: "mrr", label: m("f.mrr_no"), type: "text" },
            { name: "type", label: m("f.return_type"), type: "select", options: opts(m, "rt_type", ["SUPPLIER", "DAMAGE", "EXCESS", "WRONG_ITEM"]) },
            { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
            { name: "store", label: m("f.store"), type: "select", options: o.stores },
            { name: "from", label: m("f.challan_from"), type: "date" },
            { name: "to", label: m("f.challan_to"), type: "date" },
          ])}
          <ChemRegister
            exportName={key}
            rows={shown}
            refKey="return_no"
            columns={[
              { key: "return_no", label: m("col.rtn_challan"), type: "mono" },
              { key: "return_date", label: m("col.challan_date"), type: "date" },
              { key: "mrr_no", label: m("col.mrr_no"), type: "mono" },
              { key: "type_label", label: m("col.return_challan_type") },
              { key: "company", label: m("col.company") },
              { key: "supplier", label: m("col.supplier") },
              { key: "store", label: m("col.store") },
              { key: "finalized_label", label: m("col.is_finalized"), type: "badge" },
              { key: "remarks", label: m("col.remarks") },
            ]}
            actions={[
              ...(canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "status", in: ["DRAFT"] } }] : []),
              ...(issue
                ? [
                    { key: "finalise", label: m("reg.finalise"), title: m("reg.finalise_title", { no: ":no" }), text: m("reg.finalise_rt_text"), done: m("reg.finalised", { no: ":no" }), run: actions.finaliseChemReturn, when: { field: "status", in: ["DRAFT"] } },
                    { key: "cancel", label: m("reg.cancel"), title: m("reg.cancel_title", { no: ":no" }), text: m("reg.cancel_rt_text"), done: m("reg.cancelled", { no: ":no" }), run: actions.cancelChemReturn, when: { field: "status", in: ["DRAFT"] }, destructive: true },
                  ]
                : []),
            ]}
          />
        </>
      ),
  };
};

/* ── Stock ────────────────────────────────────────────────────────── */

const itemStock = async ({ m, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/chem-store/stock"), look()]);
  const shown = rows.filter((r) => (!q.store || r.store_id === q.store) && has(r.item, q.item) && has(r.lot_no, q.lot) && within(r.exp_date, q.from, q.to));
  return {
    body: () => (
      <>
        {filterBar(m, "chemical-item-stock", q, [
          { name: "store", label: m("f.receiving_store"), type: "select", options: o.stores },
          { name: "item", label: m("f.item"), type: "text", placeholder: m("f.select_item") },
          { name: "lot", label: m("f.lot"), type: "text", placeholder: "1102250014" },
          { name: "from", label: m("f.exp_from"), type: "date" },
          { name: "to", label: m("f.exp_to"), type: "date" },
        ])}
        <RecordsGrid
          exportName="chemical-item-stock"
          rows={shown}
          emptyState={{ title: m("stock.empty"), description: m("stock.empty_hint") }}
          columns={[
            { key: "store", label: m("col.store") },
            { key: "category", label: m("col.category") },
            { key: "item", label: m("col.item_name") },
            { key: "lot_no", label: m("col.lot"), type: "mono" },
            { key: "rate_bdt", label: m("col.unit_price"), type: "number", align: "right" },
            { key: "stock", label: m("col.stock"), type: "number", align: "right" },
            { key: "uom", label: m("col.uom") },
            { key: "exp_date", label: m("col.exp_date"), type: "date" },
          ]}
        />
      </>
    ),
  };
};

/* ── Issues ───────────────────────────────────────────────────────── */

const IS_STATUS = ["WAITING", "PARTIAL", "ISSUED", "CANCELLED"];
const ST_STATUS = ["WAITING_VERIFICATION", "VERIFIED", "PARTIAL", "ISSUED", "CANCELLED"];
const PLACEHOLDER = { TRANSFER: "REQ-ST-2026060074", PRINT: "IS-PR-2026080001", WASH: "IS-MW-2026080001", BATCH: "IS-BP-2026080001", SALE: "IS-SL-2026080001", LOAN_OUT: "IS-LN-2026080001", LOAN_RETURN: "IS-LR-2026080001" };

/** One issue kind's screen: its register (Edit, Verify, Issue, Cancel) and the editor tab. */
function issueScreen(kind, key, filters, columns) {
  return async ({ m, te, tab, canManage, permissions = [], searchParams: q = {} }) => {
    const [rows, o] = await Promise.all([apiFetch(`/dyehouse/chem-issues?kind=${kind}`), look()]);
    const statuses = kind === "TRANSFER" ? ST_STATUS : IS_STATUS;
    const f = filters(m, o, rows);
    const shown = rows
      .filter((r) => has(r.issue_no, q.no) && within(r.issue_date, q.from, q.to) && (!q.status || r.status === q.status))
      .filter((r) => f.every((x) => !x.match || x.match(r, q[x.name])))
      .map((r) => ({ ...r, status_label: m(`opt.is_status.${r.status}`), done_label: yes(m, r.status === "ISSUED"), reprocess_label: yes(m, r.is_reprocess) }));
    const canIssue = can(permissions, "issue");
    const actionsFor = [
      ...(canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "status", in: ["WAITING", "WAITING_VERIFICATION"] } }] : []),
      ...(kind === "TRANSFER" && (canIssue || can(permissions, "approve")) ? [{ key: "verify", label: m("reg.verify"), title: m("reg.verify_st_title", { no: ":no" }), text: m("reg.verify_st_text"), done: m("reg.verified", { no: ":no" }), run: actions.verifyChemIssue, when: { field: "status", in: ["WAITING_VERIFICATION"] } }] : []),
      ...(canIssue ? [{ key: "issue", label: m("reg.issue"), dialog: "issue", when: { field: "status", in: ["VERIFIED", "WAITING", "PARTIAL"] } }] : []),
      ...(canManage || canIssue ? [{ key: "cancel", label: m("reg.cancel"), title: m("reg.cancel_title", { no: ":no" }), text: m("reg.cancel_is_text"), done: m("reg.cancelled", { no: ":no" }), run: actions.cancelChemIssue, when: { field: "status", in: ["WAITING_VERIFICATION", "VERIFIED", "WAITING", "PARTIAL"] }, destructive: true }] : []),
    ];
    return {
      tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m(`tab.is.${kind}`), icon: "FlaskConical" }] : [])],
      body: () =>
        tab === "entry" && canManage ? (
          <ChemIssueEditor kind={kind} issues={rows} options={o} canManage={canManage} load={actions.loadChemIssue} save={actions.saveChemIssue} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
        ) : (
          <>
            {filterBar(m, key, q, [{ name: "no", label: kind === "TRANSFER" ? m("f.req_no") : m("f.requisition_no"), type: "text", placeholder: PLACEHOLDER[kind] }, ...f.map(({ match, ...x }) => x), { name: "status", label: m("f.status"), type: "select", options: opts(m, "is_status", statuses) }])}
            <ChemRegister exportName={key} rows={shown} columns={columns(m)} refKey="issue_no" actions={actionsFor} loadIssue={actions.loadChemIssue} issueStock={actions.issueChemStock} />
          </>
        ),
    };
  };
}

const dateFilters = (m, from = "f.date_from", to = "f.date_to") => [
  { name: "from", label: m(from), type: "date" },
  { name: "to", label: m(to), type: "date" },
];
const qtyCols = (m) => [
  { key: "required_qty", label: m("col.required_qty"), type: "number", align: "right" },
  { key: "issued_qty", label: m("col.issued_qty"), type: "number", align: "right" },
  { key: "balance_qty", label: m("col.balance_qty"), type: "number", align: "right" },
];
const statusCol = (m) => ({ key: "status_label", label: m("col.status"), type: "badge" });
const companyFilter = (m, o) => ({ name: "party", label: m("f.company"), type: "select", options: o.companies, match: (r, v) => !v || (r.party_id ?? v) === v });
const partyFilter = (m, o, label) => ({ name: "party_to", label: m(label), type: "select", options: o.suppliers, match: (r, v) => !v || r.supplier_id === v });
const reqTypeFilter = (m, rows) => ({ name: "reqtype", label: m("f.req_type"), type: "select", options: unique(rows, "req_type", "req_type"), match: (r, v) => !v || r.req_type === v });

const transferIssue = issueScreen(
  "TRANSFER",
  "store-transfer-requisition-and-issue",
  (m, o) => [
    ...dateFilters(m, "f.req_date_from", "f.req_date_to"),
    { name: "reqtype", label: m("f.req_type"), type: "select", options: ["CENTRAL_TO_SUB", "SUB_TO_SUB"].map((x) => ({ value: m(`is.transfer.${x}`), label: m(`is.transfer.${x}`) })), match: (r, v) => !v || r.req_type === v },
    { name: "store", label: m("f.from_store"), type: "select", options: o.stores, match: (r, v) => !v || r.store_id === v },
    { name: "to_store", label: m("f.to_store"), type: "select", options: o.stores, match: (r, v) => !v || r.to_store_id === v },
  ],
  (m) => [
    { key: "issue_no", label: m("col.req_no"), type: "mono" },
    { key: "gate_pass", label: m("col.gate_pass") },
    { key: "issue_date", label: m("col.req_date"), type: "date" },
    { key: "req_type", label: m("col.req_type") },
    { key: "store", label: m("col.from_store") },
    { key: "to_store", label: m("col.to_store") },
    { key: "required_qty", label: m("col.required_kg"), type: "number", align: "right" },
    { key: "issued_qty", label: m("col.issued_kg"), type: "number", align: "right" },
    { key: "balance_qty", label: m("col.balance_kg"), type: "number", align: "right" },
    statusCol(m),
  ],
);

const printIssue = issueScreen(
  "PRINT",
  "print-chemical-issue",
  (m, o, rows) => [
    ...dateFilters(m),
    { name: "buyer", label: m("f.buyer_name"), type: "select", options: o.buyers, match: (r, v) => !v || r.buyer_id === v },
    { name: "order", label: m("f.style"), type: "select", options: o.orders, match: (r, v) => !v || r.order_id === v },
    { name: "print", label: m("f.print_type"), type: "select", options: unique(rows, "print_type", "print_type"), match: (r, v) => !v || r.print_type === v },
  ],
  (m) => [
    { key: "issue_no", label: m("col.req_no"), type: "mono" },
    { key: "issue_date", label: m("col.req_date"), type: "date" },
    { key: "buyer", label: m("col.buyer") },
    { key: "style", label: m("col.style") },
    { key: "print_type", label: m("col.print_type") },
    { key: "req_type", label: m("col.req_type") },
    { key: "store", label: m("col.from_store") },
    ...qtyCols(m),
    { key: "remarks", label: m("col.remarks") },
    statusCol(m),
  ],
);

const washIssue = issueScreen(
  "WASH",
  "machine-wash-and-finishing-issue",
  (m, o, rows) => [...dateFilters(m), companyFilter(m, o), reqTypeFilter(m, rows)],
  (m) => [
    { key: "issue_no", label: m("col.req_no"), type: "mono" },
    { key: "issue_date", label: m("col.req_date"), type: "date" },
    { key: "company", label: m("col.company") },
    { key: "req_type", label: m("col.req_type") },
    { key: "store", label: m("col.from_store") },
    ...qtyCols(m),
    { key: "remarks", label: m("col.remarks") },
    statusCol(m),
  ],
);

const batchIssue = issueScreen(
  "BATCH",
  "batch-program-issue",
  (m, o) => [
    ...dateFilters(m, "f.req_date_from", "f.req_date_to"),
    { name: "batch", label: m("f.batch_no"), type: "text", placeholder: "B-26080001", match: (r, v) => has(r.batch_no, v) },
    { name: "machine", label: m("f.machine"), type: "select", options: o.machines, match: (r, v) => !v || r.machine_id === v },
  ],
  (m) => [
    { key: "issue_no", label: m("col.req_no"), type: "mono" },
    { key: "issue_date", label: m("col.req_date"), type: "date" },
    { key: "company", label: m("col.company") },
    { key: "store", label: m("col.iss_store") },
    { key: "process_type", label: m("col.process_type") },
    { key: "batch_no", label: m("col.batch_no"), type: "mono" },
    { key: "machine", label: m("col.machine") },
    { key: "issued_qty", label: m("col.ttl_iss_qty"), type: "number", align: "right" },
    { key: "done_label", label: m("col.req_done"), type: "badge" },
    { key: "reprocess_label", label: m("col.reprocess"), type: "badge" },
    statusCol(m),
  ],
);

const partyIssue = (kind, key, partyLabel, partyCol) =>
  issueScreen(
    kind,
    key,
    (m, o) => [...dateFilters(m, "f.month_from", "f.month_to"), companyFilter(m, o), partyFilter(m, o, partyLabel)],
    (m) => [
      { key: "issue_no", label: m("col.req_no"), type: "mono" },
      { key: "issue_date", label: m("col.req_date"), type: "date" },
      { key: "company", label: m("col.company") },
      { key: "supplier", label: m(partyCol) },
      { key: "req_type", label: m("col.req_type") },
      { key: "store", label: m("col.from_store") },
      ...qtyCols(m),
      ...(kind === "SALE" ? [{ key: "amount", label: m("col.amount"), type: "number", align: "right" }] : []),
      { key: "remarks", label: m("col.remarks") },
      statusCol(m),
    ],
  );

/* ── Comparative statement ────────────────────────────────────────── */

const CS_STATUS = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CLOSED"];

const comparative = async ({ m, te, tab, canManage, permissions = [], searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/chem-cs"), look()]);
  const key = "chemical-cs";
  const shown = rows
    .filter((r) => has(r.cs_no, q.no) && within(r.cs_date, q.from, q.to) && (!q.status || r.status === q.status) && (!q.category || r.category_id === q.category))
    .map((r) => ({ ...r, status_label: m(`opt.cs_status.${r.status}`), usd_label: r.usd_rate === null ? null : String(r.usd_rate) }));
  const approve = can(permissions, "approve");
  const cs = (k, run, from, extra = {}) => ({ key: k, label: m(`reg.cs_${k}`), title: m(`reg.cs_${k}_title`, { no: ":no" }), text: m(`reg.cs_${k}_text`), done: m(`reg.cs_${k}_done`, { no: ":no" }), run, when: { field: "status", in: from }, ...extra });
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "entry", label: m("tab.cs"), icon: "Scale" }] : [])],
    body: () =>
      tab === "entry" && canManage ? (
        <ChemCsEditor statements={rows} options={o} canManage={canManage} load={actions.loadChemCs} save={actions.saveChemCs} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.cs_ref"), type: "text", placeholder: "CS-…" },
            { name: "from", label: m("f.cs_from"), type: "date" },
            { name: "to", label: m("f.cs_to"), type: "date" },
            { name: "status", label: m("f.status"), type: "select", options: opts(m, "cs_status", CS_STATUS) },
            { name: "category", label: m("f.chem_category"), type: "select", options: o.categories },
          ])}
          <ChemRegister
            exportName={key}
            rows={shown}
            refKey="cs_no"
            columns={[
              { key: "cs_no", label: m("col.ref"), type: "mono" },
              { key: "category", label: m("col.chem_category") },
              { key: "cs_date", label: m("col.cs_date"), type: "date" },
              { key: "factory", label: m("col.fab_unit") },
              { key: "mou_code", label: m("col.mou_code") },
              { key: "usd_label", label: m("col.usd_bdt"), align: "right" },
              { key: "status_label", label: m("col.workflow"), type: "badge" },
              { key: "quotes", label: m("col.quotes"), type: "number", align: "right" },
            ]}
            actions={[
              ...(canManage ? [{ key: "edit", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no`, when: { field: "status", in: ["DRAFT", "REJECTED"] } }, cs("submit", actions.submitChemCs, ["DRAFT"])] : []),
              ...(approve ? [cs("approve", actions.approveChemCs, ["SUBMITTED"]), cs("reject", actions.rejectChemCs, ["SUBMITTED"], { destructive: true })] : []),
              ...(canManage ? [cs("close", actions.closeChemCs, ["APPROVED"])] : []),
            ]}
          />
        </>
      ),
  };
};

/* ── Month closing ────────────────────────────────────────────────── */

const monthClosing = async ({ m, te, tab, permissions = [] }) => {
  const rows = await apiFetch("/dyehouse/chem-closings");
  const canClose = can(permissions, "close");
  return {
    tabs: [recordsTab(te, rows.length), ...(canClose ? [{ key: "new", label: m("tab.month_close"), icon: "Lock" }] : [])],
    body: () =>
      tab === "new" && canClose ? (
        <EntryForm
          fields={[
            { name: "period", label: m("f.period"), required: true, placeholder: "Jul/2026", help: m("h.period") },
            { name: "remarks", label: m("f.remarks"), type: "textarea" },
          ]}
          action={actions.closeChemMonth}
          title={m("t.new_closing")}
          description={m("h.closing_note")}
          note={m("saved_note")}
          submitLabel={m("tab.month_close")}
          columns={2}
        />
      ) : (
        <RecordsGrid
          exportName="chemical-month-closing"
          rows={rows}
          emptyState={{ title: m("close.empty"), description: m("close.empty_hint") }}
          columns={[
            { key: "period_label", label: m("col.period"), type: "mono" },
            { key: "lots", label: m("col.lots"), type: "number", align: "right" },
            { key: "closing_qty", label: m("col.closing_qty"), type: "number", align: "right" },
            { key: "closing_value", label: m("col.closing_value"), type: "number", align: "right" },
            { key: "remarks", label: m("col.remarks") },
            { key: "closed_by", label: m("col.closed_by") },
            { key: "closed_at", label: m("col.closed_at"), type: "datetime" },
          ]}
        />
      ),
  };
};

const CHEM_SCREENS = {
  "stock-opening-balance": openingBalance,
  "daily-dyes-chemical-receive": receiptScreen("PURCHASE", "daily-dyes-chemical-receive"),
  "loan-opening-balance": loanOpening,
  "chemical-item-stock": itemStock,
  "daily-dyes-chemical-receive-return": receiptReturn,
  "store-transfer-requisition-and-issue": transferIssue,
  "print-chemical-issue": printIssue,
  "machine-wash-and-finishing-issue": washIssue,
  "batch-program-issue": batchIssue,
  "dyes-chemical-sales": partyIssue("SALE", "dyes-chemical-sales", "f.sales_to", "col.sales_to"),
  "chemical-cs": comparative,
  "requisition-for-loan-issue": partyIssue("LOAN_OUT", "requisition-for-loan-issue", "f.sales_to", "col.sales_to"),
  "loan-return-by-party": receiptScreen("LOAN_BACK", "loan-return-by-party"),
  "dc-loan-receive": receiptScreen("LOAN_IN", "dc-loan-receive"),
  "requisition-for-loan-return": partyIssue("LOAN_RETURN", "requisition-for-loan-return", "f.return_to", "col.return_to"),
  "month-closing": monthClosing,
};

// The screens whose paper opens on its editor when named in the address (?doc=).
const CHEM_EDITORS = Object.keys(CHEM_SCREENS).filter((k) => !["stock-opening-balance", "loan-opening-balance", "chemical-item-stock", "month-closing"].includes(k));

export { CHEM_SCREENS, CHEM_EDITORS };
