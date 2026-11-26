"use client";

import { useState, useTransition } from "react";
import { Layers, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
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
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qtyFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
// Brickland's defaults: USD at 1, today's USD rate; a Finish Fabric batch in kg, DIA in inches, open.
const blankHead = (date, usd) => ({ store_id: "", ob_date: date, order_id: "", currency: "USD", conv_rate: "1", usd_bdt: str(usd) });
const headOf = (data, date, usd) => Object.fromEntries(Object.entries(blankHead(date, usd)).map(([k, v]) => [k, data?.[k] === null || data?.[k] === undefined ? (k === "usd_bdt" ? "" : v) : str(data[k])]));
const blankRow = (category) => ({ category, description: "", fabric_item: "", colour: "", fabric_type_id: "", fabric_group: "", composition_id: "", gsm: "", dia: "", dia_uom: "INCH", dia_type: "OPEN", batch_no: "", rolls: "", uom: "KG", qty: "", rate: "" });
let seq = 0;
const asLine = (l) => ({ key: `g${++seq}`, ...Object.fromEntries(Object.keys(blankRow("")).map((k) => [k, str(l[k])])) });
// The order Brickland asks for them in before a row may be added.
const NEEDED = ["description", "fabric_item", "fabric_type_id", "fabric_group"];

/**
 * Finished Fabric Opening Balance → Opening Balance Entry (AnnotechERP 06/06),
 * as Brickland lays it out: the existing-balance picker and the header
 * (receiving store, OB date, order, currency and rates); Fabric Items — one
 * roll batch described above, Add Row filing it in the grid below, where
 * every cell stays editable. No workflow: Save records it, and it can be
 * corrected or deleted later. Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, canManage: boolean, actions: object }} props
 */
function FfabObEntry({ options, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.ffob.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const category = m("default_category");
  const [ob, setOb] = useState(initial);
  const [head, setHead] = useState(() => (initial ? headOf(initial, today, options.usd_bdt) : blankHead(today, options.usd_bdt)));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [row, setRow] = useState(() => blankRow(category));
  const [rowError, setRowError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage;
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const coded = (group, values) => values.map((v) => ({ value: v, label: t(`master.fob.${group}.${v}`) }));
  const toBdt = head.currency.toUpperCase() === "BDT" ? 1 : num(head.currency.toUpperCase() === "USD" ? 1 : head.conv_rate) * num(head.usd_bdt);
  const totalQty = lines.reduce((n, l) => n + num(l.qty), 0);
  const totalCost = lines.reduce((n, l) => n + num(l.qty) * num(l.rate) * toBdt, 0);

  function addRow() {
    const need = NEEDED.find((k) => !row[k].trim());
    if (need) return setRowError(m("row_needed", { field: m(`f.${need}`) }));
    if (num(row.qty) <= 0) return setRowError(m("row_needed", { field: m("f.qty") }));
    setLines((all) => [...all, asLine(row)]);
    setRow((r) => ({ ...blankRow(r.category), uom: r.uom, dia_uom: r.dia_uom, dia_type: r.dia_type }));
    setRowError(null);
  }

  function take(data) {
    setOb(data);
    setHead(headOf(data, today, options.usd_bdt));
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.ob_no)}`);
  }

  function clear() {
    setOb(null);
    setHead(blankHead(today, options.usd_bdt));
    setLines([]);
    setRow(blankRow(category));
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
      toastManager.add({ title: m("saved", { date: dateFmt.format(new Date(`${result.data.ob_date}T00:00:00Z`)), n: result.data.lines.length }), type: "success" });
    });
  }

  function remove() {
    startTransition(async () => {
      const result = await actions.remove(ob.ob_no);
      setConfirm(false);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: m("deleted", { no: ob.ob_no }), type: "success" });
      clear();
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const star = <span className="text-danger">*</span>;
  const rowInput = (key, type = "text") => (
    <label className={field}>
      <span className={lab}>{m(`f.${key}`)}</span>
      <Input type={type} min={type === "number" ? "0" : undefined} step={type === "number" ? "any" : undefined} value={row[key]} onChange={(e) => setRow((r) => ({ ...r, [key]: e.target.value }))} disabled={!editable} aria-label={m(`f.${key}`)} />
    </label>
  );
  const rowSelect = (key, list) => (
    <div className={field}>
      <span className={lab}>{m(`f.${key}`)}</span>
      <Select value={row[key]} onValueChange={(v) => setRow((r) => ({ ...r, [key]: v ?? "" }))} options={pick(list)} placeholder={m("select")} disabled={!editable} aria-label={m(`f.${key}`)} />
    </div>
  );
  const cellInput = (l, i, key, aria, width, type = "text") => (
    <Input type={type} min={type === "number" ? "0" : undefined} step={type === "number" ? "any" : undefined} value={l[key]} onChange={(e) => setLine(l.key, { [key]: e.target.value })} className={cn("h-8", width, type === "number" && "text-right")} disabled={!editable} aria-label={m(aria, { n: i + 1 })} />
  );
  const cellSelect = (l, i, key, list, aria, width) => (
    <div className={width}>
      <Select value={l[key]} onValueChange={(v) => setLine(l.key, { [key]: v ?? "" })} options={[{ value: "", label: "—" }, ...list]} disabled={!editable} aria-label={m(aria, { n: i + 1 })} />
    </div>
  );
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
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{ob ? ob.ob_no : m("new")}</span>
        </div>
        <div className={cn(field, "mt-4")}>
          <span className={lab}>{m("existing")}</span>
          <Select value={ob?.ob_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_ob") }, ...options.balances]} aria-label={m("existing")} />
          <span className={hint}>{m("existing_hint")}</span>
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
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
          <label className={field}>
            <span className={lab}>{m("f.currency")}</span>
            <Input value={head.currency} onChange={(e) => setH({ currency: e.target.value })} maxLength={8} disabled={!editable} aria-label={m("f.currency")} />
          </label>
          <label className={field}>
            <span className={lab}>{m("f.conv_rate")}</span>
            <Input type="number" min="0" step="any" value={head.conv_rate} onChange={(e) => setH({ conv_rate: e.target.value })} disabled={!editable} aria-label={m("f.conv_rate")} />
          </label>
          <label className={field}>
            <span className={lab}>{m("f.usd_bdt")}</span>
            <Input type="number" min="0" step="any" value={head.usd_bdt} onChange={(e) => setH({ usd_bdt: e.target.value })} disabled={!editable} aria-label={m("f.usd_bdt")} />
          </label>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 pt-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">{m("items")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("items_hint")}</p>
          </div>
          {editable ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addRow}>
                <Plus /> {m("add_row")}
              </Button>
              <Button variant="outline" size="sm" onClick={() => (setRow(blankRow(category)), setRowError(null))}>
                {m("cancel_row")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="px-5 pt-4">
            <div className="grid gap-4 md:grid-cols-3">
              {rowInput("category")}
              {rowInput("description")}
              {rowInput("fabric_item")}
              {rowInput("colour")}
              {rowSelect("fabric_type_id", options.fabric_types)}
              {rowSelect("fabric_group", coded("group", options.groups))}
              {rowSelect("composition_id", options.compositions)}
              {rowInput("gsm", "number")}
              {rowInput("dia", "number")}
              {rowSelect("dia_uom", coded("dia_uom", options.dia_uoms))}
              {rowSelect("dia_type", coded("dia_type", options.dia_types))}
              {rowInput("batch_no")}
              {rowInput("rolls", "number")}
              {rowSelect("uom", coded("uom", options.uoms))}
              {rowInput("qty", "number")}
              {rowInput("rate", "number")}
            </div>
            {rowError ? <p className="mt-2 text-xs text-danger">{rowError}</p> : null}
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["category", "description", "item", "colour", "type", "group", "composition", "batch", "roll", "qty", "rate", "gsm", "dia", "dia_uom", "dia_type", "uom", "remove"].map((k) => (
                  <th key={k} className={cn("px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["qty", "rate", "roll"].includes(k) && "text-right")}>
                    {k === "remove" ? <span className="sr-only">{m("col.remove")}</span> : m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  <td className="px-2 py-1.5">{cellInput(l, i, "category", "row_category", "w-28")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "description", "row_description", "w-36")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "fabric_item", "row_item", "w-32")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "colour", "row_colour", "w-24")}</td>
                  <td className="px-2 py-1.5">{cellSelect(l, i, "fabric_type_id", options.fabric_types, "row_type", "w-36")}</td>
                  <td className="px-2 py-1.5">{cellSelect(l, i, "fabric_group", coded("group", options.groups), "row_group", "w-28")}</td>
                  <td className="px-2 py-1.5">{cellSelect(l, i, "composition_id", options.compositions, "row_composition", "w-44")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "batch_no", "row_batch", "w-24")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "rolls", "row_rolls", "w-16", "number")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "qty", "row_qty", "w-24", "number")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "rate", "row_rate", "w-20", "number")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "gsm", "row_gsm", "w-16", "number")}</td>
                  <td className="px-2 py-1.5">{cellInput(l, i, "dia", "row_dia", "w-16", "number")}</td>
                  <td className="px-2 py-1.5">{cellSelect(l, i, "dia_uom", coded("dia_uom", options.dia_uoms), "row_dia_uom", "w-24")}</td>
                  <td className="px-2 py-1.5">{cellSelect(l, i, "dia_type", coded("dia_type", options.dia_types), "row_dia_type", "w-24")}</td>
                  <td className="px-2 py-1.5">{cellSelect(l, i, "uom", coded("uom", options.uoms), "row_uom", "w-24")}</td>
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
                  <td colSpan={17} className="px-5 py-10 text-center">
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
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{m("rules")}</p>
        <div className="flex flex-wrap gap-2">
          {ob && editable ? (
            <Button variant="outline" className="text-danger" onClick={() => setConfirm(true)} disabled={pending}>
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
        </div>
      </section>

      <ConfirmDialog
        open={confirm}
        onOpenChange={(o) => !o && setConfirm(false)}
        title={m("confirm_delete_title")}
        description={ob ? m("confirm_delete_hint", { date: dateFmt.format(new Date(`${ob.ob_date}T00:00:00Z`)) }) : ""}
        confirmLabel={m("delete")}
        cancelLabel={m("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

export { FfabObEntry };
