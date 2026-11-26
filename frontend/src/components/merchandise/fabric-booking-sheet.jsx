"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { Filter, Plus, RotateCcw, Save, Send, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field (an empty `errors` is no message at all).
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};

let seq = 0;
const today = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
const blankHead = () => ({ booking_date: today(), buyer_office_id: "", style_id: "", order_id: "", currency: "USD", conv_rate: "1", item_model: "", cutting_wastage: "5", yd_wastage: "4", aop_wastage: "6" });
const blankLeft = () => ({ fabric_kind: "KNIT", fabric_type_id: "", garment_part_id: "", finish_dia: "", dia_unit: "INCH", dia_type: "OPEN", consumption: "", cons_unit: "KG", cons_per: "DZ" });
const blankRight = (wastage = "5") => ({ all_orders: true, gmt_style_id: "", all_colours: false, gmt_colour_id: "", all_sizes: false, size_group: "", fab_same_as_gmt: false, fab_colour_id: "", colour_spec: "", contrast: false, cutting_wastage: wastage, add_qty: "", disp_order: "" });
const str = (v) => (v === null || v === undefined ? "" : String(v));
const fromBooking = (b) => ({
  head: { booking_date: b.booking_date, buyer_office_id: str(b.buyer_office_id), style_id: str(b.style_id), order_id: str(b.order_id), currency: b.currency, conv_rate: str(b.conv_rate), item_model: str(b.item_model), cutting_wastage: str(b.cutting_wastage), yd_wastage: str(b.yd_wastage), aop_wastage: str(b.aop_wastage) },
  lines: b.lines.map((l) => ({ ...Object.fromEntries(Object.entries(l).map(([k, v]) => [k, typeof v === "boolean" ? v : str(v)])), key: `l${++seq}`, saved: true })),
});
// The booking as last saved: what Submit sends to the approvers.
const keep = (b) => ({ booking_no: b.booking_no, revision: b.revision, program_by: b.program_by, status: b.status, approved_at: b.approved_at, approved_by: b.approved_by });
const snap = (head, lines) => JSON.stringify([head, lines.map(({ key, saved, ...l }) => l)]);
const LOCKED = ["SUBMITTED", "APPROVED"];
const UOM = { KG: "KG", KGS: "KG", YDS: "YDS", YD: "YDS", YARD: "YDS", MTR: "MTR", M: "MTR", METER: "MTR" };

/**
 * Bulk Fabric Booking Entry → New Booking / Edit (AnnotechERP 06/11), as
 * Brickland lays it out: Key Information (the order keyed once), then
 * Consumption Entry — the dia-wise CAD consumption on the left, the garment
 * item, colour, size range and fabric colour it applies to on the right, Add
 * to Grid pairing the two halves into a line — and Process Data, where each
 * line's GSM, rate and process loss are typed. Suggest fills the consumption
 * from the style's CAD record. Save keeps the whole booking (at least one
 * line); a booking already saved is revised. Submit sends the saved booking
 * to the Approval Center; while it waits, and once approved, it is read only.
 * Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, save: Function, submit: Function, suggest: Function, canManage: boolean, recordsHref: string }} props
 */
function FabricBookingSheet({ options, initial, save, submit: sendForApproval, suggest, canManage, recordsHref }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bfb.${k}`, p);
  const toastManager = useToastManager();
  const start = initial ? fromBooking(initial) : { head: blankHead(), lines: [] };
  const [booking, setBooking] = useState(initial ? keep(initial) : null);
  const [head, setHead] = useState(start.head);
  const [lines, setLines] = useState(start.lines);
  const [saved, setSaved] = useState(() => (initial ? snap(start.head, start.lines) : null));
  const [left, setLeft] = useState(blankLeft);
  const [right, setRight] = useState(() => blankRight(start.head.cutting_wastage));
  const [tab, setTab] = useState("consumption");
  const [entryError, setEntryError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const style = options.styles.find((s) => s.value === head.style_id);
  const orders = options.orders.filter((o) => !head.style_id || o.style_id === head.style_id);
  const name = (list, id) => list.find((x) => x.value === id)?.label ?? "—";
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const strip = (list) => list.map(({ value, label }) => ({ value, label }));
  const enumOpts = (group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setL = (patch) => setLeft((x) => ({ ...x, ...patch }));
  const setR = (patch) => setRight((x) => ({ ...x, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const sorted = useMemo(() => [...lines].sort((a, b) => Number(a.disp_order || 0) - Number(b.disp_order || 0)), [lines]);

  function pickStyle(id) {
    const s = options.styles.find((x) => x.value === id);
    setH({ style_id: id, order_id: "", buyer_office_id: head.buyer_office_id || s?.buyer_office_id || "", item_model: s?.items?.[0] ?? head.item_model });
    setR({ gmt_style_id: right.gmt_style_id || id });
  }

  function pickOrder(id) {
    const o = options.orders.find((x) => x.value === id);
    setH({ order_id: id, currency: o?.currency && options.currencies.includes(o.currency) ? o.currency : head.currency, conv_rate: o?.rate ? String(o.rate) : head.conv_rate, buyer_office_id: head.buyer_office_id || o?.buyer_office_id || "" });
  }

  function runSuggest() {
    if (!head.style_id) {
      setEntryError(m("need.style_first"));
      return;
    }
    startTransition(async () => {
      const result = await suggest(head.style_id);
      if (result?.status !== "success") {
        setEntryError(result?.message);
        return;
      }
      const parts = result.data.parts;
      if (!parts.length) {
        setEntryError(m("no_cad"));
        return;
      }
      const best = parts.find((p) => p.is_body) ?? parts[0];
      setL({ garment_part_id: best.garment_part_id ?? "", consumption: String(best.consumption), cons_unit: UOM[String(best.uom ?? "").toUpperCase()] ?? left.cons_unit });
      setEntryError(null);
      toastManager.add({ title: m("suggested", { job: result.data.record, part: best.part ?? "—", cons: best.consumption }), type: "success" });
    });
  }

  function addToGrid() {
    const need = !left.fabric_type_id
      ? "fabric"
      : !left.consumption
        ? "cons"
        : !right.gmt_style_id
          ? "gmt_item"
          : !right.all_colours && !right.gmt_colour_id
            ? "gmt_colour"
            : !right.all_sizes && !right.size_group
              ? "size"
              : !right.fab_same_as_gmt && !right.fab_colour_id
                ? "fab_colour"
                : right.cutting_wastage === ""
                  ? "wastage"
                  : null;
    if (need) {
      setEntryError(m(`need.${need}`));
      return;
    }
    const next = lines.reduce((n, l) => Math.max(n, Number(l.disp_order || 0)), 0) + 1;
    setLines((all) => [...all, { ...left, ...right, disp_order: right.disp_order || String(next), key: `l${++seq}`, saved: false, finish_qty: "", gsm: "", rate: "", process_loss: "" }]);
    setLeft(blankLeft());
    setRight(blankRight(head.cutting_wastage));
    setEntryError(null);
  }

  function submit() {
    if (!lines.length) {
      setErrors([m("no_lines")]);
      return;
    }
    const num = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
    const payload = {
      ...head,
      buyer_office_id: head.buyer_office_id || null,
      conv_rate: num(head.conv_rate),
      cutting_wastage: num(head.cutting_wastage),
      yd_wastage: num(head.yd_wastage),
      aop_wastage: num(head.aop_wastage),
      lines: sorted.map((l) => ({
        fabric_kind: l.fabric_kind,
        fabric_type_id: l.fabric_type_id,
        garment_part_id: l.garment_part_id || null,
        finish_dia: num(l.finish_dia),
        dia_unit: l.dia_unit,
        dia_type: l.dia_type,
        consumption: num(l.consumption),
        cons_unit: l.cons_unit,
        cons_per: l.cons_per,
        all_orders: Boolean(l.all_orders),
        gmt_style_id: l.gmt_style_id,
        all_colours: Boolean(l.all_colours),
        gmt_colour_id: l.all_colours ? null : l.gmt_colour_id || null,
        all_sizes: Boolean(l.all_sizes),
        size_group: l.all_sizes ? null : l.size_group || null,
        fab_same_as_gmt: Boolean(l.fab_same_as_gmt),
        fab_colour_id: l.fab_same_as_gmt ? null : l.fab_colour_id || null,
        colour_spec: l.colour_spec || null,
        contrast: Boolean(l.contrast),
        cutting_wastage: num(l.cutting_wastage) ?? 0,
        add_qty: num(l.add_qty),
        disp_order: num(l.disp_order),
        gsm: num(l.gsm),
        rate: num(l.rate),
        process_loss: num(l.process_loss),
      })),
    };
    startTransition(async () => {
      const result = await save(booking?.booking_no ?? null, payload);
      if (result?.status === "success") {
        const fresh = fromBooking(result.data);
        setBooking(keep(result.data));
        setHead(fresh.head);
        setLines(fresh.lines);
        setSaved(snap(fresh.head, fresh.lines));
        setErrors(null);
        toastManager.add({ title: m("saved", { no: result.data.booking_no, n: result.data.lines.length, rev: result.data.revision }), type: "success" });
        window.history.replaceState(null, "", `?tab=edit&ref=${encodeURIComponent(result.data.booking_no)}`);
      } else {
        setErrors(messagesOf(result));
      }
    });
  }

  function sendIt() {
    if (saved !== snap(head, lines)) {
      setErrors([m("submit_unsaved")]);
      return;
    }
    startTransition(async () => {
      const result = await sendForApproval(booking.booking_no);
      if (result?.status === "success") {
        setBooking(keep(result.data));
        setErrors(null);
        toastManager.add({ title: m(result.data.status === "APPROVED" ? "submitted_approved" : "submitted", { no: result.data.booking_no }), type: "success" });
      } else {
        setErrors(messagesOf(result));
      }
    });
  }

  function reset() {
    setHead(booking ? start.head : blankHead());
    setLines(booking ? start.lines : []);
    setLeft(blankLeft());
    setRight(blankRight(start.head.cutting_wastage));
    setErrors(null);
    setEntryError(null);
  }

  const row = "grid items-center gap-x-3 gap-y-1.5 sm:grid-cols-[9rem_1fr]";
  const lab = "text-[13px] font-semibold text-foreground sm:text-right";
  const star = <span className="text-danger">*</span>;
  const fieldset = "rounded-lg border border-border p-4";
  const legend = "px-1 text-[13px] font-semibold text-brand";
  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const locked = LOCKED.includes(booking?.status);
  const editable = canManage && !locked;
  const disabled = !editable;
  const approvedOn = booking?.approved_at ? new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "Asia/Dhaka" }).format(new Date(booking.approved_at)) : null;
  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-xl border border-border bg-surface p-5">
        <h2 className="text-base font-semibold text-foreground">{booking ? m("edit_title", { no: booking.booking_no }) : m("new_title")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{m("hint")}</p>
        {booking?.status === "SUBMITTED" ? <Alert className="mt-3">{m("state.SUBMITTED")}</Alert> : null}
        {booking?.status === "APPROVED" ? <Alert variant="success" className="mt-3">{booking.approved_by ? m("state.APPROVED_BY", { date: approvedOn, by: booking.approved_by }) : m("state.APPROVED", { date: approvedOn })}</Alert> : null}
        {booking?.status === "REJECTED" ? <Alert variant="danger" className="mt-3">{m("state.REJECTED")}</Alert> : null}

        <fieldset className={cn(fieldset, "mt-4")}>
          <legend className={legend}>{m("key_info")}</legend>
          <div className="grid gap-x-6 gap-y-3 lg:grid-cols-2">
            <div className={row}>
              <span className={lab}>{m("f.booking_ref")}</span>
              <Input value={booking ? `${booking.booking_no}${booking.revision ? ` · ${m("rev", { n: booking.revision })}` : ""}` : m("on_save")} readOnly className="bg-surface-muted" aria-label={m("f.booking_ref")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {star}
                {m("f.date")}
              </span>
              <Input type="date" value={head.booking_date} onChange={(e) => setH({ booking_date: e.target.value })} disabled={disabled} aria-label={m("f.date")} />
            </div>
            <div className={row}>
              <span className={lab}>{m("f.program_by")}</span>
              <Input value={booking?.program_by ?? options.me.name} readOnly className="bg-surface-muted" aria-label={m("f.program_by")} />
            </div>
            <div className={row}>
              <span className={lab}>{m("f.buyer_ac")}</span>
              <Select value={head.buyer_office_id} onValueChange={(v) => setH({ buyer_office_id: v ?? "" })} options={pick(options.offices)} placeholder={m("select")} disabled={disabled} aria-label={m("f.buyer_ac")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {star}
                {m("f.style")}
              </span>
              <Select value={head.style_id} onValueChange={(v) => pickStyle(v ?? "")} options={pick(strip(options.styles))} placeholder={m("select")} disabled={disabled} aria-label={m("f.style")} />
            </div>
            <div className={row}>
              <span className={lab}>{m("f.style_desc")}</span>
              <Input value={style?.description ?? ""} readOnly className="bg-surface-muted" aria-label={m("f.style_desc")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {star}
                {m("f.order")}
              </span>
              <Select value={head.order_id} onValueChange={(v) => pickOrder(v ?? "")} options={pick(strip(orders))} placeholder={m("select")} disabled={disabled} aria-label={m("f.order")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {star}
                {m("f.currency")}
              </span>
              <div className="grid grid-cols-[7rem_auto_1fr] items-center gap-2">
                <Select value={head.currency} onValueChange={(v) => v && setH({ currency: v })} options={options.currencies.map((c) => ({ value: c, label: c }))} disabled={disabled} aria-label={m("f.currency")} />
                <span className="text-[13px] font-semibold text-foreground">
                  {star}
                  {m("f.conv")}
                </span>
                <Input type="number" min="0" step="any" value={head.conv_rate} onChange={(e) => setH({ conv_rate: e.target.value })} disabled={disabled} aria-label={m("f.conv")} />
              </div>
            </div>
            <div className={cn(row, "lg:col-span-2 lg:grid-cols-[9rem_1fr]")}>
              <span className={lab}>
                {star}
                {m("f.item_model")}
              </span>
              <Input list="bfb-items" value={head.item_model} onChange={(e) => setH({ item_model: e.target.value })} placeholder={m("search")} disabled={disabled} maxLength={96} aria-label={m("f.item_model")} />
              <datalist id="bfb-items">
                {(style?.items ?? []).map((i) => (
                  <option key={i} value={i} />
                ))}
              </datalist>
            </div>
            <div className={row}>
              <span className={lab}>{m("f.cut_wastage")}</span>
              <Input type="number" min="0" max="100" step="any" value={head.cutting_wastage} onChange={(e) => setH({ cutting_wastage: e.target.value })} disabled={disabled} aria-label={m("f.cut_wastage")} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
                <span className="shrink-0">{m("f.yd_wastage")}</span>
                <Input type="number" min="0" max="100" step="any" value={head.yd_wastage} onChange={(e) => setH({ yd_wastage: e.target.value })} disabled={disabled} />
              </label>
              <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
                <span className="shrink-0">{m("f.aop_wastage")}</span>
                <Input type="number" min="0" max="100" step="any" value={head.aop_wastage} onChange={(e) => setH({ aop_wastage: e.target.value })} disabled={disabled} />
              </label>
            </div>
          </div>
        </fieldset>

        <div role="tablist" className="mt-5 flex gap-1 border-b border-border">
          {["consumption", "process"].map((k) => (
            <button key={k} type="button" role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={cn("-mb-px border-b-2 px-3 py-2 text-[14px] font-medium", tab === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
              {m(`tab.${k}`)}
            </button>
          ))}
        </div>

        {tab === "consumption" ? (
          <>
            {editable ? (
              <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <fieldset className={fieldset}>
                  <legend className={legend}>{m("dia_cons")}</legend>
                  <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div role="radiogroup" aria-label={m("f.kind")} className="flex gap-4">
                      {options.kinds.map((k) => (
                        <label key={k} className="flex cursor-pointer items-center gap-1.5 text-sm text-foreground">
                          <input type="radio" name="bfb-kind" checked={left.fabric_kind === k} onChange={() => setL({ fabric_kind: k })} className="size-4 accent-[var(--color-brand)]" />
                          {m(`kind.${k}`)}
                        </label>
                      ))}
                    </div>
                    <Button size="sm" onClick={runSuggest} loading={pending}>
                      <Filter /> {m("suggest")}
                    </Button>
                  </div>
                  <div className="flex flex-col gap-3">
                    <div className={row}>
                      <span className={lab}>
                        {star}
                        {m("f.fabric")}
                      </span>
                      <Select value={left.fabric_type_id} onValueChange={(v) => setL({ fabric_type_id: v ?? "" })} options={pick(options.fabrics)} placeholder={m("select")} aria-label={m("f.fabric")} />
                    </div>
                    <div className={row}>
                      <span className={lab}>{m("f.part")}</span>
                      <Select value={left.garment_part_id} onValueChange={(v) => setL({ garment_part_id: v ?? "" })} options={pick(options.parts)} placeholder={m("select")} aria-label={m("f.part")} />
                    </div>
                    <div className={row}>
                      <span className={lab}>{m("f.finish_dia")}</span>
                      <div className="grid grid-cols-3 gap-2">
                        <Input type="number" min="0" step="any" value={left.finish_dia} onChange={(e) => setL({ finish_dia: e.target.value })} aria-label={m("f.finish_dia")} />
                        <Select value={left.dia_unit} onValueChange={(v) => v && setL({ dia_unit: v })} options={enumOpts("dia_unit", options.dia_units)} aria-label={m("f.dia_unit")} />
                        <Select value={left.dia_type} onValueChange={(v) => v && setL({ dia_type: v })} options={enumOpts("dia_type", options.dia_types)} aria-label={m("f.dia_type")} />
                      </div>
                    </div>
                    <div className={row}>
                      <span className={lab}>
                        {star}
                        {m("f.cons")}
                      </span>
                      <div className="grid grid-cols-[1fr_1fr_auto_1fr] items-center gap-2">
                        <Input type="number" min="0" step="any" value={left.consumption} onChange={(e) => setL({ consumption: e.target.value })} aria-label={m("f.cons")} />
                        <Select value={left.cons_unit} onValueChange={(v) => v && setL({ cons_unit: v })} options={enumOpts("cons_unit", options.cons_units)} aria-label={m("f.cons_unit")} />
                        <span className="text-[13px] font-semibold text-foreground">{m("f.per")}</span>
                        <Select value={left.cons_per} onValueChange={(v) => v && setL({ cons_per: v })} options={enumOpts("cons_per", options.cons_per)} aria-label={m("f.per")} />
                      </div>
                    </div>
                  </div>
                </fieldset>

                <fieldset className={fieldset}>
                  <legend className={legend}>{m("gmt_qty")}</legend>
                  <div className="flex flex-col gap-3">
                    <div className={row}>
                      <span className={lab}>{m("f.order_all")}</span>
                      <label className="flex items-center gap-2 text-sm text-foreground">
                        <Checkbox checked={right.all_orders} onCheckedChange={(c) => setR({ all_orders: c === true })} aria-label={m("all_orders")} />
                        {m("all")}
                      </label>
                    </div>
                    <div className={row}>
                      <span className={lab}>
                        {star}
                        {m("f.gmt_item")}
                      </span>
                      <Select value={right.gmt_style_id} onValueChange={(v) => setR({ gmt_style_id: v ?? "" })} options={pick(strip(options.styles))} placeholder={m("select")} aria-label={m("f.gmt_item")} />
                    </div>
                    {[
                      ["gmt_colour", "all_colours", "gmt_colour_id", options.colours, "all"],
                      ["size", "all_sizes", "size_group", enumOpts("size_group", options.size_groups), "all"],
                      ["fab_colour", "fab_same_as_gmt", "fab_colour_id", options.colours, "gmt_colour_q"],
                    ].map(([key, flag, field, list, flagLabel]) => (
                      <div key={key} className={row}>
                        <span className={lab}>
                          {star}
                          {m(`f.${key}`)}
                        </span>
                        <div className="flex items-center gap-2">
                          <label className="flex shrink-0 items-center gap-1.5 text-sm text-foreground">
                            <Checkbox checked={right[flag]} onCheckedChange={(c) => setR({ [flag]: c === true, ...(c === true ? { [field]: "" } : {}) })} aria-label={`${m(`f.${key}`)} ${m(flagLabel)}`} />
                            {m(flagLabel)}
                          </label>
                          <Select value={right[field]} onValueChange={(v) => setR({ [field]: v ?? "" })} options={pick(list)} placeholder={m("search")} disabled={right[flag]} aria-label={m(`f.${key}`)} />
                        </div>
                      </div>
                    ))}
                    <div className={row}>
                      <span className={lab}>{m("f.colour_spec")}</span>
                      <div className="flex items-center gap-2">
                        <Input value={right.colour_spec} onChange={(e) => setR({ colour_spec: e.target.value })} maxLength={160} aria-label={m("f.colour_spec")} />
                        <label className="flex shrink-0 items-center gap-1.5 text-sm text-foreground">
                          <Checkbox checked={right.contrast} onCheckedChange={(c) => setR({ contrast: c === true })} aria-label={m("f.contrast")} />
                          {m("f.contrast")}
                        </label>
                      </div>
                    </div>
                    <div className={row}>
                      <span className={lab}>
                        {star}
                        {m("f.c_wastage")}
                      </span>
                      <div className="grid grid-cols-[1fr_auto_1fr_auto_1fr] items-center gap-2">
                        <Input type="number" min="0" max="100" step="any" value={right.cutting_wastage} onChange={(e) => setR({ cutting_wastage: e.target.value })} aria-label={m("f.c_wastage")} />
                        <span className="text-[13px] font-semibold">{m("f.add_qty")}</span>
                        <Input type="number" min="0" step="any" value={right.add_qty} onChange={(e) => setR({ add_qty: e.target.value })} aria-label={m("f.add_qty")} />
                        <span className="text-[13px] font-semibold">{m("f.disp_order")}</span>
                        <Input type="number" min="0" step="1" value={right.disp_order} onChange={(e) => setR({ disp_order: e.target.value })} aria-label={m("f.disp_order")} />
                      </div>
                    </div>
                    <div className="flex justify-center">
                      <Button onClick={addToGrid}>
                        <Plus /> {m("add_to_grid")}
                      </Button>
                    </div>
                  </div>
                </fieldset>
              </div>
            ) : null}
            {entryError ? (
              <Alert variant="danger" className="mt-3">
                {entryError}
              </Alert>
            ) : null}
            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-brand text-brand-foreground">
                    {["disp_order", "part", "dia", "cons", "gmt_item", "gmt_colour", "fab_colour", "size", "fin_qty", "action"].map((k) => (
                      <th key={k} className={th}>
                        {m(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {sorted.length === 0 ? (
                    <tr>
                      <td colSpan={10} className="px-4 py-10 text-center text-foreground-muted">
                        {m("grid_empty")}
                      </td>
                    </tr>
                  ) : (
                    sorted.map((l) => (
                      <tr key={l.key} className="border-b border-border last:border-0">
                        <td className="px-3 py-2 tabular-nums">{l.disp_order}</td>
                        <td className="px-3 py-2">{l.garment_part_id ? name(options.parts, l.garment_part_id) : "—"}</td>
                        <td className="px-3 py-2 whitespace-nowrap">{l.finish_dia ? `${l.finish_dia} ${m(`dia_unit.${l.dia_unit}`)} ${m(`dia_type.${l.dia_type}`)}` : "—"}</td>
                        <td className="px-3 py-2 whitespace-nowrap tabular-nums">{`${Number(l.consumption).toFixed(4)} ${m(`cons_unit.${l.cons_unit}`)}/${m(`cons_per.${l.cons_per}`)}`}</td>
                        <td className="px-3 py-2">{name(options.styles, l.gmt_style_id)}</td>
                        <td className="px-3 py-2">{l.all_colours ? m("all") : name(options.colours, l.gmt_colour_id)}</td>
                        <td className="px-3 py-2">{l.fab_same_as_gmt ? (l.all_colours ? m("as_gmt") : name(options.colours, l.gmt_colour_id)) : name(options.colours, l.fab_colour_id)}</td>
                        <td className="px-3 py-2">{l.all_sizes ? m("all") : m(`size_group.${l.size_group}`)}</td>
                        <td className="px-3 py-2 whitespace-nowrap tabular-nums">{l.saved && l.finish_qty !== "" ? `${Number(l.finish_qty).toFixed(3)} ${m(`cons_unit.${l.cons_unit}`)}` : "—"}</td>
                        <td className="px-3 py-2">
                          {editable ? (
                            <Button variant="outline" size="icon" className="size-8 text-danger" aria-label={m("remove_line")} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                              <Trash2 />
                            </Button>
                          ) : null}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
            <p className="mt-2 text-[13px] text-foreground-muted">{m("derived")}</p>
          </>
        ) : lines.length === 0 ? (
          <p className="mt-6 text-center text-sm text-foreground-muted">{m("process_empty")}</p>
        ) : (
          <div className="mt-4 overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-brand text-brand-foreground">
                  {["fabric", "part", "gsm", "rate", "loss"].map((k) => (
                    <th key={k} className={th}>
                      {m(`pcol.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {sorted.map((l) => (
                  <tr key={l.key} className="border-b border-border last:border-0">
                    <td className="px-3 py-2">{name(options.fabrics, l.fabric_type_id)}</td>
                    <td className="px-3 py-2">{l.garment_part_id ? name(options.parts, l.garment_part_id) : "—"}</td>
                    {["gsm", "rate", "process_loss"].map((k) => (
                      <td key={k} className="px-3 py-2">
                        <Input type="number" min="0" step="any" value={l[k]} onChange={(e) => setLine(l.key, { [k]: e.target.value })} className="w-28" disabled={disabled} aria-label={`${m(`pcol.${k === "process_loss" ? "loss" : k}`)} ${l.disp_order}`} />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface px-5 py-3">
        <div className="flex gap-2">
          {editable ? (
            <Button onClick={submit} loading={pending}>
              <Save /> {m("save")}
            </Button>
          ) : null}
          <Link href={recordsHref} className={buttonVariants({ variant: "outline" })}>
            {m("cancel")}
          </Link>
          {editable ? (
            <Button variant="outline" onClick={reset} disabled={pending}>
              <RotateCcw /> {m("reset")}
            </Button>
          ) : null}
          {canManage && sendForApproval && booking && ["DRAFT", "REJECTED"].includes(booking.status) ? (
            <Button variant="outline" onClick={sendIt} loading={pending}>
              <Send /> {m("submit")}
            </Button>
          ) : null}
        </div>
        <span className="text-[13px] text-foreground-muted">{m("line_count", { n: lines.length })}</span>
      </div>
    </div>
  );
}

export { FabricBookingSheet };
