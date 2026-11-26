"use client";

import { useCallback, useEffect, useMemo, useRef, useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowDown,
  ArrowRight,
  Barcode,
  Boxes,
  CircleCheck,
  CircleX,
  Clock,
  Download,
  Eye,
  FileChartColumn,
  Gauge,
  LayoutGrid,
  Lock,
  LockOpen,
  Package,
  PackageCheck,
  PackageOpen,
  Play,
  Printer,
  RotateCcw,
  Route,
  ScanBarcode,
  ScanLine,
  Search,
  Ship,
  Shirt,
  Stamp,
  Timer,
  Trash2,
  Truck,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { StatCard } from "@/components/ui/stat-card";
import { useToastManager } from "@/components/ui/toast";
import { barcodeSvg } from "@/lib/bundle-label";
import { printDocument } from "@/lib/print-document";
import { markingHtml, printCartonMarking, printPolyLabels } from "@/lib/packing-print";
import { Chip, Facts, Panel, at, downloadCsv, firstError, nativeSelect, num, useWords } from "@/components/assembly/finishing";
import { ampm } from "@/lib/clock";

/*
 * Assembly Floor → Packing (AnnotechERP 20/04) and its Packing Process
 * (20/05), as Brickland lays its eleven screens out under one row of
 * buttons: the Packing Dashboard (the flow from QC Pass to Shipment, nine key
 * figures, Packing on the Floor, Ready for Shipment, Shipment and Barcode
 * Traceability); Packing In (Section › Floor › Packing Line / Location, the
 * scan, what loads, the Packing In Qty, Open Packing Batches and today's
 * scans a supervisor may remove); the seven process desks (Find a Batch, the
 * queue and today's completions beside the batch with its processes, the
 * step's own panel — poly labels, cartons, marking, sealing — and Complete);
 * Packing Out (the scan, every check, the final packed quantity and the
 * Auto Transfer to Ready for Shipment); and the eleven Packing Reports.
 */

const STEPS = ["FOLDING", "POLY", "LABEL", "ASSORTMENT", "CARTON", "MARKING", "SEALING"];
const STEP_ICON = { FOLDING: Shirt, POLY: PackageOpen, LABEL: Barcode, ASSORTMENT: LayoutGrid, CARTON: Boxes, MARKING: Stamp, SEALING: Lock };
const STEP_SLUG = {
  FOLDING: "packing-folding",
  POLY: "packing-poly",
  LABEL: "packing-barcode-label",
  ASSORTMENT: "packing-assortment",
  CARTON: "packing-carton",
  MARKING: "packing-carton-marking",
  SEALING: "packing-carton-sealing",
};
const STEP_TONE = { PENDING: "bg-surface-muted text-foreground-muted", IN_PROGRESS: "bg-warning/15 text-warning", COMPLETED: "bg-success/15 text-success" };
const STAGE_TONE = {
  PACKING_IN: "bg-info-subtle text-info",
  IN_PROGRESS: "bg-warning/15 text-warning",
  READY_OUT: "bg-success/15 text-success",
  PACKED_OUT: "bg-success/15 text-success",
  CANCELLED: "bg-danger/15 text-danger",
};
const FLOW_TONE = { COMPLETED: "bg-success/15 text-success", IN_PROGRESS: "bg-info-subtle text-info", PENDING: "bg-warning/15 text-warning", READY: "bg-success/15 text-success", SHIPPED: "bg-info-subtle text-info" };
const CARTON_TONE = { OPEN: "bg-surface-muted text-foreground-muted", MARKED: "bg-info-subtle text-info", SEALED: "bg-success/15 text-success" };
const storeKey = "packing-in-location";
const pcs = (w, n) => w("fin.dash.pcs", { n: num(n) });
const day = (s) => {
  if (!s) return "—";
  const [y, m, d] = String(s).slice(0, 10).split("-");
  return `${d}-${m}-${y}`;
};

/** Brickland's row of eleven buttons over every packing screen. */
function PackingNav({ active }) {
  const w = useWords();
  const items = [
    ["dashboard", "packing-dashboard", "pk.nav.dashboard", Gauge],
    ["in", "packing-in", "pk.nav.in", ScanBarcode],
    ...STEPS.map((s) => [s, STEP_SLUG[s], `pk.nav.${s}`, STEP_ICON[s]]),
    ["out", "packing-out", "pk.nav.out", PackageCheck],
    ["reports", "packing-reports", "pk.nav.reports", FileChartColumn],
  ];
  return (
    <nav className="mb-4 flex flex-wrap gap-2" aria-label={w("pk.nav.label")}>
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

/** The seven processes of a batch as seven small bars. */
function StepStrip({ steps }) {
  const w = useWords();
  return (
    <ol className="flex gap-0.5" aria-label={w("pk.processes")}>
      {steps.map((s) => (
        <li key={s.step} title={`${w(`pk.step.${s.step}`)}: ${w(`pk.step_status.${s.status}`)}`} className={cn("h-1.5 w-5 rounded-full", s.status === "COMPLETED" ? "bg-success" : s.status === "IN_PROGRESS" ? "bg-warning" : "bg-surface-muted ring-1 ring-border")}>
          <span className="sr-only">
            {w(`pk.step.${s.step}`)}: {w(`pk.step_status.${s.status}`)}
          </span>
        </li>
      ))}
    </ol>
  );
}

function StageChip({ batch }) {
  const w = useWords();
  const label = batch.stage === "IN_PROGRESS" && batch.current_step ? w("pk.stage.at", { step: w(`pk.step.${batch.current_step}`) }) : w(`pk.stage.${batch.stage}`);
  return <Chip tone={STAGE_TONE[batch.stage]}>{label}</Chip>;
}

function StepChip({ status }) {
  const w = useWords();
  return <Chip tone={STEP_TONE[status]}>{w(`pk.step_status.${status}`)}</Chip>;
}

/** A saved or refused action's line under a form. */
function Notice({ result }) {
  if (!result) return null;
  return (
    <p role={result.ok ? "status" : "alert"} className={cn("flex items-start gap-2 rounded-md border px-3 py-2 text-sm", result.ok ? "border-success/40 bg-success/10 text-success" : "border-danger/40 bg-danger/10 text-danger")}>
      {result.ok ? <CircleCheck className="mt-0.5 size-4 shrink-0" /> : <CircleX className="mt-0.5 size-4 shrink-0" />}
      <span>{result.text}</span>
    </p>
  );
}

function Empty({ children }) {
  return <p className="px-5 py-8 text-center text-sm text-foreground-muted">{children}</p>;
}

/** A plain bordered table; cells are text, numbers (right-aligned) or nodes. */
function Grid({ head, rows, foot, minWidth = "40rem", numeric = [] }) {
  return (
    <div className="overflow-x-auto px-5 pb-5">
      <table className="w-full border border-border text-sm" style={{ minWidth }}>
        <thead className="bg-surface-muted text-xs text-foreground-muted">
          <tr>
            {head.map((h, i) => (
              <th key={i} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium whitespace-nowrap", numeric.includes(i) && "text-right")}>
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={r.key ?? i} className="hover:bg-surface-muted">
              {r.cells.map((c, j) => (
                <td key={j} className={cn("border border-border px-2 py-1.5", numeric.includes(j) && "text-right tabular-nums")}>
                  {c ?? "—"}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        {foot ? (
          <tfoot className="bg-surface-muted text-xs font-semibold">
            <tr>
              {foot.map((c, i) => (
                <td key={i} className={cn("border border-border px-2 py-1.5", numeric.includes(i) && "text-right tabular-nums")}>
                  {c}
                </td>
              ))}
            </tr>
          </tfoot>
        ) : null}
      </table>
    </div>
  );
}

function printWords(w) {
  return {
    print: w("pk.print.print"),
    close: w("pk.print.close"),
    po: w("pk.print.po"),
    size: w("pk.print.size"),
    qty: w("pk.print.qty"),
    dest: w("pk.print.dest"),
    labels: w("pk.print.labels"),
    marking: w("pk.print.marking"),
    buyer: w("pk.f.buyer"),
    poNo: w("pk.f.po_no"),
    styleNo: w("pk.print.style_no"),
    colour: w("pk.f.colour"),
    sizeQty: w("pk.print.size_qty"),
    cartonQty: w("pk.print.carton_qty"),
    cartonNo: w("pk.print.carton_no"),
    nOf: w("pk.print.n_of"),
    grossNet: w("pk.print.gross_net"),
    destination: w("pk.mk.destination"),
    country: w("pk.mk.country"),
    instruction: w("pk.print.instruction"),
    pcs: w("pk.print.pcs"),
  };
}

/* ───────────────────────────── Dashboard ───────────────────────────── */

const FLOW_ICON = { qc: CircleCheck, in: ScanBarcode, ...STEP_ICON, out: PackageCheck, ready: Truck, shipment: Ship };
const FLOW_SLUG = { qc: "packing-in", in: "packing-in", ...STEP_SLUG, out: "packing-out", ready: "ready-dashboard", shipment: "shipment-dashboard" };

/** The Packing Dashboard: QC Pass to Shipment on one board. */
function PackingDashboard({ initial, act }) {
  const w = useWords();
  const { kpis } = initial;
  const tiles = [
    ["today", kpis.today_packing, w("pk.kpi.today_sub", { date: day(initial.today) }), Package, "brand", true],
    ["in", kpis.packing_in, w("pk.kpi.in_sub"), ScanBarcode, "info", true],
    ["progress", kpis.in_progress, w("pk.kpi.progress_sub"), Timer, "warning", true],
    ["completed", kpis.completed, w("pk.kpi.completed_sub", { n: num(kpis.packing_out_today) }), PackageCheck, "success", true],
    ["cartons", kpis.total_cartons, w("pk.kpi.cartons_sub"), Boxes, "brand", false],
    ["pending", kpis.pending_packing, w("pk.kpi.pending_sub"), Clock, "danger", true],
    ["ready", kpis.ready_for_shipment, w("pk.kpi.ready_sub"), Truck, "success", true],
    ["shipped", kpis.total_shipped, w("pk.kpi.shipped_sub"), Ship, "info", true],
    ["total", kpis.total_packing_qty, w("pk.kpi.total_sub"), LayoutGrid, "brand", true],
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface">
        <ol className="flex items-stretch gap-1 overflow-x-auto p-3" aria-label={w("pk.flow")}>
          {initial.pipeline.map((s, i) => {
            const Icon = FLOW_ICON[s.key] ?? Package;
            return (
              <li key={s.key} className="flex shrink-0 items-center gap-1">
                <Link href={`/assembly-floor/${FLOW_SLUG[s.key]}`} className="flex w-28 flex-col items-center gap-1.5 rounded-lg px-2 py-2 text-center transition-colors hover:bg-surface-muted">
                  <span className="flex size-10 items-center justify-center rounded-xl bg-brand-subtle text-brand">
                    <Icon className="size-5" />
                  </span>
                  <span className="text-xs leading-tight font-semibold">{w(`pk.flow_stage.${s.key}`)}</span>
                  <span className="text-[11px] text-foreground-muted tabular-nums">{pcs(w, s.qty)}</span>
                  <Chip tone={FLOW_TONE[s.status]}>{w(`pk.flow_status.${s.status}`)}</Chip>
                </Link>
                {i < initial.pipeline.length - 1 ? <ArrowRight className="size-3.5 shrink-0 text-foreground-muted" /> : null}
              </li>
            );
          })}
        </ol>
      </section>

      <section className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label={w("pk.kpi.label")}>
        {tiles.map(([key, value, sub, Icon, tone, isPcs]) => (
          <StatCard key={key} label={w(`pk.kpi.${key}`)} value={isPcs ? pcs(w, value) : num(value)} supportingText={sub} icon={<Icon />} tone={tone} />
        ))}
      </section>

      <Panel icon={Package} title={w("pk.floor.title")} sub={w("pk.floor.sub")}>
        {initial.active.length === 0 ? (
          <Empty>{w("pk.floor.empty")}</Empty>
        ) : (
          <div className="pt-4">
            <Grid
              minWidth="60rem"
              numeric={[5, 6]}
              head={[w("pk.col.packing_no"), w("pk.f.buyer"), w("pk.col.po"), w("pk.f.style"), w("pk.f.colour"), w("pk.col.packing_in"), w("pk.col.cartons"), w("pk.processes"), w("pk.col.progress"), w("pk.col.stage")]}
              rows={initial.active.map((b) => ({
                key: b.id,
                cells: [
                  <span key="n" className="font-mono text-xs font-semibold">
                    {b.packing_no}
                  </span>,
                  b.buyer,
                  b.po_no,
                  b.style,
                  b.colour,
                  num(b.packing_in_qty),
                  num(b.cartons),
                  <StepStrip key="s" steps={b.steps} />,
                  <span key="p" className="flex items-center gap-2">
                    <span className="h-2 w-24 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow={b.progress_pct} aria-valuemin={0} aria-valuemax={100} aria-label={w("pk.col.progress_of", { no: b.packing_no })}>
                      <span className="block h-full rounded-full bg-success" style={{ width: `${b.progress_pct}%` }} />
                    </span>
                    <span className="text-xs tabular-nums">{b.progress_pct}%</span>
                  </span>,
                  <StageChip key="c" batch={b} />,
                ],
              }))}
            />
          </div>
        )}
      </Panel>

      <div className="grid gap-4 xl:grid-cols-2">
        <Panel
          icon={Truck}
          title={w("pk.ready.title")}
          sub={w("pk.ready.sub")}
          action={
            <Link href="/assembly-floor/ready-dashboard" className="text-xs font-medium text-brand hover:underline">
              {w("pk.ready.open")}
            </Link>
          }
        >
          {initial.ready.length === 0 ? (
            <Empty>{w("pk.ready.empty")}</Empty>
          ) : (
            <div className="pt-4">
              <Grid
                numeric={[4, 5]}
                head={[w("pk.f.buyer"), w("pk.col.po"), w("pk.f.style"), w("pk.f.colour"), w("pk.col.ready"), w("pk.col.cartons"), w("pk.col.ship_date"), w("pk.col.status")]}
                rows={initial.ready.map((r) => ({
                  key: r.key,
                  cells: [r.buyer, r.po_no, r.style, r.colour, <b key="q">{num(r.ready_qty)}</b>, num(r.cartons), day(r.ship_date), <Chip key="s" tone={r.status === "PARTIALLY_SHIPPED" ? FLOW_TONE.PENDING : FLOW_TONE.READY}>{w(`rs.status.${r.status}`)}</Chip>],
                }))}
              />
            </div>
          )}
        </Panel>
        <Panel
          icon={Ship}
          title={w("pk.ship.title")}
          sub={w("pk.ship.sub")}
          action={
            <Link href="/assembly-floor/shipment-dashboard" className="text-xs font-medium text-brand hover:underline">
              {w("pk.ready.open")}
            </Link>
          }
        >
          {initial.shipments.length === 0 ? (
            <Empty>{w("pk.ship.empty")}</Empty>
          ) : (
            <div className="pt-4">
              <Grid
                numeric={[4]}
                head={[w("pk.ship.col.no"), w("pk.f.buyer"), w("pk.col.po"), w("pk.f.style"), w("pk.col.qty"), w("pk.ship.col.date"), w("pk.col.status")]}
                rows={initial.shipments.map((s) => ({
                  key: s.id,
                  cells: [
                    <span key="n" className="font-mono text-xs">
                      {s.shipment_no}
                    </span>,
                    s.buyer,
                    s.po_no,
                    s.style,
                    num(s.qty),
                    <span key="d" title={at(s.shipped_at)}>
                      {day(s.ship_date)}
                    </span>,
                    <Chip key="c" tone={FLOW_TONE[s.status === "SHIPPED" ? "SHIPPED" : "PENDING"]}>
                      {w(s.status === "SHIPPED" && s.remaining_after === 0 ? "sh.status.COMPLETED" : `sh.status.${s.status}`)}
                    </Chip>,
                  ],
                }))}
              />
            </div>
          )}
        </Panel>
      </div>

      <PackingTrace act={act} />
    </div>
  );
}

/** Barcode Traceability — a bundle's, batch's, carton's or label's whole history through packing. */
function PackingTrace({ act }) {
  const w = useWords();
  const [code, setCode] = useState("");
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const run = (e) => {
    e.preventDefault();
    if (!code.trim()) return;
    startTransition(async () => {
      const res = await act.trace(code.trim());
      if (res?.status === "success") {
        setData(res.data);
        setError(null);
      } else {
        setData(null);
        setError(firstError(res));
      }
    });
  };
  const params = (p) => Object.fromEntries(Object.entries(p).map(([k, v]) => [k, k === "step" ? w(`pk.step.${v}`) : (v ?? "—")]));
  return (
    <Panel icon={Route} title={w("pk.trace.title")} sub={w("pk.trace.sub")}>
      <div className="flex flex-col gap-4 p-5">
        <form onSubmit={run} className="flex gap-2">
          <label htmlFor="pk-trace" className="sr-only">
            {w("pk.f.barcode")}
          </label>
          <Input id="pk-trace" value={code} onChange={(e) => setCode(e.target.value)} placeholder={w("pk.scan_or_enter_barcode")} className="h-10 font-mono" autoComplete="off" />
          <Button type="submit" disabled={pending}>
            <Search /> {w("pk.trace.run")}
          </Button>
        </form>
        {error ? (
          <p role="alert" className="text-sm text-danger">
            {error}
          </p>
        ) : null}
        {data ? (
          <div className="flex flex-col gap-3">
            <p className="flex flex-wrap items-center gap-2 text-sm">
              <Chip tone="bg-brand-subtle text-brand">{w(`pk.trace.kind.${data.kind}`)}</Chip>
              <span className="font-mono font-semibold">{data.code}</span>
              <span className="text-foreground-muted">— {[data.headline.batch, data.headline.buyer, data.headline.po_no, data.headline.style, data.headline.colour].filter(Boolean).join(" · ")}</span>
              {data.headline.stage ? <Chip tone={STAGE_TONE[data.headline.stage]}>{w(`pk.stage.${data.headline.stage}`)}</Chip> : null}
            </p>
            {data.events.length === 0 ? (
              <p className="text-sm text-foreground-muted">{w("pk.trace.none")}</p>
            ) : (
              <ol className="relative ml-2 border-l border-border">
                {data.events.map((e, i) => (
                  <li key={i} className="mb-3 ml-5 last:mb-0">
                    <span className="absolute -left-[7px] mt-1 size-3.5 rounded-full border-2 border-surface bg-brand" />
                    <p className="text-sm font-semibold">
                      {w(`pk.trace.e.${e.stage}`, params(e.params))} <span className="ml-1 font-normal text-foreground-muted">{at(e.at)}</span>
                    </p>
                    <p className="text-[13px] text-foreground-muted">{w(`pk.trace.e.${e.stage}_d`, params(e.params))}</p>
                  </li>
                ))}
              </ol>
            )}
          </div>
        ) : null}
      </div>
    </Panel>
  );
}

/* ───────────────────────────── Packing In ───────────────────────────── */

/** Packing In: a Quality-passed bundle scanned into the open batch of its PO and colour. */
function PackingInDesk({ options, initial, canManage, canSupervise, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const scanRef = useRef(null);
  const [loc, setLoc] = useState({ section: "", floor: "", line: "" });
  const [code, setCode] = useState("");
  const [preview, setPreview] = useState(null);
  const [scanError, setScanError] = useState(null);
  const [qty, setQty] = useState("");
  const [result, setResult] = useState(null);
  const [desk, setDesk] = useState(initial);
  const [pending, startTransition] = useTransition();
  const { section, floor, line } = loc;
  const floors = useMemo(() => options.floors.filter((f) => f.section_id === section), [options.floors, section]);
  const lines = useMemo(() => options.lines.filter((l) => l.section_id === section && l.floor_id === floor), [options.lines, section, floor]);
  const located = Boolean(section && floor && line);

  const place = useCallback((next) => {
    setLoc(next);
    try {
      if (next.section && next.floor && next.line) window.localStorage.setItem(storeKey, JSON.stringify(next));
      else window.localStorage.removeItem(storeKey);
    } catch {}
    if (next.line) setTimeout(() => scanRef.current?.focus(), 0);
  }, []);
  // The desk remembers its last Section › Floor › Line while that location is still offered.
  useEffect(() => {
    let saved = null;
    try {
      saved = JSON.parse(window.localStorage.getItem(storeKey) ?? "null");
    } catch {}
    if (saved && options.lines.some((l) => l.value === saved.line && l.section_id === saved.section && l.floor_id === saved.floor)) {
      const timer = setTimeout(() => place(saved), 0);
      return () => clearTimeout(timer);
    }
  }, [options.lines, place]);

  const reload = () =>
    startTransition(async () => {
      const res = await act.inDesk();
      if (res?.status === "success") setDesk(res.data);
    });
  const relocate = (next) => {
    place(next);
    setPreview(null);
    setScanError(null);
    setResult(null);
  };
  const pickLoc = (key, v) => relocate(key === "section" ? { section: v, floor: "", line: "" } : key === "floor" ? { section, floor: v, line: "" } : { section, floor, line: v });
  const scan = (e) => {
    e.preventDefault();
    if (!located || !code.trim()) return;
    setResult(null);
    startTransition(async () => {
      const res = await act.previewIn(line, code.trim());
      if (res?.status !== "success") {
        setPreview(null);
        setScanError(firstError(res));
        scanRef.current?.select();
        return;
      }
      setPreview(res.data);
      setScanError(null);
      setQty(res.data.ok ? String(res.data.card.balance_qty) : "");
    });
  };
  const confirm = () => {
    if (!preview?.ok || !canManage) return;
    startTransition(async () => {
      const res = await act.confirmIn({ code: preview.card.bundle_no, section_id: section, floor_id: floor, line_id: line, qty: qty ? Number(qty) : null });
      if (res?.status !== "success") return setResult({ ok: false, text: firstError(res) });
      const d = res.data;
      const text = w(d.opened ? "toast.pk_in_opened" : "toast.pk_in", { no: d.bundle_no, qty: num(d.qty), batch: d.packing_no });
      setResult({ ok: true, text });
      toastManager.add({ title: text, type: "success" });
      setPreview(null);
      setCode("");
      setQty("");
      reload();
      setTimeout(() => scanRef.current?.focus(), 0);
    });
  };
  const remove = (row) => {
    if (!window.confirm(w("pk.in.remove_confirm", { code: row.barcode }))) return;
    startTransition(async () => {
      const res = await act.removeIn(row.id);
      if (res?.status !== "success") return setResult({ ok: false, text: firstError(res) });
      setResult({ ok: true, text: w("toast.pk_removed", { no: res.data.bundle_no }) });
      reload();
    });
  };

  const c = preview?.card;
  const facts = c
    ? [
        [w("pk.f.buyer"), `${c.buyer ?? "—"}${c.buyer_code ? ` (${c.buyer_code})` : ""}`],
        [w("pk.f.order_no"), c.order_no],
        [w("pk.f.po_no"), c.po_no],
        [w("pk.f.style"), [c.style, c.style_name].filter(Boolean).join(" — ")],
        [w("pk.f.colour"), c.colour],
        [w("pk.f.size"), c.size],
        [w("pk.f.order_qty"), c.order_qty === null ? "—" : pcs(w, c.order_qty)],
        [w("pk.f.qc_pass_qty"), pcs(w, c.qc_pass_qty)],
        [w("pk.f.already_packed"), pcs(w, c.already_packed_qty)],
        [w("pk.f.balance_qty"), pcs(w, c.balance_qty), "text-brand font-semibold"],
        [w("pk.f.ship_date"), day(c.ship_date)],
        [w("pk.f.factory"), c.factory],
        [w("pk.f.qc_reference"), c.qc_reference ? `${c.qc_reference} · ${at(c.qc_passed_at)}` : "—"],
        [w("pk.f.barcode"), <span key="b" className="font-mono">{c.barcode}</span>],
        [w("pk.f.packing_batch"), preview.joins ? w("pk.in.joins", { no: preview.joins.packing_no, qty: num(preview.joins.packing_in_qty), n: preview.joins.bundles }) : preview.ok ? w("pk.in.new_batch") : "—"],
      ]
    : [];

  return (
    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
      <Panel icon={ScanBarcode} title={w("pk.in.title")} sub={w("pk.in.sub")}>
        <div className="flex flex-col gap-4 p-5">
          <div className="grid gap-3 sm:grid-cols-3">
            {[
              ["section", w("fin.section"), options.sections, "", options.sections.length ? w("fin.select_section") : w("pk.no_sections_short")],
              ["floor", w("fin.floor"), floors, section, section ? w("fin.select_floor") : w("fin.section_first")],
              ["line", w("pk.in.location"), lines, floor, floor ? w("fin.select_line") : w("fin.floor_first")],
            ].map(([key, label, list, parent, placeholder]) => (
              <label key={key} className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold">{label}</span>
                <select className={nativeSelect} value={loc[key]} disabled={key !== "section" && !parent} onChange={(e) => pickLoc(key, e.target.value)} aria-label={label}>
                  <option value="">{placeholder}</option>
                  {list.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </label>
            ))}
          </div>
          {options.sections.length === 0 ? <p className="text-xs text-warning">{w("pk.no_sections")}</p> : null}
          {options.unfiled.length ? <p className="text-xs text-warning">{w("pk.unfiled", { n: options.unfiled.length, lines: options.unfiled.join(", ") })}</p> : null}
          {!canManage ? <p className="text-xs text-warning">{w("pk.read_only")}</p> : null}
          <form onSubmit={scan} className="flex gap-2">
            <label htmlFor="pk-in-scan" className="sr-only">
              {w("pk.f.barcode")}
            </label>
            <Input
              id="pk-in-scan"
              ref={scanRef}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              placeholder={located ? w("pk.scan_or_enter_barcode") : w("pk.in.locate_first")}
              className="h-11 font-mono text-base"
              disabled={!located}
              autoComplete="off"
              spellCheck={false}
              maxLength={40}
            />
            <Button type="submit" size="lg" disabled={!located || !code.trim() || pending}>
              <ScanBarcode /> {w("pk.scan")}
            </Button>
          </form>
          {scanError ? <Notice result={{ ok: false, text: scanError }} /> : null}
          {preview ? (
            <p role={preview.ok ? "status" : "alert"} className={cn("text-sm font-semibold", preview.ok ? "text-success" : "text-danger")}>
              {preview.ok ? w("pk.in.ok") : preview.message}
            </p>
          ) : null}
          {c ? <Facts items={facts} /> : null}
          {preview?.ok ? (
            <div className="flex flex-wrap items-end gap-3">
              <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                {w("pk.in.qty")}
                <Input inputMode="numeric" value={qty} onChange={(e) => setQty(e.target.value.replace(/\D/g, "").slice(0, 6))} className="h-10 w-36 text-right tabular-nums" />
              </label>
              <Button size="lg" className="flex-1 justify-center" disabled={pending || !canManage || !qty} onClick={confirm}>
                <CircleCheck /> {w("pk.in.confirm")}
              </Button>
            </div>
          ) : null}
          <Notice result={result} />
        </div>
      </Panel>

      <div className="flex min-w-0 flex-col gap-4">
        <Panel icon={Package} title={w("pk.in.open_title")} sub={w("pk.in.open_sub")}>
          {desk.open.length === 0 ? (
            <Empty>{w("pk.in.open_empty")}</Empty>
          ) : (
            <div className="pt-4">
              <Grid
                minWidth="30rem"
                numeric={[4, 5]}
                head={[w("pk.col.packing_no"), w("pk.col.po"), w("pk.f.colour"), w("pk.col.sizes"), w("pk.col.bundles"), w("pk.col.qty")]}
                rows={desk.open.map((b) => ({
                  key: b.id,
                  cells: [
                    <span key="n" className="font-mono text-xs font-semibold">
                      {b.packing_no}
                    </span>,
                    b.po_no,
                    b.colour,
                    <span key="s" className="text-xs">
                      {b.sizes.map((s) => `${s.size}:${s.qty}`).join(" · ")}
                    </span>,
                    num(b.bundles),
                    <b key="q">{num(b.packing_in_qty)}</b>,
                  ],
                }))}
              />
            </div>
          )}
        </Panel>
        <Panel icon={ScanBarcode} title={w("pk.in.today_title")} sub={w("pk.in.today_sub", { n: desk.recent.length })}>
          {desk.recent.length === 0 ? (
            <Empty>{w("pk.in.today_empty")}</Empty>
          ) : (
            <div className="pt-4">
              <Grid
                minWidth="34rem"
                numeric={[4]}
                head={[w("pk.col.time"), w("pk.f.barcode"), w("pk.col.packing_no"), w("pk.f.size"), w("pk.col.qty"), ""]}
                rows={desk.recent.map((r) => ({
                  key: r.id,
                  cells: [
                    at(r.in_at),
                    <span key="b" className="font-mono text-xs">
                      {r.barcode}
                    </span>,
                    <span key="p">
                      <span className="font-mono text-xs">{r.packing_no}</span>
                      <span className="block text-xs text-foreground-muted">
                        {r.po_no} · {r.colour}
                      </span>
                    </span>,
                    r.size,
                    num(r.qty),
                    canSupervise && r.removable ? (
                      <Button key="x" size="icon" variant="ghost" className="size-7" aria-label={w("pk.in.remove", { code: r.barcode })} disabled={pending} onClick={() => remove(r)}>
                        <Trash2 className="size-3.5" />
                      </Button>
                    ) : null,
                  ],
                }))}
              />
            </div>
          )}
          <p className="flex items-center gap-2 border-t border-border px-5 py-3 text-xs text-foreground-muted">
            <Chip tone="bg-surface-muted text-foreground">{w("pk.in.supervisor")}</Chip> {w("pk.in.supervisor_note")}
          </p>
        </Panel>
      </div>
    </div>
  );
}

/* ───────────────────────────── Process desks ───────────────────────────── */

function BatchList({ title, batches, selected, onPick, empty }) {
  const w = useWords();
  return (
    <Panel icon={Package} title={title} sub={w("pk.proc.n_batches", { n: batches.length })}>
      {batches.length === 0 ? (
        <Empty>{empty}</Empty>
      ) : (
        <ul className="max-h-[26rem] divide-y divide-border overflow-auto">
          {batches.map((b) => (
            <li key={b.id}>
              <button type="button" onClick={() => onPick(b.id)} className={cn("flex w-full flex-col gap-1 px-5 py-2.5 text-left hover:bg-surface-muted", selected === b.id && "bg-brand-subtle/60")}>
                <span className="flex items-center justify-between gap-2">
                  <span className="font-mono text-xs font-semibold">{b.packing_no}</span>
                  <span className="text-xs font-semibold tabular-nums">{pcs(w, b.packing_in_qty)}</span>
                </span>
                <span className="text-xs text-foreground-muted">{[b.buyer, b.po_no, b.style, b.colour].filter(Boolean).join(" · ")}</span>
                <StepStrip steps={b.steps} />
              </button>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

/** A process desk: find or pick a batch, then run this process on it. */
function ProcessDesk({ step, initial, canManage, canSupervise, act }) {
  const w = useWords();
  const [desk, setDesk] = useState(initial);
  const [batch, setBatch] = useState(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const Icon = STEP_ICON[step];

  const reloadDesk = () =>
    startTransition(async () => {
      const res = await act.process(step);
      if (res?.status === "success") setDesk(res.data);
    });
  const pick = (id) =>
    startTransition(async () => {
      const res = await act.batch(id);
      if (res?.status === "success") {
        setBatch(res.data);
        setError(null);
      } else setError(firstError(res));
    });
  const find = (e) => {
    e.preventDefault();
    if (!code.trim()) return;
    startTransition(async () => {
      const res = await act.find(code.trim());
      if (res?.status === "success") {
        setBatch(res.data);
        setError(null);
        setCode("");
      } else setError(firstError(res));
    });
  };
  const changed = (next) => {
    if (next) setBatch(next);
    reloadDesk();
  };

  return (
    <div className="grid gap-4 xl:grid-cols-[minmax(18rem,22rem)_minmax(0,1fr)]">
      <div className="flex min-w-0 flex-col gap-4">
        <Panel icon={Search} title={w("pk.proc.find_title")} sub={w("pk.proc.find_sub")}>
          <div className="p-5">
            <form onSubmit={find} className="flex gap-2">
              <label htmlFor="pk-proc-find" className="sr-only">
                {w("pk.f.barcode")}
              </label>
              <Input id="pk-proc-find" value={code} onChange={(e) => setCode(e.target.value)} placeholder={w("pk.scan_or_enter")} className="h-10 font-mono" autoComplete="off" />
              <Button type="submit" size="icon" className="size-10" disabled={pending} aria-label={w("pk.scan")}>
                <ScanBarcode />
              </Button>
            </form>
            {error ? (
              <p role="alert" className="mt-2 text-sm text-danger">
                {error}
              </p>
            ) : null}
          </div>
        </Panel>
        <BatchList title={w("pk.proc.queue_title")} batches={desk.queue} selected={batch?.id ?? null} onPick={pick} empty={w("pk.proc.queue_empty")} />
        <BatchList title={w("pk.proc.done_title")} batches={desk.done_today} selected={batch?.id ?? null} onPick={pick} empty={w("pk.proc.done_empty")} />
      </div>
      {batch ? (
        <BatchView key={batch.id} batch={batch} step={step} canManage={canManage} canSupervise={canSupervise} act={act} onChanged={changed} />
      ) : (
        <section className="flex min-h-64 flex-col items-center justify-center gap-2 rounded-lg border border-border bg-surface p-8 text-center text-sm text-foreground-muted">
          <Icon className="size-7" />
          {w("pk.proc.pick")}
          <span className="max-w-md text-xs">{w(`pk.help.${step}`)}</span>
        </section>
      )}
    </div>
  );
}

/** Run an action that answers with the batch (or { batch }) and keep the desk in step. */
function useBatchAction(onChanged) {
  const [result, setResult] = useState(null);
  const [pending, startTransition] = useTransition();
  const run = (fn, okText, after) =>
    startTransition(async () => {
      const res = await fn();
      if (res?.status !== "success") return setResult({ ok: false, text: firstError(res) });
      const next = res.data?.batch ?? (res.data?.packing_no && res.data?.steps ? res.data : null);
      setResult(okText ? { ok: true, text: typeof okText === "function" ? okText(res.data) : okText } : null);
      after?.(res.data);
      onChanged(next);
    });
  return { result, setResult, pending, run };
}

function MiniTile({ label, value }) {
  return (
    <div className="rounded-lg bg-surface-muted px-3 py-2">
      <p className="text-[11px] text-foreground-muted">{label}</p>
      <p className="font-semibold tabular-nums">{value}</p>
    </div>
  );
}

/** One batch at a process desk: its facts, the seven processes and this process's own panel. */
function BatchView({ batch, step, canManage, canSupervise, act, onChanged }) {
  const w = useWords();
  const { result, pending, run } = useBatchAction(onChanged);
  const [qty, setQty] = useState("");
  const [ratio, setRatio] = useState(batch.assort_ratio ?? "");
  const [remarks, setRemarks] = useState("");
  const me = batch.steps.find((s) => s.step === step);
  const idx = STEPS.indexOf(step);
  const prev = idx > 0 ? batch.steps.find((s) => s.step === STEPS[idx - 1]) : null;
  const closed = batch.status === "PACKED_OUT" || batch.status === "CANCELLED";
  const canStart = !closed && batch.packing_in_qty > 0 && (!prev || prev.status === "COMPLETED");
  const max = step === "FOLDING" ? batch.packing_in_qty : step === "POLY" ? (batch.steps.find((s) => s.step === "FOLDING")?.qty ?? 0) : step === "ASSORTMENT" ? (batch.steps.find((s) => s.step === "POLY")?.qty ?? 0) : null;
  const stepName = (s) => w(`pk.step.${s}`);

  const reopen = (s) => {
    const reason = window.prompt(w("pk.proc.reopen_why", { step: stepName(s.step) }));
    if (reason) run(() => act.step(batch.id, s.step, "reopen", { reason }), w("toast.pk_reopened", { step: stepName(s.step) }));
  };

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <Panel icon={Package} title={<span className="font-mono">{batch.packing_no}</span>} sub={`${batch.buyer ?? "—"}${batch.buyer_code ? ` (${batch.buyer_code})` : ""} · ${w("pk.col.po")} ${batch.po_no ?? "—"} · ${[batch.style, batch.style_name].filter(Boolean).join(" — ")} · ${batch.colour}`} action={<StageChip batch={batch} />}>
        <div className="flex flex-col gap-4 p-5">
          <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
            <MiniTile label={w("pk.f.order_qty")} value={num(batch.order_qty)} />
            <MiniTile label={w("pk.f.qc_pass")} value={num(batch.qc_pass_qty)} />
            <MiniTile label={w("pk.col.packing_in")} value={num(batch.packing_in_qty)} />
            <MiniTile label={w("pk.col.cartons")} value={w("pk.proc.sealed_of", { n: batch.sealed_cartons, of: batch.cartons })} />
          </div>
          <p className="text-xs text-foreground-muted">{w("pk.proc.sizes_line", { sizes: batch.sizes.map((s) => `${s.size} ${s.qty}`).join(" · "), n: batch.bundles, location: batch.location ?? "—" })}</p>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[36rem] border border-border text-sm">
              <thead className="bg-surface-muted text-xs text-foreground-muted">
                <tr>
                  {[w("pk.col.step"), w("pk.col.status"), w("pk.col.quantity"), w("pk.col.by"), w("pk.col.done"), ""].map((h, i) => (
                    <th key={i} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium", i === 2 && "text-right")}>
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {batch.steps.map((s, i) => (
                  <tr key={s.step} className={cn(s.step === step && "bg-brand-subtle/50")}>
                    <td className="border border-border px-2 py-1.5">
                      <span className="mr-1.5 text-foreground-muted tabular-nums">{i + 1}</span>
                      {stepName(s.step)}
                    </td>
                    <td className="border border-border px-2 py-1.5">
                      <StepChip status={s.status} />
                    </td>
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{s.status === "PENDING" ? "—" : num(s.qty)}</td>
                    <td className="border border-border px-2 py-1.5 text-xs">{s.by ?? "—"}</td>
                    <td className="border border-border px-2 py-1.5 text-xs">{at(s.completed_at)}</td>
                    <td className="w-10 border border-border px-1 py-1">
                      {canSupervise && s.status === "COMPLETED" && !closed ? (
                        <Button size="icon" variant="ghost" className="size-7" aria-label={w("pk.proc.reopen", { step: stepName(s.step) })} disabled={pending} onClick={() => reopen(s)}>
                          <RotateCcw className="size-3.5" />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </Panel>

      <Panel icon={STEP_ICON[step]} title={stepName(step)} sub={w(`pk.help.${step}`)} action={<StepChip status={me.status} />}>
        <div className="flex flex-col gap-4 p-5">
          {me.status === "PENDING" ? (
            canStart ? (
              <Button className="self-start" disabled={pending || !canManage} onClick={() => run(() => act.step(batch.id, step, "start"), w("toast.pk_started", { step: stepName(step) }))}>
                <Play /> {w("pk.proc.start", { step: stepName(step) })}
              </Button>
            ) : (
              <p className="text-sm text-warning">{batch.status === "PACKED_OUT" ? w("pk.proc.packed_out") : prev && prev.status !== "COMPLETED" ? w("pk.proc.prev_first", { step: stepName(prev.step) }) : w("pk.proc.nothing_in")}</p>
            )
          ) : null}

          {me.status === "IN_PROGRESS" && step === "LABEL" ? <LabelPanel batch={batch} canManage={canManage} act={act} onChanged={onChanged} /> : null}
          {me.status === "IN_PROGRESS" && step === "CARTON" ? <CartonPanel batch={batch} canManage={canManage} act={act} onChanged={onChanged} /> : null}
          {me.status === "IN_PROGRESS" && step === "MARKING" ? <MarkingPanel batch={batch} canManage={canManage} act={act} onChanged={onChanged} /> : null}
          {me.status === "IN_PROGRESS" && step === "SEALING" ? <SealPanel batch={batch} canManage={canManage} canSupervise={canSupervise} act={act} onChanged={onChanged} /> : null}
          {["CARTON", "MARKING", "SEALING"].includes(step) && me.status !== "IN_PROGRESS" && batch.carton_list.length > 0 ? <CartonTable batch={batch} /> : null}

          {me.status === "IN_PROGRESS" ? (
            <div className="flex flex-wrap items-end gap-3 border-t border-border pt-4">
              {max !== null ? (
                <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                  {w("pk.proc.verified_qty", { max: num(max) })}
                  <Input inputMode="numeric" placeholder={String(max)} value={qty} onChange={(e) => setQty(e.target.value.replace(/\D/g, "").slice(0, 6))} className="h-9 w-32 text-right tabular-nums" />
                </label>
              ) : null}
              {step === "ASSORTMENT" ? (
                <label className="flex min-w-56 flex-1 flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                  {w("pk.proc.ratio")}
                  <Input value={ratio} onChange={(e) => setRatio(e.target.value)} placeholder={w("pk.proc.ratio_ph")} className="h-9 font-mono" maxLength={200} />
                  <RatioCheck batch={batch} ratio={ratio} />
                </label>
              ) : null}
              <label className="flex min-w-48 flex-1 flex-col gap-1.5 text-xs font-medium text-foreground-muted">
                {w("pk.proc.remarks")}
                <Input value={remarks} maxLength={300} onChange={(e) => setRemarks(e.target.value)} className="h-9" />
              </label>
              <Button disabled={pending || !canManage} onClick={() => run(() => act.step(batch.id, step, "complete", { qty: qty ? Number(qty) : null, ratio: step === "ASSORTMENT" ? ratio : null, remarks: remarks || null }), w("toast.pk_completed", { step: stepName(step) }))}>
                <CircleCheck /> {w("pk.proc.complete", { step: stepName(step) })}
              </Button>
            </div>
          ) : null}

          {me.status === "COMPLETED" ? (
            <p className="text-sm text-success">
              {w("pk.proc.completed_line", { at: at(me.completed_at), by: me.by ?? "—", qty: num(me.qty) })}
              {me.remarks ? ` ${me.remarks}` : ""}
            </p>
          ) : null}
          <Notice result={result} />
        </div>
      </Panel>
    </div>
  );
}

/** The ratio sizes checked against what was packed in, as the ratio is typed. */
function RatioCheck({ batch, ratio }) {
  const w = useWords();
  const packed = new Set(batch.sizes.map((s) => s.size.toUpperCase()));
  const sizes = ratio
    .split(/[,;]/)
    .map((p) => p.split(":")[0]?.trim())
    .filter(Boolean);
  if (sizes.length === 0) return null;
  const missing = sizes.filter((s) => !packed.has(s.toUpperCase()));
  return missing.length ? <span className="text-danger">{w("pk.proc.ratio_bad", { sizes: missing.join(", ") })}</span> : <span className="text-success">{w("pk.proc.ratio_ok")}</span>;
}

/** Barcode & Label: print the poly labels, then scan each to verify it. */
function LabelPanel({ batch, canManage, act, onChanged }) {
  const w = useWords();
  const { result, pending, run } = useBatchAction(onChanged);
  const [code, setCode] = useState("");
  const printed = batch.labels.some((l) => l.print_count > 0);
  const print = () => {
    printPolyLabels(batch, printWords(w));
    run(() => act.printLabels(batch.id), null);
  };
  const verify = (e) => {
    e.preventDefault();
    if (!code.trim()) return;
    run(
      () => act.verifyLabel(batch.id, code.trim()),
      (d) => w("toast.pk_label_ok", { size: d.size }),
      () => setCode(""),
    );
  };
  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap gap-2">
        <Button variant="outline" disabled={pending || batch.labels.length === 0 || !canManage} onClick={print}>
          <Printer /> {printed ? w("pk.lbl.reprint") : w("pk.lbl.print")}
        </Button>
        <form className="flex min-w-60 flex-1 gap-2" onSubmit={verify}>
          <label htmlFor="pk-label-verify" className="sr-only">
            {w("pk.lbl.scan")}
          </label>
          <Input id="pk-label-verify" value={code} onChange={(e) => setCode(e.target.value)} placeholder={w("pk.lbl.scan")} className="h-9 font-mono" disabled={!canManage} autoComplete="off" />
          <Button type="submit" disabled={pending || !code.trim()}>
            {w("pk.lbl.verify")}
          </Button>
        </form>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {batch.labels.map((l) => (
          <div key={l.id} className="flex min-w-0 flex-col items-center gap-1 rounded-lg border border-border p-2">
            <p className="text-xs text-foreground-muted">{w("pk.lbl.card", { size: l.size, qty: num(l.qty), n: l.print_count })}</p>
            <span className="w-full max-w-56 [&_svg]:h-auto [&_svg]:w-full" dangerouslySetInnerHTML={{ __html: barcodeSvg(l.barcode, 34) }} />
            <span className="font-mono text-[11px]">{l.barcode}</span>
            {l.verified_at ? <Chip tone="bg-success/15 text-success">{w("pk.lbl.verified")}</Chip> : <Chip tone="bg-warning/15 text-warning">{w("pk.lbl.not_verified")}</Chip>}
          </div>
        ))}
      </div>
      <Notice result={result} />
    </div>
  );
}

/** Carton Packing: cartons with their size break-up — whole ratio sets when the batch has a ratio. */
function CartonPanel({ batch, canManage, act, onChanged }) {
  const w = useWords();
  const { result, pending, run } = useBatchAction(onChanged);
  const ratioMap = useMemo(() => {
    const m = new Map();
    for (const p of (batch.assort_ratio ?? "").split(",")) {
      const [s, n] = p.split(":").map((x) => x?.trim());
      if (s && n) m.set(s, Number(n));
    }
    return m;
  }, [batch.assort_ratio]);
  const [lines, setLines] = useState(() => Object.fromEntries(batch.sizes.map((s) => [s.size, ratioMap.get(s.size) ? String(ratioMap.get(s.size)) : ""])));
  const [form, setForm] = useState({ carton_type: "", carton_size: "", gross_weight: "", net_weight: "", copies: "1" });
  const inCartons = new Map();
  for (const c of batch.carton_list) for (const l of c.lines) inCartons.set(l.size, (inCartons.get(l.size) ?? 0) + l.qty);
  const perCarton = Object.values(lines).reduce((a, v) => a + (Number(v) || 0), 0);
  const copies = Number(form.copies) || 1;
  const submit = (e) => {
    e.preventDefault();
    run(
      () => act.addCartons(batch.id, { lines: Object.entries(lines).map(([size, qty]) => ({ size, qty: qty === "" ? 0 : Number(qty) })), ...form, copies, gross_weight: form.gross_weight || null, net_weight: form.net_weight || null }),
      (d) => w("toast.pk_cartons", { n: d.cartons.length, from: d.cartons[0], to: d.cartons.at(-1) }),
    );
  };
  const remove = (c) => {
    if (window.confirm(w("pk.ctn.remove_confirm", { no: c.carton_no }))) run(() => act.deleteCarton(c.id), w("toast.pk_carton_removed", { no: c.carton_no }));
  };
  return (
    <div className="flex flex-col gap-4">
      {batch.assort_ratio ? <p className="text-sm text-foreground-muted">{w("pk.ctn.ratio", { ratio: batch.assort_ratio })}</p> : null}
      <form onSubmit={submit} className="flex flex-col gap-3 rounded-lg border border-border p-3">
        <div className="overflow-x-auto">
          <table className="w-full border border-border text-sm">
            <thead className="bg-surface-muted text-xs text-foreground-muted">
              <tr>
                {[w("pk.f.size"), w("pk.ctn.packed_in"), w("pk.ctn.in_cartons"), w("pk.ctn.left"), w("pk.ctn.per_carton")].map((h, i) => (
                  <th key={h} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium", i > 0 && i < 4 && "text-right")}>
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {batch.sizes.map((s) => (
                <tr key={s.size}>
                  <td className="border border-border px-2 py-1.5 font-semibold">{s.size}</td>
                  <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(s.qty)}</td>
                  <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(inCartons.get(s.size) ?? 0)}</td>
                  <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(s.qty - (inCartons.get(s.size) ?? 0))}</td>
                  <td className="w-32 border border-border px-2 py-1">
                    <Input aria-label={w("pk.ctn.per_carton_of", { size: s.size })} inputMode="numeric" value={lines[s.size] ?? ""} onChange={(e) => setLines({ ...lines, [s.size]: e.target.value.replace(/\D/g, "").slice(0, 5) })} className="h-8 text-right tabular-nums" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
          {[
            ["carton_type", w("pk.ctn.type"), "5-ply"],
            ["carton_size", w("pk.ctn.size"), "60×40×40 cm"],
            ["gross_weight", w("pk.ctn.gross"), "10.50"],
            ["net_weight", w("pk.ctn.net"), "9.80"],
            ["copies", w("pk.ctn.copies"), "1"],
          ].map(([k, label, ph]) => (
            <label key={k} className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
              {label}
              <Input value={form[k]} placeholder={ph} maxLength={40} onChange={(e) => setForm({ ...form, [k]: e.target.value })} className="h-9" />
            </label>
          ))}
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-foreground-muted">
            {w("pk.ctn.sum", { per: num(perCarton), n: copies })} <b className="text-foreground">{pcs(w, perCarton * copies)}</b>
          </p>
          <Button type="submit" disabled={pending || !canManage || perCarton === 0}>
            <Boxes /> {w("pk.ctn.create")}
          </Button>
        </div>
      </form>
      <Notice result={result} />
      <CartonTable batch={batch} onDelete={canManage ? remove : undefined} />
    </div>
  );
}

/** The batch's cartons: number, barcode, sizes, quantity, weights and status. */
function CartonTable({ batch, onDelete, onUnseal }) {
  const w = useWords();
  if (batch.carton_list.length === 0) return <p className="text-sm text-foreground-muted">{w("pk.ctn.none")}</p>;
  const total = batch.carton_list.reduce((a, c) => a + c.qty, 0);
  return (
    <div className="max-h-[28rem] overflow-auto">
      <table className="w-full min-w-[44rem] border border-border text-sm">
        <thead className="sticky top-0 bg-surface-muted text-xs text-foreground-muted">
          <tr>
            {["#", w("pk.ctn.no"), w("pk.f.barcode"), w("pk.col.sizes"), w("pk.col.qty"), w("pk.ctn.gross_s"), w("pk.ctn.net_s"), w("pk.col.status"), ""].map((h, i) => (
              <th key={i} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium", [0, 4, 5, 6].includes(i) && "text-right")}>
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {batch.carton_list.map((c) => (
            <tr key={c.id}>
              <td className="border border-border px-2 py-1.5 text-right tabular-nums">{c.serial}</td>
              <td className="border border-border px-2 py-1.5 font-mono text-xs">{c.carton_no}</td>
              <td className="border border-border px-2 py-1.5 font-mono text-xs">{c.barcode}</td>
              <td className="border border-border px-2 py-1.5 text-xs">{c.lines.map((l) => `${l.size}:${l.qty}`).join(" ")}</td>
              <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(c.qty)}</td>
              <td className="border border-border px-2 py-1.5 text-right tabular-nums">{c.gross_weight ?? "—"}</td>
              <td className="border border-border px-2 py-1.5 text-right tabular-nums">{c.net_weight ?? "—"}</td>
              <td className="border border-border px-2 py-1.5">
                <Chip tone={CARTON_TONE[c.status]}>{w(`pk.ctn.status.${c.status}`)}</Chip>
              </td>
              <td className="w-10 border border-border px-1 py-1">
                {onDelete && c.status !== "SEALED" ? (
                  <Button size="icon" variant="ghost" className="size-7" aria-label={w("pk.ctn.remove", { no: c.carton_no })} onClick={() => onDelete(c)}>
                    <Trash2 className="size-3.5" />
                  </Button>
                ) : null}
                {onUnseal && c.status === "SEALED" ? (
                  <Button size="icon" variant="ghost" className="size-7" aria-label={w("pk.ctn.unseal", { no: c.carton_no })} onClick={() => onUnseal(c)}>
                    <LockOpen className="size-3.5" />
                  </Button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot className="bg-surface-muted text-xs font-semibold">
          <tr>
            <td className="border border-border px-2 py-1.5" colSpan={4}>
              {w("pk.ctn.count", { n: batch.carton_list.length })}
            </td>
            <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(total)}</td>
            <td className="border border-border px-2 py-1.5" colSpan={4} />
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

/** Carton Marking: destination and shipping mark on every carton, previewed and printed. */
function MarkingPanel({ batch, canManage, act, onChanged }) {
  const w = useWords();
  const { result, pending, run } = useBatchAction(onChanged);
  const [form, setForm] = useState({
    destination: batch.destination ?? "",
    country: batch.country ?? "",
    shipping_mark: batch.shipping_mark ?? `${batch.buyer ?? ""} / ${w("pk.col.po")} ${batch.po_no ?? ""} / ${batch.style ?? ""}`,
    special_instruction: batch.special_instruction ?? "",
  });
  const [preview, setPreview] = useState(false);
  const ready = Boolean(batch.shipping_mark) && batch.carton_list.length > 0 && batch.carton_list.every((c) => c.status !== "OPEN");
  const words = printWords(w);
  return (
    <div className="flex flex-col gap-4">
      <form
        className="grid gap-3 sm:grid-cols-2"
        onSubmit={(e) => {
          e.preventDefault();
          run(() => act.saveMarking(batch.id, form), w("toast.pk_marked"));
        }}
      >
        {[
          ["destination", `${w("pk.mk.destination")} *`, 120],
          ["country", w("pk.mk.country"), 80],
          ["shipping_mark", `${w("pk.mk.shipping_mark")} *`, 200],
          ["special_instruction", w("pk.mk.instruction"), 300],
        ].map(([k, label, max]) => (
          <label key={k} className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {label}
            <Input value={form[k]} maxLength={max} onChange={(e) => setForm({ ...form, [k]: e.target.value })} className="h-9" />
          </label>
        ))}
        <div className="col-span-full flex flex-wrap gap-2">
          <Button type="submit" disabled={pending || !canManage}>
            {w("pk.mk.generate")}
          </Button>
          <Button type="button" variant="outline" disabled={!ready} onClick={() => setPreview(true)}>
            <Eye /> {w("pk.mk.preview")}
          </Button>
          <Button
            type="button"
            variant="outline"
            disabled={!ready || pending || !canManage}
            onClick={() => {
              printCartonMarking(batch, words);
              run(() => act.printMarking(batch.id), null);
            }}
          >
            <Printer /> {w("pk.mk.print")}
          </Button>
        </div>
      </form>
      <Notice result={result} />
      <CartonTable batch={batch} />
      <Modal open={preview} onOpenChange={setPreview} title={w("pk.mk.preview_title", { no: batch.packing_no })} className="max-w-xl">
        {batch.carton_list[0] ? <div className="overflow-auto rounded-lg bg-white p-3 text-black" dangerouslySetInnerHTML={{ __html: markingHtml(batch, batch.carton_list[0], words) }} /> : null}
      </Modal>
    </div>
  );
}

/** Carton Sealing: each carton scanned, weighed and checked, then sealed. */
function SealPanel({ batch, canManage, canSupervise, act, onChanged }) {
  const w = useWords();
  const { result, pending, run } = useBatchAction(onChanged);
  const [form, setForm] = useState({ barcode: "", gross_weight: "", net_weight: "" });
  const ref = useRef(null);
  const sealed = batch.carton_list.filter((c) => c.status === "SEALED").length;
  useEffect(() => {
    ref.current?.focus();
  }, [sealed]);
  const seal = (e) => {
    e.preventDefault();
    if (!form.barcode.trim()) return;
    run(
      () => act.seal(batch.id, { barcode: form.barcode.trim(), gross_weight: form.gross_weight || null, net_weight: form.net_weight || null }),
      (d) => w("toast.pk_sealed", { no: d.carton_no }),
      () => setForm({ barcode: "", gross_weight: "", net_weight: "" }),
    );
  };
  const unseal = (c) => {
    const reason = window.prompt(w("pk.seal.why", { no: c.carton_no }));
    if (reason) run(() => act.unseal(c.id, reason), w("toast.pk_unsealed", { no: c.carton_no }));
  };
  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-foreground-muted">{w("pk.seal.checks", { n: sealed, of: batch.carton_list.length })}</p>
      <form className="grid gap-3 sm:grid-cols-[1fr_8rem_8rem_auto]" onSubmit={seal}>
        <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("pk.seal.barcode")}
          <Input ref={ref} value={form.barcode} onChange={(e) => setForm({ ...form, barcode: e.target.value })} placeholder="CTN202611070001" className="h-10 font-mono" disabled={!canManage} autoComplete="off" />
        </label>
        <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("pk.ctn.gross")}
          <Input value={form.gross_weight} onChange={(e) => setForm({ ...form, gross_weight: e.target.value })} placeholder={w("pk.seal.if_not")} className="h-10" />
        </label>
        <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
          {w("pk.ctn.net")}
          <Input value={form.net_weight} onChange={(e) => setForm({ ...form, net_weight: e.target.value })} placeholder={w("pk.seal.if_not")} className="h-10" />
        </label>
        <Button type="submit" size="lg" className="self-end" disabled={pending || !form.barcode.trim()}>
          <Lock /> {w("pk.seal.run")}
        </Button>
      </form>
      <Notice result={result} />
      <CartonTable batch={batch} onUnseal={canSupervise ? unseal : undefined} />
    </div>
  );
}

/* ───────────────────────────── Packing Out ───────────────────────────── */

function OutList({ title, sub, batches, onPick, empty }) {
  const w = useWords();
  return (
    <Panel icon={PackageCheck} title={title} sub={sub}>
      {batches.length === 0 ? (
        <Empty>{empty}</Empty>
      ) : (
        <div className="pt-4">
          <Grid
            minWidth="34rem"
            numeric={[3, 4, 5]}
            head={[w("pk.col.packing_no"), w("pk.col.buyer_po"), w("pk.f.colour"), w("pk.col.packing_in"), w("pk.col.final"), w("pk.col.cartons"), ""]}
            rows={batches.map((b) => ({
              key: b.id,
              cells: [
                <span key="n" className="font-mono text-xs">
                  {b.packing_no}
                </span>,
                <span key="b">
                  {b.buyer}
                  <span className="block text-xs text-foreground-muted">
                    {b.po_no} · {b.style}
                  </span>
                </span>,
                b.colour,
                num(b.packing_in_qty),
                b.final_packed_qty ? num(b.final_packed_qty) : "—",
                num(b.sealed_cartons),
                onPick ? (
                  <Button key="o" size="sm" variant="outline" onClick={() => onPick(b.packing_no)}>
                    {w("pk.out.open")}
                  </Button>
                ) : null,
              ],
            }))}
          />
        </div>
      )}
    </Panel>
  );
}

/** Packing Out: scan, check every process, confirm the final packed quantity — moved to Ready for Shipment. */
function PackingOutDesk({ initial, canManage, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [desk, setDesk] = useState(initial);
  const [code, setCode] = useState("");
  const [preview, setPreview] = useState(null);
  const [scanError, setScanError] = useState(null);
  const [finalQty, setFinalQty] = useState("");
  const [result, setResult] = useState(null);
  const [added, setAdded] = useState(null);
  const [pending, startTransition] = useTransition();

  const reload = () =>
    startTransition(async () => {
      const res = await act.outDesk();
      if (res?.status === "success") setDesk(res.data);
    });
  const scan = (value, e) => {
    e?.preventDefault();
    if (!value.trim()) return;
    setResult(null);
    startTransition(async () => {
      const res = await act.previewOut(value.trim());
      if (res?.status !== "success") {
        setPreview(null);
        setScanError(firstError(res));
        return;
      }
      setPreview(res.data);
      setScanError(null);
      setFinalQty(res.data.sealed_qty ? String(res.data.sealed_qty) : "");
    });
  };
  const confirm = () =>
    startTransition(async () => {
      const res = await act.confirmOut({ packing_no: preview.batch.packing_no, final_qty: Number(finalQty) });
      if (res?.status !== "success") return setResult({ ok: false, text: firstError(res) });
      const text = w("toast.pk_out", { batch: res.data.packing_no, qty: num(res.data.final_packed_qty) });
      setResult({ ok: true, text });
      setAdded(res.data.final_packed_qty);
      toastManager.add({ title: text, type: "success" });
      setPreview(null);
      setCode("");
      reload();
    });

  const b = preview?.batch;
  const balance = b ? b.packing_in_qty - (Number(finalQty) || 0) : 0;
  const sealing = b?.steps.find((s) => s.step === "SEALING");
  const checkLabel = (c) => (c.key === "step" ? w(`pk.step.${c.step}`) : w(`pk.out.check.${c.key}`));
  const checkNote = (c) => (c.key === "step" ? w(`pk.step_status.${c.params.status}`) + (c.params.qty !== null && c.params.status === "COMPLETED" ? ` · ${pcs(w, c.params.qty)}` : "") : w("pk.out.n_of", c.params));
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(16rem,0.6fr)]">
        <Panel icon={ScanLine} title={w("pk.out.title")} sub={w("pk.out.sub")}>
          <div className="flex flex-col gap-4 p-5">
            <form onSubmit={(e) => scan(code, e)} className="flex gap-2">
              <label htmlFor="pk-out-scan" className="sr-only">
                {w("pk.out.barcode")}
              </label>
              <Input id="pk-out-scan" autoFocus value={code} onChange={(e) => setCode(e.target.value)} placeholder={w("pk.scan_or_enter_barcode")} className="h-11 font-mono text-base" autoComplete="off" />
              <Button type="submit" size="lg" disabled={pending || !code.trim()}>
                <ScanLine /> {w("pk.scan")}
              </Button>
            </form>
            {scanError ? <Notice result={{ ok: false, text: scanError }} /> : null}
            {preview ? (
              <div className={cn("rounded-lg border p-3", preview.ok ? "border-success/40 bg-success/10" : "border-danger/40 bg-danger/10")}>
                <p className={cn("flex items-center gap-2 text-sm font-semibold", preview.ok ? "text-success" : "text-danger")}>
                  {preview.ok ? <CircleCheck className="size-4" /> : <CircleX className="size-4" />}
                  {w(preview.ok ? "pk.out.ok_title" : `pk.out.no_title.${preview.reason}`)}
                </p>
                <p className="mt-1 text-sm text-foreground-muted">{w(preview.ok ? "pk.out.ok_text" : `pk.out.no_text.${preview.reason}`, { batch: b.packing_no, at: at(b.packed_out_at) })}</p>
              </div>
            ) : null}
            {b ? (
              <div className="grid gap-4 lg:grid-cols-2">
                <Facts
                  items={[
                    [w("pk.col.packing_no"), <span key="p" className="font-mono">{b.packing_no}</span>],
                    [w("pk.f.buyer"), `${b.buyer ?? "—"}${b.buyer_code ? ` (${b.buyer_code})` : ""}`],
                    [w("pk.out.po_order"), `${b.po_no ?? "—"} / ${b.order_no ?? "—"}`],
                    [w("pk.f.style"), [b.style, b.style_name].filter(Boolean).join(" — ")],
                    [w("pk.f.colour"), b.colour],
                    [w("pk.f.size"), b.sizes.map((s) => `${s.size}:${s.qty}`).join(" ")],
                    [w("pk.f.qc_pass_qty"), pcs(w, b.qc_pass_qty)],
                    [w("pk.out.in_qty"), pcs(w, b.packing_in_qty)],
                    [w("pk.out.carton_qty"), w("pk.out.sealed_of", { n: b.sealed_cartons, of: b.cartons })],
                    [w("pk.out.carton_nos"), b.carton_list.length ? `${b.carton_list[0].carton_no} – ${b.carton_list.at(-1).carton_no}` : "—"],
                    [w("pk.out.weights"), `${b.gross_weight.toFixed(2)} / ${b.net_weight.toFixed(2)} kg`],
                    [w("pk.out.start"), at(b.started_at)],
                    [w("pk.out.complete"), at(sealing?.completed_at)],
                    [w("pk.out.operator"), b.operator ?? "—"],
                    [w("pk.out.supervisor"), sealing?.by ?? "—"],
                  ]}
                />
                <div className="flex flex-col gap-3">
                  <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                    {preview.checks.map((c, i) => (
                      <li key={i} className="flex items-center justify-between gap-2 px-3 py-1.5">
                        <span className="flex items-center gap-2">
                          {c.ok ? <CircleCheck className="size-3.5 text-success" /> : <CircleX className="size-3.5 text-danger" />}
                          {checkLabel(c)}
                        </span>
                        <span className="text-xs text-foreground-muted">{checkNote(c)}</span>
                      </li>
                    ))}
                  </ul>
                  {preview.ok ? (
                    <div className="flex flex-col gap-3 rounded-lg bg-surface-muted p-3">
                      <div className="grid grid-cols-3 gap-2 text-center text-sm">
                        <div>
                          <p className="text-xs text-foreground-muted">{w("pk.col.packing_in")}</p>
                          <p className="font-semibold tabular-nums">{num(b.packing_in_qty)}</p>
                        </div>
                        <label className="text-xs text-foreground-muted">
                          {w("pk.out.final")}
                          <Input inputMode="numeric" value={finalQty} onChange={(e) => setFinalQty(e.target.value.replace(/\D/g, "").slice(0, 6))} className="mt-1 h-9 text-center font-semibold tabular-nums" />
                        </label>
                        <div>
                          <p className="text-xs text-foreground-muted">{w("pk.out.balance")}</p>
                          <p className={cn("font-semibold tabular-nums", balance > 0 && "text-warning")}>{num(balance)}</p>
                        </div>
                      </div>
                      <Button size="lg" className="justify-center" disabled={pending || !canManage || !finalQty} onClick={confirm}>
                        <CircleCheck /> {w("pk.out.confirm")}
                      </Button>
                    </div>
                  ) : null}
                </div>
              </div>
            ) : null}
            <Notice result={result} />
          </div>
        </Panel>
        <section className="flex flex-col items-center justify-center gap-3 rounded-lg border border-success/40 bg-surface p-6 text-center">
          <CircleCheck className="size-10 text-success" />
          <p className="text-lg font-semibold">{w("pk.out.auto_title")}</p>
          <p className="text-sm text-foreground-muted">{w("pk.out.auto_completed")}</p>
          <ArrowDown className="size-5 text-success" />
          <Truck className="size-9 text-success" />
          <p className="text-base font-semibold text-success">{w("pk.out.auto_ready")}</p>
          <p className="text-xs text-foreground-muted">{added !== null ? w("pk.out.auto_added", { qty: num(added) }) : w("pk.out.auto_rule")}</p>
        </section>
      </div>
      <div className="grid gap-4 xl:grid-cols-2">
        <OutList
          title={w("pk.out.waiting_title")}
          sub={w("pk.out.waiting_sub")}
          batches={desk.waiting}
          onPick={(no) => {
            setCode(no);
            scan(no);
          }}
          empty={w("pk.out.waiting_empty")}
        />
        <OutList title={w("pk.out.today_title")} sub={w("pk.out.today_sub")} batches={desk.today} empty={w("pk.out.today_empty")} />
      </div>
    </div>
  );
}

/* ───────────────────────────── Reports ───────────────────────────── */

const shiftDay = (iso, n) => {
  const d = new Date(`${iso}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
};

/**
 * A report screen: pick, range, narrow, Run; Print and CSV. The eleven
 * Packing Reports by default; `prefix` names another set's report and column
 * words (Shipment's seventeen, `sh.rep`).
 */
function PackingReports({ kinds, today, act, prefix = "pk.rep", stem = "packing" }) {
  const w = useWords();
  const [q, setQ] = useState({ kind: kinds[0], from: shiftDay(today, -29), to: today, q: "" });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const run = useCallback(
    (params) =>
      startTransition(async () => {
        const res = await act.report(params);
        if (res?.status === "success") {
          setData(res.data);
          setError(null);
        } else {
          setData(null);
          setError(firstError(res));
        }
      }),
    [act],
  );
  useEffect(() => {
    run({ kind: kinds[0], from: shiftDay(today, -29), to: today });
  }, [run, kinds, today]);

  const title = data ? w(`${prefix}.kind.${data.kind}`) : w("pk.rep.title");
  const label = (c) => w(`${prefix}.col.${c.key}`);
  const cell = (c, r) =>
    c.key === "status" && r.status
      ? w(`${prefix === "pk.rep" ? "pk.ctn.status" : `${prefix}.status`}.${r.status}`)
      : c.key === "doc_type" && r.doc_type
        ? w(`rs.doc.${r.doc_type}`)
        : c.numeric && typeof r[c.key] === "number"
          ? num(r[c.key])
          : (ampm(r[c.key]) ?? "—");
  const print = () =>
    printDocument({
      title,
      subtitle: `${day(data.from)} – ${day(data.to)}${q.q ? ` · “${q.q}”` : ""}`,
      orientation: data.columns.length > 8 ? "landscape" : "portrait",
      sections: [
        {
          columns: data.columns.map((c) => ({ label: label(c), align: c.numeric ? "right" : "left" })),
          rows: data.rows.map((r) => data.columns.map((c) => cell(c, r))),
          foot: data.columns.some((c) => c.numeric) ? [data.columns.map((c, i) => (c.numeric ? num(data.totals[c.key]) : i === 0 ? w("pk.rep.total") : ""))] : [],
          empty: w("pk.rep.empty"),
        },
      ],
    });
  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            run(q);
          }}
          className="grid grid-cols-2 gap-3 px-5 py-4 md:grid-cols-6"
        >
          <label className="col-span-2 flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("pk.rep.report")}
            <select className={nativeSelect} value={q.kind} onChange={(e) => setQ({ ...q, kind: e.target.value })}>
              <optgroup label={w(`${prefix}.group`)}>
                {kinds.map((k) => (
                  <option key={k} value={k}>
                    {w(`${prefix}.kind.${k}`)}
                  </option>
                ))}
              </optgroup>
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("pk.rep.from")}
            <Input type="date" value={q.from} onChange={(e) => setQ({ ...q, from: e.target.value })} className="h-10" />
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("pk.rep.to")}
            <Input type="date" value={q.to} onChange={(e) => setQ({ ...q, to: e.target.value })} className="h-10" />
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("pk.rep.q")}
            <Input value={q.q} maxLength={60} onChange={(e) => setQ({ ...q, q: e.target.value })} className="h-10" />
          </label>
          <div className="flex items-end">
            <Button type="submit" className="w-full justify-center" disabled={pending}>
              <Play /> {w("pk.rep.run")}
            </Button>
          </div>
        </form>
      </section>
      <Panel
        icon={FileChartColumn}
        title={title}
        sub={data ? w("pk.rep.rows", { n: data.rows.length }) : pending ? w("pk.rep.running") : undefined}
        action={
          data ? (
            <div className="flex gap-2">
              <Button size="sm" variant="outline" onClick={print}>
                <Printer /> {w("pk.rep.print")}
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={!data.rows.length}
                onClick={() =>
                  downloadCsv(
                    `${stem}-${data.kind}`,
                    data.columns.map(label),
                    data.rows.map((r) => data.columns.map((c) => r[c.key] ?? "")),
                  )
                }
              >
                <Download /> {w("pk.rep.csv")}
              </Button>
            </div>
          ) : null
        }
      >
        {error ? (
          <p role="alert" className="px-5 py-4 text-sm text-danger">
            {error}
          </p>
        ) : null}
        {data && data.rows.length === 0 ? <Empty>{w("pk.rep.empty")}</Empty> : null}
        {data && data.rows.length > 0 ? (
          <div className="max-h-[40rem] overflow-auto px-5 py-4">
            <table className="w-full border border-border text-sm">
              <thead className="sticky top-0 bg-surface-muted text-xs text-foreground-muted">
                <tr>
                  {data.columns.map((c) => (
                    <th key={c.key} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium whitespace-nowrap", c.numeric && "text-right")}>
                      {label(c)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.rows.map((r, i) => (
                  <tr key={i} className="hover:bg-surface-muted">
                    {data.columns.map((c) => (
                      <td key={c.key} className={cn("border border-border px-2 py-1.5", c.numeric && "text-right tabular-nums", ["packing_no", "bundle_no", "carton_no", "barcode"].includes(c.key) && "font-mono text-xs")}>
                        {cell(c, r)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
              {data.columns.some((c) => c.numeric) ? (
                <tfoot className="bg-surface-muted text-xs font-semibold">
                  <tr>
                    {data.columns.map((c, i) => (
                      <td key={c.key} className={cn("border border-border px-2 py-1.5", c.numeric && "text-right tabular-nums")}>
                        {c.numeric ? num(data.totals[c.key]) : i === 0 ? w("pk.rep.total") : ""}
                      </td>
                    ))}
                  </tr>
                </tfoot>
              ) : null}
            </table>
          </div>
        ) : null}
      </Panel>
    </div>
  );
}

export { PackingNav, PackingDashboard, PackingInDesk, ProcessDesk, PackingOutDesk, PackingReports };
