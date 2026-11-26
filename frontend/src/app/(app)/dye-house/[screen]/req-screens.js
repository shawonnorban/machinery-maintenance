import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ChemRequisitionEditor, ActionRegister } from "@/components/dyehouse/chem-requisition";
import * as actions from "./actions";

/*
 * Dye House → Requisition (AnnotechERP 17/05), each laid out as Brickland
 * lays it out: the four dye and chemical indents (batch programme, machine
 * wash, finishing process, re-process) and the Print desk that raises any of
 * them and issues them, the Re-Process Approval Desk, Dye Batch Re-Process
 * (check-roll returns) and Dye Machine Maintenance.
 */

const look = () => apiFetch("/dyehouse/batch/options");
const opts = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const none = (list) => [{ value: "", label: "—" }, ...list];
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/dye-house/${key}`;
const PROCESS = ["RE_MATCHING", "RE_WASH", "RE_DYEING", "STRIPPING", "SOFTENER"];
const STATUSES = ["WAITING", "PARTIAL", "ISSUED", "CANCELLED"];
const KINDS = ["BATCH_PROGRAM", "MC_WASH", "FINISHING", "REPROCESS"];
const TAB = { BATCH_PROGRAM: "tab.new_batch", MC_WASH: "tab.new_mc", FINISHING: "tab.new_fin", REPROCESS: "tab.new_rp" };

/** A register's rows with the words its grid shows. */
const decorate = (m, rows) =>
  rows.map((r) => ({
    ...r,
    status_label: r.approval === "REJECTED" ? m("opt.approval.REJECTED") : m(`opt.status.${r.status}`),
    approval_label: m(`opt.approval.${r.approval}`),
    process_label: r.process_type ? m(`opt.process.${r.process_type}`) : null,
    // What the Print desk may still do with it.
    issuable: r.status === "WAITING" && ["NOT_REQUIRED", "APPROVED"].includes(r.approval) ? "Y" : "N",
    deletable: r.status === "WAITING" && r.issued_qty === 0 ? "Y" : "N",
  }));

const statusField = (m, name = "status") => ({ name, label: m("f.status"), type: "select", options: opts(m, "status", STATUSES) });
const statusMatch = (r, s) => !s || r.status === s;

/** One requisition kind's screen: its register and the editor tab. */
function kindScreen(kind, key, filterFields, filterRow, columns) {
  return async ({ m, te, tab, canManage, searchParams: q = {} }) => {
    const [rows, o] = await Promise.all([apiFetch(`/dyehouse/chem-requisitions?for=${kind}`), look()]);
    const shown = decorate(m, rows).filter((r) => has(r.req_no, q.no) && statusMatch(r, q.status) && filterRow(r, q));
    return {
      tabs: [
        { key: "records", label: te("records"), icon: "Table2", count: rows.length },
        { key: "entry", label: m(TAB[kind]), icon: "Layers" },
      ],
      body: () =>
        tab === "entry" ? (
          <ChemRequisitionEditor kind={kind} requisitions={rows} options={o} canManage={canManage} load={actions.loadRequisition} save={actions.saveRequisition} backHref={`${base(key)}?tab=records`} initialNo={q.req ?? null} />
        ) : (
          <>
            <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={[{ name: "no", label: m("f.req_no"), type: "text", placeholder: "IR-DC-2026064108" }, ...filterFields(m, o), statusField(m)]} />
            <RecordsGrid exportName={key} rows={shown} columns={columns(m)} />
          </>
        ),
    };
  };
}

const batchProgram = kindScreen(
  "BATCH_PROGRAM",
  "requisition-for-dc-for-batch",
  (m, o) => [
    { name: "from", label: m("f.req_date_from"), type: "date" },
    { name: "to", label: m("f.req_date_to"), type: "date" },
    { name: "batch", label: m("f.batch"), type: "text", placeholder: "B-26080001" },
    { name: "machine", label: m("f.machine"), type: "select", options: o.machines },
  ],
  (r, q) => within(r.req_date, q.from, q.to) && has(r.batch_no, q.batch) && (!q.machine || r.machine_id === q.machine),
  (m) => [
    { key: "req_no", label: m("col.req_no"), type: "mono" },
    { key: "req_date", label: m("col.req_date"), type: "date" },
    { key: "colour", label: m("col.colour") },
    { key: "process_label", label: m("col.process_type") },
    { key: "batch_no", label: m("col.batch_no"), type: "mono" },
    { key: "machine", label: m("col.machine") },
    { key: "required_qty", label: m("col.required_qty"), type: "number", align: "right" },
    { key: "issued_qty", label: m("col.issued_qty"), type: "number", align: "right" },
    { key: "items", label: m("col.items"), type: "number", align: "right" },
    { key: "status_label", label: m("col.status"), type: "badge" },
  ],
);

const machineWash = kindScreen(
  "MC_WASH",
  "requisition-for-dc-machine-wash",
  (m, o) => [
    { name: "from", label: m("f.date_from"), type: "date" },
    { name: "to", label: m("f.date_to"), type: "date" },
    { name: "party", label: m("f.company"), type: "select", options: o.companies },
    { name: "machine", label: m("f.machine_no"), type: "select", options: o.machines },
  ],
  (r, q) => within(r.req_date, q.from, q.to) && (!q.party || (r.party_id ?? q.party) === q.party) && (!q.machine || r.machine_id === q.machine),
  (m) => [
    { key: "req_no", label: m("col.req_no"), type: "mono" },
    { key: "req_date", label: m("col.req_date"), type: "date" },
    { key: "company", label: m("col.company") },
    { key: "req_type", label: m("col.req_type") },
    { key: "store", label: m("col.from_store") },
    { key: "machine", label: m("col.machine") },
    { key: "required_qty", label: m("col.required_qty"), type: "number", align: "right" },
    { key: "issued_qty", label: m("col.issued_qty"), type: "number", align: "right" },
    { key: "balance_qty", label: m("col.balance_qty"), type: "number", align: "right" },
    { key: "remarks", label: m("col.remarks") },
    { key: "status_label", label: m("col.status"), type: "badge" },
  ],
);

const finishing = kindScreen(
  "FINISHING",
  "requisition-for-dc-finishing-process",
  (m, o) => [
    { name: "from", label: m("f.date_from"), type: "date" },
    { name: "to", label: m("f.date_to"), type: "date" },
    { name: "buyer", label: m("f.buyer"), type: "select", options: o.buyers },
    { name: "order", label: m("f.style_order"), type: "select", options: o.orders },
    { name: "colour", label: m("f.colour"), type: "select", options: o.colours },
    { name: "batch", label: m("f.batch_short"), type: "text", placeholder: "B-26080001" },
  ],
  (r, q) => within(r.req_date, q.from, q.to) && (!q.buyer || r.buyer_id === q.buyer) && (!q.order || r.order_id === q.order) && (!q.colour || r.colour_id === q.colour) && has(r.batch_no, q.batch),
  (m) => [
    { key: "req_no", label: m("col.req_no"), type: "mono" },
    { key: "req_date", label: m("col.req_date"), type: "date" },
    { key: "company", label: m("col.unit") },
    { key: "req_type", label: m("col.req_type") },
    { key: "store", label: m("col.from_store") },
    { key: "required_qty", label: m("col.required_qty"), type: "number", align: "right" },
    { key: "issued_qty", label: m("col.issued_qty"), type: "number", align: "right" },
    { key: "balance_qty", label: m("col.balance_qty"), type: "number", align: "right" },
    { key: "order_info", label: m("col.order_info") },
    { key: "batch_info", label: m("col.batch_info") },
    { key: "status_label", label: m("col.status"), type: "badge" },
  ],
);

const reprocessUpdate = kindScreen(
  "REPROCESS",
  "update-reprocess",
  (m, o) => [
    { name: "from", label: m("f.req_date_from"), type: "date" },
    { name: "to", label: m("f.req_date_to"), type: "date" },
    { name: "colour", label: m("f.colour_name"), type: "select", options: o.colours },
    { name: "process", label: m("f.process_type"), type: "select", options: opts(m, "process", PROCESS) },
    { name: "store", label: m("f.req_store"), type: "select", options: o.stores },
    { name: "batch", label: m("f.batch"), type: "text", placeholder: "B-26060327" },
  ],
  (r, q) => within(r.req_date, q.from, q.to) && (!q.colour || r.colour_id === q.colour) && (!q.process || r.process_type === q.process) && (!q.store || r.store_id === q.store) && has(r.batch_no, q.batch),
  (m) => [
    { key: "req_no", label: m("col.req_no"), type: "mono" },
    { key: "req_date", label: m("col.req_date"), type: "date" },
    { key: "colour", label: m("col.colour") },
    { key: "process_label", label: m("col.process_type") },
    { key: "store", label: m("col.from_store") },
    { key: "batch_no", label: m("col.batch_no"), type: "mono" },
    { key: "remarks", label: m("col.remarks") },
    { key: "status_label", label: m("col.status"), type: "badge" },
  ],
);

const approvalDesk = async ({ m, searchParams: q = {}, permissions = [] }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/chem-requisitions?for=REPROCESS"), look()]);
  const canApprove = permissions.includes("dyehouse.requisition.approve");
  const shown = decorate(m, rows).filter(
    (r) => has(r.req_no, q.no) && (!q.party || (r.party_id ?? q.party) === q.party) && (!q.process || r.process_type === q.process) && (!q.buyer || r.buyer_id === q.buyer) && has(r.batch_no, q.batch) && (!q.approval || r.approval === q.approval),
  );
  const columns = [
    { key: "req_no", label: m("col.req_no"), type: "mono" },
    { key: "company", label: m("col.company") },
    { key: "process_label", label: m("col.process_type") },
    { key: "order_info", label: m("col.order_info") },
    { key: "batch_no", label: m("col.batch_no"), type: "mono" },
    { key: "colour", label: m("col.colour") },
    { key: "required_qty", label: m("col.required_qty"), type: "number", align: "right" },
    { key: "machine", label: m("col.machine") },
    { key: "approval_label", label: m("col.status"), type: "badge" },
  ];
  const decide = (verb, run) => ({
    key: verb,
    label: m(`ap.${verb}`),
    title: m(`ap.${verb}_title`),
    text: m(`ap.${verb}_text`, { no: ":no" }),
    done: m(verb === "approve" ? "ap.approved" : "ap.rejected", { no: ":no" }),
    run,
    when: { field: "approval", in: ["PENDING"] },
    destructive: verb === "reject",
  });
  return {
    tabs: [],
    body: () => (
      <>
        <FilterBar
          instant
          basePath={base("reprocess-approval")}
          values={q}
          labels={{ show: m("filter.show"), reset: m("filter.clear") }}
          fields={[
            { name: "no", label: m("f.req_no_short"), type: "text", placeholder: "IR-DC-2026063369" },
            { name: "party", label: m("f.company"), type: "select", options: o.companies },
            { name: "process", label: m("f.rp_type"), type: "select", options: opts(m, "process", PROCESS) },
            { name: "buyer", label: m("f.buyer"), type: "select", options: o.buyers },
            { name: "batch", label: m("f.batch_short"), type: "text", placeholder: "B-26060327" },
            { name: "approval", label: m("f.status"), type: "select", options: opts(m, "approval", ["NOT_REQUIRED", "PENDING", "APPROVED", "REJECTED"]) },
          ]}
        />
        {canApprove ? (
          <ActionRegister exportName="reprocess-approval" rows={shown} columns={columns} refKey="req_no" actions={[decide("approve", actions.approveRequisition), decide("reject", actions.rejectRequisition)]} />
        ) : (
          <RecordsGrid exportName="reprocess-approval" rows={shown} columns={columns} />
        )}
      </>
    ),
  };
};

const printDesk = async ({ m, te, tab, canManage, searchParams: q = {}, permissions = [] }) => {
  const [rows, o] = await Promise.all([apiFetch("/dyehouse/chem-requisitions"), look()]);
  const canIssue = permissions.includes("dyehouse.requisition.issue") || canManage;
  const shown = decorate(m, rows).filter(
    (r) =>
      has(r.req_no, q.no) &&
      within(r.req_date, q.from, q.to) &&
      (!q.kind || r.req_for === q.kind) &&
      (!q.buyer || r.buyer_id === q.buyer) &&
      (!q.order || r.order_id === q.order) &&
      (!q.colour || r.colour_id === q.colour) &&
      has(r.batch_no, q.batch) &&
      statusMatch(r, q.status),
  );
  const columns = [
    { key: "req_no", label: m("col.req_no"), type: "mono" },
    { key: "req_date", label: m("col.req_date"), type: "date" },
    { key: "company", label: m("col.company") },
    { key: "req_type", label: m("col.req_type") },
    { key: "store", label: m("col.from_store") },
    { key: "machine", label: m("col.machine") },
    { key: "required_qty", label: m("col.required_qty"), type: "number", align: "right" },
    { key: "issued_qty", label: m("col.issued_qty"), type: "number", align: "right" },
    { key: "balance_qty", label: m("col.balance_qty"), type: "number", align: "right" },
    { key: "remarks", label: m("col.remarks") },
    { key: "status_label", label: m("col.status"), type: "badge" },
  ];
  const rowActions = [
    ...(canIssue ? [{ key: "issue", label: m("is.issue"), title: m("is.title"), text: m("is.text", { no: ":no" }), done: m("is.done", { no: ":no" }), run: actions.issueRequisition, when: { field: "issuable", in: ["Y"] } }] : []),
    ...(canManage
      ? [{ key: "delete", label: te("delete"), title: te("remove_title", { noun: m("noun.requisition") }), text: te("remove_text", { name: ":no" }), done: te("deleted"), run: actions.deleteRequisition, when: { field: "deletable", in: ["Y"] }, destructive: true }]
      : []),
  ];
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "entry", label: m("tab.raise"), icon: "Layers" },
    ],
    body: () =>
      tab === "entry" ? (
        <ChemRequisitionEditor requisitions={rows} options={o} canManage={canManage} load={actions.loadRequisition} save={actions.saveRequisition} backHref={`${base("print-chemical-requisition")}?tab=records`} initialNo={q.req ?? null} />
      ) : (
        <>
          <FilterBar
            instant
            basePath={base("print-chemical-requisition")}
            keep={{ tab: "records" }}
            values={q}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "no", label: m("f.req_no"), type: "text", placeholder: "IR-DC-2026064108" },
              { name: "from", label: m("f.date_from"), type: "date" },
              { name: "to", label: m("f.date_to"), type: "date" },
              { name: "kind", label: m("f.req_type_print"), type: "select", options: KINDS.map((k) => ({ value: k, label: m(`reqtype.${k}`) })) },
              { name: "buyer", label: m("f.buyer"), type: "select", options: o.buyers },
              { name: "order", label: m("f.style_order"), type: "select", options: o.orders },
              { name: "colour", label: m("f.colour"), type: "select", options: o.colours },
              { name: "batch", label: m("f.batch_short"), type: "text", placeholder: "B-26080001" },
              statusField(m),
            ]}
          />
          {rowActions.length ? <ActionRegister exportName="print-chemical-requisition" rows={shown} columns={columns} refKey="req_no" actions={rowActions} /> : <RecordsGrid exportName="print-chemical-requisition" rows={shown} columns={columns} />}
        </>
      ),
  };
};

const checkRollReturn = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-check-roll-returns"), look()]);
  const fields = [
    { name: "return_no", label: m("f.return_no"), readOnly: true, placeholder: m("ed.req_no_ph"), help: m("h.return_no") },
    { name: "req_date", label: m("f.req_date"), type: "date", required: true },
    { name: "not_ok_date", label: m("f.not_ok_date"), type: "date", help: m("h.not_ok") },
    { name: "reprocess_type", label: m("f.rp_type_full"), type: "select", required: true, options: opts(m, "process", PROCESS) },
    { name: "party_id", label: m("f.company"), type: "select", options: none(o.companies), placeholder: m("f.select_company") },
    { name: "store_id", label: m("f.req_store"), type: "select", options: none(o.stores), placeholder: m("f.select_store") },
    { name: "batch_id", label: m("f.batch"), type: "select", options: none(o.batches.map((b) => ({ value: b.value, label: b.label }))), placeholder: m("f.select_batch") },
    { name: "colour_id", label: m("f.colour"), type: "select", options: none(o.colours) },
    { name: "dye_machine_id", label: m("f.machine"), type: "select", options: none(o.machines), placeholder: m("f.select_machine") },
    { name: "qty_kg", label: m("f.qty"), type: "number", required: true },
    { name: "defect", label: m("f.defect"), placeholder: m("h.defect_ph") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => has(r.return_no, q.no) && within(r.req_date, q.from, q.to) && within(r.not_ok_date, q.nfrom, q.nto) && (!q.colour || r.colour_id === q.colour) && (!q.process || r.reprocess_type === q.process) && has(r.batch_no, q.batch) && (!q.machine || r.dye_machine_id === q.machine))
    .map((r) => ({
      ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])),
      qty_kg: String(r.qty_kg),
      type_label: m(`opt.process.${r.reprocess_type}`),
      waited_label: r.waited === null ? null : String(r.waited),
      open: r.sent_back ? "N" : "Y",
    }));
  const columns = [
    { key: "return_no", label: m("col.req_no"), type: "mono" },
    { key: "req_date", label: m("col.req_date"), type: "date" },
    { key: "not_ok_date", label: m("col.not_ok"), type: "date" },
    { key: "company", label: m("col.company") },
    { key: "type_label", label: m("col.rp_type") },
    { key: "store", label: m("col.req_store") },
    { key: "batch_no", label: m("col.batch_no"), type: "mono" },
    { key: "colour", label: m("col.colour") },
    { key: "defect", label: m("col.defect") },
    { key: "qty_kg", label: m("col.qty"), type: "number", align: "right" },
    { key: "waited_label", label: m("col.waited"), align: "right" },
    { key: "remarks", label: m("col.remarks") },
  ];
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-check-roll-returns", [])} title={m("t.new_crr")} description={m("h.crr_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} />
      ) : (
        <>
          <FilterBar
            instant
            basePath={base("dyeing-reprocess")}
            keep={{ tab: "records" }}
            values={q}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "no", label: m("f.req_no"), type: "text", placeholder: "CRR-2026080001" },
              { name: "from", label: m("f.req_date_from2"), type: "date" },
              { name: "to", label: m("f.req_date_to2"), type: "date" },
              { name: "nfrom", label: m("f.not_ok_from"), type: "date" },
              { name: "nto", label: m("f.not_ok_to"), type: "date" },
              { name: "colour", label: m("f.colour"), type: "select", options: o.colours },
              { name: "process", label: m("f.process_type"), type: "select", options: opts(m, "process", PROCESS) },
              { name: "batch", label: m("f.batch"), type: "text", placeholder: "B-26060327" },
              { name: "machine", label: m("f.machine"), type: "select", options: o.machines },
            ]}
          />
          <ActionRegister
            exportName="dye-batch-reprocess"
            rows={shown}
            columns={columns}
            refKey="return_no"
            actions={canManage ? [{ key: "back", label: m("crr.back"), title: m("crr.back_title", { no: ":no" }), text: m("crr.back_text"), done: m("crr.sent_toast", { no: ":no" }), run: actions.sendBackReturn, when: { field: "open", in: ["Y"] } }] : []}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-check-roll-returns", []), refKey: "return_no", title: m("t.new_crr"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-check-roll-returns") : undefined}
          />
        </>
      ),
  };
};

const machineMaintenance = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const [rows, o] = await Promise.all([apiFetch("/erp/dye-machine-maintenance"), look()]);
  const fields = [
    { name: "dye_machine_id", label: m("f.dyeing_mc"), type: "select", required: true, options: o.machines, placeholder: m("f.select_machine") },
    { name: "log_date", label: m("f.log_date"), type: "date", required: true },
    { name: "report_time", label: m("f.report_time"), type: "datetime", required: true, help: m("h.report_time") },
    { name: "problem_time", label: m("f.problem_time"), type: "datetime", help: m("h.problem_time") },
    { name: "resolved_time", label: m("f.resolved_time"), type: "datetime", help: m("h.resolved_time") },
    { name: "defect_type", label: m("f.defect_type"), required: true, placeholder: m("h.defect_type_ph") },
    { name: "report_by", label: m("f.report_by"), type: "select", options: none(o.employees), placeholder: m("f.select_employee") },
    { name: "service_by", label: m("f.service_by"), type: "select", options: none(o.employees), placeholder: m("f.select_engineer") },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "mm_status", ["REPORTED", "IN_PROGRESS", "RESOLVED", "CANCELLED"]) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const defects = [...new Set(rows.map((r) => r.defect_type))].sort().map((d) => ({ value: d, label: d }));
  const shown = rows
    .filter((r) => (!q.machine || r.dye_machine_id === q.machine) && within(r.log_date, q.from, q.to) && (!q.defect || r.defect_type === q.defect) && (!q.status || r.status === q.status))
    .map((r) => ({ ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v])), status_label: m(`opt.mm_status.${r.status}`), ref: `${r.machine}-${r.report_time}` }));
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "dye-machine-maintenance", [])} title={m("t.new_mm")} description={m("h.mm_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} />
      ) : (
        <>
          <FilterBar
            instant
            basePath={base("dyeing-mc-maintenance")}
            keep={{ tab: "records" }}
            values={q}
            labels={{ show: m("filter.show"), reset: m("filter.clear") }}
            fields={[
              { name: "machine", label: m("f.dyeing_mc"), type: "select", options: o.machines },
              { name: "from", label: m("f.date_from"), type: "date" },
              { name: "to", label: m("f.date_to"), type: "date" },
              { name: "defect", label: m("f.defect_type"), type: "select", options: defects },
              { name: "status", label: m("f.status"), type: "select", options: opts(m, "mm_status", ["REPORTED", "IN_PROGRESS", "RESOLVED", "CANCELLED"]) },
            ]}
          />
          <RecordsGrid
            exportName="dye-machine-maintenance"
            rows={shown}
            columns={[
              { key: "machine", label: m("col.dyeing_mc"), type: "mono" },
              { key: "log_date", label: m("col.date"), type: "date" },
              { key: "report_time", label: m("col.report_time"), type: "datetime" },
              { key: "problem_time", label: m("col.problem_time"), type: "datetime" },
              { key: "resolved_time", label: m("col.resolved_time"), type: "datetime" },
              { key: "downtime", label: m("col.downtime"), align: "right" },
              { key: "defect_type", label: m("col.defect_type") },
              { key: "reporter", label: m("col.report_by") },
              { key: "servicer", label: m("col.service_by") },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "dye-machine-maintenance", []), refKey: "ref", title: m("t.new_mm"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "dye-machine-maintenance") : undefined}
          />
        </>
      ),
  };
};

const REQ_SCREENS = {
  "requisition-for-dc-for-batch": batchProgram,
  "requisition-for-dc-machine-wash": machineWash,
  "requisition-for-dc-finishing-process": finishing,
  "reprocess-approval": approvalDesk,
  "update-reprocess": reprocessUpdate,
  "dyeing-reprocess": checkRollReturn,
  "dyeing-mc-maintenance": machineMaintenance,
  "print-chemical-requisition": printDesk,
};

export { REQ_SCREENS };
