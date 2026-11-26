"use client";

import { useState, useTransition } from "react";
import { Ban, CheckCheck, Pencil, Plus, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { firstError, blank, dmy, useWords, F, Pick, nativeSelect } from "./shared";

/*
 * Cutting Input → Cutting Output → bundle cards (the user's design of
 * 2026-11-16). Fabric is counted by length — the company's yards or metres —
 * with its kilos beside it, never the two added. New Cutting is the cutting
 * master's: the fabric this PO's colour needs, has had issued to cutting and
 * still holds (FabricPanel), and the cutting laid — part, fabric lot, marker
 * length × plies = the lay, the size ratio (InputForm). Bundle Entry is the
 * output clerk's: pick a cutting, record what was really cut and the fabric
 * used, and cut the bundle cards from that output (OutputPanel).
 */

const fmt = (v) => Number(v).toLocaleString("en-US", { maximumFractionDigits: 3 });
const kg = (v) => (v === null || v === undefined || v === "" ? "—" : `${fmt(v)} kg`);
const int = (v) => Number(v ?? 0).toLocaleString("en-US");
const STATUS_TONE = { INPUT: "bg-warning/15 text-warning", OUTPUT: "bg-success/15 text-success", CANCELLED: "bg-danger/15 text-danger" };
const METRES = { YDS: 0.9144, MTR: 1 };

/** The length unit's words ("yds" / "গজ"), and a length written in it. */
function useLen(unit = "YDS") {
  const w = useWords();
  const u = w(`len.${unit}`);
  return { u, len: (v) => (v === null || v === undefined || v === "" ? "—" : `${fmt(v)} ${u}`) };
}

/** Kilos of a length of this fabric: metres × GSM × open width (inch → m) ÷ 1000 — as the server works it. */
function kgOf(length, unit, gsm, dia) {
  const g = parseFloat(String(gsm ?? "").replace(/[^0-9.]/g, ""));
  const d = parseFloat(String(dia ?? "").replace(/[^0-9.]/g, ""));
  if (!(Number(length) > 0) || !(g > 0) || !(d > 0)) return null;
  return Math.round(Number(length) * METRES[unit] * g * d * 0.0254) / 1000;
}

/** The fabric of this PO's colour: needed (bookings), issued to cutting, used by its cuttings, still held — by length, kilos beside. */
function FabricPanel({ fabric }) {
  const w = useWords();
  const { len } = useLen(fabric?.unit);
  if (!fabric) return <p className="text-sm text-foreground-muted">{w("newcut.pick_order")}</p>;
  const tiles = [
    ["fab.required", fabric.required_len, fabric.required_kg, "text-foreground", fabric.required_len_partial],
    ["fab.issued", fabric.issued_len, fabric.issued_kg, "text-info"],
    ["fab.used", fabric.used_len, fabric.used_kg, "text-foreground"],
    ["fab.available", fabric.available_len, fabric.available_kg, fabric.available_len > 0 ? "text-success" : "text-danger"],
  ];
  return (
    <div className="flex flex-col gap-3 text-sm">
      <div className="grid grid-cols-2 gap-2">
        {tiles.map(([k, l, k2, tone, partial]) => (
          <div key={k} className="rounded-md border border-border p-2.5">
            <p className="text-xs text-foreground-muted">{w(k)}</p>
            <p className={cn("text-lg font-bold tabular-nums", tone)}>{len(l)}{partial ? " *" : ""}</p>
            <p className="text-xs text-foreground-muted tabular-nums">{kg(k2)}</p>
          </div>
        ))}
      </div>
      {fabric.required_len_partial ? <p className="text-xs text-warning">* {w("fab.partial")}</p> : null}
      <p className="text-xs text-foreground-muted">
        {fabric.per_piece_len
          ? w("fab.per_piece", { len: len(fabric.per_piece_len), n: int(fabric.pieces_left) })
          : fabric.per_piece_kg
            ? w("fab.per_piece", { len: kg(fabric.per_piece_kg), n: int(fabric.pieces_left) })
            : w("fab.no_booking")}
      </p>
      {fabric.lines.length ? (
        <ul className="flex flex-col gap-1 text-xs">
          {fabric.lines.map((l, i) => (
            <li key={i} className="flex justify-between gap-2 border-b border-border pb-1 last:border-0">
              <span>
                {[l.fabric, l.part].filter(Boolean).join(" · ") || "—"} — {l.consumption} {l.cons_unit.toLowerCase()}/{l.cons_per}{l.cutting_wastage ? ` +${l.cutting_wastage}%` : ""}
              </span>
              <b className="text-right tabular-nums">{len(l.required_len)}<span className="block font-normal text-foreground-muted">{kg(l.required_kg)}</span></b>
            </li>
          ))}
        </ul>
      ) : null}
      {fabric.lots.length ? (
        <div>
          <p className="mb-1 text-xs font-semibold">{w("fab.lots")}</p>
          <ul className="flex flex-col gap-1 text-xs">
            {fabric.lots.map((l) => (
              <li key={l.id} className="flex justify-between gap-2">
                <span className="font-mono">{l.lot_code}{l.lot_no ? ` · ${l.lot_no}` : ""}</span>
                <span className="tabular-nums">{w("fab.lot_line", { received: len(l.received_len), issued: len(l.issued_len) })}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : (
        <p className="text-xs text-foreground-muted">{w("fab.no_lots")}</p>
      )}
      {fabric.purchased?.length ? (
        <div>
          <p className="mb-1 text-xs font-semibold">{w("fab.purchased")}</p>
          <ul className="flex flex-col gap-1 text-xs">
            {fabric.purchased.map((p) => (
              <li key={p.mo_no} className="flex justify-between gap-2">
                <span><span className="font-mono">{p.mo_no}</span> · {p.item}</span>
                <span className="tabular-nums">{fmt(p.qty)} {String(p.uom).toLowerCase()}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}

/** New Cutting's 2: the cutting laid — number, part, batch / lot, marker × plies = the lay, size ratio, and its kilos. */
function InputForm({ order, ctx, options, canManage, act, editing, onSaved, onClose }) {
  const w = useWords();
  const toastManager = useToastManager();
  const unit = ctx?.fabric?.unit ?? editing?.len_unit ?? "YDS";
  const { u, len } = useLen(unit);
  const start = () => ({
    cutting_no: editing ? String(editing.cutting_no) : "",
    parts_name: editing?.parts_name ?? "",
    batch_no: editing?.batch_no ?? "",
    lot_id: editing?.lot_id ?? "",
    marker_length: editing?.marker_length ? String(editing.marker_length) : "",
    plies: editing ? String(editing.plies) : "",
    lay_len: "",
    fabric_kg: editing?.fabric_kg ? String(editing.fabric_kg) : "",
    remarks: editing?.remarks ?? "",
  });
  const [f, setF] = useState(start);
  const [ratio, setRatio] = useState(() => Object.fromEntries((editing?.sizes ?? []).map((s) => [s.size, String(s.ratio)])));
  const [extra, setExtra] = useState(() => (editing?.sizes ?? []).map((s) => s.size).filter((s) => !(ctx?.sizes ?? []).some((x) => x.size === s)));
  const [newSize, setNewSize] = useState("");
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  if (!order.order_id || !order.colour || !ctx) return <p className="text-sm text-foreground-muted">{w("newcut.pick_order")}</p>;

  const sizes = [...ctx.sizes.map((s) => s.size), ...extra];
  const plies = Number(f.plies) || 0;
  const planned = sizes.reduce((m, s) => m + (Number(ratio[s]) || 0) * plies, 0);
  const autoLay = Number(f.marker_length) > 0 && plies > 0 ? Math.round(Number(f.marker_length) * plies * 1000) / 1000 : null;
  const lay = f.lay_len !== "" ? Number(f.lay_len) : autoLay;
  const autoKg = kgOf(lay, unit, order.gsm, order.dia_width);
  const lots = ctx.fabric?.lots ?? [];
  const available = ctx.fabric?.available_len;
  const short = lay && available !== null && available !== undefined && lay > available ? lay - available : null;
  const cuttingNo = f.cutting_no || (editing ? "" : String(ctx.next_cutting_no ?? ""));

  function addSize() {
    const s = newSize.trim().toUpperCase();
    if (s && !sizes.includes(s)) setExtra((e) => [...e, s]);
    setNewSize("");
  }

  function save() {
    startTransition(async () => {
      const res = await act.saveInput(editing?.input_no ?? null, {
        order_id: order.order_id,
        colour: order.colour,
        garment: blank(order.garment),
        fabric_type: blank(order.fabric_type),
        gsm: blank(order.gsm),
        dia_width: blank(order.dia_width),
        cutting_no: Number(cuttingNo) || null,
        parts_name: blank(f.parts_name),
        batch_no: blank(f.batch_no),
        lot_id: blank(f.lot_id),
        marker_length: f.marker_length === "" ? null : Number(f.marker_length),
        plies: plies || null,
        lay_len: f.lay_len === "" ? null : Number(f.lay_len),
        fabric_kg: f.fabric_kg === "" ? null : Number(f.fabric_kg),
        sizes: sizes.filter((s) => Number(ratio[s]) > 0).map((s) => ({ size: s, ratio: Number(ratio[s]) })),
        remarks: blank(f.remarks),
      });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w(editing ? "input.updated" : "input.saved", { no: res.data.input_no, n: int(res.data.planned_qty), len: len(res.data.lay_len) }), type: "success" });
      if (res.data.fabric_short_len) toastManager.add({ title: w("fab.saved_short", { short: len(res.data.fabric_short_len) }), type: "warning" });
      setF(start());
      setRatio({});
      setExtra([]);
      onSaved(res.data);
    });
  }

  return (
    <div className="flex flex-col gap-4">
      {editing ? <p className="rounded-md bg-info-subtle px-3 py-2 text-sm font-semibold text-info">{w("input.editing", { no: editing.input_no })}</p> : null}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <F label={w("f.cutting_no")} required hint={editing ? null : w("newcut.next_hint", { n: ctx.next_cutting_no })}>
          <Input type="number" min="1" value={cuttingNo} onChange={(e) => set("cutting_no")(e.target.value)} aria-label={w("f.cutting_no")} />
        </F>
        <F label={w("f.parts")} required>
          <Pick id="in-parts-options" list={options.parts} value={f.parts_name} onChange={set("parts_name")} placeholder={w("f.select_type")} aria-label={w("f.parts")} />
        </F>
        <F label={w("input.lot")} hint={lots.length ? null : w("fab.no_lots")}>
          <select
            value={f.lot_id}
            onChange={(e) => {
              const lot = lots.find((l) => l.id === e.target.value);
              setF((x) => ({ ...x, lot_id: e.target.value, batch_no: lot ? (lot.lot_no || lot.lot_code) : x.batch_no }));
            }}
            className={nativeSelect}
            disabled={!lots.length}
            aria-label={w("input.lot")}
          >
            <option value="">{w("f.select")}</option>
            {lots.map((l) => (
              <option key={l.id} value={l.id}>
                {l.lot_code}{l.lot_no ? ` · ${l.lot_no}` : ""} — {len(l.issued_len)}
              </option>
            ))}
          </select>
        </F>
        <F label={w("f.batch")} required>
          <Pick id="in-batch-options" list={options.batches} value={f.batch_no} onChange={set("batch_no")} placeholder={w("f.select_type")} aria-label={w("f.batch")} />
        </F>
        <F label={w("input.marker_length", { u })}>
          <Input type="number" min="0" step="0.01" value={f.marker_length} onChange={(e) => set("marker_length")(e.target.value)} aria-label={w("input.marker_length", { u })} />
        </F>
        <F label={w("input.plies")} required>
          <Input type="number" min="1" value={f.plies} onChange={(e) => set("plies")(e.target.value)} aria-label={w("input.plies")} />
        </F>
        <F label={w("input.lay_len", { u })} required hint={autoLay ? w("input.auto_lay", { len: len(autoLay) }) : w("input.lay_hint")}>
          <Input type="number" min="0" step="0.01" value={f.lay_len} onChange={(e) => set("lay_len")(e.target.value)} placeholder={autoLay ? String(autoLay) : ""} aria-label={w("input.lay_len", { u })} />
        </F>
        <F label={w("input.fabric_kg")} hint={autoKg ? w("input.auto_kg", { kg: kg(autoKg) }) : w("input.kg_hint")}>
          <Input type="number" min="0" step="0.001" value={f.fabric_kg} onChange={(e) => set("fabric_kg")(e.target.value)} placeholder={autoKg ? String(autoKg) : ""} aria-label={w("input.fabric_kg")} />
        </F>
        <F label={w("input.remarks")} className="sm:col-span-2 lg:col-span-4">
          <Input value={f.remarks} onChange={(e) => set("remarks")(e.target.value)} aria-label={w("input.remarks")} />
        </F>
      </div>
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              {["col.size", "newcut.order", "newcut.cut", "newcut.balance", "input.ratio", "input.planned"].map((k, i) => (
                <th key={k} className={cn("px-3 py-2 font-semibold", i > 0 && "text-right")}>{w(k)}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {sizes.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-3 py-4 text-center text-foreground-muted">{w("newcut.no_sizes")}</td>
              </tr>
            ) : (
              sizes.map((s) => {
                const o = ctx.sizes.find((x) => x.size === s);
                return (
                  <tr key={s} className="border-t border-border">
                    <td className="px-3 py-1.5 font-semibold">{s}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{o?.order_qty ?? "—"}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{o?.cut ?? 0}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{o?.order_qty == null ? "—" : Math.max(0, o.order_qty - o.cut)}</td>
                    <td className="px-3 py-1.5 text-right">
                      <Input type="number" min="0" value={ratio[s] ?? ""} onChange={(e) => setRatio((r) => ({ ...r, [s]: e.target.value }))} className="ml-auto h-8 w-24 text-right" aria-label={w("input.ratio_size", { size: s })} />
                    </td>
                    <td className="px-3 py-1.5 text-right font-semibold tabular-nums">{(Number(ratio[s]) || 0) * plies || "—"}</td>
                  </tr>
                );
              })
            )}
          </tbody>
          <tfoot>
            <tr className="border-t border-border bg-surface-muted font-semibold">
              <td className="px-3 py-2" colSpan={5}>
                <span className="flex items-center gap-2">
                  <Input value={newSize} onChange={(e) => setNewSize(e.target.value)} onKeyDown={(e) => e.key === "Enter" && addSize()} placeholder={w("newcut.size_ph")} className="h-8 w-28" aria-label={w("newcut.size_ph")} />
                  <Button size="sm" variant="outline" onClick={addSize} disabled={!newSize.trim()}>
                    <Plus /> {w("newcut.add_size")}
                  </Button>
                </span>
              </td>
              <td className="px-3 py-2 text-right tabular-nums">{planned || "—"}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      {lay ? (
        <p className={cn("rounded-md px-3 py-2 text-[13px] font-semibold", short ? "bg-danger/10 text-danger" : "bg-success/10 text-success")}>
          {short ? w("fab.short", { lay: len(lay), short: len(short) }) : w("fab.enough", { lay: len(lay) })}
        </p>
      ) : null}
      {canManage ? (
        <div className="flex gap-2">
          <Button onClick={save} loading={pending} disabled={pending || planned === 0}>
            <Save /> {w(editing ? "input.update" : "input.save", { n: int(planned) })}
          </Button>
          <Button variant="outline" className="text-danger" onClick={() => { setF(start()); setRatio({}); setExtra([]); onClose?.(); }} disabled={pending}>
            <Trash2 /> {w(editing ? "input.close_edit" : "entry.clear")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

/** The cuttings laid on this PO's colour (or the latest): planned, output, carded, the fabric by length, who did what. */
function InputsTable({ rows, canManage, onEdit, onCancel, onPick, picked }) {
  const w = useWords();
  const { len } = useLen(rows[0]?.len_unit);
  if (!rows.length) return <p className="py-4 text-center text-sm text-foreground-muted">{w("input.none")}</p>;
  return (
    <div className="overflow-x-auto rounded-md border border-border">
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-brand text-left text-brand-foreground">
            {["input.no", "col.date", "col.po", "col.colour", "col.cutting_no", "col.parts", "input.plies", "input.planned", "input.output", "input.bundled", "input.fabric", "col.status", ""].map((k, i) => (
              <th key={i} className={cn("px-3 py-2 font-semibold whitespace-nowrap", i >= 6 && i <= 10 && "text-right")}>{k ? w(k) : ""}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.input_no} className={cn("border-t border-border", picked === r.input_no && "bg-brand-subtle")}>
              <td className="px-3 py-1.5 font-mono font-semibold whitespace-nowrap">{r.input_no}</td>
              <td className="px-3 py-1.5 whitespace-nowrap">{dmy(r.cut_date)}</td>
              <td className="px-3 py-1.5">{r.po_no}</td>
              <td className="px-3 py-1.5">{r.colour}</td>
              <td className="px-3 py-1.5">{r.cutting_no}</td>
              <td className="px-3 py-1.5">{r.parts_name}</td>
              <td className="px-3 py-1.5 text-right tabular-nums">{r.plies}</td>
              <td className="px-3 py-1.5 text-right tabular-nums">{int(r.planned_qty)}</td>
              <td className="px-3 py-1.5 text-right tabular-nums">{r.output_qty === null ? "—" : int(r.output_qty)}</td>
              <td className="px-3 py-1.5 text-right tabular-nums">{int(r.bundled_qty)}</td>
              <td className="px-3 py-1.5 text-right tabular-nums whitespace-nowrap">
                {len(r.used_len ?? r.lay_len)}
                <span className="block text-xs text-foreground-muted">{kg(r.used_kg ?? r.fabric_kg)}</span>
              </td>
              <td className="px-3 py-1.5">
                <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", STATUS_TONE[r.status])}>{w(`input.status.${r.status}`)}</span>
              </td>
              <td className="px-3 py-1.5">
                <span className="flex justify-end gap-1.5">
                  {onPick ? (
                    <Button size="sm" variant="outline" onClick={() => onPick(r)} disabled={r.status === "CANCELLED"}>
                      {w("input.pick")}
                    </Button>
                  ) : null}
                  {canManage && onEdit && r.status === "INPUT" ? (
                    <Button size="sm" variant="outline" className="px-2" onClick={() => onEdit(r)} aria-label={w("input.edit", { no: r.input_no })} title={w("input.edit", { no: r.input_no })}>
                      <Pencil />
                    </Button>
                  ) : null}
                  {canManage && onCancel && r.status !== "CANCELLED" && r.bundled_qty === 0 ? (
                    <Button size="sm" variant="outline" className="px-2 text-danger" onClick={() => onCancel(r)} aria-label={w("input.cancel", { no: r.input_no })} title={w("input.cancel", { no: r.input_no })}>
                      <Ban />
                    </Button>
                  ) : null}
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/**
 * Bundle Entry's 2: the cutting picked, its output — good and rejected pieces
 * per size, the fabric really used and left by length (kilos beside) — and its
 * bundle cards cut from that output; past the output it asks first.
 */
function OutputPanel({ input, canManage, act, onChanged }) {
  const w = useWords();
  const toastManager = useToastManager();
  const { u, len } = useLen(input.len_unit);
  const done = input.status === "OUTPUT";
  const [out, setOut] = useState(() => Object.fromEntries(input.sizes.map((s) => [s.size, { output_qty: String(s.output_qty ?? s.planned_qty), reject_qty: String(s.reject_qty ?? 0) }])));
  const [fabric, setFabric] = useState({
    used_len: String(input.used_len ?? input.lay_len ?? ""),
    used_kg: input.used_kg === null || input.used_kg === undefined ? "" : String(input.used_kg),
    remnant_len: input.remnant_len === null || input.remnant_len === undefined ? "" : String(input.remnant_len),
    remarks: input.output_remarks ?? "",
  });
  const [cards, setCards] = useState(() => Object.fromEntries(input.sizes.map((s) => [s.size, String(Math.max(0, (s.output_qty ?? 0) - s.bundled_qty) || "")])));
  const [per, setPer] = useState("25");
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const bundleSizes = input.sizes.filter((s) => Number(cards[s.size]) > 0).map((s) => ({ size: s.size, qty: Number(cards[s.size]) }));
  const cardCount = bundleSizes.reduce((m, s) => m + Math.ceil(s.qty / Math.max(1, Number(per) || 1)), 0);
  // Kilos follow the length at the lay's own kilos per unit length, unless weighed.
  const kgPerLen = input.lay_len && input.fabric_kg ? input.fabric_kg / input.lay_len : null;
  const autoKg = kgPerLen && Number(fabric.used_len) > 0 ? Math.round(Number(fabric.used_len) * kgPerLen * 1000) / 1000 : null;

  function saveOutput() {
    startTransition(async () => {
      const res = await act.saveOutput(input.input_no, {
        sizes: input.sizes.map((s) => ({ size: s.size, output_qty: Number(out[s.size]?.output_qty) || 0, reject_qty: Number(out[s.size]?.reject_qty) || 0 })),
        used_len: fabric.used_len === "" ? null : Number(fabric.used_len),
        used_kg: fabric.used_kg === "" ? null : Number(fabric.used_kg),
        remnant_len: fabric.remnant_len === "" ? null : Number(fabric.remnant_len),
        remarks: blank(fabric.remarks),
      });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w("output.saved", { no: input.input_no, n: int(res.data.output_qty) }), type: "success" });
      setCards(Object.fromEntries(res.data.sizes.map((s) => [s.size, String(Math.max(0, (s.output_qty ?? 0) - s.bundled_qty) || "")])));
      onChanged(res.data);
    });
  }

  function makeCards(confirm = false) {
    startTransition(async () => {
      const res = await act.cardsFromOutput(input.input_no, { pcs_per_bundle: Number(per) || null, sizes: bundleSizes, confirm_over: confirm });
      if (res?.status !== "success") {
        if (res?.errors?.confirm_over) return setAsking(res.errors.confirm_over[0]);
        return toastManager.add({ title: firstError(res), type: "danger" });
      }
      setAsking(null);
      toastManager.add({ title: w("newcut.created", { n: res.data.count, qty: res.data.qty, add: res.data.additional }), type: "success" });
      setCards(Object.fromEntries(res.data.input.sizes.map((s) => [s.size, String(Math.max(0, (s.output_qty ?? 0) - s.bundled_qty) || "")])));
      onChanged(res.data.input, res.data);
    });
  }

  const th = (k, right) => <th key={k} className={cn("px-3 py-2 font-semibold", right && "text-right")}>{w(k)}</th>;
  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm">
        <b className="font-mono">{input.input_no}</b> · {input.po_no} · {input.colour} · {w("col.cutting_no")} {input.cutting_no} · {input.parts_name} · {w("f.batch")} {input.batch_no} · {w("input.plies")} {input.plies} · {len(input.lay_len)}
        {input.fabric_kg ? ` (${kg(input.fabric_kg)})` : ""}
        {input.created_by ? <span className="text-foreground-muted"> — {w("input.by", { name: input.created_by })}</span> : null}
      </p>

      <div>
        <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold">
          <CheckCheck className="size-4" /> {w("output.title")}
          {done ? <span className="rounded-full bg-success/15 px-2 py-0.5 text-xs text-success">{w("output.done", { name: input.output_by ?? "—" })}</span> : null}
        </h3>
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {th("col.size")}
                {th("input.planned", true)}
                {th("output.good", true)}
                {th("output.reject", true)}
              </tr>
            </thead>
            <tbody>
              {input.sizes.map((s) => (
                <tr key={s.size} className="border-t border-border">
                  <td className="px-3 py-1.5 font-semibold">{s.size}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{s.planned_qty}</td>
                  {["output_qty", "reject_qty"].map((k) => (
                    <td key={k} className="px-3 py-1.5 text-right">
                      <Input type="number" min="0" value={out[s.size]?.[k] ?? ""} onChange={(e) => setOut((o) => ({ ...o, [s.size]: { ...o[s.size], [k]: e.target.value } }))} className="ml-auto h-8 w-24 text-right" disabled={!canManage} aria-label={w(k === "output_qty" ? "output.good_size" : "output.reject_size", { size: s.size })} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="mt-3 grid gap-3 sm:grid-cols-4">
          <F label={w("output.used_len", { u })} required hint={w("output.laid", { len: len(input.lay_len) })}>
            <Input type="number" min="0" step="0.01" value={fabric.used_len} onChange={(e) => setFabric((x) => ({ ...x, used_len: e.target.value }))} disabled={!canManage} aria-label={w("output.used_len", { u })} />
          </F>
          <F label={w("output.used_kg")} hint={autoKg ? w("input.auto_kg", { kg: kg(autoKg) }) : null}>
            <Input type="number" min="0" step="0.001" value={fabric.used_kg} onChange={(e) => setFabric((x) => ({ ...x, used_kg: e.target.value }))} placeholder={autoKg ? String(autoKg) : ""} disabled={!canManage} aria-label={w("output.used_kg")} />
          </F>
          <F label={w("output.remnant_len", { u })}>
            <Input type="number" min="0" step="0.01" value={fabric.remnant_len} onChange={(e) => setFabric((x) => ({ ...x, remnant_len: e.target.value }))} disabled={!canManage} aria-label={w("output.remnant_len", { u })} />
          </F>
          <F label={w("input.remarks")}>
            <Input value={fabric.remarks} onChange={(e) => setFabric((x) => ({ ...x, remarks: e.target.value }))} disabled={!canManage} aria-label={w("output.remarks")} />
          </F>
        </div>
        {canManage ? (
          <Button className="mt-3" onClick={saveOutput} loading={pending} disabled={pending}>
            <Save /> {w(done ? "output.update" : "output.save")}
          </Button>
        ) : null}
      </div>

      <div>
        <h3 className="mb-2 text-sm font-semibold">{w("output.cards_title")}</h3>
        {!done ? (
          <p className="rounded-md border border-dashed border-border px-3 py-2 text-sm text-foreground-muted">{w("output.first")}</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    {th("col.size")}
                    {th("output.good", true)}
                    {th("input.bundled", true)}
                    {th("newcut.balance", true)}
                    {th("output.to_card", true)}
                  </tr>
                </thead>
                <tbody>
                  {input.sizes.map((s) => {
                    const left = (s.output_qty ?? 0) - s.bundled_qty;
                    const over = Number(cards[s.size] || 0) > left;
                    return (
                      <tr key={s.size} className="border-t border-border">
                        <td className="px-3 py-1.5 font-semibold">{s.size}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{s.output_qty ?? 0}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{s.bundled_qty}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{Math.max(0, left)}</td>
                        <td className="px-3 py-1.5 text-right">
                          <Input type="number" min="0" value={cards[s.size] ?? ""} onChange={(e) => setCards((c) => ({ ...c, [s.size]: e.target.value }))} className={cn("ml-auto h-8 w-24 text-right", over && "border-danger text-danger")} disabled={!canManage} aria-label={w("output.to_card_size", { size: s.size })} />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            {canManage ? (
              <div className="mt-3 flex flex-wrap items-end gap-3">
                <F label={w("newcut.per")} required>
                  <Input type="number" min="1" value={per} onChange={(e) => setPer(e.target.value)} className="w-32" aria-label={w("newcut.per")} />
                </F>
                <Button onClick={() => makeCards(false)} loading={pending} disabled={pending || cardCount === 0}>
                  <Plus /> {w("newcut.create", { n: cardCount })}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </div>
      <ConfirmDialog open={asking !== null} onOpenChange={(o) => !o && setAsking(null)} title={w("output.over_title")} description={asking ?? ""} confirmLabel={w("output.over_confirm")} cancelLabel={w("list.keep")} loading={pending} onConfirm={() => makeCards(true)} />
    </div>
  );
}

export { FabricPanel, InputForm, InputsTable, OutputPanel };
