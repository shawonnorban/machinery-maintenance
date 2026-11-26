import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { BatchBoard } from "@/components/dyehouse/batch-board";
import { BatchSplit } from "@/components/dyehouse/batch-split";
import { GreyIssueRegister } from "@/components/dyehouse/grey-issue-register";
import { GreyLeftover } from "@/components/dyehouse/grey-leftover";
import * as actions from "./actions";

/*
 * Dye House → Batch (Bulk) (AnnotechERP 17/03), each laid out as Brickland
 * lays it out: Dye Batch Plan (Plan List, the board, New Plan), Bulk Grey
 * Fabric Indent and its Issue Register, Batch Card Splitting, the four
 * sub-contract pre-treatment papers, Dye Batch from Finished Fabric, the
 * order-wise and colour-replacement transfers, Batch Transfer to Leftover,
 * Grey to Leftover at batch end, and Manual Dye Batch Entry.
 */

const look = () => apiFetch("/dyehouse/batch/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const none = (list) => [{ value: "", label: "—" }, ...list];
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const blankNulls = (row, keys) => ({ ...row, ...Object.fromEntries(keys.map((k) => [k, row[k] ?? ""])) });
const asText = (row, keys) => ({ ...row, ...Object.fromEntries(keys.map((k) => [k, row[k] === null || row[k] === undefined ? "" : String(row[k])])) });
const base = (key) => `/dye-house/${key}`;

/** The Records / New pair most of these screens are: a filter row and grid, or the entry form. */
function recordsAndNew({ m, te, tab, canManage, key, rows, filters, columns, form, newTab = "tab.add_new", edit = true, remove = true, editFields, rowFields, refKey }) {
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m(newTab), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm columns={3} note={m("saved_note")} {...form} />
      ) : (
        <>
          {filters ? <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={filters.values} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={filters.fields} /> : null}
          <RecordsGrid
            exportName={key}
            rows={rows}
            columns={columns}
            edit={canManage && edit ? { fields: editFields ?? form.fields, rowFields, action: actions.updateResource.bind(null, form.resource, form.booleans ?? []), refKey, title: form.title, submitLabel: form.submitLabel, columns: 3 } : undefined}
            remove={canManage && remove ? actions.deleteResource.bind(null, form.resource) : undefined}
          />
        </>
      ),
  };
}

// ── Dye Batch Plan ────────────────────────────────────────────────────────

const batchPlan = async ({ m, te, tab, canManage, searchParams = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-batch-plans"), look()]);
  const fields = [
    { name: "code", label: m("f.plan_code"), placeholder: m("h.plan_code_ph"), help: m("h.plan_code") },
    { name: "name", label: m("f.plan_name"), required: true, placeholder: m("h.plan_name_ph") },
    { name: "plan_date", label: m("f.plan_date"), type: "date", required: true, help: m("h.plan_date") },
    { name: "party_id", label: m("f.party"), type: "select", options: none(o.companies) },
    { name: "scope", label: m("f.scope"), type: "select", required: true, options: opts(m, "scope", o.scopes), help: m("h.scope") },
    { name: "scale_hours", label: m("f.scale"), type: "select", required: true, options: opts(m, "scale", o.scales), help: m("h.scale") },
    { name: "cell_width", label: m("f.cell_width"), type: "number", required: true, placeholder: "20", help: m("h.cell_width") },
    { name: "remarks", label: m("f.remarks"), type: "textarea", placeholder: m("h.remarks_ph") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true, help: m("h.active") },
  ];
  const decorated = rows
    .filter((r) => (!searchParams.party || r.party_id === searchParams.party) && (!searchParams.scope || r.scope === searchParams.scope) && (has(r.code, searchParams.plan) || has(r.name, searchParams.plan)))
    .map((r) => ({ ...asText(blankNulls(r, ["party_id", "remarks"]), ["scale_hours", "cell_width"]), scope_label: m(`opt.scope.${r.scope}`) }));
  return {
    tabs: [
      { key: "records", label: m("tab.plan_list"), icon: "Table2", count: rows.length },
      { key: "board", label: m("tab.board"), icon: "Layers" },
      ...(canManage ? [{ key: "new", label: m("tab.new_plan"), icon: "Plus" }] : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-batch-plans", ["active"])} title={m("t.new_plan")} description={m("h.plan_note")} note={m("saved_note")} submitLabel={m("tab.new_plan")} columns={3} successHref={`${base("dyeing-batch-plan")}?tab=board`} />;
      }
      if (tab === "board") {
        return <BatchBoard options={o} canManage={canManage} initialCode={searchParams.plan ?? null} actions={{ load: actions.loadBoard, pull: actions.pullBatches, place: actions.placeBatch, clear: actions.clearBar, saveView: actions.saveBoardView }} />;
      }
      return (
        <>
          <FilterBar
            instant
            basePath={base("dyeing-batch-plan")}
            keep={{ tab: "records" }}
            values={searchParams}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "party", label: m("f.party"), type: "select", options: o.companies },
              { name: "scope", label: m("f.scope"), type: "select", options: opts(m, "scope", o.scopes) },
              { name: "plan", label: m("f.search_plan"), type: "text" },
            ]}
          />
          <RecordsGrid
            exportName="dye-batch-plans"
            rows={decorated}
            columns={[
              { key: "code", label: m("f.plan_code"), type: "mono" },
              { key: "name", label: m("f.plan_name") },
              { key: "plan_date", label: m("f.plan_date"), type: "date" },
              { key: "party", label: m("f.party") },
              { key: "scope_label", label: m("f.scope") },
              { key: "batches", label: m("f.batches"), type: "number", align: "right" },
              { key: "qty", label: m("f.qty_kg"), type: "number", align: "right" },
              { key: "laid_out", label: m("f.laid_out"), type: "number", align: "right" },
              { key: "active", label: m("f.active_q"), type: "yesno" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-batch-plans", ["active"]), refKey: "code", title: m("t.new_plan"), submitLabel: m("tab.new_plan"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-batch-plans") : undefined}
          />
        </>
      );
    },
  };
};

// ── Bulk Grey Fabric Indent and its Issue Register ────────────────────────

const greyIndent = async ({ m, te, tab, canManage, searchParams = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-grey-indents"), look()]);
  const fields = [
    { name: "batch_id", label: m("f.batch_hash"), type: "select", required: true, options: o.batches.filter((b) => b.scope === "BULK"), placeholder: m("split.search"), help: m("h.indent_batch") },
    { name: "colour_group_id", label: m("f.dyeing_rate"), type: "select", required: true, options: o.rates, help: m("h.dyeing_rate") },
    { name: "proj_rate", label: m("f.proj_rate"), type: "number", required: true, placeholder: m("h.proj_rate_ph") },
    { name: "store_id", label: m("f.receiving_store"), type: "select", required: true, options: o.stores },
  ];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("tab.create_req"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-grey-indents", [])} title={m("t.new_req")} description={m("h.indent_note")} note={m("saved_note")} submitLabel={m("tab.create_req")} columns={3} />
      ) : (
        <>
          <FilterBar
            instant
            basePath={base("grey-fabric-requisition-bulk")}
            keep={{ tab: "records" }}
            values={searchParams}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "batch", label: m("f.batch_hash"), type: "text", placeholder: m("h.batch_ph") },
              { name: "store", label: m("f.receiving_store"), type: "select", options: o.stores },
            ]}
          />
          <RecordsGrid
            exportName="bulk-grey-indents"
            rows={rows.filter((r) => has(r.batch_no, searchParams.batch) && (!searchParams.store || r.store_id === searchParams.store))}
            columns={[
              { key: "req_no", label: m("f.req_no"), type: "mono" },
              { key: "req_date", label: m("f.req_date"), type: "date" },
              { key: "batch_no", label: m("f.batch_no"), type: "mono" },
              { key: "req_kg", label: m("f.req_kg"), type: "number", align: "right" },
              { key: "store", label: m("f.receiving_store") },
              { key: "items", label: m("f.items"), type: "number", align: "right" },
            ]}
          />
        </>
      ),
  };
};

const greyIssue = async ({ m, searchParams = {}, permissions = [] }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-grey-indents"), look()]);
  const q = searchParams;
  const shown = rows
    .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.order_id === q.order) && (!q.colour || r.colour_id === q.colour) && (!q.store || r.store_id === q.store) && has(r.batch_no, q.batch))
    .map((r) => ({ ...r, type_label: m(`opt.req_type.${r.req_type}`) }));
  return {
    tabs: [],
    body: () => (
      <>
        <FilterBar
          instant
          basePath={base("grey-fabric-requisition-issue-list-bulk")}
          values={searchParams}
          labels={{ show: m("filter.show"), reset: m("filter.clear") }}
          fields={[
            { name: "buyer", label: m("f.buyer_ac"), type: "select", options: o.buyers },
            { name: "order", label: m("f.order_style"), type: "select", options: o.orders },
            { name: "colour", label: m("f.color"), type: "select", options: o.colours },
            { name: "store", label: m("f.req_store"), type: "select", options: o.stores },
            { name: "batch", label: m("f.batch_pt_no"), type: "text", placeholder: m("h.batch_ph") },
          ]}
        />
        <GreyIssueRegister rows={shown} canIssue={permissions.includes("dyehouse.batch.issue") || permissions.includes("dyehouse.batch.manage")} load={actions.loadIssue} issue={actions.issueGrey} />
      </>
    ),
  };
};

// ── Batch Card Splitting ──────────────────────────────────────────────────

const batchSplit = async ({ canManage }) => {
  const [batches, o] = await Promise.all([apiFetch("/dyehouse/batches"), look()]);
  return { tabs: [], body: () => <BatchSplit batches={batches} machines={o.machines.filter((x) => !x.is_sample)} canManage={canManage} load={actions.loadBatch} split={actions.splitBatch} /> };
};

// ── Sub-contract pre-treatment ────────────────────────────────────────────

const ptProgramme = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-pt-programmes"), look()]);
  const fields = [
    { name: "supplier_id", label: m("f.supplier"), type: "select", required: true, options: o.suppliers },
    { name: "issue_date", label: m("f.issue_date"), type: "date", required: true },
    { name: "exp_delivery_date", label: m("f.exp_delivery"), type: "date" },
    { name: "store_id", label: m("f.store"), type: "select", options: none(o.stores) },
    { name: "gate_pass_no", label: m("f.gate_pass"), placeholder: m("h.gate_pass_ph") },
  ];
  return recordsAndNew({
    ...ctx,
    key: "sc-pre-treatment-program-heat-set-etc",
    refKey: "program_no",
    rows: rows
      .filter((r) => has(r.program_no, q.program) && (!q.supplier || r.supplier_id === q.supplier) && (!q.despatched || (q.despatched === "YES") === Boolean(r.challan_id)))
      .map((r) => blankNulls(r, ["exp_delivery_date", "store_id", "gate_pass_no"])),
    filters: {
      values: q,
      fields: [
        { name: "program", label: m("f.program_hash"), type: "text", placeholder: m("h.program_ph") },
        { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
        { name: "despatched", label: m("f.despatched"), type: "select", options: opts(m, "despatched", ["YES", "NO"]) },
      ],
    },
    columns: [
      { key: "program_no", label: m("f.program_no"), type: "mono" },
      { key: "issue_date", label: m("f.issue_date"), type: "date" },
      { key: "exp_delivery_date", label: m("f.exp_delivery"), type: "date" },
      { key: "challan_no", label: m("f.challan_no"), type: "mono" },
      { key: "challan_date", label: m("f.challan_date"), type: "date" },
      { key: "company", label: m("f.company") },
      { key: "supplier", label: m("f.supplier") },
      { key: "store", label: m("f.store") },
      { key: "gate_pass_no", label: m("f.gate_pass") },
    ],
    form: { resource: "dye-pt-programmes", fields, action: actions.createResource.bind(null, "dye-pt-programmes", []), title: m("t.new_programme"), description: m("h.programme_note"), submitLabel: m("tab.add_new") },
  });
};

const ptChallan = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-pt-challans"), look()]);
  // A party's programmes not yet on a challan (and, when editing, this challan's own).
  const free = (own) => Object.fromEntries(o.suppliers.map((s) => [s.value, o.pt_programmes.filter((p) => p.supplier_id === s.value && (!p.challan_id || p.challan_id === own))]));
  const fieldsFor = (own) => [
    { name: "supplier_id", label: m("f.supplier"), type: "select", required: true, options: o.suppliers },
    { name: "challan_date", label: m("f.challan_date"), type: "date", required: true },
    { name: "store_id", label: m("f.store"), type: "select", options: none(o.stores) },
    { name: "gate_pass_no", label: m("f.gate_pass"), placeholder: m("h.gate_pass_ph") },
    { name: "programme_ids", label: m("f.programmes"), type: "multiselect", optionsFrom: { field: "supplier_id", map: free(own) }, help: m("h.challan_programmes") },
  ];
  return recordsAndNew({
    ...ctx,
    key: "sc-pre-treatment-challan",
    refKey: "challan_no",
    rows: rows.filter((r) => has(r.challan_no, q.challan) && (!q.supplier || r.supplier_id === q.supplier)).map((r) => ({ ...blankNulls(r, ["store_id", "gate_pass_no", "programme_ids"]), fields: fieldsFor(r.id) })),
    filters: {
      values: q,
      fields: [
        { name: "challan", label: m("f.challan_hash"), type: "text", placeholder: m("h.challan_ph") },
        { name: "supplier", label: m("f.supplier"), type: "select", options: o.suppliers },
      ],
    },
    columns: [
      { key: "challan_no", label: m("f.challan_no"), type: "mono" },
      { key: "challan_date", label: m("f.challan_date"), type: "date" },
      { key: "company", label: m("f.company") },
      { key: "supplier", label: m("f.supplier") },
      { key: "store", label: m("f.store") },
      { key: "gate_pass_no", label: m("f.gate_pass") },
      { key: "programs", label: m("f.programs"), type: "number", align: "right" },
    ],
    form: { resource: "dye-pt-challans", fields: fieldsFor(null), action: actions.createResource.bind(null, "dye-pt-challans", []), title: m("t.new_challan"), submitLabel: m("tab.add_new") },
    rowFields: "fields",
  });
};

const ptReceipt = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-pt-receipts"), look()]);
  const challans = Object.fromEntries(o.suppliers.map((s) => [s.value, none(o.pt_challans.filter((c) => c.supplier_id === s.value))]));
  const fields = [
    { name: "supplier_id", label: m("f.sc_party"), type: "select", required: true, options: o.suppliers },
    { name: "transfer_party_id", label: m("f.transfer_party"), type: "select", options: none(o.suppliers), help: m("h.transfer_party") },
    { name: "issue_date", label: m("f.issue_date"), type: "date", required: true },
    { name: "qc_no", label: m("f.qc_no"), placeholder: m("h.qc_ph") },
    { name: "challan_id", label: m("f.challan"), type: "select", optionsFrom: { field: "supplier_id", map: challans } },
    { name: "store_id", label: m("f.store"), type: "select", options: none(o.stores) },
  ];
  return recordsAndNew({
    ...ctx,
    key: "sc-pre-treatment-program-receive",
    refKey: "mrr_no",
    rows: rows
      .filter((r) => (!q.party || r.supplier_id === q.party) && (!q.transfer || r.transfer_party_id === q.transfer) && has(r.challan_no, q.challan) && has(r.program_list, q.program))
      .map((r) => blankNulls(r, ["transfer_party_id", "qc_no", "challan_id", "store_id"])),
    filters: {
      values: q,
      fields: [
        { name: "party", label: m("f.sc_party"), type: "select", options: o.suppliers },
        { name: "transfer", label: m("f.transfer_party"), type: "select", options: o.suppliers },
        { name: "challan", label: m("f.challan_hash"), type: "text" },
        { name: "program", label: m("f.program_hash"), type: "text" },
      ],
    },
    columns: [
      { key: "mrr_no", label: m("f.mrr_no"), type: "mono" },
      { key: "issue_date", label: m("f.issue_date"), type: "date" },
      { key: "qc_no", label: m("f.qc_no") },
      { key: "challan_no", label: m("f.challan_no"), type: "mono" },
      { key: "challan_date", label: m("f.challan_date"), type: "date" },
      { key: "supplier", label: m("f.supplier") },
      { key: "transfer_party", label: m("f.transfer_party") },
      { key: "store", label: m("f.store") },
      { key: "program_list", label: m("f.program_list") },
    ],
    form: { resource: "dye-pt-receipts", fields, action: actions.createResource.bind(null, "dye-pt-receipts", []), title: m("t.new_receive"), description: m("h.receipt_note"), submitLabel: m("tab.add_new") },
  });
};

const ptBill = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-pt-bills"), look()]);
  const fields = [
    { name: "supplier_id", label: m("f.party_bill"), type: "select", required: true, options: o.suppliers },
    { name: "bill_no", label: m("f.bill_no"), required: true, placeholder: m("h.bill_no_ph") },
    { name: "bill_date", label: m("f.bill_date"), type: "date", required: true },
    { name: "amount", label: m("f.amount"), type: "number", required: true },
    { name: "ref_bill_no", label: m("f.ref_bill"), placeholder: m("h.ref_bill_ph") },
    { name: "revision_no", label: m("f.rev_no"), type: "number" },
    { name: "revision_descr", label: m("f.rev_descr"), type: "textarea" },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "bill_status", o.bill_statuses) },
  ];
  return recordsAndNew({
    ...ctx,
    key: "sc-pre-treatment-bill",
    refKey: "bill_no",
    rows: rows
      .filter((r) => (!q.party || r.supplier_id === q.party) && (!q.status || r.status === q.status))
      .map((r) => ({ ...asText(blankNulls(r, ["ref_bill_no", "revision_descr"]), ["revision_no", "amount"]), status_label: m(`opt.bill_status.${r.status}`) })),
    filters: {
      values: q,
      fields: [
        { name: "party", label: m("f.party_bill"), type: "select", options: o.suppliers },
        { name: "status", label: m("f.status"), type: "select", options: opts(m, "bill_status", o.bill_statuses) },
      ],
    },
    columns: [
      { key: "bill_date", label: m("f.bill_date"), type: "date" },
      { key: "bill_no", label: m("f.bill_no"), type: "mono" },
      { key: "ref_bill_no", label: m("f.ref_bill"), type: "mono" },
      { key: "revision_no", label: m("f.rev_no"), type: "number", align: "right" },
      { key: "revision_descr", label: m("f.rev_descr") },
      { key: "supplier", label: m("f.supplier") },
      { key: "amount", label: m("f.amount"), type: "number", align: "right" },
      { key: "status_label", label: m("f.status"), type: "badge" },
    ],
    form: { resource: "dye-pt-bills", fields, action: actions.createResource.bind(null, "dye-pt-bills", []), title: m("t.new_bill"), description: m("h.bill_note"), submitLabel: m("tab.add_new") },
  });
};

// ── Batches raised outside the plan, and the transfers ────────────────────

const finishedBatch = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-finished-batches"), look()]);
  const fields = [
    { name: "colour_id", label: m("f.color"), type: "select", required: true, options: o.colours },
    { name: "card_qty", label: m("f.batch_qty"), type: "number", required: true },
    { name: "lot_no", label: m("f.lot_no") },
    { name: "dye_machine_id", label: m("f.machine"), type: "select", options: none(o.machines.map((x) => ({ value: x.value, label: x.label }))) },
    { name: "store_id", label: m("f.receiving_store"), type: "select", required: true, options: o.stores },
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: none(o.buyers) },
  ];
  return recordsAndNew({
    ...ctx,
    key: "dyeing-batch-src-f-fab",
    refKey: "batch_no",
    newTab: "tab.create_req",
    remove: true,
    rows: rows.filter((r) => has(r.batch_no, q.batch)).map((r) => asText(blankNulls(r, ["lot_no", "dye_machine_id", "buyer_id"]), ["card_qty"])),
    filters: { values: q, fields: [{ name: "batch", label: m("f.batch_hash"), type: "text", placeholder: m("h.batch_ph") }] },
    columns: [
      { key: "batch_no", label: m("f.batch_no"), type: "mono" },
      { key: "batch_date", label: m("f.date"), type: "date" },
      { key: "colour", label: m("f.color") },
      { key: "card_qty", label: m("f.batch_qty"), type: "number", align: "right" },
      { key: "machine", label: m("f.machine") },
      { key: "requisitions", label: m("f.requisitions"), type: "mono" },
    ],
    form: { resource: "dye-finished-batches", fields, action: actions.createResource.bind(null, "dye-finished-batches", []), title: m("t.new_ffab"), description: m("h.ffab_note"), submitLabel: m("tab.create_req") },
  });
};

const orderTransfer = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-grey-order-transfers"), look()]);
  const fields = [
    { name: "transfer_date", label: m("f.date"), type: "date", required: true },
    { name: "trans_type", label: m("f.trans_type"), type: "select", required: true, options: opts(m, "trans_type", o.trans_types) },
    { name: "buyer_id", label: m("f.buyer_ac"), type: "select", options: none(o.buyers) },
    { name: "from_order_id", label: m("f.from_order"), type: "select", options: none(o.orders) },
    { name: "to_order_id", label: m("f.to_order"), type: "select", options: none(o.orders) },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "transfer_status", o.transfer_statuses) },
    { name: "remarks", label: m("f.remarks"), type: "textarea", placeholder: m("h.remarks_otr_ph") },
  ];
  return recordsAndNew({
    ...ctx,
    key: "order-wise-grey-fabric-trans-from-batch-store",
    refKey: "transfer_no",
    newTab: "tab.new",
    rows: rows
      .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.from_order_id === q.order) && has(r.transfer_no, q.ref))
      .map((r) => ({ ...blankNulls(r, ["buyer_id", "from_order_id", "to_order_id", "remarks"]), type_label: m(`opt.trans_type.${r.trans_type}`) })),
    filters: {
      values: q,
      fields: [
        { name: "buyer", label: m("f.buyer_ac"), type: "select", options: o.buyers },
        { name: "order", label: m("f.from_order"), type: "select", options: o.orders },
        { name: "ref", label: m("f.trans_ref"), type: "text", placeholder: m("h.trans_ref_ph") },
      ],
    },
    columns: [
      { key: "transfer_date", label: m("f.date"), type: "date" },
      { key: "transfer_no", label: m("f.transfer_hash"), type: "mono" },
      { key: "type_label", label: m("f.trans_type") },
      { key: "remarks", label: m("f.remarks") },
    ],
    form: { resource: "dye-grey-order-transfers", fields, action: actions.createResource.bind(null, "dye-grey-order-transfers", []), title: m("t.new_transfer"), submitLabel: m("tab.new") },
  });
};

const colourReplace = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-colour-transfers"), look()]);
  const rest = [
    { name: "transfer_date", label: m("f.transfer_date"), type: "date", required: true },
    { name: "new_batch_no", label: m("f.new_batch_no"), required: true, placeholder: m("h.batch_no_ph") },
    { name: "lot_no", label: m("f.lot_no") },
    { name: "batch_date", label: m("f.batch_date"), type: "date", required: true },
    { name: "batch_qty", label: m("f.batch_qty"), type: "number", required: true },
    { name: "colour_id", label: m("f.new_colour"), type: "select", options: none(o.colours) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const fields = [{ name: "batch_id", label: m("f.batch_no"), type: "select", required: true, options: o.batches.map((b) => ({ value: b.value, label: `${b.label} · ${b.qty} kg${b.colour ? ` · ${b.colour}` : ""}` })) }, ...rest];
  return recordsAndNew({
    ...ctx,
    key: "dyeing-color-transfer-replace",
    refKey: "transfer_no",
    remove: false,
    editFields: rest,
    rows: rows
      .filter((r) => has(r.transfer_no, q.transfer) && within(r.transfer_date, q.from, q.to) && (has(r.batch_no, q.batch) || has(r.new_batch_no, q.batch)))
      .map((r) => asText(blankNulls(r, ["lot_no", "colour_id", "remarks"]), ["batch_qty"])),
    filters: {
      values: q,
      fields: [
        { name: "transfer", label: m("f.transfer_no"), type: "text" },
        { name: "from", label: m("f.date_from"), type: "date" },
        { name: "to", label: m("f.date_to"), type: "date" },
        { name: "batch", label: m("f.batch_no"), type: "text" },
      ],
    },
    columns: [
      { key: "batch_no", label: m("f.batch_no"), type: "mono" },
      { key: "new_batch_no", label: m("f.new_batch_no"), type: "mono" },
      { key: "lot_no", label: m("f.lot_no") },
      { key: "batch_date", label: m("f.batch_date"), type: "date" },
      { key: "batch_qty", label: m("f.batch_qty"), type: "number", align: "right" },
    ],
    form: { resource: "dye-colour-transfers", fields, action: actions.createResource.bind(null, "dye-colour-transfers", []), title: m("t.new_colour"), submitLabel: m("tab.add_new") },
  });
};

const batchLeftover = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-leftover-transfers"), look()]);
  const fields = [
    { name: "transfer_date", label: m("f.transfer_date"), type: "date", required: true },
    { name: "reason", label: m("f.reason"), type: "select", required: true, options: opts(m, "reason", o.reasons) },
    { name: "source", label: m("f.source"), required: true, placeholder: m("h.source_ph") },
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: none(o.buyers) },
    { name: "order_id", label: m("f.style"), type: "select", options: none(o.orders) },
    { name: "colour_id", label: m("f.color"), type: "select", options: none(o.colours) },
    { name: "qty", label: m("f.trns_qty"), type: "number", required: true },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "leftover_status", o.leftover_statuses) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  return recordsAndNew({
    ...ctx,
    key: "batch-transfer-to-left-over",
    refKey: "transfer_no",
    rows: rows
      .filter((r) => has(r.transfer_no, q.transfer) && within(r.transfer_date, q.from, q.to) && (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.order_id === q.order) && (!q.colour || r.colour_id === q.colour))
      .map((r) => ({ ...asText(blankNulls(r, ["buyer_id", "order_id", "colour_id", "remarks"]), ["qty"]), reason_label: m(`opt.reason.${r.reason}`) })),
    filters: {
      values: q,
      fields: [
        { name: "transfer", label: m("f.transfer_no"), type: "text" },
        { name: "from", label: m("f.date_from"), type: "date" },
        { name: "to", label: m("f.date_to"), type: "date" },
        { name: "buyer", label: m("f.buyer_ac"), type: "select", options: o.buyers },
        { name: "order", label: m("f.style_order"), type: "select", options: o.orders },
        { name: "colour", label: m("f.color"), type: "select", options: o.colours },
      ],
    },
    columns: [
      { key: "transfer_no", label: m("f.trnsfer_no"), type: "mono" },
      { key: "transfer_date", label: m("f.trnsfer_date"), type: "date" },
      { key: "reason_label", label: m("f.reason") },
      { key: "source", label: m("f.source") },
      { key: "buyer", label: m("f.buyer") },
      { key: "style", label: m("f.style_no") },
      { key: "order_no", label: m("f.order_no"), type: "mono" },
      { key: "colour", label: m("f.color") },
      { key: "qty", label: m("f.trns_qty"), type: "number", align: "right" },
      { key: "remarks", label: m("f.remarks") },
    ],
    form: { resource: "dye-leftover-transfers", fields, action: actions.createResource.bind(null, "dye-leftover-transfers", []), title: m("t.new_leftover"), submitLabel: m("tab.add_new") },
  });
};

const greyLeftover = async ({ canManage }) => {
  const [lots, o] = await Promise.all([apiFetch("/dyehouse/batch-leftover"), look()]);
  return { tabs: [], body: () => <GreyLeftover lots={lots} stores={o.stores} canManage={canManage} make={actions.makeLeftover} /> };
};

const manualBatch = async (ctx) => {
  const { m, searchParams: q = {} } = ctx;
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-manual-batches"), look()]);
  const fields = [
    { name: "manual_ref_no", label: m("f.manual_ref"), required: true, placeholder: m("h.manual_ref_ph") },
    { name: "manual_ref_date", label: m("f.ref_date"), type: "date", required: true },
    { name: "buyer_id", label: m("f.buyer"), type: "select", options: none(o.buyers) },
    { name: "order_id", label: m("f.style_no"), type: "select", options: none(o.orders) },
    { name: "colour_id", label: m("f.color"), type: "select", options: none(o.colours) },
    { name: "batch_no", label: m("f.batch_no"), required: true, placeholder: m("h.batch_no_ph") },
    { name: "lot_no", label: m("f.lot_no") },
    { name: "batch_date", label: m("f.batch_date"), type: "date", required: true },
    { name: "card_qty", label: m("f.batch_qty"), type: "number", required: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  return recordsAndNew({
    ...ctx,
    key: "manual-batch-for-dyeing",
    refKey: "manual_ref_no",
    rows: rows
      .filter((r) => has(r.manual_ref_no, q.ref) && has(r.batch_no, q.batch) && within(r.batch_date, q.from, q.to))
      .map((r) => asText(blankNulls(r, ["buyer_id", "order_id", "colour_id", "lot_no", "remarks"]), ["card_qty"])),
    filters: {
      values: q,
      fields: [
        { name: "ref", label: m("f.manual_ref"), type: "text" },
        { name: "batch", label: m("f.batch_no"), type: "text" },
        { name: "from", label: m("f.batch_date_from"), type: "date" },
        { name: "to", label: m("f.batch_date_to"), type: "date" },
      ],
    },
    columns: [
      { key: "manual_ref_no", label: m("f.ref_no"), type: "mono" },
      { key: "manual_ref_date", label: m("f.ref_date"), type: "date" },
      { key: "buyer", label: m("f.buyer") },
      { key: "style", label: m("f.style_no") },
      { key: "order_no", label: m("f.order_no"), type: "mono" },
      { key: "colour", label: m("f.color") },
      { key: "batch_no", label: m("f.batch_no"), type: "mono" },
      { key: "lot_no", label: m("f.lot_no") },
      { key: "batch_date", label: m("f.batch_date"), type: "date" },
      { key: "card_qty", label: m("f.batch_qty"), type: "number", align: "right" },
    ],
    form: { resource: "dye-manual-batches", fields, action: actions.createResource.bind(null, "dye-manual-batches", []), title: m("t.new_manual"), submitLabel: m("tab.add_new") },
  });
};

const BATCH_SCREENS = {
  "dyeing-batch-plan": batchPlan,
  "grey-fabric-requisition-bulk": greyIndent,
  "grey-fabric-requisition-issue-list-bulk": greyIssue,
  "batch-card-split": batchSplit,
  "sc-pre-treatment-program-heat-set-etc": ptProgramme,
  "sc-pre-treatment-challan": ptChallan,
  "sc-pre-treatment-program-receive": ptReceipt,
  "sc-pre-treatment-bill": ptBill,
  "dyeing-batch-src-f-fab": finishedBatch,
  "order-wise-grey-fabric-trans-from-batch-store": orderTransfer,
  "dyeing-color-transfer-replace": colourReplace,
  "batch-transfer-to-left-over": batchLeftover,
  "gray-2-leftover-transfer-batch-end": greyLeftover,
  "manual-batch-for-dyeing": manualBatch,
};

export { BATCH_SCREENS };
