import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ChemRegister } from "@/components/dyehouse/chem-store";
import { CheckRollInspection } from "@/components/dyehouse/check-roll-inspection";
import * as actions from "./actions";

/*
 * Dye House → Dyeing Q.C (AnnotechERP 17/08), each laid out as Brickland
 * lays it out: Check Roll Quality Inspection (its Records and the inspection
 * sheet), Batch Programme Completion and Rib & Collar Shade Approval.
 */

const look = () => apiFetch("/dyehouse/qc/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/dye-house/${key}`;
const filterBar = (m, key, q, fields) => <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const SHADE = ["PENDING", "PASSED", "HOLD_DYEING", "REJECTED"];

const checkRoll = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/check-roll-inspections"), look()]);
  const shown = rows
    .filter((r) => has(r.inspection_no, q.no) && has(r.batch_no, q.batch) && has(r.roll_no, q.roll) && within(r.inspection_date, q.from, q.to) && (!q.result || r.result === q.result))
    .map((r) => ({ ...r, id: r.inspection_no, marked: r.defects.map((d) => m(`defect.${d}`)).join(", "), result_label: m(`opt.result.${r.result}`) }));
  return {
    tabs: [recordsTab(te, rows.length), { key: "entry", label: m("tab.inspection"), icon: "ScanSearch" }],
    body: () =>
      tab === "entry" ? (
        <CheckRollInspection batches={o.batches} options={o} canManage={canManage} load={actions.loadCheckRoll} save={actions.saveInspection} />
      ) : (
        <>
          {filterBar(m, "check-roll-inspection", q, [
            { name: "no", label: m("f.req_no"), type: "text", placeholder: "CRI-2026110001" },
            { name: "batch", label: m("f.batch_no"), type: "text", placeholder: "B-26110001" },
            { name: "roll", label: m("f.roll_no"), type: "text" },
            { name: "from", label: m("f.date_from"), type: "date" },
            { name: "to", label: m("f.date_to"), type: "date" },
            { name: "result", label: m("f.result"), type: "select", options: opts(m, "result", ["PASSED", "HELD"]) },
          ])}
          <RecordsGrid
            exportName="check-roll-inspection"
            rows={shown}
            emptyState={{ title: m("records.empty_inspection"), description: m("records.empty_inspection_hint") }}
            columns={[
              { key: "inspection_no", label: m("col.req_no"), type: "mono" },
              { key: "inspection_date", label: m("col.req_date"), type: "date" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "roll_no", label: m("col.roll_no"), type: "mono" },
              { key: "defect_count", label: m("col.defects"), type: "number", align: "right" },
              { key: "marked", label: m("col.marked") },
              { key: "department", label: m("col.department") },
              { key: "result_label", label: m("col.result"), type: "badge" },
            ]}
          />
        </>
      ),
  };
};

const completion = async ({ m, canManage, searchParams: q = {} }) => {
  const rows = await apiFetch("/dyehouse/batch-completions");
  const colours = [...new Map(rows.filter((r) => r.colour_id).map((r) => [r.colour_id, { value: r.colour_id, label: r.colour }])).values()];
  const stores = [...new Map(rows.map((r) => [r.store_id, { value: r.store_id, label: r.store }])).values()];
  const processes = [...new Set(rows.map((r) => r.process_type).filter(Boolean))].map((p) => ({ value: p, label: m(`opt.process.${p}`) }));
  const shown = rows
    .filter((r) => within(r.req_date, q.from, q.to) && (!q.colour || r.colour_id === q.colour) && (!q.process || r.process_type === q.process) && (!q.store || r.store_id === q.store) && has(r.batch_no, q.batch))
    .filter((r) => !q.back || (q.back === "Y" ? r.back_from_df : !r.back_from_df))
    .map((r) => ({
      ...r,
      process_label: r.process_type ? m(`opt.process.${r.process_type}`) : m(`opt.req_for.${r.req_for}`),
      gates_label: r.gates ? (r.gates.failing ? m(`gate.${r.gates.failing}`, { rolls: r.gates.held_rolls.join(", "), count: r.gates.open_shades }) : m("gate.ready")) : m("gate.no_batch"),
      back_label: r.back_from_df ? m("opt.yes") : m("opt.no"),
      status_label: m(`opt.completion.${r.status}`),
    }));
  return {
    body: () => (
      <>
        {filterBar(m, "complete-batch-program", q, [
          { name: "from", label: m("f.req_date_from"), type: "date" },
          { name: "to", label: m("f.req_date_to"), type: "date" },
          { name: "colour", label: m("f.colour_name"), type: "select", options: colours },
          { name: "process", label: m("f.process_type"), type: "select", options: processes },
          { name: "store", label: m("f.req_store"), type: "select", options: stores },
          { name: "batch", label: m("f.batch_no"), type: "text", placeholder: "B-26070008" },
          { name: "back", label: m("f.back_from_df"), type: "select", options: [{ value: "Y", label: m("opt.back.Y") }, { value: "N", label: m("opt.back.N") }] },
        ])}
        <ChemRegister
          exportName="batch-programme-completion"
          rows={shown}
          refKey="req_no"
          columns={[
            { key: "req_no", label: m("col.requisition_no"), type: "mono" },
            { key: "req_date", label: m("col.req_date"), type: "date" },
            { key: "colour", label: m("col.colour_name") },
            { key: "process_label", label: m("col.process_type") },
            { key: "store", label: m("col.req_store") },
            { key: "batch_no", label: m("col.batch_no"), type: "mono" },
            { key: "gates_label", label: m("col.gates") },
            { key: "back_label", label: m("col.back_from_df"), type: "badge" },
            { key: "status_label", label: m("col.status"), type: "badge" },
          ]}
          actions={canManage ? [{ key: "complete", label: m("reg.complete"), title: m("reg.complete_title", { no: ":no" }), text: m("reg.complete_text"), done: m("reg.completed", { no: ":no" }), run: actions.completeBatchProgramme, when: { field: "status", in: ["OPEN"] } }] : []}
        />
      </>
    ),
  };
};

const shadeApproval = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-shade-approvals"), look()]);
  const fields = [
    { name: "batch_id", label: m("f.batch_no"), type: "select", required: true, options: o.batches.map((b) => ({ value: b.value, label: `${b.batch_no}${b.colour ? ` · ${b.colour}` : ""}` })), placeholder: m("f.select_batch"), help: m("h.batch") },
    { name: "lot_no", label: m("f.lot"), placeholder: "1-A", help: m("h.lot") },
    { name: "sl_no", label: m("f.sl_no"), type: "number", required: true, help: m("h.sl_no") },
    { name: "b_qty", label: m("f.b_qty"), type: "number" },
    { name: "rp_qty", label: m("f.rp_qty"), type: "number" },
    { name: "rcv_date", label: m("f.rcv_date"), type: "date" },
    { name: "aprv_date", label: m("f.aprv_date"), type: "date", help: m("h.aprv_date") },
    { name: "buyer_id", label: m("f.buyer_name"), type: "select", options: [{ value: "", label: "—" }, ...o.buyers], placeholder: m("f.select") },
    { name: "order_id", label: m("f.style_order"), type: "select", options: [{ value: "", label: "—" }, ...o.orders], placeholder: m("f.select") },
    { name: "colour_id", label: m("f.colour"), type: "select", options: [{ value: "", label: "—" }, ...o.colours], placeholder: m("f.select") },
    { name: "g_qty", label: m("f.g_qty"), type: "number", help: m("h.grey_fin") },
    { name: "g_roll", label: m("f.g_roll"), type: "number" },
    { name: "fin_qty", label: m("f.fin_qty"), type: "number" },
    { name: "fin_roll", label: m("f.fin_roll"), type: "number" },
    { name: "status", label: m("f.qc_status"), type: "select", required: true, options: opts(m, "shade", SHADE) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && has(r.style_order, q.style) && (!q.colour || r.colour_id === q.colour) && has(r.batch_no, q.batch) && (!q.status || r.status === q.status) && within(r.rcv_date, q.from, q.to))
    .map((r) => ({ ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])), status_label: m(`opt.shade.${r.status}`), ref: `${r.batch_no}-${r.sl_no}` }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.shade"), icon: "Palette" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-shade-approvals", [])} title={m("tab.shade")} description={m("h.shade_note")} note={m("saved_note")} submitLabel={m("btn.save_shade")} columns={3} />
      ) : (
        <>
          {filterBar(m, "rib-collar-shade-approval", q, [
            { name: "buyer", label: m("f.buyer_ac"), type: "select", options: o.buyers },
            { name: "style", label: m("f.style_order"), type: "text" },
            { name: "colour", label: m("f.colour"), type: "select", options: o.colours },
            { name: "batch", label: m("f.batch_hash"), type: "text", placeholder: "B-26061108" },
            { name: "status", label: m("f.qc_status"), type: "select", options: opts(m, "shade", SHADE) },
            { name: "from", label: m("f.rcv_from"), type: "date" },
            { name: "to", label: m("f.rcv_to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="rib-collar-shade-approval"
            rows={shown}
            emptyState={{ title: m("records.empty_shade"), description: m("records.empty_shade_hint") }}
            columns={[
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "lot_no", label: m("col.lot") },
              { key: "sl_no", label: m("col.sl_no"), type: "number", align: "right" },
              { key: "b_qty", label: m("col.b_qty"), type: "number", align: "right" },
              { key: "rp_qty", label: m("col.rp_qty"), type: "number", align: "right" },
              { key: "rcv_date", label: m("col.rcv_date"), type: "date" },
              { key: "aprv_date", label: m("col.aprv_date"), type: "date" },
              { key: "buyer", label: m("col.buyer_name") },
              { key: "style_order", label: m("col.style_order") },
              { key: "colour", label: m("col.colour") },
              { key: "g_qty", label: m("col.g_qty"), type: "number", align: "right" },
              { key: "g_roll", label: m("col.g_roll"), type: "number", align: "right" },
              { key: "fin_qty", label: m("col.fin_qty"), type: "number", align: "right" },
              { key: "fin_roll", label: m("col.fin_roll"), type: "number", align: "right" },
              { key: "created_by_name", label: m("col.created_by") },
              { key: "status_label", label: m("col.qc_status"), type: "badge" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-shade-approvals", []), refKey: "ref", title: m("tab.shade"), submitLabel: m("btn.save_shade"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-shade-approvals") : undefined}
          />
        </>
      ),
  };
};

const QC_SCREENS = {
  "check-roll-inspection": checkRoll,
  "complete-batch-program": completion,
  "rib-collar-shade-approval": shadeApproval,
};

export { QC_SCREENS };
