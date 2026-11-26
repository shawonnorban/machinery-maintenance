"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { Ban, Boxes, CalendarCheck, CheckCheck, CircleCheck, Clock, Download, FileChartColumn, ListChecks, PackagePlus, RotateCcw, Search, Ship, Truck, Users } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { useToastManager } from "@/components/ui/toast";
import { Chip, Panel, at, downloadCsv, firstError, nativeSelect, num, useWords } from "@/components/assembly/finishing";

/*
 * Assembly Floor → Shipment (AnnotechERP 20/07), as Brickland lays its five
 * screens out under the Dashboard / Shipment Entry / Mark as Shipped /
 * History / Reports buttons: the Shipment Dashboard (eight key figures, the
 * filters and every shipment with its actions), Shipment Entry (pick a PO /
 * colour from Ready for Shipment, enter the shipment, Open Shipments), Mark
 * as Shipped (the Ready formula, confirmed and planned shipments) and
 * Shipment History; the reports reuse the packing report screen.
 */

const STATUSES = ["PLANNED", "CONFIRMED", "SHIPPED", "CANCELLED", "REVERSED"];
const STATUS_TONE = {
  PLANNED: "bg-brand-subtle text-brand",
  CONFIRMED: "bg-info-subtle text-info",
  SHIPPED: "bg-success/15 text-success",
  CANCELLED: "bg-surface-muted text-foreground-muted",
  REVERSED: "bg-danger/15 text-danger",
  COMPLETED: "bg-info-subtle text-info",
};
const READY_TONE = { READY: "bg-success/15 text-success", PARTIALLY_SHIPPED: "bg-warning/15 text-warning", SHIPMENT_COMPLETED: "bg-info-subtle text-info" };
const day = (s) => {
  if (!s) return "—";
  const [y, m, d] = String(s).slice(0, 10).split("-");
  return `${d}-${m}-${y}`;
};

/** The five buttons over every shipment screen. */
function ShipmentNav({ active }) {
  const w = useWords();
  const items = [
    ["dashboard", "shipment-dashboard", "sh.nav.dashboard", Ship],
    ["entry", "shipment-entry", "sh.nav.entry", PackagePlus],
    ["mark", "shipment-mark-shipped", "sh.nav.mark", CheckCheck],
    ["history", "shipment-history", "sh.nav.history", ListChecks],
    ["reports", "shipment-reports", "sh.nav.reports", FileChartColumn],
  ];
  return (
    <nav className="mb-4 flex flex-wrap gap-2" aria-label={w("sh.nav.label")}>
      {items.map(([key, slug, label, Icon]) => (
        <Link
          key={key}
          href={`/assembly-floor/${slug}`}
          aria-current={key === active ? "page" : undefined}
          className={cn(
            "flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[13px] font-semibold transition-colors",
            key === active ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          <Icon className="size-4" /> {w(label)}
        </Link>
      ))}
    </nav>
  );
}

function Empty({ children }) {
  return <p className="px-5 py-8 text-center text-sm text-foreground-muted">{children}</p>;
}

function Notice({ result }) {
  if (!result) return null;
  return (
    <p role={result.ok ? "status" : "alert"} className={cn("mx-5 mt-3 rounded-md border px-3 py-2 text-sm", result.ok ? "border-success/40 bg-success/10 text-success" : "border-danger/40 bg-danger/10 text-danger")}>
      {result.text}
    </p>
  );
}

function StatusChip({ row }) {
  const w = useWords();
  const done = row.status === "SHIPPED" && row.remaining_after === 0;
  return <Chip tone={STATUS_TONE[done ? "COMPLETED" : row.status]}>{w(done ? "sh.status.COMPLETED" : `sh.status.${row.status}`)}</Chip>;
}

const csvHead = (w) => ["date", "shipment_no", "buyer", "po", "style", "colour", "qty", "cartons", "ready_before", "remaining", "destination", "status"].map((k) => w(`sh.col.${k}`));
const csvRows = (w, rows) => rows.map((r) => [day(r.ship_date), r.shipment_no, r.buyer, r.po_no, r.style, r.colour, r.qty, r.carton_qty ?? "", r.ready_before ?? "", r.remaining_after ?? "", r.destination ?? "", w(`sh.status.${r.status}`)]);

/** The shipments table, with Confirm / Mark as Shipped / Correct / Cancel as the status and the viewer allow. */
function ShipmentTable({ rows, can, act, onChanged, empty }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [result, setResult] = useState(null);
  const [pending, startTransition] = useTransition();
  const run = (fn, okText) =>
    startTransition(async () => {
      const res = await fn();
      if (res?.status !== "success") return setResult({ ok: false, text: firstError(res) });
      const text = typeof okText === "function" ? okText(res.data) : okText;
      setResult({ ok: true, text });
      toastManager.add({ title: text, type: "success" });
      onChanged?.();
    });
  const confirmRow = (r) => run(() => act.confirm(r.id), w("toast.sh_confirmed", { no: r.shipment_no }));
  const ship = (r) => {
    if (window.confirm(w("sh.ship_confirm", { no: r.shipment_no, qty: num(r.qty) }))) run(() => act.ship(r.id), (d) => w("toast.sh_shipped", { no: d.shipment_no, left: num(d.remaining_after) }));
  };
  const correct = (r) => {
    const reason = window.prompt(w("sh.correct_why", { no: r.shipment_no, qty: num(r.qty) }));
    if (!reason) return;
    const qty = window.prompt(w("sh.correct_qty"), "") ?? "";
    run(() => act.correct(r.id, { reason, corrected_qty: qty.trim() ? Number(qty) : null }), (d) => (d.corrected ? w("toast.sh_corrected", { no: d.reversed.shipment_no, new: d.corrected.shipment_no }) : w("toast.sh_reversed", { no: d.reversed.shipment_no })));
  };
  const cancel = (r) => {
    const reason = window.prompt(w("sh.cancel_why", { no: r.shipment_no }));
    if (reason) run(() => act.cancel(r.id, reason), w("toast.sh_cancelled", { no: r.shipment_no }));
  };
  if (rows.length === 0) return <Empty>{empty}</Empty>;
  return (
    <>
      <Notice result={result} />
      <div className="overflow-x-auto px-5 py-4">
        <table className="w-full min-w-[64rem] border border-border text-sm">
          <thead className="bg-surface-muted text-xs text-foreground-muted">
            <tr>
              {["sl", "ref", "buyer", "po", "style", "colour", "qty", "cartons", "ready_before", "remaining", "date", "destination", "status", "action"].map((k) => (
                <th key={k} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium whitespace-nowrap", ["sl", "qty", "cartons", "ready_before", "remaining"].includes(k) && "text-right")}>
                  {w(`sh.col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((r, i) => (
              <tr key={r.id} className="hover:bg-surface-muted">
                <td className="border border-border px-2 py-1.5 text-right tabular-nums">{i + 1}</td>
                <td className="border border-border px-2 py-1.5 font-mono text-xs" title={w("sh.entered_by", { name: r.created_by ?? "—" }) + (r.confirmed_by ? ` · ${w("sh.confirmed_by", { name: r.confirmed_by })}` : "") + (r.shipped_by ? ` · ${w("sh.shipped_by", { name: r.shipped_by, at: at(r.shipped_at) })}` : "")}>
                  {r.shipment_no}
                </td>
                <td className="border border-border px-2 py-1.5">{r.buyer ?? "—"}</td>
                <td className="border border-border px-2 py-1.5">{r.po_no ?? "—"}</td>
                <td className="border border-border px-2 py-1.5">{r.style ?? "—"}</td>
                <td className="border border-border px-2 py-1.5">{r.colour}</td>
                <td className="border border-border px-2 py-1.5 text-right font-semibold tabular-nums">{num(r.qty)}</td>
                <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.carton_qty)}</td>
                <td className="border border-border px-2 py-1.5 text-right tabular-nums">{r.ready_before === null ? "—" : num(r.ready_before)}</td>
                <td className="border border-border px-2 py-1.5 text-right tabular-nums">{r.remaining_after === null ? "—" : num(r.remaining_after)}</td>
                <td className="border border-border px-2 py-1.5 whitespace-nowrap">{day(r.ship_date)}</td>
                <td className="border border-border px-2 py-1.5">
                  {r.destination ?? "—"}
                  {r.mode ? (
                    <span className="block text-xs text-foreground-muted">
                      {w(`sh.mode.${r.mode}`)}
                      {r.vehicle_ref ? ` · ${r.vehicle_ref}` : ""}
                    </span>
                  ) : null}
                </td>
                <td className="border border-border px-2 py-1.5">
                  <StatusChip row={r} />
                  {r.cancel_reason ? <span className="block text-xs text-foreground-muted">{r.cancel_reason}</span> : null}
                  {r.reverse_reason ? <span className="block text-xs text-foreground-muted">{w("sh.reversed_note", { by: r.reversed_by ?? "—", reason: r.reverse_reason })}</span> : null}
                  {r.corrects_no ? <span className="block text-xs text-foreground-muted">{w("sh.corrects", { no: r.corrects_no })}</span> : null}
                </td>
                <td className="border border-border px-2 py-1.5 whitespace-nowrap">
                  <div className="flex gap-1.5">
                    {r.status === "PLANNED" && can.ship ? (
                      <Button size="sm" variant="outline" disabled={pending} onClick={() => confirmRow(r)}>
                        <CalendarCheck /> {w("sh.confirm")}
                      </Button>
                    ) : null}
                    {r.status === "CONFIRMED" && can.ship ? (
                      <Button size="sm" disabled={pending} onClick={() => ship(r)}>
                        <Ship /> {w("sh.mark")}
                      </Button>
                    ) : null}
                    {r.status === "SHIPPED" && can.supervise ? (
                      <Button size="sm" variant="outline" disabled={pending} onClick={() => correct(r)}>
                        <RotateCcw /> {w("sh.correct")}
                      </Button>
                    ) : null}
                    {(r.status === "PLANNED" && can.ship) || (r.status === "CONFIRMED" && can.supervise) ? (
                      <Button size="icon" variant="ghost" className="size-8" aria-label={w("sh.cancel", { no: r.shipment_no })} disabled={pending} onClick={() => cancel(r)}>
                        <Ban className="size-3.5" />
                      </Button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}

/** Search, status and ship-date filters for the dashboard and the history. */
function ShipmentFilter({ onSearch, pending }) {
  const w = useWords();
  const [f, setF] = useState({ q: "", status: "", from: "", to: "" });
  return (
    <section className="rounded-lg border border-border bg-surface">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          onSearch(f);
        }}
        className="grid grid-cols-2 gap-3 px-5 py-4 md:grid-cols-6"
      >
        <label className="col-span-2 flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("sh.f.search")}
          <Input value={f.q} maxLength={60} placeholder={w("sh.f.search_ph")} onChange={(e) => setF({ ...f, q: e.target.value })} className="h-10" />
        </label>
        <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("sh.f.status")}
          <select className={nativeSelect} value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}>
            <option value="">{w("sh.f.all")}</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {w(`sh.f.status_${s}`)}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("sh.f.from")}
          <Input type="date" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} className="h-10" />
        </label>
        <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("sh.f.to")}
          <Input type="date" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} className="h-10" />
        </label>
        <div className="flex items-end">
          <Button type="submit" className="w-full justify-center" disabled={pending}>
            <Search /> {w("sh.f.run")}
          </Button>
        </div>
      </form>
    </section>
  );
}

/** The Shipment Dashboard. */
function ShipmentDashboard({ initial, can, act }) {
  const w = useWords();
  const [view, setView] = useState(initial);
  const [filter, setFilter] = useState({});
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const load = (f) =>
    startTransition(async () => {
      const res = await act.dashboard(f);
      if (res?.status === "success") {
        setView(res.data);
        setError(null);
      } else setError(firstError(res));
    });
  const k = view.kpis;
  const tiles = [
    ["ready", `${num(k.ready_for_shipment)} ${w("rs.pcs")}`, null, Truck, "brand"],
    ["today", `${num(k.today_shipment)} ${w("rs.pcs")}`, null, Ship, "info"],
    ["shipped", `${num(k.total_shipped)} ${w("rs.pcs")}`, null, CircleCheck, "success"],
    ["pending", `${num(k.pending_shipment)} ${w("rs.pcs")}`, w("sh.kpi.pending_sub"), Clock, "warning"],
    ["partial", num(k.partial_shipment), w("sh.kpi.rows_sub"), PackagePlus, "info"],
    ["completed", num(k.shipment_completed), w("sh.kpi.rows_sub"), CalendarCheck, "success"],
    ["cartons", num(k.total_cartons), w("sh.kpi.cartons_sub"), Boxes, "brand"],
    ["buyers", num(k.total_buyers), null, Users, "danger"],
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label={w("rs.kpi.label")}>
        {tiles.map(([key, value, sub, Icon, tone]) => (
          <StatCard key={key} label={w(`sh.kpi.${key}`)} value={value} supportingText={sub} icon={<Icon />} tone={tone} />
        ))}
      </section>
      <ShipmentFilter
        pending={pending}
        onSearch={(f) => {
          setFilter(f);
          load(f);
        }}
      />
      <Panel
        icon={Ship}
        title={w("sh.list.title")}
        sub={w("sh.list.sub")}
        action={
          <Button size="sm" variant="outline" disabled={!view.shipments.length} onClick={() => downloadCsv("shipments", csvHead(w), csvRows(w, view.shipments))}>
            <Download /> {w("rs.csv")}
          </Button>
        }
      >
        {error ? <Notice result={{ ok: false, text: error }} /> : null}
        <ShipmentTable rows={view.shipments} can={can} act={act} onChanged={() => load(filter)} empty={w("sh.list.empty")} />
      </Panel>
    </div>
  );
}

/** Shipment History: every shipment with the Ready balance it left. */
function ShipmentHistory({ initial, can, act }) {
  const w = useWords();
  const [rows, setRows] = useState(initial.rows);
  const [filter, setFilter] = useState({});
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const load = (f) =>
    startTransition(async () => {
      const res = await act.history(f);
      if (res?.status === "success") {
        setRows(res.data.rows);
        setError(null);
      } else setError(firstError(res));
    });
  return (
    <div className="flex flex-col gap-4">
      <ShipmentFilter
        pending={pending}
        onSearch={(f) => {
          setFilter(f);
          load(f);
        }}
      />
      <Panel
        icon={ListChecks}
        title={w("sh.hist.title")}
        sub={w("sh.hist.sub", { n: rows.length })}
        action={
          <Button size="sm" variant="outline" disabled={!rows.length} onClick={() => downloadCsv("shipment-history", csvHead(w), csvRows(w, rows))}>
            <Download /> {w("rs.csv")}
          </Button>
        }
      >
        {error ? <Notice result={{ ok: false, text: error }} /> : null}
        <ShipmentTable rows={rows} can={can} act={act} onChanged={() => load(filter)} empty={w("sh.list.empty")} />
      </Panel>
    </div>
  );
}

/** Mark as Shipped: the Ready formula, confirmed shipments to ship and planned ones to confirm. */
function MarkShipped({ initial, can, act }) {
  const w = useWords();
  const [open, setOpen] = useState(initial.open);
  const [, startTransition] = useTransition();
  const reload = () =>
    startTransition(async () => {
      const res = await act.entry();
      if (res?.status === "success") setOpen(res.data.open);
    });
  const confirmed = open.filter((s) => s.status === "CONFIRMED");
  const planned = open.filter((s) => s.status === "PLANNED");
  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface px-5 py-4 text-sm text-foreground-muted">
        <p className="font-semibold text-foreground">{w("sh.mk.formula")}</p>
        <p className="mt-1">{w("sh.mk.rule")}</p>
      </section>
      <Panel icon={Ship} title={w("sh.mk.confirmed")} sub={w("sh.n_shipments", { n: confirmed.length })}>
        <ShipmentTable rows={confirmed} can={can} act={act} onChanged={reload} empty={w("sh.mk.confirmed_empty")} />
      </Panel>
      <Panel icon={CalendarCheck} title={w("sh.mk.planned")} sub={w("sh.n_shipments", { n: planned.length })}>
        <ShipmentTable rows={planned} can={can} act={act} onChanged={reload} empty={w("sh.mk.planned_empty")} />
      </Panel>
    </div>
  );
}

/** Shipment Entry: pick a PO / colour with Ready quantity, enter the shipment (never above what is available). */
function ShipmentEntry({ initial, pick, can, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [view, setView] = useState(initial);
  const [key, setKey] = useState(() => initial.ready.find((r) => pick && r.po_no === pick.po && r.colour === pick.colour)?.key ?? "");
  const row = useMemo(() => view.ready.find((r) => r.key === key) ?? null, [key, view.ready]);
  const blank = { qty: "", carton_qty: "", ship_date: initial.today, destination: "", mode: "SEA", vehicle_ref: "", remarks: "" };
  const [form, setForm] = useState(blank);
  const [result, setResult] = useState(null);
  const [pending, startTransition] = useTransition();
  const qty = Number(form.qty) || 0;
  const over = row && qty > row.available_qty;
  const reload = () =>
    startTransition(async () => {
      const res = await act.entry();
      if (res?.status === "success") setView(res.data);
    });
  const submit = (e) => {
    e.preventDefault();
    if (!row) return;
    startTransition(async () => {
      const res = await act.create({ key: row.key, ...form, qty, carton_qty: form.carton_qty === "" ? null : Number(form.carton_qty), destination: form.destination || row.destination || null });
      if (res?.status !== "success") return setResult({ ok: false, text: firstError(res) });
      const text = w(res.data.partial ? "toast.sh_created_partial" : "toast.sh_created", { no: res.data.shipment_no, qty: num(res.data.qty) });
      setResult({ ok: true, text });
      toastManager.add({ title: text, type: "success" });
      setForm({ ...form, qty: "", carton_qty: "", vehicle_ref: "", remarks: "" });
      reload();
    });
  };
  const tile = (label, value) => (
    <div key={label} className="rounded-lg bg-surface-muted px-3 py-2">
      <p className="text-[11px] text-foreground-muted">{label}</p>
      <p className="truncate font-semibold">{value ?? "—"}</p>
    </div>
  );
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 xl:grid-cols-2">
        <Panel icon={Truck} title={w("sh.en.pick_title")} sub={w("sh.en.pick_sub", { n: view.ready.length })}>
          {view.ready.length === 0 ? (
            <Empty>{w("sh.en.pick_empty")}</Empty>
          ) : (
            <div className="max-h-[30rem] overflow-auto px-5 py-4">
              <table className="w-full border border-border text-sm">
                <thead className="sticky top-0 bg-surface-muted text-xs text-foreground-muted">
                  <tr>
                    {["po", "buyer_style", "colour", "ready", "available", "status"].map((k) => (
                      <th key={k} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium", ["ready", "available"].includes(k) && "text-right")}>
                        {w(`sh.en.col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {view.ready.map((r) => (
                    <tr key={r.key} className={cn("cursor-pointer hover:bg-surface-muted", r.key === key && "bg-brand-subtle/60")} onClick={() => setKey(r.key)}>
                      <td className="border border-border px-2 py-1.5 font-semibold">
                        <label className="flex items-center gap-2">
                          <input type="radio" name="ready-row" checked={r.key === key} onChange={() => setKey(r.key)} /> {r.po_no}
                        </label>
                      </td>
                      <td className="border border-border px-2 py-1.5">
                        {r.buyer}
                        <span className="block text-xs text-foreground-muted">{r.style}</span>
                      </td>
                      <td className="border border-border px-2 py-1.5">{r.colour}</td>
                      <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.ready_qty)}</td>
                      <td className="border border-border px-2 py-1.5 text-right font-semibold tabular-nums">{num(r.available_qty)}</td>
                      <td className="border border-border px-2 py-1.5">
                        <Chip tone={READY_TONE[r.status]}>{w(`rs.status.${r.status}`)}</Chip>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
        <Panel icon={PackagePlus} title={w("sh.en.title")} sub={w("sh.en.sub")}>
          <div className="p-5">
            {row ? (
              <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                  {tile(w("sh.en.buyer"), row.buyer)}
                  {tile(w("sh.en.po"), row.po_no)}
                  {tile(w("sh.en.style"), row.style)}
                  {tile(w("sh.en.colour"), row.colour)}
                  {tile(w("sh.en.size"), row.sizes.join(", "))}
                  {tile(w("sh.en.po_date"), day(row.ship_date))}
                  {tile(w("sh.en.ready"), num(row.ready_qty))}
                  {tile(w("sh.en.reserved"), num(row.reserved_qty))}
                  {tile(w("sh.en.cartons"), num(row.cartons))}
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.qty", { max: num(row.available_qty) })}
                    <Input inputMode="numeric" value={form.qty} onChange={(e) => setForm({ ...form, qty: e.target.value.replace(/\D/g, "").slice(0, 7) })} className="h-10 text-right tabular-nums" />
                  </label>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.carton_qty")}
                    <Input inputMode="numeric" value={form.carton_qty} onChange={(e) => setForm({ ...form, carton_qty: e.target.value.replace(/\D/g, "").slice(0, 6) })} className="h-10 text-right tabular-nums" />
                  </label>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.date")}
                    <Input type="date" value={form.ship_date} onChange={(e) => setForm({ ...form, ship_date: e.target.value })} className="h-10" />
                  </label>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.destination")}
                    <Input value={form.destination} maxLength={120} placeholder={row.destination ?? ""} onChange={(e) => setForm({ ...form, destination: e.target.value })} className="h-10" />
                  </label>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.mode")}
                    <select className={nativeSelect} value={form.mode} onChange={(e) => setForm({ ...form, mode: e.target.value })}>
                      {["SEA", "AIR", "ROAD"].map((m) => (
                        <option key={m} value={m}>
                          {w(`sh.mode.${m}`)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.vehicle")}
                    <Input value={form.vehicle_ref} maxLength={80} onChange={(e) => setForm({ ...form, vehicle_ref: e.target.value })} className="h-10" />
                  </label>
                  <label className="col-span-full flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                    {w("sh.en.remarks")}
                    <Input value={form.remarks} maxLength={300} onChange={(e) => setForm({ ...form, remarks: e.target.value })} className="h-10" />
                  </label>
                </div>
                <div className="grid grid-cols-3 gap-2 rounded-lg bg-surface-muted p-3 text-center text-sm">
                  <div>
                    <p className="text-xs text-foreground-muted">{w("sh.en.before")}</p>
                    <p className="font-semibold tabular-nums">{num(row.ready_qty)}</p>
                  </div>
                  <div>
                    <p className="text-xs text-foreground-muted">{w("sh.en.shipment")}</p>
                    <p className="font-semibold tabular-nums">{num(qty)}</p>
                  </div>
                  <div>
                    <p className="text-xs text-foreground-muted">{w("sh.en.remaining")}</p>
                    <p className={cn("font-semibold tabular-nums", over && "text-danger")}>{num(row.ready_qty - qty)}</p>
                  </div>
                </div>
                {over ? <p className="text-sm text-danger">{w("sh.en.over", { n: num(row.available_qty) })}</p> : null}
                <Button type="submit" size="lg" className="justify-center" disabled={pending || !can.ship || qty <= 0 || over}>
                  <PackagePlus /> {qty > 0 && qty < row.ready_qty ? w("sh.en.create_partial") : w("sh.en.create")}
                </Button>
                {!can.ship ? <p className="text-sm text-warning">{w("sh.en.read_only")}</p> : null}
              </form>
            ) : (
              <Empty>{w("sh.en.pick_first")}</Empty>
            )}
            {result ? (
              <p role={result.ok ? "status" : "alert"} className={cn("mt-3 rounded-md border px-3 py-2 text-sm", result.ok ? "border-success/40 bg-success/10 text-success" : "border-danger/40 bg-danger/10 text-danger")}>
                {result.text}
              </p>
            ) : null}
          </div>
        </Panel>
      </div>
      <Panel icon={Ship} title={w("sh.en.open_title")} sub={w("sh.en.open_sub")}>
        <ShipmentTable rows={view.open} can={can} act={act} onChanged={reload} empty={w("sh.en.open_empty")} />
      </Panel>
    </div>
  );
}

export { ShipmentNav, ShipmentDashboard, ShipmentEntry, MarkShipped, ShipmentHistory };
