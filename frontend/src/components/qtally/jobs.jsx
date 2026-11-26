"use client";

import { useState } from "react";
import { Briefcase, Lock, LockOpen, Pencil, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { SECTIONS, useQt, useRun, useFilters, num, pct, stamp, day, Panel, Table, Field, Select, Pill, FilterCard } from "./common";

/*
 * Quality Tally → Planning → Jobs (AnnotechERP 36): the jobs this module
 * tallies — its own input, not linked to the rest of the ERP yet (user,
 * 2026-11-24). A closed job can no longer be tapped; a job already tapped
 * cannot be deleted, only closed. Pass is shown per section: the same
 * garment passes cutting, sewing and quality, so they are never added.
 */

function QtJobs({ rows, query, canManage, act }) {
  const p = useQt();
  const { apply, pending: filtering } = useFilters(query);
  const [status, setStatus] = useState(query.status ?? "");
  const [search, setSearch] = useState(query.search ?? "");
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const { run, pending } = useRun();

  const progress = (r, s) => {
    const n = r.pass?.[s] ?? 0;
    return (
      <span className="tabular-nums">
        {num(n)}
        {r.order_qty ? <span className="block text-xs text-foreground-muted">{pct(Math.round((n / r.order_qty) * 10000) / 100)}</span> : null}
      </span>
    );
  };

  const cols = [
    { key: "job_no", label: p("col.job_no"), render: (r) => <b>{r.job_no}</b> },
    { key: "buyer_name", label: p("col.buyer") },
    { key: "style", label: p("col.style") },
    { key: "color", label: p("col.color"), render: (r) => r.color || "—" },
    { key: "size", label: p("col.size"), render: (r) => r.size || "—" },
    { key: "po_no", label: p("col.po"), render: (r) => r.po_no || "—" },
    { key: "order_qty", label: p("col.order_qty"), align: "right", render: (r) => num(r.order_qty) },
    { key: "smv", label: p("col.smv"), align: "right", render: (r) => (r.smv === null ? "—" : r.smv) },
    {
      key: "due_date",
      label: p("col.due_date"),
      render: (r) =>
        r.due_date ? (
          <span className="whitespace-nowrap">
            {day(r.due_date)}
            {r.status === "OPEN" && r.days_left !== null ? (
              <span className={`block text-xs font-semibold ${r.days_left < 0 ? "text-danger" : r.days_left <= 7 ? "text-warning" : "text-foreground-muted"}`}>
                {r.days_left < 0 ? p("jobs.overdue_by", { n: -r.days_left }) : p("jobs.days_left", { n: r.days_left })}
              </span>
            ) : null}
          </span>
        ) : (
          "—"
        ),
    },
    ...SECTIONS.map((s) => ({ key: `pass_${s}`, label: p(`col.pass_${s}`), align: "right", render: (r) => progress(r, s) })),
    { key: "status", label: p("col.status"), render: (r) => <Pill tone={r.status === "OPEN" ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted"}>{p(`job_status.${r.status}`)}</Pill> },
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
                <Button
                  size="sm"
                  variant="outline"
                  loading={pending}
                  title={p(r.status === "OPEN" ? "btn.close_job" : "btn.reopen")}
                  aria-label={p(r.status === "OPEN" ? "btn.close_job" : "btn.reopen")}
                  onClick={() => run(() => act("saveJob", { ...jobBody(r), status: r.status === "OPEN" ? "CLOSED" : "OPEN" }, r.id), p(r.status === "OPEN" ? "toast.job_closed" : "toast.job_reopened"))}
                >
                  {r.status === "OPEN" ? <Lock /> : <LockOpen />}
                </Button>
                {!r.tallied ? (
                  <Button size="sm" variant="outline" onClick={() => setDeleting(r)} aria-label={p("btn.delete")}>
                    <Trash2 />
                  </Button>
                ) : null}
              </div>
            ),
          },
        ]
      : []),
  ];

  return (
    <>
      <FilterCard
        onApply={() => apply({ status, search })}
        pending={filtering}
        applyLabel={p("apply")}
        extra={
          canManage ? (
            <Button className="ml-auto" onClick={() => setEditing({})}>
              <Plus /> {p("btn.add_job")}
            </Button>
          ) : null
        }
      >
        <Field label={p("f.status")}>
          <Select value={status} onChange={setStatus} options={["OPEN", "CLOSED"].map((s) => ({ value: s, label: p(`job_status.${s}`) }))} placeholder={p("all")} />
        </Field>
        <Field label={p("f.search")} className="min-w-64 flex-1">
          <Input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === "Enter" && apply({ status, search })} placeholder={p("f.search_job")} />
        </Field>
      </FilterCard>
      <Panel title={p("jobs.title")} subtitle={p("jobs.count", { n: rows.length })} icon={Briefcase}>
        <Table columns={cols} rows={rows} empty={p("empty.jobs")} />
      </Panel>
      {editing ? <JobForm row={editing.id ? editing : null} act={act} onClose={() => setEditing(null)} /> : null}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={p("jobs.delete_title")}
        description={deleting ? p("jobs.delete_body", { job: deleting.job_no }) : ""}
        confirmLabel={p("btn.delete")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("deleteJob", deleting.id), p("toast.deleted"), () => setDeleting(null))}
      />
    </>
  );
}

const OPENING = ["opening_cut", "opening_sewing", "opening_quality"];

const jobBody = (r) => ({ job_no: r.job_no, buyer_name: r.buyer_name, style: r.style, po_no: r.po_no ?? "", color: r.color ?? "", size: r.size ?? "", order_qty: r.order_qty, smv: r.smv, due_date: r.due_date ?? null, remarks: r.remarks ?? null });

function JobForm({ row, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [errors, setErrors] = useState({});
  const [v, setV] = useState(() => ({
    job_no: row?.job_no ?? "", buyer_name: row?.buyer_name ?? "", style: row?.style ?? "", po_no: row?.po_no ?? "", color: row?.color ?? "", size: row?.size ?? "",
    order_qty: row?.order_qty ?? "", smv: row?.smv ?? "", due_date: row?.due_date ?? "", remarks: row?.remarks ?? "",
    ...Object.fromEntries(OPENING.map((k) => [k, row?.[k] ?? 0])),
  }));
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const save = () =>
    run(
      () => act("saveJob", { ...v, order_qty: v.order_qty === "" ? null : Number(v.order_qty), smv: v.smv === "" ? null : Number(v.smv), due_date: v.due_date || null, remarks: v.remarks || null, ...Object.fromEntries(OPENING.map((k) => [k, Number(v[k]) || 0])) }, row?.id),
      p("toast.job_saved"),
      (res) => (res.status === "success" ? onClose() : setErrors(res.errors ?? {})),
    );

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "jobs.edit_title" : "jobs.add_title")}
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
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={p("f.job_no")} required error={err("job_no")}>
          <Input value={v.job_no} onChange={(e) => set("job_no")(e.target.value)} maxLength={40} />
        </Field>
        <Field label={p("f.buyer")} required error={err("buyer_name")}>
          <Input value={v.buyer_name} onChange={(e) => set("buyer_name")(e.target.value)} maxLength={120} />
        </Field>
        <Field label={p("f.style")} required error={err("style")}>
          <Input value={v.style} onChange={(e) => set("style")(e.target.value)} maxLength={120} />
        </Field>
        <Field label={p("f.color")} error={err("color")}>
          <Input value={v.color} onChange={(e) => set("color")(e.target.value)} maxLength={64} />
        </Field>
        <Field label={p("f.size")} error={err("size")}>
          <Input value={v.size} onChange={(e) => set("size")(e.target.value)} maxLength={64} />
        </Field>
        <Field label={p("f.due_date")} error={err("due_date")} hint={p("f.due_date_hint")}>
          <Input type="date" value={v.due_date} onChange={(e) => set("due_date")(e.target.value)} />
        </Field>
        <Field label={p("f.po")} error={err("po_no")}>
          <Input value={v.po_no} onChange={(e) => set("po_no")(e.target.value)} maxLength={64} />
        </Field>
        <Field label={p("f.order_qty")} required error={err("order_qty")} hint={p("f.pcs")}>
          <Input type="number" min="1" value={v.order_qty} onChange={(e) => set("order_qty")(e.target.value)} />
        </Field>
        <Field label={p("f.smv")} error={err("smv")} hint={p("f.smv_hint")}>
          <Input type="number" min="0" step="0.001" value={v.smv} onChange={(e) => set("smv")(e.target.value)} />
        </Field>
        <Field label={p("f.remarks")} className="sm:col-span-2">
          <Textarea value={v.remarks} onChange={(e) => set("remarks")(e.target.value)} rows={2} />
        </Field>
      </div>
      <fieldset className="mt-4 rounded-md border border-border p-3">
        <legend className="px-1 text-[13px] font-semibold text-foreground">{p("jobs.opening")}</legend>
        <p className="mb-2 text-xs text-foreground-muted">{p("jobs.opening_hint")}</p>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {OPENING.map((k) => (
            <Field key={k} label={p(`f.${k}`)} error={err(k)}>
              <Input type="number" min="0" value={v[k]} onChange={(e) => set(k)(e.target.value)} />
            </Field>
          ))}
        </div>
      </fieldset>
    </Modal>
  );
}

export { QtJobs };
