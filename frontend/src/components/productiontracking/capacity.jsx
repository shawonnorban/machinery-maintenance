"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CircleAlert, Gauge, Pencil, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { usePt, num, stamp, Panel, Table } from "./common";
import { Field, NativeSelect } from "./plans";

/*
 * Production Tracking → Line Capacity Master (AnnotechERP 30/01): each
 * sewing and finishing line's standard capacity, effective by date; the
 * lines without a capacity today (no target can be generated for them).
 */

function LineCapacities({ data, lookups, canManage, act }) {
  const p = usePt();
  const [section, setSection] = useState("");
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState(null);
  const rows = data.rows.filter((r) => (!section || r.section === section) && (!search || `${r.line ?? ""}`.toLowerCase().includes(search.toLowerCase())));
  const cols = [
    { key: "line", label: p("col.line"), render: (r) => (<><b>{r.line_code}</b><div className="text-xs text-foreground-muted">{r.line}</div></>) },
    { key: "section", label: p("col.section"), render: (r) => p(`section.${r.section}`) },
    { key: "capacity_type", label: p("col.type"), render: (r) => p(`capacity.${r.capacity_type}`) },
    { key: "standard_capacity", label: p("col.standard_capacity"), align: "right", render: (r) => <b>{num(r.standard_capacity)}</b> },
    { key: "default_efficiency", label: p("col.default_eff"), align: "right", render: (r) => (r.default_efficiency === null ? "—" : `${r.default_efficiency}%`) },
    { key: "includes_efficiency", label: p("col.incl_eff"), render: (r) => p(r.includes_efficiency ? "yes" : "no") },
    { key: "effective", label: p("col.effective"), render: (r) => `${r.effective_from} → ${r.effective_to ?? p("open_ended")}` },
    {
      key: "status",
      label: p("col.status"),
      render: (r) => (
        <div className="flex flex-col items-start gap-1">
          <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", r.active ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted")}>{p(r.active ? "active" : "inactive")}</span>
          {r.current ? <span className="rounded-full bg-info-subtle px-2 py-0.5 text-xs font-semibold text-info">{p("current")}</span> : null}
        </div>
      ),
    },
    { key: "updated", label: p("col.updated"), render: (r) => (<span className="text-xs">{r.updated_by ?? "—"}<div className="text-foreground-muted">{stamp(r.updated_at)}</div></span>) },
    ...(canManage ? [{ key: "edit", label: "", render: (r) => <Button size="sm" variant="outline" onClick={() => setEditing(r)}><Pencil /> {p("btn.edit")}</Button> }] : []),
  ];

  return (
    <>
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <Field label={p("f.section")}>
          <NativeSelect value={section} onChange={setSection} options={["SEWING", "FINISHING"].map((s) => ({ value: s, label: p(`section.${s}`) }))} placeholder={p("all_sections")} />
        </Field>
        <Field label={p("f.search_line")} className="min-w-56 flex-1">
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={p("f.line_code_name")} />
        </Field>
        {canManage ? <Button onClick={() => setEditing({})}><Plus /> {p("btn.add_capacity")}</Button> : null}
      </div>
      {data.without.length ? (
        <Panel title={p("capacity.without_title")} subtitle={p("capacity.without_hint")} icon={CircleAlert} className="mb-4">
          <div className="flex flex-wrap gap-2 px-5 py-4">
            {data.without.map((l) => (
              <span key={l.id} className="rounded-md bg-warning-subtle px-2 py-1 text-xs font-semibold text-warning">
                {l.label} · {p(`section.${l.section}`)}
              </span>
            ))}
          </div>
        </Panel>
      ) : null}
      <Panel title={p("capacity.title")} subtitle={p("capacity.count", { n: rows.length, of: data.rows.length })} icon={Gauge}>
        <Table columns={cols} rows={rows} empty={p("empty.capacities")} />
      </Panel>
      {editing ? <CapacityForm row={editing.id ? editing : null} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
    </>
  );
}

function CapacityForm({ row, lookups, act, onClose }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const today = lookups.today;
  const [v, setV] = useState(() => ({
    section: row?.section ?? "SEWING", line_id: row?.line_id ?? "", capacity_type: row?.capacity_type ?? "PER_HOUR", standard_capacity: row?.standard_capacity ?? "",
    default_efficiency: row?.default_efficiency ?? "", includes_efficiency: row?.includes_efficiency ?? false, effective_from: row?.effective_from ?? today,
    effective_to: row?.effective_to ?? "", active: row?.active ?? true, remarks: row?.remarks ?? "",
  }));
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const lines = v.section === "FINISHING" ? lookups.finishing_lines : lookups.sewing_lines;
  const err = (k) => errors[k]?.[0];
  const save = () =>
    start(async () => {
      setErrors({});
      const body = { ...v, default_efficiency: v.default_efficiency === "" ? null : Number(v.default_efficiency), effective_to: v.effective_to || null, remarks: v.remarks || null };
      const res = await act("saveCapacity", body, row?.id);
      if (res?.status === "success") {
        toast.add({ title: p("toast.capacity_saved"), type: "success" });
        onClose();
        router.refresh();
      } else {
        setErrors(res?.errors ?? {});
        toast.add({ title: res?.message ?? p("error"), type: "danger" });
      }
    });
  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p(row ? "capacity.edit_title" : "capacity.add_title")}
      className="max-w-2xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>{p("btn.close")}</Button>
          <Button loading={pending} onClick={save}>{p("btn.save")}</Button>
        </>
      }
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={p("f.section")}>
          <NativeSelect value={v.section} onChange={(s) => setV((all) => ({ ...all, section: s, line_id: "" }))} options={["SEWING", "FINISHING"].map((s) => ({ value: s, label: p(`section.${s}`) }))} />
        </Field>
        <Field label={p("f.line")} required error={err("line_id")}>
          <NativeSelect value={v.line_id} onChange={set("line_id")} options={lines} placeholder={p("f.select_line")} />
        </Field>
        <fieldset className="sm:col-span-2">
          <legend className="mb-1.5 text-[13px] font-semibold text-foreground">{p("f.capacity_type")} <span className="text-danger">*</span></legend>
          <div className="flex gap-4 text-sm">
            {["PER_HOUR", "PER_DAY"].map((t) => (
              <label key={t} className="inline-flex items-center gap-2">
                <input type="radio" name="capacity_type" checked={v.capacity_type === t} onChange={() => set("capacity_type")(t)} /> {p(`capacity.${t}_long`)}
              </label>
            ))}
          </div>
        </fieldset>
        <Field label={p(v.capacity_type === "PER_HOUR" ? "f.standard_hourly" : "f.standard_daily")} required error={err("standard_capacity")}>
          <Input type="number" min="0" step="0.01" value={v.standard_capacity} onChange={(e) => set("standard_capacity")(e.target.value)} />
        </Field>
        <Field label={p("f.default_efficiency")} error={err("default_efficiency")}>
          <Input type="number" min="0" step="0.1" value={v.default_efficiency} onChange={(e) => set("default_efficiency")(e.target.value)} />
        </Field>
        <label className="inline-flex items-start gap-2 text-sm sm:col-span-2">
          <input type="checkbox" checked={v.includes_efficiency} onChange={(e) => set("includes_efficiency")(e.target.checked)} className="mt-1" />
          <span>
            {p("f.includes_efficiency")}
            <span className="block text-xs text-foreground-muted">{p("capacity.includes_hint")}</span>
          </span>
        </label>
        <Field label={p("f.effective_from")} required error={err("effective_from")}>
          <Input type="date" value={v.effective_from} onChange={(e) => set("effective_from")(e.target.value)} />
        </Field>
        <Field label={p("f.effective_to")} error={err("effective_to")}>
          <Input type="date" value={v.effective_to} onChange={(e) => set("effective_to")(e.target.value)} />
        </Field>
        <label className="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" checked={v.active} onChange={(e) => set("active")(e.target.checked)} /> {p("active")}
        </label>
        <Field label={p("f.remarks")} className="sm:col-span-2">
          <Textarea value={v.remarks} onChange={(e) => set("remarks")(e.target.value)} rows={2} />
        </Field>
      </div>
    </Modal>
  );
}

export { LineCapacities };
