import { apiFetch } from "@/lib/api-server";
import { screenContext } from "@/lib/navigation";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ProductionSheetEditor, SheetRegister, DeliveryBoard } from "@/components/finishing/df-production";
import * as actions from "./actions";

/*
 * Finishing Line → DF Production (AnnotechERP 18/02), each laid out as
 * Brickland lays it out: Batch Receipt from Dye House, Machine-Wise
 * Production Entry (the register and one tab per line), Process-Wise
 * Delivery (the floor), Batch Re-Process Return and Finishing Production
 * Amendment. Brickland left the receipt, the return and the amendment as
 * scaffolds; they are built as its other registers are — Records and Add New.
 */

const STAGES = ["AWAITING_LOAD", "DYEING_RUNNING", "DEWATERING", "DRYING", "HEAT_SET", "STENTERING", "COMPACTING", "CALENDERING", "BRUSHING", "QC_INSPECTION", "READY_FOR_DELIVERY", "DELIVERED", "ON_HOLD", "AT_SUB_CONTRACT"];
const look = () => apiFetch("/finishing/production/options");
const opts = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const all = (m) => ({ value: "", label: m("all") });
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/finishing-line/${key}`;
const filterBar = (m, key, q, fields) => <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("filter.show"), reset: m("filter.clear") }} fields={fields} />;
const recordsTab = (te, count) => ({ key: "records", label: te("records"), icon: "Table2", count });
const blankNulls = (r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v]));
const unique = (list) => list.filter((o, i) => list.findIndex((x) => x.value === o.value) === i);

const batchReceipt = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "batch-receive-from-dyeing";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-batch-receipts"), look()]);
  // A receipt being edited keeps its own batch among the choices.
  const batches = unique([...o.receivable, ...rows.map((r) => ({ value: r.batch_id, label: `${r.batch_no} · ${r.colour ?? "—"} · ${r.order_no ?? "—"}` }))]);
  const fields = (list) => [
    { name: "receive_date", label: m("f.receive_date"), type: "date", required: true },
    { name: "batch_id", label: m("f.batch"), type: "select", required: true, options: list, placeholder: m("f.select"), help: m("h.batch") },
    { name: "dia_type", label: m("f.dia_type"), type: "select", required: true, options: opts(m, "dia", ["OPEN", "TUBE", "BOTH"]), help: m("h.dia") },
    { name: "booking_type", label: m("f.booking_type"), type: "select", options: [{ value: "", label: "—" }, ...opts(m, "booking", ["IN_HOUSE", "SUB_CONTRACT", "SAMPLE"])], help: m("h.booking") },
    { name: "challan_no", label: m("f.challan_no") },
    { name: "challan_qty_kg", label: m("f.challan_qty_kg"), type: "number", step: "0.001", help: m("h.challan_qty") },
    { name: "challan_rolls", label: m("f.challan_rolls"), type: "number" },
    { name: "recv_qty_kg", label: m("f.recv_qty_kg"), type: "number", step: "0.001", required: true },
    { name: "recv_rolls", label: m("f.recv_rolls"), type: "number" },
    { name: "floor_id", label: m("f.floor"), type: "select", options: [{ value: "", label: "—" }, ...o.floors], placeholder: m("f.select") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (has(r.receipt_no, q.no) || has(r.batch_no, q.no)) && (!q.stage || r.stage === q.stage) && (!q.status || r.status === q.status) && within(r.receive_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), dia_label: m(`opt.dia.${r.dia_type}`), stage_label: m(`opt.stage.${r.stage}`), status_label: m(`opt.card.${r.status}`), ref: r.receipt_no }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields(o.receivable)} action={actions.createResource.bind(null, "fin-batch-receipts", [])} title={m("t.new_receipt")} description={m("h.receipt_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.search"), type: "text", placeholder: "FBR-… / B-…" },
            { name: "stage", label: m("f.stage"), type: "select", options: [all(m), ...opts(m, "stage", STAGES)] },
            { name: "status", label: m("f.status"), type: "select", options: [all(m), ...opts(m, "card", ["ON_FLOOR", "RETURNED"])] },
            { name: "from", label: m("f.from"), type: "date" },
            { name: "to", label: m("f.to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="batch-receipts-from-dye-house"
            rows={shown}
            emptyState={{ title: m("records.empty_receipt"), text: m("records.empty_receipt_hint") }}
            columns={[
              { key: "receipt_no", label: m("col.receipt_no"), type: "mono" },
              { key: "receive_date", label: m("col.receive_date"), type: "date" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "buyer", label: m("col.buyer") },
              { key: "order_no", label: m("col.order_no") },
              { key: "colour", label: m("col.colour") },
              { key: "dia_label", label: m("col.dia_type") },
              { key: "challan_qty_kg", label: m("col.challan_qty_kg"), type: "number", align: "right" },
              { key: "recv_qty_kg", label: m("col.recv_qty_kg"), type: "number", align: "right" },
              { key: "diff_kg", label: m("col.diff_kg"), type: "number", align: "right" },
              { key: "recv_rolls", label: m("col.recv_rolls"), type: "number", align: "right" },
              { key: "stage_label", label: m("col.stage"), type: "badge" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            edit={canManage ? { fields: fields(batches), action: actions.updateResource.bind(null, "fin-batch-receipts", []), refKey: "ref", title: m("t.new_receipt"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-batch-receipts") : undefined}
          />
        </>
      ),
  };
};

const productionEntry = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "df-production-entry-mc-wise";
  const [sheets, o] = await Promise.all([apiFetch("/finishing/production-sheets"), look()]);
  const shown = sheets.filter((s) => (!q.line || s.line === q.line) && (!q.shift || s.shift === q.shift) && (!q.status || s.status === q.status));
  return {
    tabs: [
      { key: "records", label: m("tab.sheets"), icon: "Table2", count: sheets.length },
      { key: "open", label: m("tab.open"), icon: "Layers" },
      { key: "tube", label: m("tab.tube"), icon: "Layers" },
    ],
    body: () =>
      tab === "open" || tab === "tube" ? (
        <ProductionSheetEditor key={`${tab}-${q.doc ?? ""}`} line={tab.toUpperCase()} sheets={sheets} options={o} canManage={canManage} load={actions.loadSheet} save={actions.saveSheet} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, key, q, [
            { name: "line", label: m("f.sheet_line"), type: "select", options: [all(m), ...opts(m, "line", ["OPEN", "TUBE"])] },
            { name: "shift", label: m("f.shift"), type: "select", options: [all(m), ...opts(m, "shift", ["DAY", "NIGHT"])] },
            { name: "status", label: m("f.status"), type: "select", options: [all(m), ...opts(m, "status", ["DRAFT", "POSTED"])] },
          ])}
          <SheetRegister rows={shown} total={sheets.length} canManage={canManage} post={actions.postSheet} remove={actions.deleteSheet} base={base(key)} />
        </div>
      ),
  };
};

const processDelivery = async ({ canManage, searchParams: q = {} }) => {
  const key = "df-process-wise-delivery";
  const params = new URLSearchParams(Object.entries(q).filter(([k, v]) => ["dia", "stage", "booking", "buyer", "style", "order", "colour", "batch"].includes(k) && v));
  const [data, o, sc, bt] = await Promise.all([
    apiFetch(`/finishing/delivery-board${params.toString() ? `?${params}` : ""}`),
    look(),
    // S/C Prod. and BT Trans. show once their desks (18/03, 18/05) are on the menu.
    screenContext("/finishing-line/sc-delivery-challan-gatepass"),
    screenContext("/finishing-line/finish-fabric-store-receive"),
  ]);
  return {
    body: () => (
      <DeliveryBoard
        key={params.toString()}
        data={data}
        query={q}
        options={o}
        canManage={canManage}
        deliver={actions.deliverBatch}
        base={base(key)}
        links={{ sc: sc?.screen.url === "/finishing-line/sc-delivery-challan-gatepass" ? sc.screen.url : null, bt: bt?.screen.url === "/finishing-line/finish-fabric-store-receive" ? bt.screen.url : null }}
      />
    ),
  };
};

const reprocessReturn = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "batch-return-to-re-process";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-reprocess-returns"), look()]);
  const live = o.cards.filter((x) => !["AT_SUB_CONTRACT", "DELIVERED"].includes(x.stage));
  const cards = unique([...live, ...rows.map((r) => ({ value: r.receipt_id, label: `${r.batch_no ?? "—"} · ${r.colour ?? "—"} · ${r.receipt_no}` }))]);
  const fields = (list) => [
    { name: "return_date", label: m("f.return_date"), type: "date", required: true },
    { name: "receipt_id", label: m("f.card"), type: "select", required: true, options: list, placeholder: m("f.select"), help: m("h.card") },
    { name: "reason", label: m("f.reason"), type: "select", required: true, options: opts(m, "reason", ["SHADE", "GSM", "WIDTH", "OTHER"]) },
    { name: "qty_kg", label: m("f.qty_kg"), type: "number", step: "0.001", required: true },
    { name: "rolls", label: m("f.rolls"), type: "number" },
    { name: "remarks", label: m("f.remarks"), type: "textarea", help: m("h.remarks_other") },
  ];
  const shown = rows
    .filter((r) => (has(r.return_no, q.no) || has(r.batch_no, q.no)) && (!q.reason || r.reason === q.reason) && within(r.return_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), reason_label: m(`opt.reason.${r.reason}`), ref: r.return_no }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields(live)} action={actions.createResource.bind(null, "fin-reprocess-returns", [])} title={m("t.new_return")} description={m("h.return_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.search"), type: "text", placeholder: "FRP-… / B-…" },
            { name: "reason", label: m("f.reason"), type: "select", options: [all(m), ...opts(m, "reason", ["SHADE", "GSM", "WIDTH", "OTHER"])] },
            { name: "from", label: m("f.from"), type: "date" },
            { name: "to", label: m("f.to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="batch-re-process-returns"
            rows={shown}
            emptyState={{ title: m("records.empty_return"), text: m("records.empty_return_hint") }}
            columns={[
              { key: "return_no", label: m("col.return_no"), type: "mono" },
              { key: "return_date", label: m("col.return_date"), type: "date" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "receipt_no", label: m("col.receipt_no"), type: "mono" },
              { key: "buyer", label: m("col.buyer") },
              { key: "order_no", label: m("col.order_no") },
              { key: "colour", label: m("col.colour") },
              { key: "reason_label", label: m("col.reason"), type: "badge" },
              { key: "qty_kg", label: m("col.qty_kg"), type: "number", align: "right" },
              { key: "rolls", label: m("col.rolls"), type: "number", align: "right" },
              { key: "remarks", label: m("col.remarks") },
            ]}
            edit={canManage ? { fields: fields(cards), action: actions.updateResource.bind(null, "fin-reprocess-returns", []), refKey: "ref", title: m("t.new_return"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-reprocess-returns") : undefined}
          />
        </>
      ),
  };
};

const amendment = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "df-production-amendment";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-production-amendments"), look()]);
  const fields = [
    { name: "amend_date", label: m("f.amend_date"), type: "date", required: true },
    { name: "line_id", label: m("f.line"), type: "select", required: true, options: o.posted_lines.map((l) => ({ value: l.value, label: `${l.label} · ${l.input_kg} → ${l.output_kg} kg` })), placeholder: m("f.select"), help: m("h.line") },
    { name: "new_input_kg", label: m("f.new_input_kg"), type: "number", step: "0.001", required: true },
    { name: "new_output_kg", label: m("f.new_output_kg"), type: "number", step: "0.001", required: true },
    { name: "new_machine_id", label: m("f.new_machine"), type: "select", options: [{ value: "", label: "—" }, ...o.machines], placeholder: m("f.select"), help: m("h.keep") },
    { name: "new_process_id", label: m("f.new_process"), type: "select", options: [{ value: "", label: "—" }, ...o.processes], placeholder: m("f.select"), help: m("h.keep") },
    { name: "reason", label: m("f.amend_reason"), type: "textarea", required: true },
  ];
  const shown = rows.filter((r) => (has(r.amend_no, q.no) || has(r.sheet_no, q.no) || has(r.batch_no, q.no)) && within(r.amend_date, q.from, q.to)).map(blankNulls);
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields} action={actions.createResource.bind(null, "fin-production-amendments", [])} title={m("t.new_amendment")} description={m("h.amend_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          {filterBar(m, key, q, [
            { name: "no", label: m("f.search"), type: "text", placeholder: "FPA-… / DFP-…" },
            { name: "from", label: m("f.from"), type: "date" },
            { name: "to", label: m("f.to"), type: "date" },
          ])}
          <RecordsGrid
            exportName="finishing-production-amendments"
            rows={shown}
            emptyState={{ title: m("records.empty_amendment"), text: m("records.empty_amendment_hint") }}
            columns={[
              { key: "amend_no", label: m("col.amend_no"), type: "mono" },
              { key: "amend_date", label: m("col.amend_date"), type: "date" },
              { key: "sheet_no", label: m("col.sheet_no"), type: "mono" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "old_input_kg", label: m("col.old_input"), type: "number", align: "right" },
              { key: "new_input_kg", label: m("col.new_input"), type: "number", align: "right" },
              { key: "old_output_kg", label: m("col.old_output"), type: "number", align: "right" },
              { key: "new_output_kg", label: m("col.new_output"), type: "number", align: "right" },
              { key: "old_machine", label: m("col.old_machine") },
              { key: "new_machine", label: m("col.new_machine") },
              { key: "old_process", label: m("col.old_process") },
              { key: "new_process", label: m("col.new_process") },
              { key: "reason", label: m("col.amend_reason") },
            ]}
          />
        </>
      ),
  };
};

const PROD_SCREENS = {
  "batch-receive-from-dyeing": batchReceipt,
  "df-production-entry-mc-wise": productionEntry,
  "df-process-wise-delivery": processDelivery,
  "batch-return-to-re-process": reprocessReturn,
  "df-production-amendment": amendment,
};

/** Machine-Wise Production Entry opens on its Open Line tab, as Brickland's does. */
const PROD_DEFAULT_TABS = { "df-production-entry-mc-wise": "open" };

export { PROD_SCREENS, PROD_DEFAULT_TABS };
