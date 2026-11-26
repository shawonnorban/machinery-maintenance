import Link from "next/link";
import { Hand, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ScRegister } from "@/components/finishing/sc-process";
import { FinInspectionEditor } from "@/components/finishing/qc-process";
import * as actions from "./actions";

/*
 * Finishing Line → QC Process (AnnotechERP 18/04), each laid out as
 * Brickland lays it out: Finishing Quality Entry, the Shade, GSM & Width
 * Test (Batch List and New shade decision), the Four-Point Fabric Inspection
 * (its list with the four counts, the entry tab, and the tab that points to
 * it) and the Rejection & Re-Process Advice. Brickland left the first and
 * the last as scaffolds; they are built as its other registers are.
 */

const look = () => apiFetch("/finishing/qc/options");
const opts = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const all = (m) => ({ value: "", label: m("f.all") });
const has = (text, needle) => !needle || String(text ?? "").toLowerCase().includes(String(needle).trim().toLowerCase());
const within = (day, from, to) => (!from || (day ?? "") >= from) && (!to || (day ?? "") <= to);
const base = (key) => `/finishing-line/${key}`;
const blankNulls = (r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === null ? "" : v]));
const unique = (list) => list.filter((o, i) => list.findIndex((x) => x.value === o.value) === i);
const recordsTab = (te, count, label) => ({ key: "records", label: label ?? te("records"), icon: "Table2", count });
const SHIFTS = ["DAY", "NIGHT"];
const STAGES = ["AWAITING_LOAD", "DYEING_RUNNING", "DEWATERING", "DRYING", "HEAT_SET", "STENTERING", "COMPACTING", "CALENDERING", "BRUSHING", "QC_INSPECTION", "READY_FOR_DELIVERY", "DELIVERED", "ON_HOLD", "AT_SUB_CONTRACT"];
const cardOf = (r) => ({ value: r.receipt_id, label: `${r.batch_no ?? "—"} · ${r.colour ?? "—"} · ${r.receipt_no ?? ""}` });

const qualityEntry = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "finishing-qc-entry";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-qc-entries"), look()]);
  const fields = (cards) => [
    { name: "entry_date", label: m("f.entry_date"), type: "date", required: true },
    { name: "receipt_id", label: m("f.batch_no"), type: "select", required: true, options: cards, placeholder: m("f.select"), help: m("h.card") },
    { name: "machine_id", label: m("f.machine"), type: "select", options: [{ value: "", label: "—" }, ...o.machines], placeholder: m("f.select") },
    { name: "process_id", label: m("f.process"), type: "select", options: [{ value: "", label: "—" }, ...o.processes], placeholder: m("f.select") },
    { name: "shift", label: m("f.shift"), type: "select", required: true, options: opts(m, "shift", SHIFTS) },
    { name: "roll_no", label: m("f.roll_no"), required: true, help: m("h.roll_no") },
    { name: "weight_kg", label: m("f.weight_kg"), type: "number", step: "0.001", required: true },
    { name: "gsm", label: m("f.gsm"), type: "number", step: "0.01" },
    { name: "dia", label: m("f.dia"), type: "number", step: "0.01" },
    { name: "shade", label: m("f.shade"), type: "select", required: true, options: opts(m, "shade", ["OK", "OFF"]) },
    { name: "result", label: m("f.result"), type: "select", required: true, options: opts(m, "entry_result", ["OK", "FAULT"]) },
    { name: "defect", label: m("f.defect"), help: m("h.defect") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const shown = rows
    .filter((r) => (has(r.batch_no, q.no) || has(r.roll_no, q.no)) && (!q.result || r.result === q.result) && within(r.entry_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), shift_label: m(`opt.shift.${r.shift}`), shade_label: m(`opt.shade.${r.shade}`), result_label: m(`opt.entry_result.${r.result}`), ref: `${r.batch_no}-${r.roll_no}` }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields(o.cards)} action={actions.createResource.bind(null, "fin-qc-entries", [])} title={m("t.new_entry")} description={m("h.entry_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("list.load"), reset: m("list.clear") }} fields={[
            { name: "no", label: m("f.batch_or_roll"), type: "text" },
            { name: "result", label: m("f.result"), type: "select", options: [all(m), ...opts(m, "entry_result", ["OK", "FAULT"])] },
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
          ]} />
          <RecordsGrid
            exportName="finishing-quality-entries"
            rows={shown}
            emptyState={{ title: m("records.empty_entry"), text: m("records.empty_hint") }}
            columns={[
              { key: "entry_date", label: m("col.date"), type: "date" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "receipt_no", label: m("col.receipt_no"), type: "mono" },
              { key: "machine", label: m("col.machine") },
              { key: "process", label: m("col.process") },
              { key: "shift_label", label: m("col.shift") },
              { key: "roll_no", label: m("col.roll_no"), type: "mono" },
              { key: "weight_kg", label: m("col.weight_kg"), type: "number", align: "right" },
              { key: "gsm", label: m("col.gsm"), type: "number", align: "right" },
              { key: "dia", label: m("col.dia"), type: "number", align: "right" },
              { key: "shade_label", label: m("col.shade"), type: "badge" },
              { key: "result_label", label: m("col.result"), type: "badge" },
              { key: "defect", label: m("col.defect") },
            ]}
            edit={canManage ? { fields: fields(unique([...o.cards, ...rows.map(cardOf)])), action: actions.updateResource.bind(null, "fin-qc-entries", []), refKey: "ref", title: m("t.new_entry"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-qc-entries") : undefined}
          />
        </>
      ),
  };
};

const shadeTest = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "shade-gsm-width-check";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-shade-decisions"), look()]);
  const fields = (cards) => [
    { name: "receipt_id", label: m("f.batch"), type: "select", required: true, options: cards, placeholder: m("f.select_batch"), help: m("h.decision_batch") },
    { name: "buyer_id", label: m("f.buyer_ac"), type: "select", options: [{ value: "", label: m("f.select_buyer") }, ...o.buyers], placeholder: m("f.select_buyer") },
    { name: "style", label: m("f.style") },
    { name: "order_id", label: m("f.order"), type: "select", options: [{ value: "", label: "—" }, ...o.orders], placeholder: m("f.select") },
    { name: "colour", label: m("f.colour") },
    { name: "lot_no", label: m("f.lot_no") },
    { name: "batch_qty", label: m("f.batch_qty"), type: "number", step: "0.001" },
    { name: "model_continuity", label: m("f.model_continuity") },
    { name: "status", label: m("f.status"), type: "select", required: true, options: opts(m, "decision", ["PENDING", "APPROVED", "REJECTED"]) },
    { name: "decided_by", label: m("f.decided_by"), type: "select", options: [{ value: "", label: m("f.not_decided") }, ...o.users], placeholder: m("f.not_decided"), help: m("h.decided_by") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ];
  const pick = (k) => [...new Set(rows.map((r) => r[k]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
  const shown = rows
    .filter((r) => (!q.buyer || r.buyer_id === q.buyer) && (!q.style || r.style === q.style) && (!q.colour || r.colour === q.colour) && has(r.batch_no, q.batch) && (!q.status || r.status === q.status))
    .map((r) => ({ ...blankNulls(r), status_label: m(`opt.decision.${r.status}`), ref: r.batch_no }));
  return {
    tabs: [{ key: "records", label: m("tab.batch_list"), icon: "Table2", count: rows.length }, ...(canManage ? [{ key: "new", label: m("tab.new_decision"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields(o.cards)} action={actions.createResource.bind(null, "fin-shade-decisions", [])} title={m("tab.new_decision")} description={m("h.decision_note")} note={m("saved_note")} submitLabel={m("t.save_decision")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          <FilterBar basePath={base(key)} keep={{ tab: "records" }} values={q} title={m("list.search")} hint={m("h.decision_list")} labels={{ show: m("list.load"), reset: m("list.clear") }} fields={[
            { name: "buyer", label: m("f.buyer_ac"), type: "select", options: [all(m), ...o.buyers] },
            { name: "style", label: m("f.style"), type: "select", options: [all(m), ...pick("style")] },
            { name: "colour", label: m("f.colour"), type: "select", options: [all(m), ...pick("colour")] },
            { name: "batch", label: m("f.batch_hash"), type: "text" },
            { name: "status", label: m("f.status"), type: "select", options: [all(m), ...opts(m, "decision", ["PENDING", "APPROVED", "REJECTED"])] },
          ]} />
          <RecordsGrid
            exportName="shade-gsm-width-decisions"
            rows={shown}
            emptyState={{ title: m("records.empty_decision"), text: m("records.empty_hint") }}
            columns={[
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "receipt_no", label: m("col.receipt_no"), type: "mono" },
              { key: "buyer", label: m("col.buyer") },
              { key: "order_no", label: m("col.order_no") },
              { key: "style", label: m("col.style") },
              { key: "colour", label: m("col.colour") },
              { key: "lot_no", label: m("col.lot_no") },
              { key: "batch_qty", label: m("col.batch_qty"), type: "number", align: "right" },
              { key: "model_continuity", label: m("col.model_continuity") },
              { key: "status_label", label: m("col.status"), type: "badge" },
              { key: "decided_by_name", label: m("col.decided_by") },
              { key: "decided_at", label: m("col.decided_at"), type: "datetime" },
            ]}
            edit={canManage ? { fields: fields(unique([...o.cards, ...rows.map(cardOf)])), action: actions.updateResource.bind(null, "fin-shade-decisions", []), refKey: "ref", title: m("tab.new_decision"), submitLabel: m("t.save_decision"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-shade-decisions") : undefined}
          />
        </>
      ),
  };
};

const inspection = async ({ m, tab, canManage, searchParams: q = {} }) => {
  const key = "fabric-inspection-4-point";
  const [rows, o] = await Promise.all([apiFetch("/finishing/inspections"), look()]);
  const shown = rows
    .filter((r) => has(r.batch_no, q.batch) && has(r.rolls, q.roll) && has(r.ref_no, q.ref) && (!q.result || r.verdict === q.result) && within(r.insp_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), shift_label: m(`opt.shift.${r.shift}`), verdict_label: m(`opt.verdict.${r.verdict}`), status_label: m(`opt.status.${r.status}`) }));
  const count = (v) => shown.filter((r) => r.verdict === v).length;
  return {
    tabs: [
      { key: "records", label: m("tab.roll_list"), icon: "Table2", count: rows.length },
      { key: "entry", label: m("tab.inspection_entry"), icon: "Layers" },
      { key: "new", label: m("tab.inspection_new"), icon: "Plus" },
    ],
    body: () =>
      tab === "entry" ? (
        <FinInspectionEditor key={q.doc ?? "new"} inspections={rows} options={o} canManage={canManage} load={actions.loadInspection} save={actions.saveInspection} backHref={`${base(key)}?tab=records`} initialNo={q.doc ?? null} />
      ) : tab === "new" ? (
        <div className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-5">
          <h2 className="text-base font-semibold">{m("tab.inspection_new")}</h2>
          <p className="text-[13px] text-foreground-muted">{m("insp.pointer")}</p>
          <div>
            <Link href={`${base(key)}?tab=entry`} className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {m("list.new_inspection")}
            </Link>
          </div>
        </div>
      ) : (
        <div className="flex flex-col gap-3">
          <FilterBar basePath={base(key)} keep={{ tab: "records" }} values={q} title={m("list.search_data")} hint={m("h.inspection_list")} labels={{ show: m("list.load"), reset: m("list.reset") }} fields={[
            { name: "batch", label: m("f.batch_no"), type: "text", placeholder: "B-…" },
            { name: "roll", label: m("f.roll_no_search"), type: "text", placeholder: m("f.scan_roll") },
            { name: "ref", label: m("insp.ref_no"), type: "text" },
            { name: "result", label: m("f.result"), type: "select", options: [{ value: "", label: m("f.any_result") }, ...opts(m, "verdict", ["PASS", "HOLD", "REJECT"])] },
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
          ]} />
          <div className="flex flex-wrap items-center gap-2">
            {[
              ["list.inspections", shown.length, "bg-brand-subtle text-brand"],
              ["list.passed", count("PASS"), "bg-success/15 text-success"],
              ["list.held", count("HOLD"), "bg-warning/15 text-warning"],
              ["list.rejected", count("REJECT"), "bg-danger/15 text-danger"],
            ].map(([k, n, tone]) => (
              <span key={k} className="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-1.5 text-sm font-medium">
                {m(k)} <span className={cn("rounded-full px-1.5 text-xs font-semibold tabular-nums", tone)}>{n}</span>
              </span>
            ))}
            <span className="ml-auto flex gap-2">
              <Link href={`${base(key)}?tab=records&result=HOLD`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Hand /> {m("list.hold_list")}
              </Link>
              <Link href={`${base(key)}?tab=entry`} className={cn(buttonVariants({ size: "sm" }))}>
                <Plus /> {m("list.new_inspection")}
              </Link>
            </span>
          </div>
          <ScRegister
            rows={shown}
            exportName="four-point-inspections"
            refKey="ref_no"
            emptyState={{ title: m("records.empty_inspection"), text: m("records.empty_hint") }}
            columns={[
              { key: "ref_no", label: m("col.ref_no"), type: "mono" },
              { key: "insp_date", label: m("col.date"), type: "date" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "buyer", label: m("col.buyer") },
              { key: "colour", label: m("col.colour") },
              { key: "shift_label", label: m("col.shift") },
              { key: "roll_count", label: m("col.rolls"), type: "number", align: "right" },
              { key: "total_kg", label: m("col.weight_kg"), type: "number", align: "right" },
              { key: "total_points", label: m("col.points"), type: "number", align: "right" },
              { key: "verdict_label", label: m("col.verdict"), type: "badge" },
              { key: "status_label", label: m("col.status"), type: "badge" },
            ]}
            actions={[
              { key: "open", label: m("reg.edit"), href: `${base(key)}?tab=entry&doc=:no` },
              ...(canManage ? [{ key: "post", label: m("reg.post"), when: { field: "status", in: ["DRAFT"] }, run: actions.postInspection, title: m("reg.post_title"), text: m("reg.post_text"), done: m("reg.posted") }] : []),
            ]}
          />
        </div>
      ),
  };
};

const advice = async ({ m, te, tab, canManage, searchParams: q = {} }) => {
  const key = "qc-rejection-re-process-advice";
  const [rows, o] = await Promise.all([apiFetch("/erp/fin-reprocess-advices"), look()]);
  const fields = (inspections) => [
    { name: "advice_date", label: m("f.advice_date"), type: "date", required: true },
    { name: "inspection_id", label: m("f.inspection"), type: "select", required: true, options: inspections, placeholder: m("f.select"), help: m("h.inspection") },
    { name: "process_id", label: m("f.reprocess"), type: "select", required: true, options: o.processes, placeholder: m("f.select") },
    { name: "machine_id", label: m("f.machine"), type: "select", options: [{ value: "", label: "—" }, ...o.machines], placeholder: m("f.select"), help: m("h.machine") },
    { name: "reason", label: m("f.reason"), type: "select", required: true, options: opts(m, "reason", ["SHADE", "GSM", "WIDTH", "DEFECT", "OTHER"]) },
    { name: "remarks", label: m("f.remarks"), type: "textarea", help: m("h.remarks_other") },
  ];
  const shown = rows
    .filter((r) => (has(r.advice_no, q.no) || has(r.batch_no, q.no) || has(r.ref_no, q.no)) && (!q.reason || r.reason === q.reason) && within(r.advice_date, q.from, q.to))
    .map((r) => ({ ...blankNulls(r), reason_label: m(`opt.reason.${r.reason}`), verdict_label: r.verdict ? m(`opt.verdict.${r.verdict}`) : "", stage_label: r.stage && STAGES.includes(r.stage) ? m(`opt.stage.${r.stage}`) : "", ref: r.advice_no }));
  return {
    tabs: [recordsTab(te, rows.length), ...(canManage ? [{ key: "new", label: m("tab.add_new"), icon: "Plus" }] : [])],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm fields={fields(o.rejected)} action={actions.createResource.bind(null, "fin-reprocess-advices", [])} title={m("t.new_advice")} description={m("h.advice_note")} note={m("saved_note")} submitLabel={m("tab.add_new")} columns={3} successHref={`${base(key)}?tab=records`} />
      ) : (
        <>
          <FilterBar instant basePath={base(key)} keep={{ tab: "records" }} values={q} labels={{ show: m("list.load"), reset: m("list.clear") }} fields={[
            { name: "no", label: m("f.advice_search"), type: "text", placeholder: "FRA-… / FFI-… / B-…" },
            { name: "reason", label: m("f.reason"), type: "select", options: [all(m), ...opts(m, "reason", ["SHADE", "GSM", "WIDTH", "DEFECT", "OTHER"])] },
            { name: "from", label: m("f.from_date"), type: "date" },
            { name: "to", label: m("f.to_date"), type: "date" },
          ]} />
          <RecordsGrid
            exportName="rejection-re-process-advice"
            rows={shown}
            emptyState={{ title: m("records.empty_advice"), text: m("records.empty_hint") }}
            columns={[
              { key: "advice_no", label: m("col.advice_no"), type: "mono" },
              { key: "advice_date", label: m("col.date"), type: "date" },
              { key: "ref_no", label: m("col.ref_no"), type: "mono" },
              { key: "batch_no", label: m("col.batch_no"), type: "mono" },
              { key: "verdict_label", label: m("col.verdict"), type: "badge" },
              { key: "rejected_rolls", label: m("col.rejected_rolls"), type: "number", align: "right" },
              { key: "rejected_kg", label: m("col.rejected_kg"), type: "number", align: "right" },
              { key: "process", label: m("col.reprocess") },
              { key: "machine", label: m("col.machine") },
              { key: "reason_label", label: m("col.reason"), type: "badge" },
              { key: "stage_label", label: m("col.stage") },
              { key: "remarks", label: m("col.remarks") },
            ]}
            edit={canManage ? { fields: fields(unique([...o.rejected, ...rows.map((r) => ({ value: r.inspection_id, label: `${r.ref_no} · ${r.batch_no ?? "—"}` }))])), action: actions.updateResource.bind(null, "fin-reprocess-advices", []), refKey: "ref", title: m("t.new_advice"), submitLabel: m("tab.add_new"), columns: 3 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "fin-reprocess-advices") : undefined}
          />
        </>
      ),
  };
};

const QC_SCREENS = {
  "finishing-qc-entry": qualityEntry,
  "shade-gsm-width-check": shadeTest,
  "fabric-inspection-4-point": inspection,
  "qc-rejection-re-process-advice": advice,
};

export { QC_SCREENS };
