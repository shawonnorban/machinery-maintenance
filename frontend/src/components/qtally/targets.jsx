"use client";

import { useState } from "react";
import { CalendarRange, ClipboardList, Copy, Pencil, Target, Trash2 } from "lucide-react";
import { PlanDay } from "./plan-day";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useQt, useRun, useFilters, num, pct, day, stamp, achievementTone, Panel, Table, Field, Select, FilterCard } from "./common";

/*
 * Quality Tally → Cutting / Sewing / Quality → Line Target (user, 2026-11-25):
 * each section's manager sees the orders that section still has to finish —
 * nearest due date first: done, left, what the section before has ready, what
 * is already planned — and gives a line its target from there. The target is
 * the line's input: there is nothing else to enter, and the QC of a sewing or
 * quality line taps on the jobs targeted there. Pass so far comes from the
 * day's taps. Hourly target = target ÷ hours.
 */

function LineTargets({ data, pendingOrders, lookups, query, section, canManage, act }) {
  const p = useQt();
  const { apply, pending: filtering } = useFilters(query);
  const [date, setDate] = useState(query.date ?? data.date);
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [copying, setCopying] = useState(false);
  const [planning, setPlanning] = useState(false);
  const { run, pending } = useRun();
  const rows = data.rows;
  const total = (k) => rows.reduce((a, r) => a + (r[k] ?? 0), 0);
  const totalTarget = total("target_qty");
  const cutting = section === "CUTTING";
  const before = cutting ? null : p(`section.${section === "SEWING" ? "CUTTING" : "SEWING"}`);
  const needle = search.trim().toLowerCase();
  const orders = needle ? pendingOrders.filter((o) => o.label.toLowerCase().includes(needle)) : pendingOrders;
  const sum = (k) => orders.reduce((a, o) => a + (o[k] ?? 0), 0);

  const cols = [
    { key: "line", label: p("col.line"), render: (r) => <b>{r.line ?? "—"}</b> },
    { key: "job", label: p("col.job"), render: (r) => (<><b>{r.job_no}</b><div className="text-xs text-foreground-muted">{r.buyer_name} · {r.style}</div></>) },
    { key: "smv", label: p("col.smv"), align: "right", render: (r) => (r.smv === null ? "—" : r.smv) },
    { key: "working_hours", label: p("col.hours"), align: "right" },
    { key: "manpower", label: p("col.manpower"), align: "right", render: (r) => num(r.manpower) },
    { key: "target_qty", label: p(cutting ? "col.target_panels" : "col.target"), align: "right", render: (r) => <b>{num(r.target_qty)}</b> },
    { key: "hourly_target", label: p("col.hourly_target"), align: "right", render: (r) => num(r.hourly_target) },
    { key: "pass", label: p("col.pass"), align: "right", render: (r) => num(r.pass) },
    { key: "achievement", label: p("col.achievement"), align: "right", render: (r) => <span className={achievementTone(r.achievement)}>{pct(r.achievement)}</span> },
    { key: "updated", label: p("col.updated"), render: (r) => (<span className="text-xs">{r.updated_by ?? "—"}<div className="text-foreground-muted">{stamp(r.updated_at)}</div></span>) },
    ...(canManage
      ? [
          {
            key: "act",
            label: "",
            render: (r) => (
              <div className="flex gap-1.5">
                <Button size="sm" variant="outline" onClick={() => setEditing(r)}>
                  <Pencil /> {p("btn.edit")}
                </Button>
                <Button size="sm" variant="outline" onClick={() => setDeleting(r)} aria-label={p("btn.delete")}>
                  <Trash2 />
                </Button>
              </div>
            ),
          },
        ]
      : []),
  ];

  const orderCols = [
    { key: "job", label: p("col.job"), render: (o) => (<><b>{o.job_no}</b><div className="text-xs text-foreground-muted">{[o.buyer_name, o.style, o.color, o.size, o.po_no ? `PO ${o.po_no}` : ""].filter(Boolean).join(" · ")}</div></>) },
    {
      key: "due_date",
      label: p("col.due_date"),
      render: (o) => (o.due_date ? (<>{day(o.due_date)}<div className={o.days_left < 0 ? "text-xs text-danger" : o.days_left <= 3 ? "text-xs text-warning" : "text-xs text-foreground-muted"}>{p(o.days_left < 0 ? "pend.late" : "pend.days_left", { n: Math.abs(o.days_left) })}</div></>) : "—"),
    },
    { key: "order_qty", label: p("col.order_qty"), align: "right", render: (o) => num(o.order_qty) },
    ...(cutting ? [{ key: "given", label: p("pend.given"), align: "right", render: (o) => num(o.given) }] : []),
    { key: "done", label: p(cutting ? "flow.pieces" : "pend.done"), align: "right", render: (o) => num(o.done) },
    { key: "left", label: p("pend.left"), align: "right", render: (o) => <b className="text-danger">{num(o.left)}</b> },
    ...(cutting ? [] : [{ key: "ready", label: p("pend.ready", { before }), align: "right", render: (o) => <b className={o.ready > 0 ? "text-success" : "text-foreground-muted"}>{num(o.ready)}</b> }]),
    { key: "planned", label: p("pend.planned"), align: "right", render: (o) => (o.planned ? (<><b>{num(o.planned)}</b><div className="text-xs text-foreground-muted">{o.planned_lines.join(", ")}</div></>) : "—") },
    ...(canManage
      ? [
          {
            key: "act",
            label: "",
            render: (o) => (
              <Button size="sm" onClick={() => setEditing({ target_date: data.date, job_id: o.id, target_qty: o.suggest ?? "", order: o })}>
                <Target /> {p("pend.give")}
              </Button>
            ),
          },
        ]
      : []),
  ];

  return (
    <>
      <FilterCard
        onApply={() => apply({ date })}
        pending={filtering}
        applyLabel={p("apply")}
        extra={
          canManage ? (
            <div className="ml-auto flex flex-wrap gap-2">
              <Button variant="outline" onClick={() => setCopying(true)}>
                <Copy /> {p("btn.copy_targets")}
              </Button>
              <Button variant="outline" onClick={() => setPlanning(true)}>
                <CalendarRange /> {p("btn.plan_day")}
              </Button>
            </div>
          ) : null
        }
      >
        <Field label={p("f.date")}>
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
        </Field>
      </FilterCard>
      <Panel
        title={p("pend.title")}
        subtitle={p(cutting ? "pend.hint_cutting" : "pend.hint", { n: pendingOrders.length, before: before ?? "" })}
        icon={ClipboardList}
        className="mb-4"
        action={<Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={p("f.search_job")} aria-label={p("f.search")} className="w-64" />}
      >
        <Table
          columns={orderCols}
          rows={orders}
          empty={p("pend.none")}
          foot={{ job: p("total"), order_qty: num(sum("order_qty")), done: num(sum("done")), left: num(sum("left")), planned: num(sum("planned")), ...(cutting ? { given: num(sum("given")) } : { ready: num(sum("ready")) }) }}
        />
      </Panel>
      <Panel title={p("targets.title", { date: day(data.date) })} subtitle={p("targets.count", { n: rows.length })} icon={Target}>
        <Table
          columns={cols}
          rows={rows}
          empty={p("empty.targets")}
          foot={{ line: p("total"), manpower: num(total("manpower")), target_qty: num(totalTarget), pass: num(total("pass")), achievement: totalTarget ? pct(Math.round((total("pass") / totalTarget) * 10000) / 100) : "—" }}
        />
      </Panel>
      {editing ? <TargetForm row={editing.id ? editing : null} seed={editing} section={section} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
      {planning ? <PlanDay date={data.date} section={section} rows={data.rows} lookups={lookups} act={act} onClose={() => setPlanning(false)} /> : null}
      {copying ? <CopyForm date={data.date} section={section} act={act} onClose={() => setCopying(false)} /> : null}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={p("targets.delete_title")}
        description={deleting ? `${deleting.line} · ${deleting.job_no} · ${num(deleting.target_qty)}` : ""}
        confirmLabel={p("btn.delete")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("deleteTarget", deleting.id), p("toast.deleted"), () => setDeleting(null))}
      />
    </>
  );
}

function TargetForm({ row, seed, section, lookups, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [errors, setErrors] = useState({});
  const [v, setV] = useState(() => ({
    target_date: row?.target_date ?? seed.target_date ?? lookups.today, section, line_id: row?.line_id ?? "", job_id: row?.job_id ?? seed.job_id ?? "",
    working_hours: row?.working_hours ?? 8, manpower: row?.manpower ?? "", target_qty: row?.target_qty ?? seed.target_qty ?? "", remarks: row?.remarks ?? "",
  }));
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const jobs = row && !lookups.jobs.some((j) => j.value === row.job_id) ? [...lookups.jobs, { value: row.job_id, label: row.job, smv: row.smv }] : lookups.jobs;
  const job = jobs.find((j) => j.value === v.job_id);
  const hours = Number(v.working_hours) || 0;
  const hourly = hours > 0 && Number(v.target_qty) > 0 ? Math.round(Number(v.target_qty) / hours) : null;
  // Pieces at 100 % efficiency: manpower × minutes ÷ SMV — a guide, not the target.
  const capacity = section !== "CUTTING" && job?.smv && Number(v.manpower) > 0 && hours > 0 ? Math.floor((Number(v.manpower) * hours * 60) / job.smv) : null;
  const save = () =>
    run(
      () => act("saveTarget", { ...v, working_hours: Number(v.working_hours), manpower: v.manpower === "" ? null : Number(v.manpower), target_qty: v.target_qty === "" ? null : Number(v.target_qty), remarks: v.remarks || null }, row?.id),
      p("toast.target_saved"),
      (res) => (res.status === "success" ? onClose() : setErrors(res.errors ?? {})),
    );

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "targets.edit_title" : "targets.add_title")}
      className="max-w-2xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={pending} onClick={save}>
            {p("btn.save")}
          </Button>
        </>
      }
    >
      {seed.order ? (
        <p className="mb-3 rounded-md bg-surface-muted px-3 py-2 text-sm">
          {p(section !== "CUTTING" ? "pend.form" : seed.order.panels ? "pend.form_cutting" : "pend.form_cutting_new", { order: num(seed.order.order_qty), done: num(seed.order.done), left: num(seed.order.left), ready: num(seed.order.ready), panels: num(seed.order.panels), planned: num(seed.order.planned) })}
        </p>
      ) : null}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={p("f.date")} required error={err("target_date")}>
          <Input type="date" value={v.target_date} onChange={(e) => set("target_date")(e.target.value)} />
        </Field>
        <Field label={p("f.line")} required error={err("line_id")} hint={lookups.lines[section]?.length ? null : p("f.no_lines", { section: p(`section.${section}`) })}>
          <Select value={v.line_id} onChange={set("line_id")} options={lookups.lines[section] ?? []} placeholder={p("f.select_line")} />
        </Field>
        <Field label={p("f.job")} required error={err("job_id")} className="sm:col-span-2">
          <Select value={v.job_id} onChange={set("job_id")} options={jobs} placeholder={p("f.select_job")} disabled={Boolean(seed.order)} />
        </Field>
        <Field label={p("f.working_hours")} required error={err("working_hours")}>
          <Input type="number" min="0.5" max="24" step="0.5" value={v.working_hours} onChange={(e) => set("working_hours")(e.target.value)} />
        </Field>
        <Field label={p("f.manpower")} error={err("manpower")}>
          <Input type="number" min="1" value={v.manpower} onChange={(e) => set("manpower")(e.target.value)} />
        </Field>
        <Field label={p(section === "CUTTING" ? "f.target_panels" : "f.target_qty")} required error={err("target_qty")} hint={hourly ? p("f.hourly_is", { n: hourly }) : p(section === "CUTTING" ? "f.panels_day" : "f.pcs_day")}>
          <Input type="number" min="1" value={v.target_qty} onChange={(e) => set("target_qty")(e.target.value)} />
        </Field>
        <div className="self-end rounded-md bg-surface-muted px-3 py-2 text-xs text-foreground-muted">
          {section === "CUTTING" ? p("pend.panels_hint") : capacity ? p("f.capacity_hint", { n: capacity.toLocaleString("en-US"), smv: job.smv }) : p("f.capacity_none")}
        </div>
        <Field label={p("f.remarks")} className="sm:col-span-2">
          <Textarea value={v.remarks} onChange={(e) => set("remarks")(e.target.value)} rows={2} />
        </Field>
      </div>
    </Modal>
  );
}

function CopyForm({ date, section, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const prev = new Date(`${date}T00:00:00`);
  prev.setDate(prev.getDate() - 1);
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  const [from, setFrom] = useState(iso(prev));
  const [to, setTo] = useState(date);

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p("targets.copy_title")}
      className="max-w-lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={pending} onClick={() => run(() => act("copyTargets", { from, to, section }), (d) => p("toast.targets_copied", { n: d.copied }), (res) => res.status === "success" && onClose())}>
            <Copy /> {p("btn.copy")}
          </Button>
        </>
      }
    >
      <p className="mb-3 text-sm text-foreground-muted">{p("targets.copy_hint")}</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={p("f.copy_from")}>
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </Field>
        <Field label={p("f.copy_to")}>
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </Field>
      </div>
    </Modal>
  );
}

export { LineTargets };
