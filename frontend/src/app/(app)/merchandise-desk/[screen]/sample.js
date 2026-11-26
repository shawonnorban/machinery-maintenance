import { apiFetch } from "@/lib/api-server";
import { PickPanel } from "@/components/erp/pick-panel";
import { LabdipShades } from "@/components/merchandise/labdip-shades";
import { SampleLines } from "@/components/merchandise/sample-lines";
import { PrintLines } from "@/components/merchandise/print-lines";
import { SampleProduction } from "@/components/merchandise/sample-production";
import { ChallanItems } from "@/components/merchandise/challan-items";
import { BulkShade } from "@/components/merchandise/bulk-shade";
import { MeasurePoints } from "@/components/merchandise/measure-points";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UrlFilters } from "@/components/erp/url-filters";
import { findByRef, refsFor } from "@/lib/row-ref";
import { masterScreen } from "./screens";
import * as actions from "./actions";

/*
 * Merchandise Desk → Lab Dip & Sample (AnnotechERP 06/10): Lab Dip Programme
 * — lab dip requests raised per buyer to a lab, and the shades on each — and
 * Lab Dip Submission, each shade's way to the buyer and the buyer's verdict —
 * Sample Programme, the samples planned against a job and their lines — and
 * Print Design Sheet, the print design and strike-off asked for per style.
 */

const STATUSES = ["DRAFT", "SUBMITTED", "IN_PROGRESS", "SENT", "APPROVED", "REJECTED", "CANCELLED"];

const labelled = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const pickOne = (m, list) => [{ value: "", label: m("select") }, ...list];
const distinct = (rows, key) => [...new Set(rows.map((r) => r[key]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));

/**
 * Labdip Specification, shared by Lab Dip Programme and Lab Dip Submission:
 * the programs on the left (named in the url by their number), the shades of
 * the one picked beside them — on Submission with their way to the buyer.
 */
async function specPanel({ m, rows, canManage, searchParams, screen, submission = false }) {
  const refs = refsFor(rows, "program_no");
  const picked = findByRef(rows, "program_no", searchParams.program);
  const data = picked ? await apiFetch(`/merchandise/labdip-programs/${picked.id}/shades`) : null;
  return (
    <PickPanel
      title={m("ldp.programs")}
      items={rows.map((r) => ({ key: refs.get(r.id), label: r.program_no, sub: [r.buyer, r.request_to_name].filter(Boolean).join(" · "), count: r.shades }))}
      active={picked ? refs.get(picked.id) : null}
      hrefBase={`/merchandise-desk/${screen}?tab=specification&program=`}
      searchPlaceholder={m("ldp.search")}
      noMatch={m("ldp.no_match")}
      panelTitle={picked ? m("ldp.spec_of", { no: picked.program_no }) : m("ldp.tab_spec")}
      panelHint={m("ldp.spec_hint")}
      emptyTitle={m("ldp.pick")}
      emptyText={m("ldp.pick_hint")}
    >
      {data ? <LabdipShades key={picked.id} programId={picked.id} data={data} save={actions.saveLabdipShades} canManage={canManage} submission={submission} /> : null}
    </PickPanel>
  );
}

const labdipProgram = masterScreen({
  resource: "merch-labdip-programs",
  screen: "labdip-program",
  noun: "labdip_program",
  nameKey: "program_no",
  refKey: "program_no",
  newLabel: "ldp.new",
  description: "ldp.new_hint",
  formColumns: 3,
  booleans: [],
  load: async () => {
    const [orderLook, look] = await Promise.all([apiFetch("/merchandise/orders/lookups"), apiFetch("/merchandise/lookups")]);
    return { orderLook, look };
  },
  // The lab is an internal section or a nominated house, picked as one value.
  fields: ({ m, orderLook, look }) => [
    { name: "buyer_office_id", label: m("ldp.f.buyer_ac"), type: "select", required: true, options: pickOne(m, look.offices) },
    { name: "buyer_id", label: m("ldp.f.buyer"), type: "select", required: true, options: pickOne(m, orderLook.buyers) },
    { name: "request_date", label: m("ldp.f.request_date"), type: "date", required: true },
    {
      name: "request_to",
      label: m("ldp.f.request_to"),
      type: "select",
      required: true,
      help: m("ldp.request_to_hint"),
      options: pickOne(m, [
        ...look.sections.map((s) => ({ value: `SECTION:${s.value}`, label: `${m("ldp.lab_section")} · ${s.label}` })),
        ...look.suppliers.map((s) => ({ value: `SUPPLIER:${s.value}`, label: `${m("ldp.lab_house")} · ${s.label}` })),
      ]),
    },
    { name: "request_by", label: m("ldp.f.request_by"), type: "select", required: true, options: pickOne(m, look.users) },
    { name: "attn_user_id", label: m("ldp.f.attn_user"), type: "select", options: pickOne(m, look.users) },
    { name: "remarks", label: m("ldp.f.remarks"), type: "textarea" },
    { name: "status", label: m("ldp.f.status"), type: "select", default: "DRAFT", options: pickOne(m, labelled(m, "ldp.status", STATUSES)) },
  ],
  decorate: (row, { m }) => ({
    ...row,
    attn_user_id: row.attn_user_id ?? "",
    remarks: row.remarks ?? "",
    status_label: row.status ? m(`ldp.status.${row.status}`) : null,
  }),
  columns: ({ m }) => [
    { key: "program_no", label: m("ldp.f.program_no"), type: "mono" },
    { key: "buyer", label: m("ldp.f.buyer") },
    { key: "buyer_ac", label: m("ldp.f.buyer_ac") },
    { key: "request_date", label: m("ldp.f.request_date"), type: "date" },
    { key: "request_to_name", label: m("ldp.f.request_to") },
    { key: "request_by_name", label: m("ldp.f.request_by") },
    { key: "attn_user_name", label: m("ldp.f.attn_user") },
    { key: "shades", label: m("ldp.f.shades"), type: "number" },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer_ac", field: "buyer_ac", label: m("ldp.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "buyer", field: "buyer", label: m("ldp.f.buyer"), options: distinct(rows, "buyer") },
    { name: "request_to", field: "request_to_name", label: m("ldp.f.request_to"), options: distinct(rows, "request_to_name") },
    { name: "from", label: m("ldp.f.date_from"), type: "date", match: (r, v) => r.request_date >= v },
    { name: "to", label: m("ldp.f.date_to"), type: "date", match: (r, v) => r.request_date <= v },
    { name: "status", field: "status", label: m("ldp.f.status"), options: labelled(m, "ldp.status", STATUSES) },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "specification",
      label: m("ldp.tab_spec"),
      icon: "ListChecks",
      render: () => specPanel({ m, rows, canManage, searchParams, screen: "labdip-program" }),
    },
  ],
});

const VERDICTS = ["PENDING", "SUBMITTED", "APPROVED", "REJECTED", "RESUBMIT"];

/**
 * Lab Dip Submission: Records — every shade on its way to the buyer, read
 * only as Brickland has it — and Labdip Specification, where each shade's
 * submission and the buyer's verdict are recorded.
 */
async function labdipSubmission({ m, te, tab, canManage, searchParams = {} }) {
  if (tab === "specification") {
    const programs = await apiFetch("/erp/merch-labdip-programs");
    return {
      tabs: [{ key: "records", label: te("records"), icon: "Table2" }, { key: "specification", label: m("ldp.tab_spec"), icon: "ListChecks" }],
      body: () => specPanel({ m, rows: programs, canManage, searchParams, screen: "labdip-submission", submission: true }),
    };
  }
  const rows = await apiFetch("/merchandise/labdip-submissions");
  const has = (value, needle) => String(value ?? "").toLowerCase().includes(needle.toLowerCase());
  const filters = [
    { name: "buyer_ac", field: "buyer_ac", label: m("lds.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "style", label: m("lds.f.style"), type: "text", match: (r, v) => has(r.style_no, v) },
    { name: "order", label: m("lds.f.order"), type: "text", match: (r, v) => has(r.order_no, v) },
    { name: "from", label: m("lds.f.target_from"), type: "date", match: (r, v) => Boolean(r.target_date) && r.target_date >= v },
    { name: "to", label: m("lds.f.target_to"), type: "date", match: (r, v) => Boolean(r.target_date) && r.target_date <= v },
    { name: "status", field: "status", label: m("lds.f.status"), options: VERDICTS.map((v) => ({ value: v, label: m(`ldp.verdict.${v}`) })) },
  ];
  const shown = rows.filter((r) => filters.every((f) => !searchParams[f.name] || (f.match ? f.match(r, searchParams[f.name]) : String(r[f.field]) === searchParams[f.name])));
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, { key: "specification", label: m("ldp.tab_spec"), icon: "ListChecks" }],
    body: () => (
      <div className="flex flex-col gap-3">
        <div className="rounded-lg border border-border bg-surface p-4">
          <UrlFilters filters={filters.map(({ match, ...f }) => ({ ...f, allLabel: m("all") }))} shown={m("shown", { shown: shown.length, total: rows.length })} clearLabel={m("clear")} />
        </div>
        <RecordsGrid
          exportName="labdip-submission"
          rows={shown}
          header={{ title: m("screens.labdip-submission"), subtitle: m("lds.caption"), searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") }}
          columns={[
            { key: "style_no", label: m("lds.f.style") },
            { key: "order_no", label: m("lds.f.order") },
            { key: "program_no", label: m("lds.f.program"), type: "mono" },
            { key: "colour", label: m("lds.f.colour") },
            { key: "reg_date", label: m("lds.f.reg_date"), type: "date" },
            { key: "target_date", label: m("lds.f.target_date"), type: "date" },
            { key: "submitted_on", label: m("lds.f.submitted"), type: "date" },
            { key: "status", label: m("lds.f.feedback"), type: "badge", labels: Object.fromEntries(VERDICTS.map((v) => [v, m(`ldp.verdict.${v}`)])) },
          ]}
          emptyText={m("lds.empty")}
        />
      </div>
    ),
  };
}

/**
 * Sample Programme: Records, Sample Lines (a programme's lines beside the
 * list of programmes, named in the url by number) and New program — whose
 * save button Brickland calls "New program".
 */
const sampleProgram = masterScreen({
  resource: "merch-sample-programs",
  screen: "sample-program",
  noun: "sample_program",
  nameKey: "program_no",
  refKey: "program_no",
  tabLabel: "smp.new_tab",
  newLabel: "smp.new_title",
  submitLabel: "smp.new_tab",
  description: "smp.new_hint",
  formColumns: 3,
  booleans: [],
  load: async () => {
    const [look, orders] = await Promise.all([apiFetch("/merchandise/lookups"), apiFetch("/erp/merch-orders")]);
    return { look, orders };
  },
  fields: ({ m, look, orders }) => [
    { name: "program_date", label: m("smp.f.program_date"), type: "date", required: true },
    { name: "tran_ref", label: m("smp.f.tran_ref"), placeholder: "BK-BUL-00012", help: m("smp.tran_ref_hint") },
    { name: "buyer_office_id", label: m("smp.f.buyer_ac"), type: "select", options: pickOne(m, look.offices) },
    { name: "order_id", label: m("smp.f.job"), type: "select", options: pickOne(m, orders.map((o) => ({ value: o.id, label: `${o.order_no} · ${o.style_no} (${o.work_style_no || o.style_no})` }))) },
    { name: "work_style_no", label: m("smp.f.work_style") },
    { name: "status", label: m("smp.f.status"), type: "select", default: "DRAFT", options: pickOne(m, labelled(m, "ldp.status", STATUSES)) },
    { name: "remarks", label: m("smp.f.remarks"), type: "textarea" },
  ],
  decorate: (row) => ({ ...row, tran_ref: row.tran_ref ?? "", buyer_office_id: row.buyer_office_id ?? "", order_id: row.order_id ?? "", work_style_no: row.work_style_no ?? "", remarks: row.remarks ?? "" }),
  columns: ({ m }) => [
    { key: "program_date", label: m("smp.f.program_date"), type: "date" },
    { key: "program_no", label: m("smp.f.program_no"), type: "mono" },
    { key: "tran_ref", label: m("smp.f.tran_ref") },
    { key: "job_label", label: m("smp.f.job") },
    { key: "order_no", label: m("smp.f.order") },
    { key: "buyer_ac", label: m("smp.f.buyer_ac") },
    { key: "lines", label: m("smp.f.lines"), type: "number" },
    { key: "req_qty", label: m("smp.f.req_qty"), type: "number" },
    { key: "remarks", label: m("smp.f.remarks") },
    { key: "status", label: m("smp.f.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((v) => [v, m(`ldp.status.${v}`)])) },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer_ac", field: "buyer_ac", label: m("smp.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "job", label: m("smp.f.job"), type: "text", match: (r, v) => [r.job_label, r.order_no].some((x) => String(x ?? "").toLowerCase().includes(v.toLowerCase())) },
    { name: "from", label: m("smp.f.date_from"), type: "date", match: (r, v) => r.program_date >= v },
    { name: "to", label: m("smp.f.date_to"), type: "date", match: (r, v) => r.program_date <= v },
    { name: "status", field: "status", label: m("smp.f.status"), options: labelled(m, "ldp.status", STATUSES) },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "lines",
      label: m("smp.tab_lines"),
      icon: "ListChecks",
      render: async () => {
        const refs = refsFor(rows, "program_no");
        const picked = findByRef(rows, "program_no", searchParams.program);
        const data = picked ? await apiFetch(`/merchandise/sample-programs/${picked.id}/lines`) : null;
        return (
          <PickPanel
            title={m("ldp.programs")}
            items={rows.map((r) => ({ key: refs.get(r.id), label: r.program_no, sub: r.program_date, count: r.lines }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase="/merchandise-desk/sample-program?tab=lines&program="
            searchPlaceholder={m("ldp.search")}
            noMatch={m("ldp.no_match")}
            panelTitle={picked ? m("smp.spec_of", { no: picked.program_no }) : m("smp.spec")}
            panelHint={m("smp.spec_hint")}
            emptyTitle={m("smp.pick")}
            emptyText={m("smp.pick_hint")}
          >
            {data ? <SampleLines key={picked.id} programId={picked.id} data={data} add={actions.addSampleLine} save={actions.saveSampleLines} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

/**
 * Print Design Sheet: Records, Style & Order Lines (a sheet's lines beside
 * the list of sheets, named in the url by number) and New program — Brickland's
 * Program Parameter, saved with "Save program".
 */
const printDesign = masterScreen({
  resource: "merch-print-programs",
  screen: "print-design",
  noun: "print_program",
  nameKey: "program_no",
  refKey: "program_no",
  tabLabel: "pds.new_tab",
  newLabel: "pds.parameter",
  submitLabel: "pds.save",
  description: "pds.parameter_hint",
  formColumns: 3,
  booleans: [],
  load: async () => {
    const [look, orderLook, me] = await Promise.all([apiFetch("/merchandise/lookups"), apiFetch("/merchandise/orders/lookups"), apiFetch("/auth/me")]);
    return { look, orderLook, me };
  },
  fields: ({ m, look, orderLook, me }) => [
    { name: "buyer_office_id", label: m("pds.f.buyer_ac"), type: "select", required: true, options: pickOne(m, look.offices) },
    { name: "buyer_id", label: m("pds.f.buyer"), type: "select", required: true, options: pickOne(m, orderLook.buyers) },
    { name: "requisition_date", label: m("pds.f.requisition_date"), type: "date", required: true },
    { name: "program_by", label: m("pds.f.program_by"), type: "select", required: true, options: pickOne(m, look.users) },
    { name: "request_to", label: m("pds.f.request_to"), required: true },
    { name: "company", label: m("pds.f.company"), type: "select", required: true, default: me.company?.id ?? "", options: pickOne(m, me.company ? [{ value: me.company.id, label: me.company.name }] : []) },
  ],
  columns: ({ m }) => [
    { key: "program_no", label: m("pds.f.program_no"), type: "mono" },
    { key: "buyer", label: m("pds.f.buyer") },
    { key: "buyer_ac", label: m("pds.f.buyer_ac") },
    { key: "requisition_date", label: m("pds.f.requisition_date"), type: "date" },
    { key: "program_by_name", label: m("pds.f.program_by") },
    { key: "request_to", label: m("pds.f.request_to") },
    { key: "lines", label: m("pds.col.lines"), type: "number" },
    { key: "req_qty", label: m("pds.col.req_qty"), type: "number" },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer_ac", field: "buyer_ac", label: m("pds.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "buyer", field: "buyer", label: m("pds.f.buyer"), options: distinct(rows, "buyer") },
    { name: "from", label: m("pds.f.date_from"), type: "date", match: (r, v) => r.requisition_date >= v },
    { name: "to", label: m("pds.f.date_to"), type: "date", match: (r, v) => r.requisition_date <= v },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "lines",
      label: m("pds.tab_lines"),
      icon: "ListChecks",
      render: async () => {
        const refs = refsFor(rows, "program_no");
        const picked = findByRef(rows, "program_no", searchParams.program);
        const data = picked ? await apiFetch(`/merchandise/print-programs/${picked.id}/lines`) : null;
        return (
          <PickPanel
            title={m("pds.programs")}
            items={rows.map((r) => ({ key: refs.get(r.id), label: r.program_no, sub: [r.buyer, r.request_to].filter(Boolean).join(" · "), count: r.lines }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase="/merchandise-desk/print-design?tab=lines&program="
            searchPlaceholder={m("ldp.search")}
            noMatch={m("ldp.no_match")}
            panelTitle={picked ? m("pds.list_of", { no: picked.program_no }) : m("pds.list")}
            panelHint={m("pds.list_hint")}
            emptyTitle={m("pds.pick")}
            emptyText={m("pds.pick_hint")}
          >
            {data ? <PrintLines key={picked.id} programId={picked.id} data={data} save={actions.savePrintLines} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

/**
 * Sample Production Entry: one workbench, no tabs, as Brickland has it.
 * `?batch=<Batch#>` opens that batch.
 */
async function sampleProduction({ canManage, searchParams = {} }) {
  const data = await apiFetch("/merchandise/sample-production");
  const picked = searchParams.batch ? data.batches.find((b) => b.batch_no === searchParams.batch) : null;
  const initial = picked ? await apiFetch(`/merchandise/sample-batches/${picked.id}`) : null;
  const today = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
  const act = {
    load: actions.loadSampleProduction,
    show: actions.showSampleBatch,
    open: actions.openSampleBatch,
    add: actions.addToSampleBatch,
    drop: actions.dropFromSampleBatch,
    save: actions.saveSampleBatch,
    submit: actions.submitSampleBatch,
  };
  return {
    tabs: [],
    body: () => <SampleProduction key={initial?.batch.id ?? "none"} data={data} initial={initial} act={act} canManage={canManage} today={today} />,
  };
}

/**
 * Sample Delivery Challan: Records, Challan Items (a challan's items beside
 * the list of challans, named in the url by number) and New challan.
 */
const sampleChallan = masterScreen({
  resource: "merch-sample-challans",
  screen: "sample-delivery-challan",
  noun: "sample_challan",
  nameKey: "challan_no",
  refKey: "challan_no",
  newLabel: "sdc.new",
  description: "sdc.new_hint",
  formColumns: 3,
  booleans: [],
  load: async () => ({ look: await apiFetch("/merchandise/lookups") }),
  fields: ({ m, look }) => [
    { name: "challan_date", label: m("sdc.f.challan_date"), type: "date", required: true },
    { name: "buyer_office_id", label: m("sdc.f.buyer_office"), type: "select", options: pickOne(m, look.offices) },
    { name: "vehicle_no", label: m("sdc.f.vehicle") },
    { name: "carrier", label: m("sdc.f.carrier"), placeholder: "DHL" },
    { name: "status", label: m("sdc.f.status"), type: "select", default: "DRAFT", options: pickOne(m, labelled(m, "ldp.status", STATUSES)) },
    { name: "remarks", label: m("sdc.f.remarks"), type: "textarea" },
  ],
  decorate: (row) => ({ ...row, buyer_office_id: row.buyer_office_id ?? "", vehicle_no: row.vehicle_no ?? "", carrier: row.carrier ?? "", remarks: row.remarks ?? "" }),
  columns: ({ m }) => [
    { key: "created_on", label: m("sdc.f.created_on"), type: "date" },
    { key: "buyer_office", label: m("sdc.f.buyer_office") },
    { key: "challan_no", label: m("sdc.f.challan_no"), type: "mono" },
    { key: "challan_date", label: m("sdc.f.chln_date"), type: "date" },
    { key: "vehicle_no", label: m("sdc.f.vehicle") },
    { key: "carrier", label: m("sdc.f.carrier") },
    { key: "items", label: m("sdc.f.items"), type: "number" },
    { key: "status", label: m("sdc.f.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((v) => [v, m(`ldp.status.${v}`)])) },
  ],
  filters: ({ m, rows }) => [
    { name: "created_from", label: m("sdc.f.created_from"), type: "date", match: (r, v) => (r.created_on ?? "") >= v },
    { name: "created_to", label: m("sdc.f.created_to"), type: "date", match: (r, v) => (r.created_on ?? "") <= v },
    { name: "challan", label: m("sdc.f.challan_no"), type: "text", match: (r, v) => String(r.challan_no).toLowerCase().includes(v.toLowerCase()) },
    { name: "from", label: m("sdc.f.chln_from"), type: "date", match: (r, v) => r.challan_date >= v },
    { name: "to", label: m("sdc.f.chln_to"), type: "date", match: (r, v) => r.challan_date <= v },
    { name: "office", field: "buyer_office", label: m("sdc.f.buyer_office"), options: distinct(rows, "buyer_office") },
    { name: "status", field: "status", label: m("sdc.f.status"), options: labelled(m, "ldp.status", STATUSES) },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "items",
      label: m("sdc.tab_items"),
      icon: "ListChecks",
      render: async () => {
        const refs = refsFor(rows, "challan_no");
        const picked = findByRef(rows, "challan_no", searchParams.challan);
        const data = picked ? await apiFetch(`/merchandise/sample-challans/${picked.id}/items`) : null;
        return (
          <PickPanel
            title={m("sdc.challans")}
            items={rows.map((r) => ({ key: refs.get(r.id), label: r.challan_no, sub: [r.challan_date, r.buyer_office].filter(Boolean).join(" · "), count: r.items }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase="/merchandise-desk/sample-delivery-challan?tab=items&challan="
            searchPlaceholder={m("ldp.search")}
            noMatch={m("sdc.no_match")}
            panelTitle={picked ? m("sdc.items_of", { no: picked.challan_no }) : m("sdc.items")}
            panelHint={m("sdc.items_hint")}
            emptyTitle={m("sdc.pick")}
            emptyText={m("sdc.pick_hint")}
          >
            {data ? <ChallanItems key={picked.id} challanId={picked.id} data={data} add={actions.addChallanItem} save={actions.saveChallanItems} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

const FEEDBACK = ["PENDING", "SUBMITTED", "APPROVED", "REJECTED", "RESUBMIT"];

/**
 * Buyer Sample Feedback: Records, Bulk Shade Approval (a buyer account's
 * samples beside the list of accounts, each released or held) and New
 * feedback.
 */
const sampleFeedback = masterScreen({
  resource: "merch-sample-feedback",
  screen: "buyer-feedback-for-sample",
  noun: "sample_feedback",
  nameKey: "order_no",
  refKey: "order_no",
  newLabel: "bsf.new",
  formColumns: 3,
  booleans: ["bulk_shade_approved"],
  load: async () => {
    const [look, orders, types] = await Promise.all([apiFetch("/merchandise/lookups"), apiFetch("/erp/merch-orders"), apiFetch("/erp/sample-types")]);
    return { look, orders, types };
  },
  fields: ({ m, look, orders, types }) => [
    { name: "order_id", label: m("bsf.f.job_order"), type: "select", options: pickOne(m, orders.map((o) => ({ value: o.id, label: `${o.order_no} · ${o.style_no} (${o.work_style_no || o.style_no})` }))) },
    { name: "buyer_office_id", label: m("bsf.f.buyer_ac"), type: "select", help: m("bsf.buyer_ac_hint"), options: pickOne(m, look.offices) },
    { name: "sample_type_id", label: m("bsf.f.type"), type: "select", options: pickOne(m, types.filter((x) => x.active !== false).map((x) => ({ value: x.id, label: x.name }))) },
    { name: "submit_date", label: m("bsf.f.submit_date"), type: "date", required: true },
    { name: "status", label: m("bsf.f.status"), type: "select", default: "SUBMITTED", options: pickOne(m, labelled(m, "bsf.status", FEEDBACK)) },
    { name: "bulk_shade_approved", label: m("bsf.f.bulk_shade"), type: "checkbox", help: m("bsf.bulk_shade_hint") },
    { name: "comments", label: m("bsf.f.comments"), type: "textarea" },
  ],
  decorate: (row, { m }) => ({
    ...row,
    order_id: row.order_id ?? "",
    buyer_office_id: row.buyer_office_id ?? "",
    sample_type_id: row.sample_type_id ?? "",
    comments: row.comments ?? "",
    bulk_label: m(row.bulk_shade_approved ? "bsf.yes" : "bsf.no"),
    bulk_filter: row.bulk_shade_approved ? "APPROVED" : "NOT_APPROVED",
  }),
  columns: ({ m }) => [
    { key: "batch_no", label: m("bsf.col.batch"), type: "mono" },
    { key: "submit_date", label: m("bsf.col.submit_date"), type: "date" },
    { key: "buyer_ac", label: m("bsf.f.buyer_ac") },
    { key: "job", label: m("bsf.col.job") },
    { key: "order_no", label: m("bsf.col.order") },
    { key: "type", label: m("bsf.f.type") },
    { key: "bulk_label", label: m("bsf.col.bulk_shade") },
    { key: "comments", label: m("bsf.f.comments") },
    { key: "status", label: m("bsf.f.status"), type: "badge", labels: Object.fromEntries(FEEDBACK.map((v) => [v, m(`bsf.status.${v}`)])) },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer_ac", field: "buyer_ac", label: m("bsf.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "job", label: m("bsf.f.job_order"), type: "text", match: (r, v) => [r.job, r.order_no].some((x) => String(x ?? "").toLowerCase().includes(v.toLowerCase())) },
    { name: "type", field: "type", label: m("bsf.f.type"), options: distinct(rows, "type") },
    { name: "from", label: m("bsf.f.date_from"), type: "date", match: (r, v) => r.submit_date >= v },
    { name: "to", label: m("bsf.f.date_to"), type: "date", match: (r, v) => r.submit_date <= v },
    { name: "status", field: "status", label: m("bsf.f.status"), options: labelled(m, "bsf.status", FEEDBACK) },
    { name: "bulk", field: "bulk_filter", label: m("bsf.f.bulk"), options: [{ value: "APPROVED", label: m("bsf.approved") }, { value: "NOT_APPROVED", label: m("bsf.not_approved") }] },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "bulk",
      label: m("bsf.tab_bulk"),
      icon: "Palette",
      render: () => {
        // A buyer account is named in the url by its name; feedback with none is grouped as "—".
        const accounts = [...new Set(rows.map((r) => r.buyer_ac ?? "—"))].sort().map((name) => ({ id: name, name, count: rows.filter((r) => (r.buyer_ac ?? "—") === name).length }));
        const refs = refsFor(accounts, "name");
        const picked = findByRef(accounts, "name", searchParams.account);
        return (
          <PickPanel
            title={m("bsf.accounts")}
            items={accounts.map((a) => ({ key: refs.get(a.id), label: a.name, count: a.count }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase="/merchandise-desk/buyer-feedback-for-sample?tab=bulk&account="
            searchPlaceholder={m("bsf.search_accounts")}
            noMatch={m("bsf.no_match")}
            panelTitle={picked ? m("bsf.samples_of", { name: picked.name }) : m("bsf.samples")}
            panelHint={m("bsf.bulk_hint")}
            emptyTitle={m("bsf.pick")}
            emptyText={m("bsf.pick_hint")}
          >
            {picked ? <BulkShade key={picked.id} rows={rows.filter((r) => (r.buyer_ac ?? "—") === picked.name)} setShade={actions.setBulkShade} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

/**
 * Collar & Cuff Measurement Sheet: Records, Add Measurement (a chart's points
 * of measure beside the list of charts, named in the url as "style · item")
 * and New measurement chart.
 */
const measureChart = masterScreen({
  resource: "merch-measure-charts",
  screen: "collar-cuff-measurement",
  noun: "measure_chart",
  nameKey: "label",
  refKey: "label",
  newLabel: "ccm.new",
  description: "ccm.new_hint",
  formColumns: 3,
  booleans: [],
  load: async () => {
    const [look, orderLook] = await Promise.all([apiFetch("/merchandise/lookups"), apiFetch("/merchandise/orders/lookups")]);
    return { look, orderLook };
  },
  fields: ({ m, look, orderLook }) => [
    { name: "style_id", label: m("ccm.f.style"), type: "select", required: true, options: pickOne(m, orderLook.styles.map(({ value, label }) => ({ value, label }))) },
    { name: "buyer_office_id", label: m("ccm.f.buyer_ac"), type: "select", help: m("ccm.buyer_ac_hint"), options: pickOne(m, look.offices) },
    { name: "sub_style", label: m("ccm.f.sub_style") },
    { name: "item_name", label: m("ccm.f.item"), required: true, placeholder: "Collar" },
    { name: "model_no", label: m("ccm.f.model") },
    { name: "status", label: m("ccm.f.status"), type: "select", default: "DRAFT", options: pickOne(m, labelled(m, "ldp.status", STATUSES)) },
  ],
  decorate: (row) => ({ ...row, buyer_office_id: row.buyer_office_id ?? "", sub_style: row.sub_style ?? "", model_no: row.model_no ?? "" }),
  columns: ({ m }) => [
    { key: "buyer_ac", label: m("ccm.f.buyer_ac") },
    { key: "style_name", label: m("ccm.col.style_name") },
    { key: "style_no", label: m("ccm.col.style_no") },
    { key: "sub_style", label: m("ccm.f.sub_style") },
    { key: "item_name", label: m("ccm.f.item") },
    { key: "model_no", label: m("ccm.f.model") },
    { key: "points", label: m("ccm.col.points"), type: "number" },
    { key: "status", label: m("ccm.f.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((v) => [v, m(`ldp.status.${v}`)])) },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer_ac", field: "buyer_ac", label: m("ccm.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "style", label: m("ccm.col.style_no"), type: "text", match: (r, v) => String(r.style_no ?? "").toLowerCase().includes(v.toLowerCase()) },
    { name: "status", field: "status", label: m("ccm.f.status"), options: labelled(m, "ldp.status", STATUSES) },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "measure",
      label: m("ccm.tab_measure"),
      icon: "Ruler",
      render: async () => {
        const refs = refsFor(rows, "label");
        const picked = findByRef(rows, "label", searchParams.chart);
        const data = picked ? await apiFetch(`/merchandise/measure-charts/${picked.id}/points`) : null;
        return (
          <PickPanel
            title={m("ccm.charts")}
            items={rows.map((r) => ({ key: refs.get(r.id), label: r.label, sub: [r.sub_style, r.model_no].filter(Boolean).join(" · "), count: r.points }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase="/merchandise-desk/collar-cuff-measurement?tab=measure&chart="
            searchPlaceholder={m("ldp.search")}
            noMatch={m("ccm.no_match")}
            panelTitle={picked ? m("ccm.points_of", { chart: picked.label }) : m("ccm.points")}
            panelHint={m("ccm.points_hint")}
            emptyTitle={m("ccm.pick")}
            emptyText={m("ccm.pick_hint")}
          >
            {data ? <MeasurePoints key={picked.id} chartId={picked.id} data={data} add={actions.addMeasurePoint} save={actions.saveMeasurePoints} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

const SAMPLE_SCREENS = {
  "labdip-program": labdipProgram,
  "labdip-submission": labdipSubmission,
  "sample-program": sampleProgram,
  "print-design": printDesign,
  "sample-production": sampleProduction,
  "sample-delivery-challan": sampleChallan,
  "buyer-feedback-for-sample": sampleFeedback,
  "collar-cuff-measurement": measureChart,
};

/** Each Lab Dip & Sample screen is changed under the sample permission. */
const SAMPLE_MANAGE = {
  "labdip-program": "merchandise.sample.manage",
  "labdip-submission": "merchandise.sample.manage",
  "sample-program": "merchandise.sample.manage",
  "print-design": "merchandise.sample.manage",
  "sample-production": "merchandise.sample.manage",
  "sample-delivery-challan": "merchandise.sample.manage",
  "buyer-feedback-for-sample": "merchandise.sample.manage",
  "collar-cuff-measurement": "merchandise.sample.manage",
};

export { SAMPLE_SCREENS, SAMPLE_MANAGE };
