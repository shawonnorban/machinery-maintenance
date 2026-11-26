"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Ban, Factory, FileSpreadsheet, Layers, Plus, Printer, RotateCcw, Search, Send } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { Field, Group, EditorFoot, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

/*
 * Finishing Line → DF Production (AnnotechERP 18/02): the machine-wise
 * production sheet (one editor per line), the register of sheets with Post
 * and Delete, and the Process-Wise Delivery board.
 */

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const blank = (v) => (v === "" || v === undefined ? null : v);
const numOrNull = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const SHIFTS = ["DAY", "NIGHT"];
const STAGES = ["AWAITING_LOAD", "DYEING_RUNNING", "DEWATERING", "DRYING", "HEAT_SET", "STENTERING", "COMPACTING", "CALENDERING", "BRUSHING", "QC_INSPECTION", "READY_FOR_DELIVERY", "DELIVERED", "ON_HOLD", "AT_SUB_CONTRACT"];
const DESK_STAGES = ["AT_SUB_CONTRACT", "DELIVERED"];
const fits = (dia, line) => dia === "BOTH" || dia === line;
/** "3h 05m" from minutes, or a dash. */
const spent = (mins) => (mins === null || mins === undefined ? "—" : `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2, "0")}m`);

/**
 * Machine-Wise Production Entry, one line's tab (Open or Tube): the sheet's
 * header, then one row per batch through one machine on one process — the
 * batch from the cards on the floor that go through this line, the machine
 * and process from what the Machine-Process Mapping allows on it. Save Sheet
 * keeps it a draft; a posted sheet reads only.
 *
 * @param {{ line: "OPEN"|"TUBE", sheets: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ProductionSheetEditor({ line, sheets, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("finishing");
  const router = useRouter();
  const c = (k, p) => t(`prod.${k}`, p);
  const fresh = () => ({ sheet_no: null, status: "DRAFT", production_date: today(), company: options.companies[0]?.value ?? "", floor_id: "", shift: "", remarks: "", lines: [] });
  const toForm = (s) => ({
    sheet_no: s.sheet_no,
    status: s.status,
    production_date: s.production_date,
    company: options.companies[0]?.value ?? "",
    floor_id: s.floor_id ?? "",
    shift: s.shift,
    remarks: s.remarks ?? "",
    lines: s.lines.map((l) => ({ ...l, ...Object.fromEntries(["input_kg", "output_kg", "rolls", "fin_gsm", "fin_dia", "load_time", "unload_time", "remarks"].map((k) => [k, l[k] === null || l[k] === undefined ? "" : String(l[k])])) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "sheet" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.status === "DRAFT";
  const none = { receipt_id: "", machine_id: "", process_id: "", input_kg: "", output_kg: "", rolls: "", fin_gsm: "", fin_dia: "", load_time: "", unload_time: "", remarks: "" };
  const e = entry ?? none;

  const onLine = options.mappings.filter((m) => fits(m.dia_type, line));
  const mapped = (machine, process) => onLine.some((m) => m.machine_id === machine && m.process_id === process);
  const cards = options.cards.filter((x) => fits(x.dia_type, line));
  const machines = options.machines.filter((m) => onLine.some((x) => x.machine_id === m.value));
  const processes = options.processes.filter((p) => onLine.some((x) => x.process_id === p.value && (!e.machine_id || x.machine_id === e.machine_id)));

  function add() {
    if (!e.receipt_id || !e.machine_id || !e.process_id || !(Number(e.input_kg) > 0)) return warn(c("sheet.line_incomplete"));
    if (!mapped(e.machine_id, e.process_id)) return warn(c("sheet.not_mapped"));
    const label = (list, v) => list.find((x) => x.value === v)?.label ?? "";
    setForm((f) => ({ ...f, lines: [...f.lines, { ...e, batch: label(options.cards, e.receipt_id), machine: label(options.machines, e.machine_id), process: label(options.processes, e.process_id) }] }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.sheet_no, {
        ...(form.sheet_no ? {} : { line }),
        production_date: blank(form.production_date),
        floor_id: blank(form.floor_id),
        shift: blank(form.shift),
        remarks: blank(form.remarks),
        lines: form.lines.map((l) => ({
          receipt_id: l.receipt_id,
          machine_id: l.machine_id,
          process_id: l.process_id,
          input_kg: numOrNull(l.input_kg),
          output_kg: numOrNull(l.output_kg) ?? 0,
          rolls: numOrNull(l.rolls),
          fin_gsm: numOrNull(l.fin_gsm),
          fin_dia: numOrNull(l.fin_dia),
          load_time: blank(l.load_time),
          unload_time: blank(l.unload_time),
          remarks: blank(l.remarks),
        })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.sheet));
        toastManager.add({ title: c("sheet.saved", { no: res.sheet.sheet_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const mine = sheets.filter((s) => s.line === line);
  const input = form.lines.reduce((s, l) => s + (Number(l.input_kg) || 0), 0);
  const output = form.lines.reduce((s, l) => s + (Number(l.output_kg) || 0), 0);
  const noChoices = (list) => (list.length ? null : c("sheet.no_choices"));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold">
              <Layers className="size-4" /> {c(`sheet.title_${line}`)}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{c(`sheet.hint_${line}`)}</p>
          </div>
          <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.status === "POSTED" ? c("sheet.posted_badge") : form.sheet_no ? c("ed.editing") : c("ed.new_badge")}</span>
        </div>
        <Field label={c("sheet.existing")} hint={c("sheet.existing_hint")}>
          <Select value={form.sheet_no ?? ""} onValueChange={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))} options={[{ value: "", label: c("sheet.new_option") }, ...mine.map((s) => ({ value: s.sheet_no, label: `${s.sheet_no} · ${s.production_date} · ${c(`opt.shift.${s.shift}`)}` }))]} placeholder={c("sheet.new_option")} />
        </Field>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={c("sheet.date")} required>
            <Input type="date" value={form.production_date} onChange={(ev) => set("production_date", ev.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("sheet.company")} required>
            <Select value={form.company} onValueChange={(v) => set("company", v ?? "")} options={options.companies} placeholder={c("sheet.select_company")} disabled={!editable} />
          </Field>
          <Field label={c("sheet.floor")}>
            <Select value={form.floor_id} onValueChange={(v) => set("floor_id", v ?? "")} options={[{ value: "", label: c("sheet.select_floor") }, ...options.floors]} placeholder={c("sheet.select_floor")} disabled={!editable} />
          </Field>
          <Field label={c("sheet.shift")} required>
            <Select value={form.shift} onValueChange={(v) => set("shift", v ?? "")} options={SHIFTS.map((s) => ({ value: s, label: c(`opt.shift.${s}`) }))} placeholder={c("sheet.select_shift")} disabled={!editable} />
          </Field>
          <Field label={c("sheet.line")} hint={c("sheet.line_hint")}>
            <Input value={c(`opt.line.${line}`)} readOnly disabled />
          </Field>
          <Field label={c("sheet.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={3} disabled={!editable} />
          </Field>
        </div>
      </div>

      <LinesCard
        c={c}
        title={c("sheet.lines")}
        hint={c("sheet.lines_hint")}
        cols={[
          { key: "receipt_id", label: c("sheet.batch"), type: "item", lineType: "readonly", labelKey: "batch", options: cards, placeholder: c("sheet.batch_ph"), width: "w-72", onPick: (v) => ({ input_kg: String(cards.find((x) => x.value === v)?.qty ?? ""), rolls: String(cards.find((x) => x.value === v)?.rolls ?? "") }) },
          { key: "machine_id", label: c("sheet.machine"), type: "item", lineType: "readonly", labelKey: "machine", options: machines, placeholder: c("sheet.machine_ph"), onPick: (v) => (e.process_id && !mapped(v, e.process_id) ? { process_id: "" } : {}) },
          { key: "process_id", label: c("sheet.process"), type: "item", lineType: "readonly", labelKey: "process", options: processes, placeholder: c("sheet.process_ph") },
          { key: "input_kg", label: c("sheet.input"), type: "number" },
          { key: "output_kg", label: c("sheet.output"), type: "number" },
          { key: "rolls", label: c("sheet.rolls"), type: "number" },
          { key: "fin_gsm", label: c("sheet.gsm"), type: "number" },
          { key: "fin_dia", label: c("sheet.dia"), type: "number" },
          { key: "load_time", label: c("sheet.load"), type: "datetime" },
          { key: "unload_time", label: c("sheet.unload"), type: "datetime" },
          { key: "remarks", label: c("sheet.line_remarks"), type: "text" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("sheet.no_lines_hint")}
      />
      {editable && entry ? (
        <div className="-mt-2 flex flex-wrap gap-x-8 gap-y-1 px-1 text-xs text-foreground-muted">
          {noChoices(cards) ? <span>{c("sheet.batch")}: {noChoices(cards)}</span> : null}
          {noChoices(machines) ? <span>{c("sheet.machine")}: {noChoices(machines)}</span> : null}
        </div>
      ) : null}
      {form.lines.length ? (
        <p className="px-1 text-[13px] text-foreground-muted tabular-nums">
          {c("sheet.totals", { in: kg(input), out: kg(output), loss: kg(input - output), pct: input > 0 ? (((input - output) / input) * 100).toFixed(2) : "0.00" })}
        </p>
      ) : null}
      <EditorFoot c={c} backHref={backHref} note={form.status === "POSTED" ? c("sheet.footer_posted") : c("sheet.footer")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("sheet.save")} />
    </div>
  );
}

/**
 * The Production Sheets register: every sheet with its batches, what went in
 * and came off and the loss; Edit (View once posted) opens it on its line's
 * tab, Post and Delete — drafts only — ask first.
 *
 * @param {{ rows: object[], total: number, canManage: boolean, post: Function, remove: Function, base: string }} props
 */
function SheetRegister({ rows, total, canManage, post, remove, base }) {
  const t = useT("finishing");
  const router = useRouter();
  const toastManager = useToastManager();
  const c = (k, p) => t(`prod.${k}`, p);
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();

  function go() {
    const { kind, row } = asking;
    startTransition(async () => {
      const res = await (kind === "post" ? post : remove)(row.sheet_no);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: c(kind === "post" ? "reg.posted" : "reg.deleted", { no: row.sheet_no }), type: "success" });
        router.refresh();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  const shown = rows.map((r) => ({ ...r, line_label: c(`opt.line.${r.line}`), shift_label: c(`opt.shift.${r.shift}`), status_label: c(`opt.status.${r.status}`), loss_pct_label: `${Number(r.loss_pct).toFixed(2)}%` }));
  const draft = (row) => row.status !== "DRAFT";

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-3">
        <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{c("reg.summary", { count: total })}</p>
        {canManage ? (
          <Link href={`${base}?tab=open`} className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {c("reg.new")}
          </Link>
        ) : null}
      </div>
      <RecordsGrid
        exportName="df-production-sheets"
        rows={shown}
        rowKey="sheet_no"
        emptyState={{ title: c("reg.empty"), text: c("reg.empty_hint") }}
        columns={[
          { key: "sheet_no", label: c("col.sheet_no"), type: "mono" },
          { key: "production_date", label: c("col.date"), type: "date" },
          { key: "line_label", label: c("col.line") },
          { key: "shift_label", label: c("col.shift") },
          { key: "floor", label: c("col.floor") },
          { key: "batches", label: c("col.batches"), type: "number", align: "right" },
          { key: "input_kg", label: c("col.input_kg"), type: "number", align: "right" },
          { key: "output_kg", label: c("col.output_kg"), type: "number", align: "right" },
          { key: "loss_kg", label: c("col.loss_kg"), type: "number", align: "right" },
          { key: "loss_pct_label", label: c("col.loss_pct"), align: "right" },
          { key: "status_label", label: c("col.status"), type: "badge" },
        ]}
        rowActions={[
          { key: "open", label: c("reg.edit"), onClick: (row) => router.push(`${base}?tab=${row.line.toLowerCase()}&doc=${encodeURIComponent(row.sheet_no)}`) },
          ...(canManage
            ? [
                { key: "post", label: c("reg.post"), icon: <Send />, onClick: (row) => setAsking({ kind: "post", row }), disabled: draft },
                { key: "delete", label: c("reg.delete"), icon: <Ban />, onClick: (row) => setAsking({ kind: "delete", row }), disabled: draft },
              ]
            : []),
        ]}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? c(asking.kind === "post" ? "reg.post_title" : "reg.delete_title", { no: asking.row.sheet_no }) : ""}
        description={asking ? c(asking.kind === "post" ? "reg.post_text" : "reg.delete_text", { no: asking.row.sheet_no }) : ""}
        confirmLabel={asking ? c(asking.kind === "post" ? "reg.post" : "reg.delete") : ""}
        cancelLabel={c("reg.keep")}
        destructive={asking?.kind === "delete"}
        loading={pending}
        onConfirm={go}
      />
    </div>
  );
}

/**
 * Process-Wise Delivery: the Search card (Show reads the floor as asked —
 * the boxes live in the url; Excel and Pdf write the same rows; S/C Prod.
 * and BT Trans. open their desks once those are built), the On Floor / On
 * Hold / Ready counts (each narrows the grid), and the floor itself — each
 * batch passed on to its next stage by Deliver.
 *
 * @param {{ data: { rows: object[], tiles: object, choices: object }, query: object, options: object, canManage: boolean, deliver: Function, base: string, links: { sc?: string, bt?: string } }} props
 */
function DeliveryBoard({ data, query, options, canManage, deliver, base, links }) {
  const t = useT("finishing");
  const router = useRouter();
  const toastManager = useToastManager();
  const c = (k, p) => t(`prod.${k}`, p);
  const keys = ["dia", "stage", "booking", "buyer", "style", "order", "colour", "batch"];
  const [box, setBox] = useState(() => Object.fromEntries(keys.map((k) => [k, query[k] ?? ""])));
  const [company, setCompany] = useState(options.companies[0]?.value ?? "");
  const [tile, setTile] = useState(null);
  const [moving, setMoving] = useState(null);
  const [to, setTo] = useState("");
  const [remarks, setRemarks] = useState("");
  const [pending, startTransition] = useTransition();
  const qs = new URLSearchParams(Object.entries(query).filter(([k, v]) => keys.includes(k) && v)).toString();
  const opt = (group, values) => values.map((v) => ({ value: v, label: c(`opt.${group}.${v}`) }));
  const list = (values) => values.map((v) => ({ value: v, label: v }));
  const pick = (k) => (v) => setBox((b) => ({ ...b, [k]: v ?? "" }));

  function show() {
    const p = new URLSearchParams(Object.entries(box).filter(([, v]) => v));
    router.push(p.toString() ? `${base}?${p}` : base);
  }

  function reset() {
    setBox(Object.fromEntries(keys.map((k) => [k, ""])));
    setTile(null);
    router.push(base);
  }

  function move() {
    if (!to) return toastManager.add({ title: c("v.to_stage"), type: "danger" });
    startTransition(async () => {
      const res = await deliver(moving.receipt_no, { to_stage: to, remarks: blank(remarks) });
      if (res?.status === "success") {
        toastManager.add({ title: c("board.done", { batch: moving.batch_no, stage: c(`opt.stage.${to}`) }), type: "success" });
        setMoving(null);
        router.refresh();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  const inTile = (r) => !tile || (tile === "on_hold" ? r.stage === "ON_HOLD" : tile === "ready" ? r.stage === "READY_FOR_DELIVERY" : !["ON_HOLD", "READY_FOR_DELIVERY", "DELIVERED"].includes(r.stage));
  const rows = data.rows.filter(inTile).map((r) => ({
    ...r,
    dia_label: c(`opt.dia.${r.dia_type}`),
    booking_label: c(`opt.booking.${r.booking_type}`),
    stage_label: c(`opt.stage.${r.stage}`),
    last_load: r.last_load_at ? new Date(r.last_load_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—",
    spent_label: spent(r.spent_minutes),
  }));
  const tiles = [
    ["on_floor", c("board.on_floor"), data.tiles.on_floor, "bg-brand-subtle text-brand"],
    ["on_hold", c("board.on_hold"), data.tiles.on_hold, "bg-warning/15 text-warning"],
    ["ready", c("board.ready"), data.tiles.ready, "bg-success/15 text-success"],
  ];
  const toOptions = moving ? opt("stage", STAGES.filter((s) => !DESK_STAGES.includes(s) && s !== moving.stage)) : [];
  const linkBtn = cn(buttonVariants({ variant: "outline" }));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold">
            <Search className="size-4" /> {c("board.search")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{c("board.hint")}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={c("f.company")}>
            <Select value={company} onValueChange={(v) => setCompany(v ?? "")} options={options.companies} placeholder={c("f.select_company")} />
          </Field>
          <Field label={c("f.dia_type")}>
            <Select value={box.dia} onValueChange={pick("dia")} options={[{ value: "", label: c("f.select_dia") }, ...opt("dia", ["OPEN", "TUBE", "BOTH"])]} placeholder={c("f.select_dia")} />
          </Field>
          <Field label={c("f.stage")}>
            <Select value={box.stage} onValueChange={pick("stage")} options={[{ value: "", label: c("f.select_wip") }, ...opt("stage", STAGES)]} placeholder={c("f.select_wip")} />
          </Field>
          <Field label={c("f.booking_type")}>
            <Select value={box.booking} onValueChange={pick("booking")} options={[{ value: "", label: c("f.select_type") }, ...opt("booking", ["IN_HOUSE", "SUB_CONTRACT", "SAMPLE"])]} placeholder={c("f.select_type")} />
          </Field>
          <Field label={c("f.buyer")} hint={options.buyers.length ? null : c("sheet.no_choices")}>
            <Select value={box.buyer} onValueChange={pick("buyer")} options={[{ value: "", label: c("f.select_buyer") }, ...options.buyers]} placeholder={c("f.select_buyer")} />
          </Field>
          <Field label={c("f.style")}>
            <Select value={box.style} onValueChange={pick("style")} options={[{ value: "", label: c("f.select_style") }, ...list(data.choices.styles)]} placeholder={c("f.select_style")} />
          </Field>
          <Field label={c("f.order")}>
            <Select value={box.order} onValueChange={pick("order")} options={[{ value: "", label: c("f.select_order") }, ...list(data.choices.orders)]} placeholder={c("f.select_order")} />
          </Field>
          <Field label={c("f.colour")}>
            <Select value={box.colour} onValueChange={pick("colour")} options={[{ value: "", label: c("f.select_colour") }, ...list(data.choices.colours)]} placeholder={c("f.select_colour")} />
          </Field>
          <Field label={c("f.batch_search")}>
            <Input value={box.batch} onChange={(ev) => pick("batch")(ev.target.value)} onKeyDown={(ev) => ev.key === "Enter" && show()} placeholder={c("f.search_ph")} />
          </Field>
        </div>
        <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
          <Button onClick={show}>
            <Search /> {c("board.show")}
          </Button>
          {data.rows.length ? (
            <a href={`/api/finishing/delivery-board/xlsx${qs ? `?${qs}` : ""}`} className={linkBtn}>
              <FileSpreadsheet /> {c("board.excel")}
            </a>
          ) : (
            <Button variant="outline" disabled>
              <FileSpreadsheet /> {c("board.excel")}
            </Button>
          )}
          <a href={`/api/finishing/delivery-board/pdf${qs ? `?${qs}` : ""}`} target="_blank" rel="noopener" className={linkBtn}>
            <Printer /> {c("board.pdf")}
          </a>
          {links.sc ? (
            <Link href={links.sc} className={linkBtn}>
              <Printer /> {c("board.sc")}
            </Link>
          ) : null}
          {links.bt ? (
            <Link href={links.bt} className={linkBtn}>
              <Printer /> {c("board.bt")}
            </Link>
          ) : null}
          <Button variant="outline" onClick={reset} className="ml-auto">
            <RotateCcw /> {c("board.reset")}
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-4">
        <div className="flex flex-wrap gap-2">
          {tiles.map(([key, label, count, tone]) => (
            <button key={key} type="button" onClick={() => setTile(tile === key ? null : key)} className={cn("inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium", tile === key ? "border-brand bg-brand-subtle" : "border-border")} aria-pressed={tile === key}>
              {label} <span className={cn("rounded-full px-1.5 text-xs font-semibold tabular-nums", tone)}>{count}</span>
            </button>
          ))}
        </div>
        <RecordsGrid
          exportName="process-wise-delivery"
          rows={rows}
          rowKey="receipt_no"
          emptyState={{ title: c("board.empty"), text: c("board.empty_hint") }}
          columns={[
            { key: "batch_no", label: c("col.batch_no"), type: "mono" },
            { key: "receipt_no", label: c("col.receipt_no"), type: "mono" },
            { key: "buyer", label: c("col.buyer") },
            { key: "style", label: c("col.style") },
            { key: "order_no", label: c("col.order_no") },
            { key: "colour", label: c("col.colour") },
            { key: "dia_label", label: c("col.dia_type") },
            { key: "booking_label", label: c("col.booking") },
            { key: "recv_qty_kg", label: c("col.recv_qty_kg"), type: "number", align: "right" },
            { key: "recv_rolls", label: c("col.recv_rolls"), type: "number", align: "right" },
            { key: "stage_label", label: c("col.stage"), type: "badge" },
            { key: "last_process", label: c("col.last_process") },
            { key: "last_machine", label: c("col.last_machine") },
            { key: "last_load", label: c("col.last_load") },
            { key: "spent_label", label: c("col.spent"), align: "right" },
          ]}
          rowActions={
            canManage
              ? [
                  {
                    key: "deliver",
                    label: c("board.deliver"),
                    icon: <Factory />,
                    onClick: (row) => {
                      setMoving(row);
                      setTo("");
                      setRemarks("");
                    },
                    disabled: (row) => DESK_STAGES.includes(row.stage),
                  },
                ]
              : undefined
          }
        />
      </div>

      <Modal
        open={moving !== null}
        onOpenChange={(o) => !o && setMoving(null)}
        title={moving ? c("board.deliver_title", { batch: moving.batch_no }) : ""}
        description={moving ? c("board.deliver_hint", { stage: c(`opt.stage.${moving.stage}`) }) : ""}
        footer={
          <>
            <Button variant="outline" onClick={() => setMoving(null)}>
              {c("board.cancel")}
            </Button>
            <Button onClick={move} loading={pending}>
              <Send /> {c("board.deliver")}
            </Button>
          </>
        }
      >
        {moving ? (
          <div className="flex flex-col gap-4">
            <Field label={c("board.now")}>
              <Input value={c(`opt.stage.${moving.stage}`)} readOnly disabled />
            </Field>
            <Field label={c("board.to")} required>
              <Select value={to} onValueChange={(v) => setTo(v ?? "")} options={toOptions} placeholder={c("f.select_wip")} />
            </Field>
            <Field label={c("board.remarks")}>
              <Input value={remarks} onChange={(ev) => setRemarks(ev.target.value)} maxLength={255} />
            </Field>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

export { ProductionSheetEditor, SheetRegister, DeliveryBoard };
