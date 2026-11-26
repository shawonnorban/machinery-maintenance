"use client";

import { useState } from "react";
import { Layers, ListPlus, Pencil, Plus, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useQt, useRun, Panel, Table, Field, Pill } from "./common";

/*
 * Quality Tally → Setup → Panels (user, 2026-11-25): each garment type's
 * panels — T-Shirt: Back, Front, Sleeve; Hoody: … Hood Top, Hood Inner — the
 * list a Cut Panel Inspection picks a bundle's panel from. A panel already
 * inspected cannot be deleted; make it inactive.
 */

function Panels({ rows, canManage, act }) {
  const p = useQt();
  const types = [...new Set(rows.map((r) => r.garment_type))];
  const [type, setType] = useState(types[0] ?? "");
  const current = types.includes(type) ? type : (types[0] ?? "");
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const { run, pending } = useRun();
  const list = rows.filter((r) => r.garment_type === current);

  const cols = [
    { key: "sort_order", label: p("col.sort"), align: "right" },
    { key: "code", label: p("col.code"), render: (r) => <b>{r.code || "—"}</b> },
    { key: "name", label: p("col.panel") },
    { key: "per_piece", label: p("col.per_piece"), align: "right", render: (r) => <b className={r.per_piece > 1 ? "text-brand" : undefined}>{r.per_piece}</b> },
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
        <div className="flex flex-wrap rounded-md border border-border-strong p-0.5" role="tablist">
          {types.length === 0 ? <span className="px-3 py-1.5 text-[13px] text-foreground-muted">{p("panels.none")}</span> : null}
          {types.map((t) => (
            <button
              key={t}
              type="button"
              role="tab"
              aria-selected={current === t}
              onClick={() => setType(t)}
              className={cn("rounded px-4 py-1.5 text-[13px] font-semibold", current === t ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}
            >
              {t} <span className="opacity-75">({rows.filter((r) => r.garment_type === t).length})</span>
            </button>
          ))}
        </div>
        {canManage ? (
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" loading={pending} onClick={() => run(() => act("standardPanels"), (d) => p("toast.panels_added", { n: d.added }))}>
              <ListPlus /> {p("btn.load_standard_panels")}
            </Button>
            <Button onClick={() => setEditing({ garment_type: current })}>
              <Plus /> {p("btn.add_panel")}
            </Button>
          </div>
        ) : null}
      </div>
      <Panel title={current ? p("panels.title", { type: current }) : p("screens.panels")} subtitle={p("panels.hint")} icon={Layers}>
        <Table columns={cols} rows={list} empty={canManage ? p("empty.panels_manage") : p("empty.panels")} />
      </Panel>
      {editing ? <PanelForm row={editing.id ? editing : null} type={editing.garment_type} types={types} act={act} onClose={() => setEditing(null)} /> : null}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={p("panels.delete_title")}
        description={deleting ? `${deleting.garment_type} — ${deleting.name}` : ""}
        confirmLabel={p("btn.delete")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("deletePanel", deleting.id), p("toast.deleted"), () => setDeleting(null))}
      />
    </>
  );
}

function PanelForm({ row, type, types, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [errors, setErrors] = useState({});
  const [v, setV] = useState(() => ({ garment_type: row?.garment_type ?? type ?? "", code: row?.code ?? "", name: row?.name ?? "", per_piece: row?.per_piece ?? 1, sort_order: row?.sort_order ?? "", active: row?.active ?? true }));
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const save = () =>
    run(
      () => act("savePanel", { ...v, per_piece: Number(v.per_piece) || 1, sort_order: v.sort_order === "" ? null : Number(v.sort_order) }, row?.id),
      p("toast.panel_saved"),
      (res) => (res.status === "success" ? onClose() : setErrors(res.errors ?? {})),
    );

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "panels.edit_title" : "panels.add_title")}
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
        <Field label={p("f.garment_type")} required error={err("garment_type")} hint={p("f.garment_type_hint")} className="sm:col-span-2">
          <Input value={v.garment_type} onChange={(e) => set("garment_type")(e.target.value)} list="qt-garment-types" maxLength={40} />
          <datalist id="qt-garment-types">
            {types.map((t) => (
              <option key={t} value={t} />
            ))}
          </datalist>
        </Field>
        <Field label={p("f.panel_name")} required error={err("name")}>
          <Input value={v.name} onChange={(e) => set("name")(e.target.value)} maxLength={80} />
        </Field>
        <Field label={p("f.code")} error={err("code")}>
          <Input value={v.code} onChange={(e) => set("code")(e.target.value.toUpperCase())} maxLength={12} />
        </Field>
        <Field label={p("f.per_piece")} error={err("per_piece")} hint={p("f.per_piece_hint")}>
          <Input type="number" min="1" max="20" value={v.per_piece} onChange={(e) => set("per_piece")(e.target.value)} />
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

export { Panels };
