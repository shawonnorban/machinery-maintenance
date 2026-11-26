"use client";

import { useState, useTransition } from "react";
import { usePathname, useRouter } from "next/navigation";
import Link from "next/link";
import { Database, Pencil, Plus, Scissors, Shirt, Sparkles, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { usePt, num, Panel, Kpi, Table } from "./common";
import { Field, NativeSelect } from "./plans";

/*
 * Production Tracking → Production Master Data (AnnotechERP 30/01): a day's
 * cutting, sewing and finishing figures — the Daily Production Report
 * columns — added, edited and deleted; All / Cutting / Sewing / Finishing.
 */

const SECTIONS = ["CUTTING", "SEWING", "FINISHING"];
const ICON = { CUTTING: Scissors, SEWING: Shirt, FINISHING: Sparkles };
const DASH = { CUTTING: "cutting-dashboard", SEWING: "sewing-dashboard", FINISHING: "finishing-dashboard" };

function ProductionEntries({ data, lookups, tab, canManage, act }) {
  const p = usePt();
  const router = useRouter();
  const pathname = usePathname();
  const toast = useToastManager();
  const [current, setCurrent] = useState(tab.toUpperCase() === "ALL" ? "ALL" : tab.toUpperCase());
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, start] = useTransition();
  const shown = current === "ALL" ? SECTIONS : [current];
  const go = (date) => router.replace(`${pathname}?date=${date}`, { scroll: false });

  const remove = () =>
    start(async () => {
      const res = await act("deleteEntry", deleting.id);
      if (res?.status === "success") {
        toast.add({ title: p("toast.entry_deleted"), type: "success" });
        setDeleting(null);
        router.refresh();
      } else toast.add({ title: res?.message ?? p("error"), type: "danger" });
    });

  const columns = (section) => [
    ...(section === "CUTTING" ? [] : [{ key: "line", label: p("col.line"), render: (r) => <b>{r.line ?? "—"}</b> }]),
    { key: "buyer_name", label: p("col.buyer") },
    { key: "style", label: p("col.style") },
    { key: "po_no", label: p("col.po") },
    { key: "target", label: p("col.target"), align: "right", render: (r) => num(r.target) },
    { key: "production", label: p(section === "CUTTING" ? "col.cut_ready" : "col.production"), align: "right", render: (r) => <b>{num(r.production)}</b> },
    ...(section === "CUTTING"
      ? [
          { key: "bundles_ready", label: p("col.bundles_ready"), align: "right", render: (r) => num(r.bundles_ready) },
          { key: "bundles_issued", label: p("col.bundles_issued"), align: "right", render: (r) => num(r.bundles_issued) },
        ]
      : [{ key: "rework", label: p("col.rework"), align: "right", render: (r) => num(r.rework) }]),
    { key: "reject", label: p("col.reject"), align: "right", render: (r) => num(r.reject) },
    ...(canManage
      ? [{ key: "x", label: "", render: (r) => (<div className="flex justify-end gap-1.5"><Button size="sm" variant="outline" onClick={() => setEditing(r)}><Pencil /> {p("btn.edit")}</Button><Button size="sm" variant="outline" onClick={() => setDeleting(r)}><Trash2 /> {p("btn.delete")}</Button></div>) }]
      : []),
  ];

  return (
    <>
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <Field label={p("f.date")}>
          <Input type="date" value={data.date} onChange={(e) => go(e.target.value)} className="w-44" />
        </Field>
        <div className="flex rounded-md border border-border-strong p-0.5">
          {["ALL", ...SECTIONS].map((s) => (
            <button key={s} type="button" onClick={() => setCurrent(s)} className={cn("rounded px-3 py-1.5 text-[13px] font-semibold", current === s ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}>
              {p(s === "ALL" ? "tabs.all" : `section.${s}`)}
            </button>
          ))}
        </div>
        <div className="flex-1" />
        {canManage
          ? shown.map((s) => (
              <Button key={s} variant="outline" onClick={() => setEditing({ section: s })}>
                <Plus /> {p(`btn.add_${s.toLowerCase()}`)}
              </Button>
            ))
          : null}
      </div>
      <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
        {SECTIONS.map((s) => (
          <Kpi key={s} label={`${p(`section.${s}`)} · ${data.date}`} value={num(data.totals[s].pcs)} sub={p("entry.kpi_sub", { n: data.totals[s].entries, target: num(data.totals[s].target) })} tone={s === "CUTTING" ? "success" : s === "SEWING" ? "info" : "brand"} icon={ICON[s]} />
        ))}
      </div>
      <div className="space-y-4">
        {shown.map((s) => {
          const rows = data.rows.filter((r) => r.section === s);
          return (
            <Panel key={s} title={p(`entry.${s}_title`)} subtitle={p("entry.rows_hint", { n: rows.length, dash: p(`section.${s}`) })} icon={Database} action={<Link href={`/production-tracking/${DASH[s]}`} className={buttonVariants({ variant: "outline", size: "sm" })}>{p(`entry.open_${s}`)}</Link>}>
              <Table columns={columns(s)} rows={rows} empty={p(`empty.${s}_entries`)} />
            </Panel>
          );
        })}
      </div>
      {editing ? <EntryForm row={editing.id ? editing : null} section={editing.section} date={data.date} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
      <ConfirmDialog open={Boolean(deleting)} onOpenChange={() => setDeleting(null)} title={p("entry.delete_title")} description={p("entry.delete_hint")} confirmLabel={p("btn.delete")} loading={pending} onConfirm={remove} />
    </>
  );
}

function EntryForm({ row, section, date, lookups, act, onClose }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const keys = ["buyer_name", "style", "po_no", "item", "order_qty", "target", "production", "bundles_ready", "bundles_issued", "reject", "rework", "smv", "manpower", "available_hours", "plan_hours", "run_day", "total_produced", "remarks"];
  const [v, setV] = useState(() => ({ entry_date: row?.entry_date ?? date, line_id: row?.line_id ?? "", ...Object.fromEntries(keys.map((k) => [k, row?.[k] ?? ""])) }));
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const lines = section === "FINISHING" ? lookups.finishing_lines : lookups.sewing_lines;
  const numbers = section === "CUTTING" ? ["order_qty", "target", "production", "bundles_ready", "bundles_issued", "reject"] : ["order_qty", "target", "production", "reject", "rework", "smv", "manpower", "available_hours", "plan_hours", "run_day", "total_produced"];
  const label = (k) => p(section === "CUTTING" && k === "production" ? "f.cut_pcs_ready" : `f.${k}`);
  const save = () =>
    start(async () => {
      setErrors({});
      const body = Object.fromEntries(Object.entries({ ...v, section }).map(([k, val]) => [k, val === "" ? null : val]));
      const res = await act("saveEntry", body, row?.id);
      if (res?.status === "success") {
        toast.add({ title: p("toast.entry_saved"), type: "success" });
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
      title={p(row ? `entry.edit_${section}` : `entry.add_${section}`)}
      description={section === "CUTTING" ? null : p("entry.form_hint")}
      className="max-w-3xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>{p("btn.close")}</Button>
          <Button loading={pending} onClick={save}>{p("btn.save")}</Button>
        </>
      }
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Field label={p("f.date")} required error={err("entry_date")}>
          <Input type="date" value={v.entry_date} onChange={(e) => set("entry_date")(e.target.value)} />
        </Field>
        {section === "CUTTING" ? null : (
          <Field label={p("f.line")} required error={err("line_id")} className="sm:col-span-2">
            <NativeSelect value={v.line_id} onChange={set("line_id")} options={lines} placeholder={p("f.select_line")} />
          </Field>
        )}
        {["buyer_name", "style", "po_no", "item"].map((k) => (
          <Field key={k} label={p(`f.${k}`)} error={err(k)}>
            <Input value={v[k]} onChange={(e) => set(k)(e.target.value)} />
          </Field>
        ))}
        {numbers.map((k) => (
          <Field key={k} label={label(k)} error={err(k)} hint={k === "available_hours" ? p("entry.available_hint") : k === "run_day" ? p("entry.run_day_hint") : k === "total_produced" ? p("entry.total_hint") : undefined}>
            <Input type="number" min="0" step={["smv", "available_hours", "plan_hours"].includes(k) ? "0.01" : "1"} value={v[k]} onChange={(e) => set(k)(e.target.value)} />
          </Field>
        ))}
        <Field label={p("f.remarks")} className="sm:col-span-3">
          <Textarea value={v.remarks} onChange={(e) => set("remarks")(e.target.value)} rows={2} />
        </Field>
      </div>
    </Modal>
  );
}

export { ProductionEntries };
