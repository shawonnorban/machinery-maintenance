import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ReportSet } from "@/components/erp/report-set";
import { BatchProduction } from "@/components/dyehouse/batch-production";
import { GreyIssueRegister } from "@/components/dyehouse/grey-issue-register";
import * as actions from "./actions";

/*
 * Dye House → Batch (Sample) (AnnotechERP 17/04), each laid out as Brickland
 * lays it out, on 17/03's batch cards of scope SAMPLE: Sample Grey Fabric
 * Indent (with its Batch and Program Lists), the Sample Grey Fabric Issue
 * Register, Sample Batch Production, Sample Finishing Quality Check and
 * Sample Batch from Bulk Lot.
 */

const look = () => apiFetch("/dyehouse/batch/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const none = (list) => [{ value: "", label: "—" }, ...list];
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const blankNulls = (row, keys) => ({ ...row, ...Object.fromEntries(keys.map((k) => [k, row[k] === null || row[k] === undefined ? "" : String(row[k])])) });
const base = (key) => `/dye-house/${key}`;
const filterBar = (m, key, values, fields, keep = { tab: "records" }) => <FilterBar instant basePath={base(key)} keep={keep} values={values} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const LISTS = ["batch-list", "program-list", "pending-labdip"];
const KINDS = ["SAMPLE", "BULK", "PRE_TREATMENT"];

const sampleIndent = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-sample-indents"), look()]);
  const fields = [
    { name: "batch_id", label: m("f.batch_hash"), type: "select", required: true, options: o.batches.filter((b) => b.scope === "SAMPLE"), placeholder: m("split.search"), help: m("h.indent_batch") },
    { name: "plan_id", label: m("f.schedule"), type: "select", options: none(o.plans.filter((p) => p.scope === "SAMPLE" && p.active).map((p) => ({ value: p.id ?? p.value, label: p.label }))), help: m("h.schedule") },
    { name: "party_id", label: m("f.company"), type: "select", options: none(o.companies) },
    { name: "colour_group_id", label: m("f.dyeing_rate"), type: "select", required: true, options: o.rates, help: m("h.dyeing_rate") },
    { name: "proj_rate", label: m("f.proj_rate"), type: "number", required: true, placeholder: m("h.proj_rate_ph") },
    { name: "store_id", label: m("f.receiving_store"), type: "select", required: true, options: o.stores },
  ];
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "lists", label: m("tab.lists"), icon: "ListChecks" },
      ...(canManage ? [{ key: "new", label: m("tab.create_req"), icon: "Plus" }] : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-sample-indents", [])} title={m("t.new_req")} description={m("h.sample_indent_note")} note={m("saved_note")} submitLabel={m("tab.create_req")} columns={3} />;
      }
      if (tab === "lists") {
        return <ReportSet namespace="dyehouse" prefix="batch.lists" reports={LISTS} preview={actions.previewSampleList} withTo />;
      }
      return (
        <>
          {filterBar(m, "grey-fabric-requisition-sample", q, [
            { name: "batch", label: m("f.batch_hash"), type: "text", placeholder: m("h.sample_ph") },
            { name: "store", label: m("f.receiving_store"), type: "select", options: o.stores },
            { name: "party", label: m("f.company"), type: "select", options: o.companies },
          ])}
          <RecordsGrid
            exportName="sample-grey-indents"
            rows={rows.filter((r) => has(r.batch_no, q.batch) && (!q.store || r.store_id === q.store) && (!q.party || (r.party_id ?? o.companies[0]?.value) === q.party))}
            columns={[
              { key: "req_no", label: m("f.req_no"), type: "mono" },
              { key: "req_date", label: m("f.req_date"), type: "date" },
              { key: "batch_no", label: m("f.batch_no"), type: "mono" },
              { key: "req_kg", label: m("f.req_kg"), type: "number", align: "right" },
              { key: "store", label: m("f.receiving_store") },
              { key: "company", label: m("f.company") },
              { key: "items", label: m("f.items"), type: "number", align: "right" },
            ]}
          />
        </>
      );
    },
  };
};

const sampleIssue = async ({ m, searchParams: q = {}, permissions = [] }) => {
  const [sample, bulk, o] = await Promise.all([apiFetch("/erp/dye-sample-indents"), apiFetch("/erp/dye-grey-indents"), look()]);
  const rows = [...sample, ...bulk]
    .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.order_id === q.order) && (!q.colour || r.colour_id === q.colour) && (!q.store || r.store_id === q.store) && has(r.batch_no, q.batch) && (!q.type || r.req_type === q.type))
    .map((r) => ({ ...r, type_label: m(`opt.req_kind.${r.req_type}`) }));
  return {
    tabs: [],
    body: () => (
      <>
        {filterBar(
          m,
          "grey-fabric-requisition-issue-list-sample",
          q,
          [
            { name: "buyer", label: m("f.buyer_ac"), type: "select", options: o.buyers },
            { name: "order", label: m("f.order_style"), type: "select", options: o.orders },
            { name: "colour", label: m("f.color"), type: "select", options: o.colours },
            { name: "store", label: m("f.req_store"), type: "select", options: o.stores },
            { name: "batch", label: m("f.batch_pt_no"), type: "text", placeholder: m("h.batch_ph") },
            { name: "type", label: m("f.req_type"), type: "select", options: opts(m, "req_kind", KINDS) },
          ],
          {},
        )}
        <GreyIssueRegister rows={rows} canIssue={permissions.includes("dyehouse.batch.issue") || permissions.includes("dyehouse.batch.manage")} load={actions.loadIssue} issue={actions.issueGrey} />
      </>
    ),
  };
};

const sampleProduction = async ({ m, canManage }) => {
  const [batches, o] = await Promise.all([apiFetch("/dyehouse/production/batches?scope=SAMPLE"), look()]);
  return {
    tabs: [],
    body: () => <BatchProduction batches={batches} machines={o.machines.filter((x) => x.is_sample)} canManage={canManage} load={actions.loadProduction} save={actions.saveProduction} title={m("prod.title")} />,
  };
};

const sampleQc = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-sample-qcs"), look()]);
  const run = o.run_batches.filter((b) => b.scope === "SAMPLE");
  const fields = [
    { name: "batch_id", label: m("f.qc_batch"), type: "select", required: true, options: run, help: m("h.qc_batch") },
    { name: "lot", label: m("f.lot"), type: "derived", from: "batch_id", values: Object.fromEntries(run.map((b) => [b.value, b.lot_no ?? ""])) },
    { name: "qty", label: m("f.batch_qty"), type: "derived", from: "batch_id", values: Object.fromEntries(run.map((b) => [b.value, String(b.qty)])) },
    { name: "qc_date", label: m("f.qc_date"), type: "date", required: true },
    { name: "result", label: m("f.batch_status"), type: "select", required: true, options: opts(m, "qc_result", ["PENDING", "PASSED", "FAILED", "REWORK"]) },
    { name: "model_combo", label: m("f.model_combo"), placeholder: m("h.model_ph") },
    { name: "gsm", label: m("f.gsm"), type: "number" },
    { name: "width_inch", label: m("f.width"), type: "number" },
    { name: "checked_by", label: m("f.checked_by"), type: "select", options: none(o.employees), placeholder: m("f.select_employee") },
    { name: "shade_note", label: m("f.shade_note"), placeholder: m("h.shade_ph"), span: 2 },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("tab.add"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-sample-qcs", [])} title={m("t.new_qc")} description={m("h.qc_note")} note={m("saved_note")} submitLabel={m("tab.add")} columns={3} />
      ) : (
        <>
          {filterBar(m, "dyeing-finishing-qc-sample", q, [
            { name: "buyer", label: m("f.buyer_ac"), type: "select", options: o.buyers },
            { name: "order", label: m("f.style_order_sp"), type: "select", options: o.orders },
            { name: "colour", label: m("f.color"), type: "select", options: o.colours },
            { name: "batch", label: m("f.batch_hash_sp"), type: "text", placeholder: m("h.sample_ph") },
            { name: "result", label: m("f.batch_status"), type: "select", options: opts(m, "qc_result", ["PENDING", "PASSED", "FAILED", "REWORK"]) },
          ])}
          <RecordsGrid
            exportName="sample-finishing-qc"
            rows={rows
              .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.order_id === q.order) && (!q.colour || r.colour_id === q.colour) && has(r.batch_no, q.batch) && (!q.result || r.result === q.result))
              .map((r) => ({ ...blankNulls(r, ["model_combo", "gsm", "width_inch", "checked_by", "shade_note", "remarks"]), ref: `${r.batch_no}-${r.qc_date}` }))}
            columns={[
              { key: "batch_no", label: m("f.qc_batch"), type: "mono" },
              { key: "lot_no", label: m("f.lot") },
              { key: "batch_qty", label: m("f.batch_qty"), type: "number", align: "right" },
              { key: "buyer", label: m("f.buyer") },
              { key: "style", label: m("f.style") },
              { key: "order_no", label: m("f.order"), type: "mono" },
              { key: "colour", label: m("f.color") },
              { key: "model_combo", label: m("f.model_combo") },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-sample-qcs", []), refKey: "ref", title: m("t.new_qc"), submitLabel: m("tab.add"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-sample-qcs") : undefined}
          />
        </>
      ),
  };
};

const sampleFromBulk = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-sample-transfers"), look()]);
  const fields = [
    { name: "transfer_no", label: m("f.transfer_no"), readOnly: true, placeholder: m("h.transfer_no_ph"), help: m("h.transfer_no") },
    { name: "transfer_date", label: m("f.transfer_date"), type: "date", required: true },
    { name: "batch_id", label: m("f.bulk_batch"), type: "select", required: true, options: o.batches.filter((b) => b.scope === "BULK").map((b) => ({ value: b.value, label: `${b.label} · ${b.qty} kg${b.colour ? ` · ${b.colour}` : ""}` })), placeholder: m("f.select_bulk"), help: m("h.bulk_batch") },
    { name: "new_batch_no", label: m("f.new_batch_no"), required: true, placeholder: m("h.sample_batch_ph") },
    { name: "lot_no", label: m("f.lot_no"), placeholder: m("h.sample_lot_ph") },
    { name: "batch_date", label: m("f.batch_date"), type: "date", required: true },
    { name: "batch_qty", label: m("f.batch_qty"), type: "number", required: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  // Once drawn, only the lot, the dates and the remarks change.
  const editFields = [
    { name: "transfer_no", label: m("f.transfer_no"), readOnly: true },
    { name: "transfer_date", label: m("f.transfer_date"), type: "date", required: true },
    { name: "batch_no", label: m("f.bulk_batch"), readOnly: true },
    { name: "new_batch_no", label: m("f.new_batch_no"), readOnly: true },
    { name: "lot_no", label: m("f.lot_no"), placeholder: m("h.sample_lot_ph") },
    { name: "batch_date", label: m("f.batch_date"), type: "date", required: true },
    { name: "batch_qty", label: m("f.batch_qty"), readOnly: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-sample-transfers", [])} title={m("t.new_sample_transfer")} description={m("h.sample_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} />
      ) : (
        <>
          {filterBar(m, "sample-batch-from-bulk", q, [
            { name: "transfer", label: m("f.transfer_no"), type: "text", placeholder: m("h.sbb_ph") },
            { name: "from", label: m("f.date_from"), type: "date" },
            { name: "to", label: m("f.date_to"), type: "date" },
            { name: "batch", label: m("f.batch_no"), type: "text", placeholder: m("h.batch_ph") },
          ])}
          <RecordsGrid
            exportName="sample-batch-from-bulk"
            rows={rows.filter((r) => has(r.transfer_no, q.transfer) && within(r.transfer_date, q.from, q.to) && (has(r.batch_no, q.batch) || has(r.new_batch_no, q.batch))).map((r) => blankNulls(r, ["lot_no", "remarks", "batch_qty"]))}
            columns={[
              { key: "batch_no", label: m("f.batch_no"), type: "mono" },
              { key: "new_batch_no", label: m("f.new_batch_no"), type: "mono" },
              { key: "lot_no", label: m("f.lot_no") },
              { key: "batch_date", label: m("f.batch_date"), type: "date" },
              { key: "batch_qty", label: m("f.batch_qty"), type: "number", align: "right" },
            ]}
            edit={canManage ? { fields: editFields, action: actions.updateResource.bind(null, "dye-sample-transfers", []), refKey: "transfer_no", title: m("t.new_sample_transfer"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
          />
        </>
      ),
  };
};

const SAMPLE_SCREENS = {
  "grey-fabric-requisition-sample": sampleIndent,
  "grey-fabric-requisition-issue-list-sample": sampleIssue,
  "batch-production-sample": sampleProduction,
  "dyeing-finishing-qc-sample": sampleQc,
  "sample-batch-from-bulk": sampleFromBulk,
};

export { SAMPLE_SCREENS };
