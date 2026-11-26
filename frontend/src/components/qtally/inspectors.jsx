"use client";

import { useEffect, useMemo, useState } from "react";
import { KeyRound, Pencil, Plus, Trash2, UserCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { SECTIONS, useQt, useRun, num, Panel, Table, Field, Select, Pill, SectionPill, Notice } from "./common";

/*
 * Quality Tally → Planning → QC Assignment (AnnotechERP 36): a QC is a user
 * of this company (who signs in on the tablet and taps) tied to their HR &
 * Payroll employee (user, 2026-11-24), and taps only on the lines given here.
 * Picking an employee picks the user with the same email when there is one.
 */

function QcAssignment({ rows, lookups, canManage, act }) {
  const p = useQt();
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [resetting, setResetting] = useState(null);
  const { run, pending } = useRun();

  const cols = [
    {
      key: "qc",
      label: p("col.qc"),
      render: (r) => (
        <>
          <b>{r.employee ?? r.user}</b>
          <div className="text-xs text-foreground-muted">{[r.employee_code, r.designation].filter(Boolean).join(" · ") || p("qc.no_employee")}</div>
        </>
      ),
    },
    { key: "user", label: p("col.login"), render: (r) => (<>{r.user}<div className="text-xs text-foreground-muted">{r.email}</div></>) },
    {
      key: "lines",
      label: p("col.lines"),
      render: (r) =>
        r.lines.length ? (
          <div className="flex max-w-xs flex-wrap gap-1">
            {r.lines.map((l) => (
              <span key={`${l.section}:${l.line_id}`} className="inline-flex items-center gap-1 rounded border border-border px-1.5 py-0.5 text-xs">
                <SectionPill section={l.section} /> {l.label}
              </span>
            ))}
          </div>
        ) : (
          <span className="text-xs text-warning">{p("qc.no_lines")}</span>
        ),
    },
    {
      key: "pin",
      label: p("col.pin"),
      render: (r) => {
        if (!r.pin) return <span className="block max-w-40 text-xs text-foreground-muted">{p("qc.pin_none")}</span>;
        return (
          <>
            <Pill tone={r.pin === "OWN" ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning"}>{p(r.pin === "OWN" ? "qc.pin_own" : "qc.pin_default")}</Pill>
            {canManage && r.pin === "OWN" ? (
              <button type="button" onClick={() => setResetting(r)} className="mt-1 flex items-center gap-1 text-xs font-semibold text-brand hover:underline">
                <KeyRound className="size-3.5" aria-hidden /> {p("btn.reset_pin")}
              </button>
            ) : null}
          </>
        );
      },
    },
    { key: "taps_today", label: p("col.taps_today"), align: "right", render: (r) => num(r.taps_today) },
    { key: "active", label: p("col.status"), render: (r) => <Pill tone={r.active ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted"}>{p(r.active ? "active" : "inactive")}</Pill> },
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

  return (
    <>
      <Panel
        title={p("qc.title")}
        subtitle={p("qc.count", { n: rows.length })}
        icon={UserCheck}
        action={
          canManage ? (
            <Button onClick={() => setEditing({})}>
              <Plus /> {p("btn.add_qc")}
            </Button>
          ) : null
        }
      >
        <Notice tone="info" className="mx-5 my-3">{p("qc.phone_hint")}</Notice>
        <Table columns={cols} rows={rows} empty={p("empty.qc")} />
      </Panel>
      {editing ? <QcForm row={editing.id ? editing : null} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
      <ConfirmDialog
        open={resetting !== null}
        onOpenChange={(o) => !o && setResetting(null)}
        title={p("qc.reset_title")}
        description={resetting ? p("qc.reset_body", { name: resetting.employee ?? resetting.user }) : ""}
        confirmLabel={p("btn.reset_pin")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("resetPin", resetting.id), p("toast.pin_reset"), () => setResetting(null))}
      />
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={p("qc.delete_title")}
        description={deleting ? p("qc.delete_body", { name: deleting.employee ?? deleting.user }) : ""}
        confirmLabel={p("btn.delete")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("deleteInspector", deleting.id), p("toast.deleted"), () => setDeleting(null))}
      />
    </>
  );
}

function QcForm({ row, lookups, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [options, setOptions] = useState(null);
  const [errors, setErrors] = useState({});
  const [find, setFind] = useState("");
  const [v, setV] = useState(() => ({
    user_id: row?.user_id ?? "", hr_employee_id: row?.hr_employee_id ?? "", active: row?.active ?? true,
    lines: new Set((row?.lines ?? []).map((l) => `${l.section}:${l.line_id}`)),
  }));
  const err = (k) => errors[k]?.[0];

  useEffect(() => {
    let live = true;
    act("inspectorOptions").then((res) => live && setOptions(res?.status === "success" ? res.data : { users: [], employees: [] }));
    return () => {
      live = false;
    };
  }, [act]);

  const employees = useMemo(() => {
    const all = (options?.employees ?? []).map((e) => ({ ...e, disabled: Boolean(e.inspector_id && e.inspector_id !== row?.id) }));
    const q = find.trim().toLowerCase();
    return q ? all.filter((e) => e.label.toLowerCase().includes(q) || e.value === v.hr_employee_id) : all;
  }, [options, find, row, v.hr_employee_id]);
  const users = (options?.users ?? []).map((u) => ({ ...u, disabled: Boolean(u.inspector_id && u.inspector_id !== row?.id) }));

  const pickEmployee = (id) => {
    const e = options?.employees.find((x) => x.value === id);
    const match = e?.email ? users.find((u) => !u.disabled && u.email?.toLowerCase() === e.email.toLowerCase()) : null;
    setV((all) => ({ ...all, hr_employee_id: id, user_id: all.user_id || match?.value || "" }));
  };
  const toggle = (key) =>
    setV((all) => {
      const next = new Set(all.lines);
      next.has(key) ? next.delete(key) : next.add(key);
      return { ...all, lines: next };
    });
  const save = () =>
    run(
      () =>
        act(
          "saveInspector",
          { user_id: v.user_id, hr_employee_id: v.hr_employee_id || null, active: v.active, lines: [...v.lines].map((k) => ({ section: k.split(":")[0], line_id: k.split(":")[1] })) },
          row?.id,
        ),
      p("toast.qc_saved"),
      (res) => (res.status === "success" ? onClose() : setErrors(res.errors ?? {})),
    );

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "qc.edit_title" : "qc.add_title")}
      className="max-w-3xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={pending} disabled={!options} onClick={save}>
            {p("btn.save")}
          </Button>
        </>
      }
    >
      {!options ? (
        <p className="py-6 text-center text-sm text-foreground-muted">{p("loading")}</p>
      ) : (
        <div className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label={p("f.employee")} error={err("hr_employee_id")} hint={p("f.employee_hint")}>
              <Input value={find} onChange={(e) => setFind(e.target.value)} placeholder={p("f.find_employee")} className="mb-1.5" />
              <Select value={v.hr_employee_id} onChange={pickEmployee} options={employees} placeholder={p("f.no_employee")} />
            </Field>
            <Field label={p("f.user")} required error={err("user_id")} hint={p("f.user_hint")}>
              <Select value={v.user_id} onChange={(id) => setV((all) => ({ ...all, user_id: id }))} options={users} placeholder={p("f.select_user")} className="mt-[42px]" />
            </Field>
          </div>
          <div>
            <p className="mb-1.5 text-[13px] font-semibold text-foreground">{p("f.lines")}</p>
            {err("lines") ? <Notice tone="danger" className="mb-2">{err("lines")}</Notice> : null}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              {SECTIONS.map((s) => (
                <fieldset key={s} className="rounded-md border border-border p-3">
                  <legend className="px-1">
                    <SectionPill section={s} />
                  </legend>
                  {(lookups.lines[s] ?? []).length === 0 ? (
                    <p className="text-xs text-foreground-muted">{p("f.no_lines", { section: p(`section.${s}`) })}</p>
                  ) : (
                    <div className="flex max-h-56 flex-col gap-1.5 overflow-y-auto">
                      {lookups.lines[s].map((l) => (
                        <label key={l.value} className="inline-flex items-center gap-2 text-sm">
                          <input type="checkbox" checked={v.lines.has(`${s}:${l.value}`)} onChange={() => toggle(`${s}:${l.value}`)} /> {l.label}
                        </label>
                      ))}
                    </div>
                  )}
                </fieldset>
              ))}
            </div>
          </div>
          <label className="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" checked={v.active} onChange={(e) => setV((all) => ({ ...all, active: e.target.checked }))} /> {p("qc.active_hint")}
          </label>
        </div>
      )}
    </Modal>
  );
}

export { QcAssignment };
