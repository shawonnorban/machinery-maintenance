"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Ban, Pencil, Plus, Printer, RotateCcw, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { barcodeSvg, printBundleCards } from "@/lib/bundle-label";
import { firstError, blank, dmy, useWords, Section, F, Pick, nativeSelect } from "./shared";
import { FabricPanel, InputForm, InputsTable, OutputPanel } from "./inputs";

/*
 * Cutting Floor (AnnotechERP 19), as Brickland lays its bundle screens out:
 * 1 Order Information (a PO, its style, buyer, colour and order quantity),
 * 2 Bundle Information (the serial run a card carries), 3 Bundle List (the
 * window, search, Print / Reprint Selected and the label size), 4 Bundle
 * Card Preview, 5 Today's Summary and 6 Serial Search. Bundle Card List is
 * the list alone.
 */

const daysAgo = (n, today) => {
  const d = new Date(`${today}T00:00:00`);
  d.setDate(d.getDate() - n);
  return d.toLocaleDateString("en-CA");
};
const STATUSES = ["CREATED", "PRINTED", "REPRINTED", "CANCELLED"];
const CUT_STATUSES = ["REGULAR", "ANY_ADDITIONAL", "ADDITIONAL", "PARTIAL_ADDITIONAL"];
const TONE = { CREATED: "bg-surface-muted text-foreground-muted", PRINTED: "bg-success/15 text-success", REPRINTED: "bg-brand-subtle text-brand", CANCELLED: "bg-danger/15 text-danger" };


/** The card as it prints. */
function CardPreview({ card, company, w }) {
  if (!card) return <p className="py-8 text-center text-sm text-foreground-muted">{w("preview.empty")}</p>;
  const row = (k, v, bold = true) => (
    <tr>
      <td className="pr-2 whitespace-nowrap">{w(`card.${k}`)}</td>
      <td className={cn(bold && "font-bold")}>: {v}</td>
    </tr>
  );
  return (
    <div className="mx-auto max-w-sm rounded-md border border-border bg-white p-3 text-[12px] text-black shadow-sm">
      <p className="mb-1.5 text-center text-[13px] font-bold">{company}</p>
      <div className="flex items-start gap-2">
        <div className="w-1/2">
          <div className="h-10" dangerouslySetInnerHTML={{ __html: barcodeSvg(card.barcode ?? "") }} />
          <p className="text-center text-[9px] tracking-wider">{card.barcode}</p>
        </div>
        <table className="text-[11px]">
          <tbody>
            {row("po", card.po_no, false)}
            {row("style", card.style, false)}
            {row("buyer", card.buyer ?? "", false)}
            {row("colour", card.colour, false)}
          </tbody>
        </table>
      </div>
      <table className="mt-2">
        <tbody>
          {row("bundle", card.bundle_no)}
          {row("cutting", card.cutting_no)}
          {row("size", card.size)}
          {row("from", card.serial_from)}
          {row("to", card.serial_to)}
          {row("qty", `${card.qty} ${w("card.pcs")}`)}
          {row("parts", card.parts_name)}
          {row("batch", card.batch_no)}
        </tbody>
      </table>
      <div className="mt-2 flex justify-between text-[11px] font-bold">
        <span>
          {w("card.date")} : {dmy(card.cut_date)}
        </span>
        <span>{company}</span>
      </div>
    </div>
  );
}

/**
 * The bundle list (section 3, or Bundle Card List alone): the window and
 * search, Print / Reprint Selected, the label size, the summary line and the
 * grid with each card's Print, Reprint, Edit and Cancel.
 */
function BundleList({ initial, today, canManage, act, onEdit, onPreview, embedded = false, onChanged }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [filter, setFilter] = useState({ from: daysAgo(6, today), to: today, q: "", status: "", cutting_status: "" });
  const [data, setData] = useState(initial);
  const [picked, setPicked] = useState([]);
  const [size, setSize] = useState("50x75");
  const [custom, setCustom] = useState({ w: "75", h: "50" });
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const company = () => (typeof document !== "undefined" ? document.getElementById("erp-print-context")?.dataset.company ?? "" : "").toUpperCase();
  const words = useMemo(() => Object.fromEntries(["po", "style", "buyer", "colour", "bundle", "cutting", "size", "from", "to", "qty", "parts", "batch", "date", "pcs"].map((k) => [k, w(`card.${k}`)]).concat([["title", w("print.title")], ["print", w("print.print")], ["close", w("print.close")]])), [w]);

  function load(next = filter) {
    startTransition(async () => {
      const res = await act.list(next);
      if (res?.status === "success") {
        setData(res.data);
        setPicked((p) => p.filter((no) => res.data.rows.some((r) => r.bundle_no === no)));
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  useEffect(() => {
    if (onChanged) onChanged.current = () => load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filter]);

  function print(nos, reprint) {
    if (!nos.length) return toastManager.add({ title: w("v.pick"), type: "warning" });
    startTransition(async () => {
      const res = await act.print(nos, reprint);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      if (!printBundleCards(res.data.cards, { size, custom, company: company(), words })) toastManager.add({ title: w("print.blocked"), type: "warning" });
      toastManager.add({ title: w(reprint ? "print.reprinted" : "print.printed", { count: nos.length }), type: "success" });
      setPicked([]);
      load();
    });
  }

  function cancel(row) {
    startTransition(async () => {
      const res = await act.cancel(row.bundle_no);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: w("list.cancelled", { no: row.bundle_no }), type: "success" });
        load();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  const rows = data.rows;
  const printable = picked.filter((no) => rows.find((r) => r.bundle_no === no)?.status === "CREATED");
  const reprintable = picked.filter((no) => ["PRINTED", "REPRINTED"].includes(rows.find((r) => r.bundle_no === no)?.status));
  const set = (k) => (e) => setFilter((f) => ({ ...f, [k]: e.target.value }));
  const all = rows.length > 0 && rows.every((r) => picked.includes(r.bundle_no));

  const body = (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end gap-2">
        <F label={w("list.from")}>
          <Input type="date" value={filter.from} onChange={set("from")} className="w-40" />
        </F>
        <F label={w("list.to")}>
          <Input type="date" value={filter.to} onChange={set("to")} className="w-40" />
        </F>
        <Input value={filter.q} onChange={set("q")} onKeyDown={(e) => e.key === "Enter" && load()} placeholder={w("list.search_ph")} className="w-72" aria-label={w("list.search_ph")} />
        <select value={filter.status} onChange={set("status")} className={cn(nativeSelect, "w-40")} aria-label={w("col.status")}>
          <option value="">{w("list.all_status")}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {w(`status.${s}`)}
            </option>
          ))}
        </select>
        <select value={filter.cutting_status} onChange={set("cutting_status")} className={cn(nativeSelect, "w-48")} aria-label={w("col.cutting_status")}>
          <option value="">{w("list.all_cutting")}</option>
          {CUT_STATUSES.map((s) => (
            <option key={s} value={s}>
              {w(`cut_status.${s}`)}
            </option>
          ))}
        </select>
        <Button onClick={() => load()} loading={pending}>
          <Search /> {w("list.search")}
        </Button>
      </div>
      <div className="flex flex-wrap items-center gap-2 border-t border-border pt-3">
        {canManage ? (
          <>
            <Button size="sm" onClick={() => print(printable, false)} disabled={!printable.length || pending}>
              <Printer /> {w("list.print_selected", { count: printable.length })}
            </Button>
            <Button size="sm" variant="outline" onClick={() => print(reprintable, true)} disabled={!reprintable.length || pending}>
              <RotateCcw /> {w("list.reprint_selected", { count: reprintable.length })}
            </Button>
          </>
        ) : null}
        <select value={size} onChange={(e) => setSize(e.target.value)} className={cn(nativeSelect, "w-48")} aria-label={w("list.page_setup")}>
          {["50x75", "75x75", "75x100", "custom"].map((s) => (
            <option key={s} value={s}>
              {w(`label.${s}`)}
            </option>
          ))}
        </select>
        {size === "custom" ? (
          <span className="flex items-center gap-1 text-xs">
            <Input type="number" value={custom.w} onChange={(e) => setCustom((c) => ({ ...c, w: e.target.value }))} className="w-20" aria-label={w("label.width")} /> ×
            <Input type="number" value={custom.h} onChange={(e) => setCustom((c) => ({ ...c, h: e.target.value }))} className="w-20" aria-label={w("label.height")} /> mm
          </span>
        ) : null}
        <span className="ml-auto text-[13px] text-foreground-muted">{w("list.summary", { bundles: data.bundles, pcs: data.pcs, additional: data.additional })}</span>
      </div>
      <div className="max-h-[34rem] overflow-auto rounded-md border border-border">
        <table className="w-full min-w-max text-sm">
          <thead className="sticky top-0 z-10">
            <tr className="bg-brand text-left text-brand-foreground">
              <th className="px-2 py-2">
                <input type="checkbox" checked={all} onChange={(e) => setPicked(e.target.checked ? rows.map((r) => r.bundle_no) : [])} aria-label={w("list.select_all")} />
              </th>
              {["sl", "date", "po", "style", "colour", "cutting_no", "bundle_no", "size", "parts", "batch", "serial_from", "serial_to", "qty", "cutting_status", "barcode", "status", "print_date", "action"].map((k) => (
                <th key={k} className={cn("px-2 py-2 font-semibold whitespace-nowrap", ["serial_from", "serial_to", "qty", "cutting_no"].includes(k) && "text-right")}>
                  {w(`col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={19} className="px-4 py-10 text-center text-foreground-muted">
                  {w("list.empty")}
                </td>
              </tr>
            ) : (
              rows.map((r, i) => (
                <tr key={r.bundle_no} className="cursor-pointer border-b border-border last:border-0 hover:bg-surface-muted" onClick={() => onPreview?.(r)}>
                  <td className="px-2 py-1.5" onClick={(e) => e.stopPropagation()}>
                    <input type="checkbox" checked={picked.includes(r.bundle_no)} onChange={(e) => setPicked((p) => (e.target.checked ? [...p, r.bundle_no] : p.filter((x) => x !== r.bundle_no)))} aria-label={w("list.select", { no: r.bundle_no })} />
                  </td>
                  <td className="px-2 py-1.5">{i + 1}</td>
                  <td className="px-2 py-1.5 whitespace-nowrap">{dmy(r.cut_date)}</td>
                  <td className="px-2 py-1.5">{r.po_no}</td>
                  <td className="px-2 py-1.5">{r.style}</td>
                  <td className="px-2 py-1.5">{r.colour}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">{r.cutting_no}</td>
                  <td className="px-2 py-1.5 font-mono font-semibold whitespace-nowrap">{r.bundle_no}</td>
                  <td className="px-2 py-1.5">{r.size}</td>
                  <td className="px-2 py-1.5 whitespace-nowrap">{r.parts_name}</td>
                  <td className="px-2 py-1.5">{r.batch_no}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">{r.serial_from}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">{r.serial_to}</td>
                  <td className="px-2 py-1.5 text-right font-semibold tabular-nums">{r.qty}</td>
                  <td className="px-2 py-1.5 whitespace-nowrap">{w(`cut_status.${r.cutting_status}`)}</td>
                  <td className="px-2 py-1.5">
                    <div className="h-6 w-28" dangerouslySetInnerHTML={{ __html: barcodeSvg(r.barcode, 30) }} />
                    <span className="block text-center text-[9px] tracking-wider">{r.barcode}</span>
                  </td>
                  <td className="px-2 py-1.5">
                    <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", TONE[r.status])}>{w(`status.${r.status}`)}</span>
                  </td>
                  <td className="px-2 py-1.5 whitespace-nowrap">{r.printed_at ? new Date(r.printed_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()).replace(",", "") : "—"}</td>
                  <td className="px-2 py-1.5" onClick={(e) => e.stopPropagation()}>
                    {canManage ? (
                      <span className="flex gap-1">
                        <Button size="sm" onClick={() => print([r.bundle_no], false)} disabled={r.status !== "CREATED" || pending}>
                          <Printer /> {w("list.print")}
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => print([r.bundle_no], true)} disabled={!["PRINTED", "REPRINTED"].includes(r.status) || pending}>
                          <RotateCcw /> {w("list.reprint")}
                        </Button>
                        {onEdit ? (
                          <Button size="sm" variant="ghost" onClick={() => onEdit(r)} disabled={r.status === "CANCELLED"} aria-label={w("list.edit", { no: r.bundle_no })}>
                            <Pencil />
                          </Button>
                        ) : null}
                        <Button size="sm" variant="outline" className="text-danger" onClick={() => setAsking(r)} disabled={r.status === "CANCELLED" || pending}>
                          <Ban /> {w("list.cancel")}
                        </Button>
                      </span>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? w("list.cancel_title", { no: asking.bundle_no }) : ""}
        description={w("list.cancel_text")}
        confirmLabel={w("list.cancel")}
        cancelLabel={w("list.keep")}
        destructive
        loading={pending}
        onConfirm={() => cancel(asking)}
      />
    </div>
  );

  return embedded ? body : (
    <Section n={1} title={w("list.title")}>
      {body}
    </Section>
  );
}

/**
 * New Cutting / Bundle Entry: the order and the card being cut on the left,
 * the card's preview, today's summary and the serial search on the right.
 * `mode` "new" is Cutting Input (the cutting master lays a cutting: InputForm,
 * the fabric of the PO's colour, the cuttings laid); "entry" is the output
 * clerk's: pick a cutting, record its output and cut its cards (OutputPanel).
 */
function BundleEntry({ mode = "entry", options, initial, todays, inputs: recent = [], canManage, act, prefill = {} }) {
  const w = useWords();
  const router = useRouter();
  const toastManager = useToastManager();
  const freshOrder = () => ({ po: prefill.po ?? "", order_id: "", colour: prefill.colour ?? "", garment: "", fabric_type: "", gsm: "", dia_width: "" });
  const freshCard = () => ({ cutting_no: "", size: "", serial_from: "", serial_to: "", parts_name: "", batch_no: "" });
  const [order, setOrder] = useState(freshOrder);
  const [card, setCard] = useState(freshCard);
  const [editing, setEditing] = useState(null);
  const [preview, setPreview] = useState(null);
  const [today, setToday] = useState(todays);
  const [serial, setSerial] = useState("");
  const [found, setFound] = useState(null);
  const [loaded, setLoaded] = useState(null);
  const [ctxTick, setCtxTick] = useState(0);
  const [recentRows, setRecentRows] = useState(recent);
  const [editInput, setEditInput] = useState(null);
  const [pickedNo, setPickedNo] = useState(null);
  const [cancelling, setCancelling] = useState(null);
  const [pending, startTransition] = useTransition();
  const reload = useMemo(() => ({ current: null }), []);
  const fresh = mode === "new";

  const styles = options.orders.filter((o) => o.po_no === order.po);
  const picked = options.orders.find((o) => o.value === order.order_id);
  const colours = picked?.colours ?? [];
  const colour = colours.find((c) => c.colour === order.colour);
  const orderQty = colour ? colour.qty : picked?.total_qty;
  const qty = Number(card.serial_to) >= Number(card.serial_from) && card.serial_from !== "" && card.serial_to !== "" ? Number(card.serial_to) - Number(card.serial_from) + 1 : null;
  const company = (typeof document !== "undefined" ? document.getElementById("erp-print-context")?.dataset.company ?? "" : "").toUpperCase();

  useEffect(() => {
    // A PO with one style picks it; a style with one colour picks that too.
    if (styles.length === 1 && order.order_id !== styles[0].value) pickStyle(styles[0].value);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [order.po]);

  // Where this PO's colour stands: sizes cut, the next cutting number, the cuttings started, the serial each size carries on from.
  useEffect(() => {
    if (!order.order_id || !order.colour) return undefined;
    let live = true;
    const key = `${order.order_id}|${order.colour}`;
    const timer = setTimeout(async () => {
      const [res, ins] = await Promise.all([act.context({ order_id: order.order_id, colour: order.colour }), act.inputs({ order_id: order.order_id, colour: order.colour })]);
      if (live) setLoaded({ key, data: res?.status === "success" ? res.data : null, inputs: ins?.status === "success" ? ins.data : [] });
    }, 250);
    return () => {
      live = false;
      clearTimeout(timer);
    };
  }, [order.order_id, order.colour, ctxTick, act]);

  // Only what was read for the order and colour on screen now.
  const ctx = loaded && loaded.key === `${order.order_id}|${order.colour}` ? loaded.data : null;
  // The cuttings of the PO's colour on screen, or the latest of all.
  const rows = (loaded && loaded.key === `${order.order_id}|${order.colour}` ? loaded.inputs : null) ?? recentRows;
  const pickedInput = rows.find((r) => r.input_no === pickedNo) ?? null;

  function afterInput() {
    setCtxTick((n) => n + 1);
    startTransition(async () => {
      const res = await act.inputs({});
      if (res?.status === "success") setRecentRows(res.data);
    });
  }

  function afterOutput(row, made) {
    setLoaded((l) => (l ? { ...l, inputs: l.inputs.map((r) => (r.input_no === row.input_no ? row : r)) } : l));
    setRecentRows((rs) => rs.map((r) => (r.input_no === row.input_no ? row : r)));
    if (made) {
      reload.current?.();
      refreshToday();
      router.refresh();
    }
  }

  function editCutting(r) {
    const o = options.orders.find((x) => x.value === r.order_id);
    setOrder({ po: r.po_no, order_id: r.order_id, colour: r.colour, garment: r.garment ?? o?.garment ?? "", fabric_type: r.fabric_type ?? "", gsm: r.gsm ?? "", dia_width: r.dia_width ?? "" });
    setEditInput(r);
    window.scrollTo?.({ top: 0, behavior: "smooth" });
  }

  function cancelCutting() {
    startTransition(async () => {
      const res = await act.cancelInput(cancelling.input_no);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w("input.cancelled", { no: cancelling.input_no }), type: "success" });
      setCancelling(null);
      afterInput();
    });
  }

  function pickStyle(id) {
    const o = options.orders.find((x) => x.value === id);
    setOrder((f) => ({ ...f, order_id: id, garment: o?.garment ?? "", colour: o?.colours.some((c) => c.colour === f.colour) ? f.colour : o?.colours.length === 1 ? o.colours[0].colour : "" }));
  }

  function clear() {
    setCard(freshCard());
    setEditing(null);
  }

  function refreshToday() {
    startTransition(async () => {
      const res = await act.list({ from: options.today, to: options.today });
      if (res?.status === "success") setToday(res.data);
    });
  }

  function submit() {
    startTransition(async () => {
      const res = await act.save(editing, {
        order_id: blank(order.order_id),
        colour: blank(order.colour),
        garment: blank(order.garment),
        fabric_type: blank(order.fabric_type),
        gsm: blank(order.gsm),
        dia_width: blank(order.dia_width),
        cutting_no: blank(card.cutting_no) === null ? null : Number(card.cutting_no),
        size: blank(card.size),
        serial_from: blank(card.serial_from) === null ? null : Number(card.serial_from),
        serial_to: blank(card.serial_to) === null ? null : Number(card.serial_to),
        parts_name: blank(card.parts_name),
        batch_no: blank(card.batch_no),
      });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w(editing ? "entry.updated" : "entry.added", { no: res.data.bundle_no, qty: res.data.qty }), type: "success" });
      setPreview(res.data);
      // The next card carries on from the serial this one ended at.
      setCard((c) => (editing ? freshCard() : { ...c, serial_from: String(res.data.serial_to + 1), serial_to: "" }));
      setEditing(null);
      setCtxTick((n) => n + 1);
      reload.current?.();
      refreshToday();
      router.refresh();
    });
  }

  function edit(r) {
    const o = options.orders.find((x) => x.value === r.order_id);
    setOrder({ po: r.po_no, order_id: r.order_id, colour: r.colour, garment: r.garment ?? o?.garment ?? "", fabric_type: r.fabric_type ?? "", gsm: r.gsm ?? "", dia_width: r.dia_width ?? "" });
    setCard({ cutting_no: String(r.cutting_no), size: r.size, serial_from: String(r.serial_from), serial_to: String(r.serial_to), parts_name: r.parts_name, batch_no: r.batch_no });
    setEditing(r.bundle_no);
    setPreview(r);
    window.scrollTo?.({ top: 0, behavior: "smooth" });
  }

  function findSerial() {
    if (!serial) return;
    startTransition(async () => {
      const res = await act.serial({ serial, order_id: order.order_id || undefined, colour: order.colour || undefined, size: card.size || undefined });
      if (res?.status === "success") setFound(res.data);
      else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  const draft = picked && qty ? { bundle_no: editing ?? options.next_bundle_no, barcode: (editing ?? options.next_bundle_no).replaceAll("-", ""), cut_date: options.today, po_no: picked.po_no, style: picked.style, buyer: picked.buyer, colour: order.colour, cutting_no: card.cutting_no, size: card.size.toUpperCase(), serial_from: card.serial_from, serial_to: card.serial_to, qty, parts_name: card.parts_name, batch_no: card.batch_no.toUpperCase() } : null;
  const shown = draft ?? preview;
  const bySize = (today.rows ?? []).filter((r) => r.status !== "CANCELLED").reduce((m, r) => ({ ...m, [r.size]: (m[r.size] ?? 0) + r.qty }), {});
  const setO = (k) => (v) => setOrder((f) => ({ ...f, [k]: v }));
  const setC = (k) => (v) => setCard((f) => ({ ...f, [k]: v }));

  return (
    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div className="flex min-w-0 flex-col gap-4">
        <Section n={1} title={w("entry.order")} right={<span className="text-sm">{w("entry.date")} : <b>{dmy(options.today)}</b></span>}>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <F label={w("f.po")} required>
              <Pick id="cut-po-options" list={[...new Set(options.orders.map((o) => o.po_no))]} value={order.po} onChange={(v) => setOrder((f) => ({ ...freshOrder(), po: v, colour: f.colour }))} placeholder={w("f.po_ph")} disabled={!!editing} aria-label={w("f.po")} />
            </F>
            <F label={w("f.style")} required hint={styles.length ? null : w("f.style_hint")}>
              <select value={order.order_id} onChange={(e) => pickStyle(e.target.value)} className={nativeSelect} disabled={!styles.length || !!editing} aria-label={w("f.style")}>
                <option value="">{styles.length ? w("f.select") : w("f.style_hint")}</option>
                {styles.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.style}
                  </option>
                ))}
              </select>
            </F>
            <F label={w("f.buyer")}>
              <Input value={picked?.buyer ?? ""} placeholder={w("f.auto")} readOnly />
            </F>
            <F label={w("f.colour")} required>
              {colours.length ? (
                <select value={order.colour} onChange={(e) => setO("colour")(e.target.value)} className={nativeSelect} disabled={!!editing} aria-label={w("f.colour")}>
                  <option value="">{w("f.select")}</option>
                  {colours.map((c) => (
                    <option key={c.colour} value={c.colour}>
                      {c.colour}
                    </option>
                  ))}
                </select>
              ) : (
                <Input value={order.colour} onChange={(e) => setO("colour")(e.target.value)} placeholder={w("f.auto")} disabled={!!editing} aria-label={w("f.colour")} />
              )}
            </F>
            <F label={w("f.garment")}>
              <Pick id="cut-garment-options" list={options.garments} value={order.garment} onChange={setO("garment")} placeholder={w("f.auto")} />
            </F>
            <F label={w("f.fabric_type")}>
              <Pick id="cut-fabric-type-options" list={options.fabric_types} value={order.fabric_type} onChange={setO("fabric_type")} placeholder={w("f.auto")} />
            </F>
            <F label={w("f.gsm")}>
              <Pick id="cut-gsm-options" list={options.gsm} value={order.gsm} onChange={setO("gsm")} placeholder={w("f.auto")} />
            </F>
            <F label={w("f.dia_width")}>
              <Pick id="cut-dia-width-options" list={options.dia_width} value={order.dia_width} onChange={setO("dia_width")} placeholder={w("f.auto")} />
            </F>
            <F label={w("f.order_qty")}>
              <Input value={orderQty ?? ""} placeholder={w("f.auto")} readOnly />
            </F>
          </div>
        </Section>

        <Section n={2} title={w(editing ? "entry.bundle" : fresh ? "input.title" : "output.section")} right={editing ? <span className="text-sm">{w("entry.bundle_no")} : <b className="font-mono">{editing}</b></span> : null}>
          {!editing && fresh ? (
            <InputForm key={editInput?.input_no ?? "new"} order={order} ctx={ctx} options={options} canManage={canManage} act={act} editing={editInput} onSaved={() => { setEditInput(null); afterInput(); }} onClose={() => setEditInput(null)} />
          ) : !editing ? (
            <div className="flex flex-col gap-4">
              <p className="text-[13px] text-foreground-muted">{w("output.pick_hint")}</p>
              <InputsTable rows={rows.filter((r) => r.status !== "CANCELLED")} picked={pickedNo} onPick={(r) => setPickedNo(r.input_no)} />
              {pickedInput ? <OutputPanel key={`${pickedInput.input_no}-${pickedInput.status}-${pickedInput.bundled_qty}`} input={pickedInput} canManage={canManage} act={act} onChanged={afterOutput} /> : null}
              {!rows.length && order.order_id && order.colour ? (
                <p className="rounded-md border border-dashed border-border px-3 py-2 text-sm text-foreground-muted">
                  {w("bentry.none")} <Link href="/cutting-floor/new-cutting" className="font-semibold text-brand hover:underline">{w("bentry.start")}</Link>
                </p>
              ) : null}
            </div>
          ) : (
            <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <F label={w("f.cutting_no")} required>
              <Input type="number" min="1" value={card.cutting_no} onChange={(e) => setC("cutting_no")(e.target.value)} placeholder="1, 2, 3…" aria-label={w("f.cutting_no")} />
            </F>
            <F label={w("f.bundle_no")}>
              <Input value={editing ?? options.next_bundle_no} readOnly className="font-mono font-semibold" />
            </F>
            <F label={w("f.size")} required>
              <Pick id="cut-size-options" list={colour?.sizes ?? []} value={card.size} onChange={setC("size")} placeholder={w("f.select_type")} aria-label={w("f.size")} />
            </F>
            <F label={w("f.serial_from")} required>
              <Input type="number" min="1" value={card.serial_from} onChange={(e) => setC("serial_from")(e.target.value)} aria-label={w("f.serial_from")} />
            </F>
            <F label={w("f.serial_to")} required>
              <Input type="number" min="1" value={card.serial_to} onChange={(e) => setC("serial_to")(e.target.value)} aria-label={w("f.serial_to")} />
            </F>
            <F label={w("f.total_qty")}>
              <Input value={qty ?? "—"} readOnly className="text-center font-semibold" />
            </F>
            <F label={w("f.parts")} required>
              <Pick id="cut-parts-options" list={options.parts} value={card.parts_name} onChange={setC("parts_name")} placeholder={w("f.select_type")} aria-label={w("f.parts")} />
            </F>
            <F label={w("f.batch")} required>
              <Pick id="cut-batch-options" list={options.batches} value={card.batch_no} onChange={setC("batch_no")} placeholder={w("f.select_type")} aria-label={w("f.batch")} />
            </F>
          </div>
          {canManage ? (
            <div className="mt-4 flex gap-2">
              <Button variant="outline" onClick={submit} loading={pending && !!editing} disabled={!editing || pending}>
                <Pencil /> {w("entry.update")}
              </Button>
              <Button variant="outline" className="text-danger" onClick={clear} disabled={pending}>
                <Trash2 /> {w("entry.clear")}
              </Button>
            </div>
          ) : null}
            </>
          )}
        </Section>

        {fresh ? (
          <Section n={3} title={w("input.list_title")} tone="bg-info">
            <InputsTable rows={rows} canManage={canManage} onEdit={editCutting} onCancel={setCancelling} picked={editInput?.input_no} />
          </Section>
        ) : (
          <Section n={3} title={w("list.title")} tone="bg-info">
            <BundleList initial={initial} today={options.today} canManage={canManage} act={act} onEdit={canManage ? edit : undefined} onPreview={setPreview} embedded onChanged={reload} />
          </Section>
        )}
      </div>

      {fresh ? (
        <div className="flex flex-col gap-4">
          <Section n={4} title={w("fab.title")} tone="bg-success">
            <FabricPanel fabric={order.order_id && order.colour ? ctx?.fabric : null} />
          </Section>
          <ConfirmDialog open={cancelling !== null} onOpenChange={(o) => !o && setCancelling(null)} title={cancelling ? w("input.cancel_title", { no: cancelling.input_no }) : ""} description={w("input.cancel_text")} confirmLabel={w("input.cancel_confirm")} cancelLabel={w("list.keep")} destructive loading={pending} onConfirm={cancelCutting} />
        </div>
      ) : (
      <div className="flex flex-col gap-4">
        <Section
          n={4}
          title={w("preview.title")}
          tone="bg-success"
          right={
            canManage && preview?.bundle_no && !draft ? (
              <Button size="sm" variant="outline" className="bg-white/10 text-white hover:bg-white/20" onClick={() => printBundleCards([preview], { size: "50x75", company, words: Object.fromEntries(["po", "style", "buyer", "colour", "bundle", "cutting", "size", "from", "to", "qty", "parts", "batch", "date", "pcs"].map((k) => [k, w(`card.${k}`)]).concat([["title", w("print.title")], ["print", w("print.print")], ["close", w("print.close")]])) })}>
                <Printer /> {w("list.print")}
              </Button>
            ) : null
          }
        >
          <CardPreview card={shown} company={company} w={w} />
          {shown ? (
            <p className="mt-2 text-xs text-foreground-muted">
              {draft ? w("preview.draft") : <span className={cn("rounded-full px-2 py-0.5 font-semibold", TONE[shown.status])}>{w(`status.${shown.status}`)}</span>}
              {!draft && preview ? (
                <button type="button" className="ml-2 underline" onClick={() => setPreview(null)}>
                  {w("preview.back")}
                </button>
              ) : null}
            </p>
          ) : null}
        </Section>

        <Section n={5} title={w("summary.title")} tone="bg-warning">
          <div className="grid grid-cols-2 gap-2">
            <div className="rounded-md border border-border p-3 text-center">
              <p className="text-2xl font-bold tabular-nums">{today.bundles}</p>
              <p className="text-xs text-foreground-muted">{w("summary.bundles")}</p>
            </div>
            <div className="rounded-md border border-border p-3 text-center">
              <p className="text-2xl font-bold tabular-nums">{today.pcs}</p>
              <p className="text-xs text-foreground-muted">{w("summary.pcs")}</p>
            </div>
          </div>
          {Object.keys(bySize).length ? (
            <ul className="mt-3 flex flex-wrap gap-1.5 text-xs">
              {Object.entries(bySize).map(([s, n]) => (
                <li key={s} className="rounded-md bg-surface-muted px-2 py-1">
                  {s}: <b className="tabular-nums">{n}</b>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-3 text-center text-xs text-foreground-muted">{w("summary.none")}</p>
          )}
        </Section>

        <Section n={6} title={w("serial.title")} tone="bg-brand">
          <div className="flex gap-2">
            <Input type="number" min="1" value={serial} onChange={(e) => setSerial(e.target.value)} onKeyDown={(e) => e.key === "Enter" && findSerial()} placeholder={w("serial.ph")} aria-label={w("serial.label")} />
            <Button onClick={findSerial} disabled={!serial || pending}>
              <Search /> {w("list.search")}
            </Button>
          </div>
          <p className="mt-1.5 text-xs text-foreground-muted">{w("serial.hint")}</p>
          {found ? (
            found.length ? (
              <ul className="mt-2 flex flex-col gap-1 text-sm">
                {found.map((b) => (
                  <li key={b.bundle_no}>
                    <button type="button" className="w-full rounded-md border border-border px-2 py-1.5 text-left hover:bg-surface-muted" onClick={() => setPreview(b)}>
                      <b className="font-mono">{b.bundle_no}</b> · {b.po_no} · {b.colour} · {b.size} · {b.parts_name} · {b.serial_from}–{b.serial_to}
                    </button>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-2 text-sm text-foreground-muted">{w("serial.none", { serial })}</p>
            )
          ) : null}
        </Section>
      </div>
      )}
    </div>
  );
}

export { BundleEntry, BundleList };
