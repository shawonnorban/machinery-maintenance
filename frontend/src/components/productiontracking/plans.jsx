"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Ban, CalendarCheck, Check, History, Pencil, Plus, Send, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { usePt, num, stamp, useFilters, Panel, FilterSelect, Table } from "./common";

/*
 * Production Tracking → Daily Production Plan (AnnotechERP 30/06): the plans
 * of a day (date, section, status), New Plan with its calculated target,
 * and each plan's Submit / Approve / Reject / Revise / Cancel / History.
 */

const STATUS_TONE = {
  DRAFT: "bg-surface-muted text-foreground-muted",
  PENDING_APPROVAL: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  CANCELLED: "bg-surface-muted text-foreground-subtle line-through",
};

function Field({ label, required, error, hint, children, className }) {
  return (
    <label className={cn("block text-[13px] font-semibold text-foreground", className)}>
      {label}
      {required ? <span className="text-danger"> *</span> : null}
      <span className="mt-1.5 block font-normal">{children}</span>
      {error ? <span className="mt-1 block text-xs font-normal text-danger">{error}</span> : hint ? <span className="mt-1 block text-xs font-normal text-foreground-muted">{hint}</span> : null}
    </label>
  );
}

function NativeSelect({ value, onChange, options, placeholder }) {
  return (
    <select value={value ?? ""} onChange={(e) => onChange(e.target.value)} className="block h-9 w-full rounded-sm border border-border-strong bg-surface px-2.5 text-sm text-foreground">
      {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
      {options.map((o) => (
        <option key={o.value} value={o.value} disabled={o.disabled}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

function DailyPlans({ data, lookups, query, canPrepare, canApprove, act }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const { apply } = useFilters(query);
  const [editing, setEditing] = useState(null);
  const [deciding, setDeciding] = useState(null);
  const [history, setHistory] = useState(null);
  const [pending, start] = useTransition();

  const run = (fn, message) =>
    start(async () => {
      const res = await fn();
      if (res?.status === "success") {
        toast.add({ title: message, type: "success" });
        router.refresh();
      } else toast.add({ title: res?.message ?? p("error"), type: "danger" });
    });

  const openHistory = (row) =>
    start(async () => {
      const res = await act("planHistory", row.id);
      if (res?.status === "success") setHistory(res.data);
    });

  const cols = [
    { key: "plan_no", label: p("col.plan_no"), render: (r) => (<><b>{r.plan_no}</b><div className="text-xs text-foreground-muted">{p("plan.rev", { n: r.revision })}</div></>) },
    { key: "line", label: p("col.line"), render: (r) => (<><b>{r.line_code}</b><div className="text-xs text-foreground-muted">{p(`section.${r.section}`)}</div></>) },
    { key: "bsp", label: p("col.buyer_style_po"), render: (r) => (<div className="max-w-64">{[r.buyer_name, r.style, r.po_no].filter(Boolean).join(" · ") || "—"}{r.override_reason ? <div className="text-xs text-warning">{p("plan.override", { reason: r.override_reason })}</div> : null}</div>) },
    { key: "method", label: p("col.method"), render: (r) => (<>{r.method} · {p(`method.${r.method}`)}<div className="text-xs text-foreground-muted">{p("plan.calculated", { n: num(r.calculated_target) })}</div></>) },
    { key: "proposed_target", label: p("col.proposed"), align: "right", render: (r) => <b>{num(r.proposed_target)}</b> },
    { key: "approved_target", label: p("col.approved"), align: "right", render: (r) => <b className="text-brand">{num(r.approved_target)}</b> },
    { key: "status", label: p("col.status"), render: (r) => <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", STATUS_TONE[r.status])}>{p(`status.${r.status}`)}</span> },
    { key: "by", label: p("col.by"), render: (r) => (<span className="text-xs">{r.created_by}{r.approved_by ? <div className="text-foreground-muted">{p("plan.decided_by", { name: r.approved_by })}</div> : null}</span>) },
    {
      key: "actions",
      label: p("col.actions"),
      render: (r) => (
        <div className="flex justify-end gap-1.5 whitespace-nowrap">
          {canPrepare && ["DRAFT", "REJECTED"].includes(r.status) ? (
            <>
              <Button size="sm" variant="outline" className="px-2" aria-label={p("btn.edit")} title={p("btn.edit")} onClick={() => setEditing(r)}><Pencil /></Button>
              <Button size="sm" loading={pending} onClick={() => run(() => act("planAct", r.id, "submit"), p("toast.submitted", { no: r.plan_no }))}><Send /> {p("btn.submit")}</Button>
            </>
          ) : null}
          {canApprove && r.status === "PENDING_APPROVAL" && !r.mine ? (
            <>
              <Button size="sm" onClick={() => setDeciding({ row: r, action: "approve" })}><Check /> {p("btn.approve")}</Button>
              <Button size="sm" variant="outline" className="px-2" aria-label={p("btn.reject")} title={p("btn.reject")} onClick={() => setDeciding({ row: r, action: "reject" })}><X /></Button>
            </>
          ) : null}
          {canPrepare && r.status === "APPROVED" ? <Button size="sm" variant="outline" loading={pending} onClick={() => run(() => act("planAct", r.id, "revise"), p("toast.revised", { no: r.plan_no }))}><Pencil /> {p("btn.revise")}</Button> : null}
          {(canPrepare || canApprove) && r.status !== "CANCELLED" ? <Button size="sm" variant="outline" className="px-2" aria-label={p("btn.cancel")} title={p("btn.cancel")} onClick={() => setDeciding({ row: r, action: "cancel" })}><Ban /></Button> : null}
          <Button size="sm" variant="outline" className="px-2" aria-label={p("btn.history")} title={p("btn.history")} onClick={() => openHistory(r)}><History /></Button>
        </div>
      ),
    },
  ];

  return (
    <>
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <Field label={p("f.production_date")}>
          <Input type="date" value={data.date} onChange={(e) => apply({ date: e.target.value })} className="w-44" />
        </Field>
        <FilterSelect label={p("f.section")} value={query.section} onChange={(v) => apply({ section: v })} options={["SEWING", "FINISHING"].map((s) => ({ value: s, label: p(`section.${s}`) }))} all={p("all_sections")} />
        <FilterSelect label={p("f.status")} value={query.status} onChange={(v) => apply({ status: v })} options={["DRAFT", "PENDING_APPROVAL", "APPROVED", "REJECTED", "CANCELLED"].map((s) => ({ value: s, label: p(`status.${s}`) }))} all={p("all_statuses")} />
        <p className="flex-1 text-[13px] text-foreground-muted">{p(data.approval_workflow ? "plan.workflow_on" : "plan.workflow_off")}</p>
        {canPrepare ? (
          <Button onClick={() => setEditing({})}>
            <Plus /> {p("btn.new_plan")}
          </Button>
        ) : null}
      </div>
      <Panel title={p("plan.for_day", { date: data.date })} subtitle={p("plan.summary", { n: data.rows.length, proposed: num(data.proposed), approved: num(data.approved) })} icon={CalendarCheck}>
        <Table columns={cols} rows={data.rows} empty={p("empty.plans")} />
      </Panel>
      {editing ? <PlanForm row={editing.id ? editing : null} date={data.date} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
      {deciding ? <DecisionModal {...deciding} act={act} onClose={() => setDeciding(null)} /> : null}
      <Modal open={Boolean(history)} onOpenChange={() => setHistory(null)} title={p("plan.history", { no: history?.plan_no ?? "" })}>
        <ol className="space-y-2 text-sm">
          {(history?.events ?? []).map((e, i) => (
            <li key={i} className="rounded-md border border-border px-3 py-2">
              <b>{p(`event.${e.action}`)}</b> · {p("plan.rev", { n: e.revision })} {e.to ? `· ${p(`status.${e.to}`)}` : ""}
              <div className="text-xs text-foreground-muted">{e.by ?? "—"} · {stamp(e.at)}</div>
              {e.note ? <div className="mt-1 text-xs">{e.note}</div> : null}
            </li>
          ))}
        </ol>
      </Modal>
    </>
  );
}

/** New plan, or a draft edited: line, PO (or buyer / style), method, hours, and the calculated target. */
function PlanForm({ row, date, lookups, act, onClose }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const [v, setV] = useState(() => ({
    plan_date: row?.plan_date ?? date, section: row?.section ?? "SEWING", line_id: row?.line_id ?? "", order_id: row?.order_id ?? "",
    buyer_name: row?.buyer_name ?? "", style: row?.style ?? "", po_no: row?.po_no ?? "", method: row?.method ?? "A",
    planned_hours: row?.planned_hours ?? 8, proposed_target: row && row.proposed_target !== row.calculated_target ? row.proposed_target : "",
    override_reason: row?.override_reason ?? "", remarks: row?.remarks ?? "",
  }));
  const [calc, setCalc] = useState(null);
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const lines = [...lookups.sewing_lines.map((l) => ({ ...l, label: `${l.label} · ${p("section.SEWING")}` })), ...lookups.finishing_lines.map((l) => ({ ...l, label: `${l.label} · ${p("section.FINISHING")}` }))];
  const smvOn = lookups.settings.smv_method;

  // The calculated target follows the line, day, method and hours.
  useEffect(() => {
    if (!v.line_id) return undefined;
    let live = true;
    act("previewPlan", { plan_date: v.plan_date, section: v.section, line_id: v.line_id, method: v.method, planned_hours: v.planned_hours || null }).then((res) => {
      if (!live) return;
      if (res?.status === "success") setCalc({ ...res.data, error: null });
      else setCalc({ error: res?.errors ? Object.values(res.errors)[0]?.[0] : res?.message });
    });
    return () => {
      live = false;
    };
  }, [v.plan_date, v.section, v.line_id, v.method, v.planned_hours, act]);

  const pickLine = (id) => {
    const line = lines.find((l) => l.value === id);
    setV((all) => ({ ...all, line_id: id, section: line?.section ?? all.section }));
  };
  const pickOrder = (id) => {
    const o = lookups.orders.find((x) => x.value === id);
    setV((all) => ({ ...all, order_id: id, buyer_name: o?.buyer ?? all.buyer_name, style: o?.style ?? all.style, po_no: o?.po ?? all.po_no }));
  };

  const save = () =>
    start(async () => {
      setErrors({});
      const body = { ...v, order_id: v.order_id || null, proposed_target: v.proposed_target === "" ? null : Number(v.proposed_target) };
      const res = await act("savePlan", body, row?.id);
      if (res?.status === "success") {
        toast.add({ title: p("toast.plan_saved", { no: res.data.plan_no }), type: "success" });
        onClose();
        router.refresh();
      } else {
        setErrors(res?.errors ?? {});
        toast.add({ title: res?.message ?? p("error"), type: "danger" });
      }
    });
  const err = (k) => errors[k]?.[0];

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "plan.edit_title" : "plan.new_title")}
      description={p("plan.form_hint")}
      className="max-w-3xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>{p("btn.close")}</Button>
          <Button loading={pending} onClick={save}>{p("btn.save_draft")}</Button>
        </>
      }
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={p("f.production_date")} required error={err("plan_date")}>
          <Input type="date" value={v.plan_date} onChange={(e) => set("plan_date")(e.target.value)} />
        </Field>
        <Field label={p("f.line")} required error={err("line_id")}>
          <NativeSelect value={v.line_id} onChange={pickLine} options={lines} placeholder={p("f.select_line")} />
        </Field>
        <Field label={p("f.po")} className="sm:col-span-2">
          <NativeSelect value={v.order_id} onChange={pickOrder} options={lookups.orders} placeholder={p("f.no_po")} />
        </Field>
        <Field label={p("f.buyer")}>
          <NativeSelect value={v.buyer_name} onChange={set("buyer_name")} options={lookups.buyers.map((b) => ({ value: b, label: b }))} placeholder={p("f.any_buyer")} />
        </Field>
        <Field label={p("f.style")}>
          <NativeSelect value={v.style} onChange={set("style")} options={lookups.styles.map((s) => ({ value: s, label: s }))} placeholder={p("f.no_style")} />
        </Field>
        <Field label={p("f.method")} required error={err("method")}>
          <NativeSelect value={v.method} onChange={set("method")} options={["A", "B", "C", "D"].map((m) => ({ value: m, label: `${m} — ${p(`method.${m}_long`)}${m === "D" && !smvOn ? ` (${p("plan.not_enabled")})` : ""}`, disabled: m === "D" && !smvOn }))} />
        </Field>
        <Field label={p("f.planned_hours")} required error={err("planned_hours")}>
          <Input type="number" step="0.5" min="0" value={v.planned_hours} onChange={(e) => set("planned_hours")(e.target.value)} />
        </Field>
        <div className="rounded-md border border-border bg-surface-muted p-3 sm:col-span-2">
          <p className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">{p("plan.calculated_target")}</p>
          {!v.line_id ? (
            <p className="text-sm text-foreground-muted">{p("plan.pick_line")}</p>
          ) : calc?.error ? (
            <p className="text-sm text-danger">{calc.error}</p>
          ) : calc ? (
            <>
              <p className="text-2xl font-bold text-foreground tabular-nums">{num(calc.calculated)}</p>
              <p className="text-xs text-foreground-muted">{calc.formula} · {p("plan.capacity_line", { std: calc.capacity.standard, type: p(`capacity.${calc.capacity.type}`), from: calc.capacity.from })}</p>
            </>
          ) : null}
        </div>
        <Field label={p("f.proposed_target")} hint={p("plan.blank_calculated")} error={err("proposed_target")}>
          <Input type="number" min="0" value={v.proposed_target} onChange={(e) => set("proposed_target")(e.target.value)} />
        </Field>
        <Field label={p("f.override_reason")} error={err("override_reason")}>
          <Textarea value={v.override_reason} onChange={(e) => set("override_reason")(e.target.value)} placeholder={p("plan.override_hint")} rows={2} />
        </Field>
        <Field label={p("f.remarks")} className="sm:col-span-2">
          <Textarea value={v.remarks} onChange={(e) => set("remarks")(e.target.value)} rows={2} />
        </Field>
      </div>
    </Modal>
  );
}

/** Approve (with the target to approve), reject or cancel — reject and cancel need a reason. */
function DecisionModal({ row, action, act, onClose }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const [note, setNote] = useState("");
  const [target, setTarget] = useState(String(row.proposed_target));
  const [error, setError] = useState(null);
  const [pending, start] = useTransition();
  const go = () =>
    start(async () => {
      const res = await act("planAct", row.id, action, { note: note || null, approved_target: action === "approve" ? Number(target) : undefined });
      if (res?.status === "success") {
        toast.add({ title: p(`toast.${action}`, { no: row.plan_no }), type: "success" });
        onClose();
        router.refresh();
      } else setError(res?.errors ? Object.values(res.errors)[0]?.[0] : res?.message);
    });
  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(`plan.${action}_title`, { no: row.plan_no })}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>{p("btn.close")}</Button>
          <Button loading={pending} onClick={go} variant={action === "approve" ? "primary" : "danger"}>{p(`btn.${action}`)}</Button>
        </>
      }
    >
      <div className="space-y-3">
        {action === "approve" ? (
          <Field label={p("f.approved_target")}>
            <Input type="number" min="0" value={target} onChange={(e) => setTarget(e.target.value)} />
          </Field>
        ) : null}
        <Field label={p("f.note")} required={action !== "approve"} error={error}>
          <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} />
        </Field>
      </div>
    </Modal>
  );
}

export { DailyPlans, Field, NativeSelect };
