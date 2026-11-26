"use client";

import { useRef, useState, useTransition } from "react";
import Link from "next/link";
import { ArrowLeft, Check, Layers, Lock, Paperclip, Plus, RotateCcw, Save, Send, Trash2, Undo2, Upload, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qtyFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
// Brickland's defaults: USD at 1, today's USD rate, a physical count.
const blankHead = (date, usd) => ({ unit_company_id: "", store_id: "", ob_date: date, order_id: "", currency: "USD", conv_rate: "1", usd_bdt: str(usd), source: "PHYSICAL_COUNT", reference_no: "", attachment: "", remarks: "" });
const headOf = (data, date, usd) => Object.fromEntries(Object.entries(blankHead(date, usd)).map(([k, v]) => [k, data?.[k] === null || data?.[k] === undefined ? (k === "usd_bdt" ? "" : v) : str(data[k])]));
const blankRow = () => ({ category: "PURCHASE", description: "", fabric_item: "", colour_id: "", fabric_type_id: "", fabric_group: "", composition_id: "", gsm: "", dia: "", dia_uom: "INCH", dia_type: "OPEN", batch_no: "", rolls: "", roll_nos: "", uom: "KG", qty: "", rate: "" });
let seq = 0;
const asLine = (l = {}) => ({ key: `f${++seq}`, ...Object.fromEntries(Object.entries(blankRow()).map(([k, v]) => [k, l[k] === null || l[k] === undefined ? (l === undefined ? v : "") : str(l[k])])) });
const FLOW = { DRAFT: ["submit"], SUBMITTED: ["approve", "reject"], APPROVED: ["post"], POSTED: ["unpost", "lock"], LOCKED: [] };
const CONFIRM = ["reject", "post", "unpost", "lock"];
const ICON = { submit: Send, approve: Check, reject: X, post: Upload, unpost: Undo2, lock: Lock };

/**
 * Purchased Fabric Opening Balance → Opening Balance Entry (AnnotechERP
 * 06/06), as Brickland lays it out: the existing-balance picker; Header;
 * Source & Reference with an attachment; and Fabric Details — one batch
 * described in the form above, Add Row filing it in the grid below, where it
 * stays editable. Save keeps a draft; the workflow then moves it Submitted →
 * Approved → Posted → Locked (a submitted one may be sent back, a posted one
 * unposted until locked). Only a draft is edited. Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, canManage: boolean, canApprove: boolean, actions: object, recordsHref: string }} props
 */
function FabricObEntry({ options, initial, canManage, canApprove, actions, recordsHref }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.fob.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [ob, setOb] = useState(initial);
  const [head, setHead] = useState(() => (initial ? headOf(initial, today, options.usd_bdt) : blankHead(today, options.usd_bdt)));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map((l) => asLine(l)));
  const [row, setRow] = useState(blankRow);
  const [rowError, setRowError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const fileRef = useRef(null);
  const editable = canManage && (!ob || ob.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const coded = (group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
  const plain = (values) => values.map((v) => ({ value: v, label: v }));
  const toBdt = head.currency === "BDT" ? 1 : num(head.currency === "USD" ? 1 : head.conv_rate) * num(head.usd_bdt);
  const totalQty = lines.reduce((n, l) => n + num(l.qty), 0);
  const totalCost = lines.reduce((n, l) => n + num(l.qty) * num(l.rate) * toBdt, 0);
  const can = (action) => (["approve", "reject", "lock"].includes(action) ? canApprove : canManage);

  function addRow() {
    // Brickland names the first field the row still needs.
    const need = ["category", "fabric_item", "fabric_group", "uom"].find((k) => !row[k].trim());
    if (need) return setRowError(m("row_needed", { field: m(`f.${need}`) }));
    if (num(row.qty) <= 0) return setRowError(m("row_needed", { field: m("f.qty") }));
    setLines((all) => [...all, asLine(row)]);
    setRow((r) => ({ ...blankRow(), category: r.category, uom: r.uom, dia_uom: r.dia_uom, dia_type: r.dia_type }));
    setRowError(null);
  }

  function take(data) {
    setOb(data);
    setHead(headOf(data, today, options.usd_bdt));
    setLines(data.lines.map((l) => asLine(l)));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.ob_no)}`);
  }

  function clear() {
    setOb(null);
    setHead(blankHead(today, options.usd_bdt));
    setLines([]);
    setRow(blankRow());
    setErrors(null);
    setRowError(null);
    window.history.replaceState(null, "", "?tab=entry");
  }

  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
    });
  }

  function save() {
    startTransition(async () => {
      const blank = (v) => (v === "" ? null : v);
      const number = (v) => (v === "" ? null : Number(v));
      const payload = {
        ...Object.fromEntries(Object.entries(head).map(([k, v]) => [k, blank(v)])),
        conv_rate: number(head.conv_rate),
        usd_bdt: number(head.usd_bdt),
        lines: lines.map(({ key, ...l }) => ({ ...Object.fromEntries(Object.entries(l).map(([k, v]) => [k, blank(v)])), gsm: number(l.gsm), dia: number(l.dia), rolls: number(l.rolls), qty: number(l.qty), rate: number(l.rate) })),
      };
      const result = await actions.save(ob?.ob_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m("saved", { no: result.data.ob_no }), type: "success" });
    });
  }

  function run(action) {
    startTransition(async () => {
      const result = action === "delete" ? await actions.remove(ob.ob_no) : await actions.transition(ob.ob_no, action);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: action === "delete" ? m("deleted", { no: ob.ob_no }) : m(`done.${action}`, { no: ob.ob_no }), type: "success" });
      if (action === "delete") clear();
      else take(result.data);
    });
  }

  async function attach(file) {
    if (!file || !ob) return;
    const body = new FormData();
    body.append("file", file);
    const response = await fetch(`/api/merchandise/fabric-ob-attachment/${encodeURIComponent(ob.ob_no)}`, { method: "POST", body });
    const json = await response.json().catch(() => null);
    if (!response.ok) return setErrors(messagesOf(json));
    take(json.data);
    toastManager.add({ title: m("attached", { name: file.name }), type: "success" });
  }

  const card = "rounded-xl border border-border bg-surface";
  const group = "rounded-lg border border-border p-4";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const star = <span className="text-danger">*</span>;
  const rowInput = (key, type = "text", ph) => (
    <label className={field}>
      <span className={lab}>{m(`f.${key}`)}</span>
      <Input type={type} min={type === "number" ? "0" : undefined} step={type === "number" ? "any" : undefined} value={row[key]} onChange={(e) => setRow((r) => ({ ...r, [key]: e.target.value }))} placeholder={ph} disabled={!editable} aria-label={m(`f.${key}`)} />
    </label>
  );
  const rowSelect = (key, list) => (
    <div className={field}>
      <span className={lab}>{m(`f.${key}`)}</span>
      <Select value={row[key]} onValueChange={(v) => setRow((r) => ({ ...r, [key]: v ?? "" }))} options={pick(list)} placeholder={m("select")} disabled={!editable} aria-label={m(`f.${key}`)} />
    </div>
  );
  const cellSelect = (l, i, key, list, aria) => (
    <Select value={l[key]} onValueChange={(v) => setLine(l.key, { [key]: v ?? "" })} options={[{ value: "", label: "—" }, ...list]} disabled={!editable} aria-label={m(aria, { n: i + 1 })} />
  );
  const status = ob?.status ?? "DRAFT";
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <Layers className="size-4" /> {m("entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("entry_hint")}</p>
          </div>
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{ob ? m(`status.${status}`) : m("new")}</span>
        </div>
        <div className={cn(field, "mt-4")}>
          <span className={lab}>{m("existing")}</span>
          <Select value={ob?.ob_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_ob") }, ...options.balances]} aria-label={m("existing")} />
          <span className={hint}>{m("existing_hint")}</span>
        </div>

        <fieldset className={cn(group, "mt-4")}>
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("header")}</legend>
          <div className="grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>{m("f.ob_no")}</span>
              <Input value={ob?.ob_no ?? ""} placeholder={m("auto_gen")} readOnly className="bg-surface-muted" aria-label={m("f.ob_no")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.status")}</span>
              <Input value={ob ? m(`status.${status}`) : ""} placeholder={m("status.DRAFT")} readOnly className="bg-surface-muted" aria-label={m("f.status")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.unit_company_id")}
                {star}
              </span>
              <Select value={head.unit_company_id} onValueChange={(v) => setH({ unit_company_id: v ?? "" })} options={pick(options.companies)} disabled={!editable} aria-label={m("f.unit_company_id")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.store_id")}
                {star}
              </span>
              <Select value={head.store_id} onValueChange={(v) => setH({ store_id: v ?? "" })} options={pick(options.stores)} placeholder={m("select")} disabled={!editable} aria-label={m("f.store_id")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.ob_date")}
                {star}
              </span>
              <Input type="date" value={head.ob_date} onChange={(e) => setH({ ob_date: e.target.value })} disabled={!editable} aria-label={m("f.ob_date")} />
            </label>
            <div className={field}>
              <span className={lab}>{m("f.order_id")}</span>
              <Select value={head.order_id} onValueChange={(v) => setH({ order_id: v ?? "" })} options={pick(options.orders)} placeholder={m("select")} disabled={!editable} aria-label={m("f.order_id")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.currency")}
                {star}
              </span>
              <Select value={head.currency} onValueChange={(v) => setH({ currency: v ?? "", ...(v === "USD" ? { conv_rate: "1" } : {}) })} options={pick(plain(options.currencies))} disabled={!editable} aria-label={m("f.currency")} />
            </div>
            <label className={field}>
              <span className={lab}>{m("f.conv_rate")}</span>
              <Input type="number" min="0" step="any" value={head.conv_rate} onChange={(e) => setH({ conv_rate: e.target.value })} disabled={!editable || ["USD", "BDT"].includes(head.currency)} aria-label={m("f.conv_rate")} />
              <span className={hint}>{m("conv_hint")}</span>
            </label>
            <label className={field}>
              <span className={lab}>{m("f.usd_bdt")}</span>
              <Input type="number" min="0" step="any" value={head.usd_bdt} onChange={(e) => setH({ usd_bdt: e.target.value })} disabled={!editable || head.currency === "BDT"} aria-label={m("f.usd_bdt")} />
              <span className={hint}>{m("usd_hint")}</span>
            </label>
          </div>
        </fieldset>

        <fieldset className={cn(group, "mt-4")}>
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("source_ref")}</legend>
          <div className="grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>
                {m("f.source")}
                {star}
              </span>
              <Select value={head.source} onValueChange={(v) => setH({ source: v ?? "" })} options={pick(coded("source", options.sources))} disabled={!editable} aria-label={m("f.source")} />
            </div>
            <label className={field}>
              <span className={lab}>{m("f.reference_no")}</span>
              <Input value={head.reference_no} onChange={(e) => setH({ reference_no: e.target.value })} placeholder={m("reference_ph")} maxLength={80} disabled={!editable} aria-label={m("f.reference_no")} />
            </label>
            <div className={field}>
              <span className={lab}>{m("f.attachment")}</span>
              <div className="flex gap-2">
                <Input value={head.attachment} onChange={(e) => setH({ attachment: e.target.value })} placeholder={m("attachment_ph")} maxLength={255} disabled={!editable} aria-label={m("f.attachment")} />
                <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,image/heic,application/pdf" className="hidden" onChange={(e) => attach(e.target.files?.[0])} aria-label={m("attach")} />
                <Button variant="outline" onClick={() => fileRef.current?.click()} disabled={!editable || !ob} title={ob ? undefined : m("attach_save_first")}>
                  <Paperclip /> {m("attach")}
                </Button>
              </div>
              <span className={hint}>
                {ob?.attachment_file_id ? (
                  <a href={`/api/files/${ob.attachment_file_id}/download`} target="_blank" rel="noreferrer" className="text-brand hover:underline">
                    {ob.attachment}
                  </a>
                ) : (
                  m(ob ? "attach_hint" : "attach_save_first")
                )}
              </span>
            </div>
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.remarks")}</span>
              <Textarea rows={3} value={head.remarks} onChange={(e) => setH({ remarks: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.remarks")} />
            </label>
          </div>
        </fieldset>

        <fieldset className={cn(group, "mt-4")}>
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("details")}</legend>
          <p className={cn(hint, "mb-3")}>{m("details_hint")}</p>
          {editable ? (
            <>
              <div className="grid gap-4 md:grid-cols-4">
                {rowSelect("category", coded("category", options.categories))}
                {rowInput("description")}
                {rowInput("fabric_item")}
                {rowSelect("colour_id", options.colours)}
                {rowSelect("fabric_type_id", options.fabric_types)}
                {rowSelect("fabric_group", coded("group", options.groups))}
                {rowSelect("composition_id", options.compositions)}
                {rowInput("gsm", "number")}
                {rowInput("dia", "number")}
                {rowSelect("dia_uom", coded("dia_uom", options.dia_uoms))}
                {rowSelect("dia_type", coded("dia_type", options.dia_types))}
                {rowInput("batch_no")}
                {rowInput("rolls", "number")}
                <label className={field}>
                  <span className={lab}>{m("f.roll_nos")}</span>
                  <Input value={row.roll_nos} onChange={(e) => setRow((r) => ({ ...r, roll_nos: e.target.value }))} placeholder={m("roll_ph")} maxLength={500} aria-label={m("f.roll_nos")} />
                  <span className={hint}>{m("roll_hint")}</span>
                </label>
                {rowSelect("uom", coded("uom", options.uoms))}
                {rowInput("qty", "number")}
                {rowInput("rate", "number")}
              </div>
              {rowError ? <p className="mt-2 text-xs text-danger">{rowError}</p> : null}
              <div className="mt-3 flex gap-2">
                <Button variant="outline" size="sm" onClick={addRow}>
                  <Plus /> {m("add_row")}
                </Button>
                <Button variant="ghost" size="sm" onClick={() => (setRow(blankRow()), setRowError(null))}>
                  {m("cancel_row")}
                </Button>
              </div>
            </>
          ) : null}
        </fieldset>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["category", "description", "item", "colour", "type", "group", "composition", "batch", "roll", "qty", "uom", "rate", "currency", "conversion", "amount", "remove"].map((k) => (
                  <th key={k} className={cn("px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["qty", "rate", "amount", "roll"].includes(k) && "text-right")}>
                    {k === "remove" ? <span className="sr-only">{m("col.remove")}</span> : m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  <td className="px-2 py-1.5">
                    <div className="w-36">{cellSelect(l, i, "category", coded("category", options.categories), "row_category")}</div>
                  </td>
                  <td className="px-2 py-1.5">
                    <Input value={l.description} onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={200} className="h-8 w-36" disabled={!editable} aria-label={m("row_description", { n: i + 1 })} />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input value={l.fabric_item} onChange={(e) => setLine(l.key, { fabric_item: e.target.value })} maxLength={120} className="h-8 w-32" disabled={!editable} aria-label={m("row_item", { n: i + 1 })} />
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-40">{cellSelect(l, i, "colour_id", options.colours, "row_colour")}</div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-36">{cellSelect(l, i, "fabric_type_id", options.fabric_types, "row_type")}</div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-28">{cellSelect(l, i, "fabric_group", coded("group", options.groups), "row_group")}</div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-40">{cellSelect(l, i, "composition_id", options.compositions, "row_composition")}</div>
                  </td>
                  <td className="px-2 py-1.5">
                    <Input value={l.batch_no} onChange={(e) => setLine(l.key, { batch_no: e.target.value })} maxLength={60} className="h-8 w-28" disabled={!editable} aria-label={m("row_batch", { n: i + 1 })} />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input type="number" min="0" value={l.rolls} onChange={(e) => setLine(l.key, { rolls: e.target.value })} className="h-8 w-16 text-right" disabled={!editable} aria-label={m("row_rolls", { n: i + 1 })} />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input type="number" min="0" step="any" value={l.qty} onChange={(e) => setLine(l.key, { qty: e.target.value })} className="h-8 w-24 text-right" disabled={!editable} aria-label={m("row_qty", { n: i + 1 })} />
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-24">{cellSelect(l, i, "uom", coded("uom", options.uoms), "row_uom")}</div>
                  </td>
                  <td className="px-2 py-1.5">
                    <Input type="number" min="0" step="any" value={l.rate} onChange={(e) => setLine(l.key, { rate: e.target.value })} className="h-8 w-24 text-right" disabled={!editable} aria-label={m("row_rate", { n: i + 1 })} />
                  </td>
                  <td className="px-2 py-1.5">{head.currency}</td>
                  <td className="px-2 py-1.5 tabular-nums">{head.currency === "BDT" ? "—" : head.currency === "USD" ? "1" : head.conv_rate || "—"}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">{money.format(num(l.qty) * num(l.rate))}</td>
                  <td className="px-2 py-1.5 text-right">
                    {editable ? (
                      <Button variant="ghost" size="icon" className="size-8" aria-label={m("row_remove")} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                        <Trash2 />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
              {lines.length === 0 ? (
                <tr>
                  <td colSpan={16} className="px-5 py-8 text-center">
                    <p className="text-sm font-semibold text-foreground">{m("no_lines")}</p>
                    <p className="mt-1 text-xs text-foreground-muted">{m("no_lines_hint")}</p>
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        {lines.length ? <div className="border-t border-border px-5 py-3 text-sm font-semibold text-foreground">{m("total", { n: lines.length, qty: qtyFmt.format(totalQty), cost: money.format(totalCost) })}</div> : null}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <div>
          <Link href={recordsHref} className={buttonVariants({ variant: "outline", size: "sm" })}>
            <ArrowLeft /> {m("back")}
          </Link>
        </div>
        <p className="text-[13px] text-foreground-muted">{m("rules")}</p>
        <div className="flex flex-wrap gap-2">
          {ob && editable ? (
            <Button variant="outline" className="text-danger" onClick={() => setConfirm("delete")} disabled={pending}>
              <Trash2 /> {m("delete")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {m("clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {ob ? m("save_changes") : m("save")}
            </Button>
          ) : null}
          {ob
            ? FLOW[status]
                .filter(can)
                .map((action) => {
                  const Icon = ICON[action];
                  return (
                    <Button key={action} variant={action === "approve" || action === "post" ? "success" : "outline"} className={action === "reject" ? "text-danger" : undefined} onClick={() => (CONFIRM.includes(action) ? setConfirm(action) : run(action))} disabled={pending}>
                      <Icon /> {m(`do.${action}`)}
                    </Button>
                  );
                })
            : null}
        </div>
      </section>

      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? m(`confirm.${confirm}_title`) : ""}
        description={confirm ? m(`confirm.${confirm}_hint`) : ""}
        confirmLabel={confirm ? (confirm === "delete" ? m("delete") : m(`do.${confirm}`)) : ""}
        cancelLabel={m("cancel")}
        destructive={confirm === "delete" || confirm === "reject"}
        onConfirm={() => confirm && run(confirm)}
        loading={pending}
      />
    </div>
  );
}

export { FabricObEntry };
