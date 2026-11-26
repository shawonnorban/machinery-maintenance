"use client";

import { useState } from "react";
import { ListPlus, Pencil, Plus, ShieldAlert, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { SECTIONS, useQt, useRun, Panel, Table, Field, Select, Pill } from "./common";

/*
 * Quality Tally → Setup → Defect Types (AnnotechERP 36): Cutting, Sewing and
 * Quality each keep their own defect list (user, 2026-11-24). A defect
 * already tagged on a tap cannot be deleted or moved to another section —
 * switch it off instead.
 */

function DefectTypes({ rows, canManage, act }) {
  const p = useQt();
  const [section, setSection] = useState("SEWING");
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const { run, pending } = useRun();
  const list = rows.filter((r) => r.section === section);
  const count = (s) => rows.filter((r) => r.section === s).length;

  const cols = [
    { key: "sort_order", label: p("col.sort"), align: "right" },
    { key: "code", label: p("col.code"), render: (r) => <b>{r.code}</b> },
    { key: "name", label: p("col.defect_name") },
    { key: "name_bn", label: p("col.name_bn"), render: (r) => r.name_bn || "—" },
    { key: "used", label: p("col.used"), align: "right", render: (r) => r.used.toLocaleString("en-US") },
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
                {r.used === 0 ? (
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
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface p-3">
        <div className="flex rounded-md border border-border-strong p-0.5" role="tablist">
          {SECTIONS.map((s) => (
            <button
              key={s}
              type="button"
              role="tab"
              aria-selected={section === s}
              onClick={() => setSection(s)}
              className={cn("rounded px-4 py-1.5 text-[13px] font-semibold", section === s ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}
            >
              {p(`section.${s}`)} <span className="opacity-75">({count(s)})</span>
            </button>
          ))}
        </div>
        {canManage ? (
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" loading={pending} onClick={() => run(() => act("standardDefects", section), (d) => p("toast.standard_added", { n: d.added }))}>
              <ListPlus /> {p("btn.load_standard")}
            </Button>
            <Button onClick={() => setEditing({ section })}>
              <Plus /> {p("btn.add_defect")}
            </Button>
          </div>
        ) : null}
      </div>
      <Panel title={p("defects.title", { section: p(`section.${section}`) })} subtitle={p("defects.hint")} icon={ShieldAlert}>
        <Table columns={cols} rows={list} empty={canManage ? p("empty.defects_manage") : p("empty.defects")} />
      </Panel>
      {editing ? <DefectForm row={editing.id ? editing : null} section={editing.section} act={act} onClose={() => setEditing(null)} /> : null}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={p("defects.delete_title")}
        description={deleting ? `${deleting.code} — ${deleting.name}` : ""}
        confirmLabel={p("btn.delete")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("deleteDefect", deleting.id), p("toast.deleted"), () => setDeleting(null))}
      />
    </>
  );
}

function DefectForm({ row, section, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [errors, setErrors] = useState({});
  const [v, setV] = useState(() => ({
    section: row?.section ?? section ?? "SEWING", code: row?.code ?? "", name: row?.name ?? "", name_bn: row?.name_bn ?? "", sort_order: row?.sort_order ?? "", active: row?.active ?? true,
  }));
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const save = () =>
    run(
      () => act("saveDefect", { ...v, sort_order: v.sort_order === "" ? null : Number(v.sort_order), name_bn: v.name_bn || null }, row?.id),
      p("toast.defect_saved"),
      (res) => (res.status === "success" ? onClose() : setErrors(res.errors ?? {})),
    );

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "defects.edit_title" : "defects.add_title")}
      className="max-w-xl"
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
        <Field label={p("f.section")} required error={err("section")}>
          <Select value={v.section} onChange={set("section")} options={SECTIONS.map((s) => ({ value: s, label: p(`section.${s}`) }))} />
        </Field>
        <Field label={p("f.code")} required error={err("code")} hint={p("f.code_hint")}>
          <Input value={v.code} onChange={(e) => set("code")(e.target.value.toUpperCase())} maxLength={20} />
        </Field>
        <Field label={p("f.defect_name")} required error={err("name")} className="sm:col-span-2">
          <Input value={v.name} onChange={(e) => set("name")(e.target.value)} maxLength={120} />
        </Field>
        <Field label={p("f.name_bn")} error={err("name_bn")} className="sm:col-span-2">
          <Input value={v.name_bn} onChange={(e) => set("name_bn")(e.target.value)} maxLength={120} />
        </Field>
        <Field label={p("f.sort")} error={err("sort_order")}>
          <Input type="number" min="0" value={v.sort_order} onChange={(e) => set("sort_order")(e.target.value)} />
        </Field>
        <label className="inline-flex items-center gap-2 self-end pb-2 text-sm">
          <input type="checkbox" checked={v.active} onChange={(e) => set("active")(e.target.checked)} /> {p("active")}
        </label>
      </div>
    </Modal>
  );
}

export { DefectTypes };
