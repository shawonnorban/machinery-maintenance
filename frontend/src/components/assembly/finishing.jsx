"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  Barcode,
  Boxes,
  CircleCheck,
  CircleX,
  ClipboardList,
  Clock,
  Download,
  Eye,
  Hammer,
  History,
  Layers,
  PackageCheck,
  PackageOpen,
  PackagePlus,
  RefreshCw,
  ScanLine,
  Target,
  Timer,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { StatCard } from "@/components/ui/stat-card";
import { Tabs, TabsIndicator, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

/*
 * Assembly Floor → Finishing (AnnotechERP 20/02), as Brickland lays its
 * three screens out under the Dashboard / Bundle IN / Bundle OUT buttons:
 * the two desks (four steps, Section › Floor › Line, the scan box, Bundle
 * Information, Output Quantity on the OUT desk, Planning & Target, Recently
 * Scanned Bundles (Today), Clear and Confirm) and the Finishing Dashboard
 * (filters, key figures, Target vs Actual, the seven tabs, Bundle History).
 */

const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const num = (v) => (v === null || v === undefined || v === "" ? "—" : Number(v).toLocaleString("en-US"));
const nativeSelect = "h-10 w-full rounded-md border border-border bg-surface px-3 text-sm outline-none focus:border-brand disabled:opacity-60";
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
/** "2026-11-06 10:38" → "06 Nov, 10:38 AM", as Brickland writes a scan's time. */
const at = (s) => {
  if (!s) return "—";
  const [d, t] = s.split(" ");
  const [, m, day] = d.split("-");
  if (!t) return `${day} ${MONTHS[Number(m) - 1]} ${d.slice(0, 4)}`;
  const [h, min] = t.split(":").map(Number);
  return `${day} ${MONTHS[Number(m) - 1]}, ${String(h % 12 || 12).padStart(2, "0")}:${String(min).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
};
const dayLabel = (d) => at(d);
const addDays = (d, n) => {
  const x = new Date(`${d}T00:00:00Z`);
  x.setUTCDate(x.getUTCDate() + n);
  return x.toISOString().slice(0, 10);
};

function useWords() {
  const t = useT("assembly");
  return (k, p) => t(k, p);
}

function downloadCsv(name, head, rows) {
  const cell = (v) => {
    const s = String(v ?? "");
    return /[",\n]/.test(s) ? `"${s.replaceAll('"', '""')}"` : s;
  };
  const text = [head, ...rows].map((r) => r.map(cell).join(",")).join("\r\n");
  const url = URL.createObjectURL(new Blob([`﻿${text}`], { type: "text/csv;charset=utf-8" }));
  const a = document.createElement("a");
  a.href = url;
  a.download = `${name}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

/** The Dashboard / Bundle IN / Bundle OUT buttons over each finishing screen. */
function FinishingNav({ active }) {
  const w = useWords();
  const items = [
    ["dashboard", "/assembly-floor/finishing-dashboard", "nav.dashboard"],
    ["in", "/assembly-floor/finishing-bundle-in", "nav.bundle_in"],
    ["out", "/assembly-floor/finishing-bundle-out", "nav.bundle_out"],
  ];
  return (
    <nav className="mb-4 grid gap-3 sm:grid-cols-3">
      {items.map(([key, href, label]) => (
        <Link
          key={key}
          href={href}
          aria-current={key === active ? "page" : undefined}
          className={cn(
            "flex h-11 items-center justify-center rounded-lg border text-sm font-semibold transition-colors",
            key === active ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          {w(label)}
        </Link>
      ))}
    </nav>
  );
}

/** A card with Brickland's title row: an icon, the title, its line, an action on the right. */
function Panel({ icon: Icon = ScanLine, title, sub, action, children, className }) {
  return (
    <section className={cn("rounded-lg border border-border bg-surface", className)}>
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-3">
        <div className="flex gap-2.5">
          <Icon className="mt-0.5 size-4 text-brand" />
          <div>
            <h2 className="text-[15px] font-semibold">{title}</h2>
            {sub ? <p className="text-[13px] text-foreground-muted">{sub}</p> : null}
          </div>
        </div>
        {action}
      </header>
      {children}
    </section>
  );
}

function Facts({ items }) {
  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
      {items.map(([label, value, tone]) => (
        <div key={label} className="contents">
          <dt className="text-foreground-muted">{label}</dt>
          <dd className={cn("font-medium", tone)}>{value ?? "—"}</dd>
        </div>
      ))}
    </dl>
  );
}

const STATUS_TONE = { NOT_READY: "bg-danger/15 text-danger", READY: "bg-info-subtle text-info", IN_FINISHING: "bg-warning/15 text-warning", FINISHING_OUT: "bg-success/15 text-success" };
const REWORK_TONE = { PENDING: "bg-warning/15 text-warning", PROCESSING: "bg-info-subtle text-info", COMPLETED: "bg-success/15 text-success" };

function Chip({ tone, children }) {
  return <span className={cn("inline-block rounded-md px-2 py-0.5 text-xs font-semibold whitespace-nowrap", tone)}>{children}</span>;
}

/** Brickland's report table: a note or the row count, CSV, the rows and a total row. */
function Table({ name, head, rows, totals, empty, note, csv, w }) {
  const right = (v) => typeof v === "number";
  const plain = (v) => (v && typeof v === "object" && "text" in v ? v.text : v);
  return (
    <div className="flex flex-col gap-3 py-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-foreground-muted">{note ?? w("fin.dash.rows", { n: rows.length })}</p>
        <Button size="sm" variant="outline" disabled={!rows.length} onClick={() => downloadCsv(name, head, (csv ?? rows).map((r) => r.map(plain)))}>
          <Download /> {w("fin.dash.csv")}
        </Button>
      </div>
      {rows.length === 0 ? (
        <p className="pb-2 text-sm text-foreground-muted">{empty}</p>
      ) : (
        <div className="max-h-[32rem] overflow-auto">
          <table className="w-full min-w-[52rem] border border-border text-sm">
            <thead className="sticky top-0 bg-surface-muted text-xs text-foreground-muted">
              <tr>
                {head.map((h) => (
                  <th key={h} scope="col" className="border border-border px-2 py-2 text-left font-medium whitespace-nowrap">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr key={i} className="hover:bg-surface-muted">
                  {r.map((c, j) => (
                    <td key={j} className={cn("border border-border px-2 py-1.5", right(c) && "text-right tabular-nums")}>
                      {right(c) ? num(c) : c && typeof c === "object" && "node" in c ? c.node : (ampm(c) ?? "—")}
                    </td>
                  ))}
                </tr>
              ))}
              {totals ? (
                <tr className="bg-surface-muted font-semibold">
                  {totals.map((c, j) => (
                    <td key={j} className={cn("border border-border px-2 py-1.5", right(c) && "text-right tabular-nums")}>
                      {right(c) ? num(c) : c}
                    </td>
                  ))}
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

const sumCols = (rows) => rows.reduce((acc, r) => r.map((v, i) => (acc[i] ?? 0) + (Number(v) || 0)), []);

/** Bundle History — a bundle's way from cutting through sewing and finishing: open(code) fetches it, dialog shows it. */
function useBundleHistory(act) {
  const [state, setState] = useState(null);
  const [, startTransition] = useTransition();
  const open = (code) => {
    setState({ code, data: null, error: null });
    startTransition(async () => {
      const res = await act.trace(code);
      setState((cur) => (cur?.code === code ? { code, data: res?.status === "success" ? res.data : null, error: res?.status === "success" ? null : firstError(res) } : cur));
    });
  };
  return { open, dialog: <HistoryDialog state={state} onClose={() => setState(null)} /> };
}

function HistoryDialog({ state, onClose }) {
  const w = useWords();
  const data = state?.data;
  return (
    <Modal open={Boolean(state)} onOpenChange={(o) => !o && onClose()} title={w("fin.history.title", { no: data?.bundle.bundle_no ?? state?.code ?? "" })} description={w("fin.history.sub")} className="max-w-2xl">
      {state?.error ? <p className="text-sm text-danger">{state.error}</p> : null}
      {state && !data && !state.error ? <p className="text-sm text-foreground-muted">{w("fin.history.tracing")}</p> : null}
      {data ? (
        <>
          <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <span className="font-mono font-semibold">{data.bundle.bundle_no}</span>
            <span className="text-foreground-muted">
              {data.bundle.order_no} · {data.bundle.style} · {data.bundle.colour} · {data.bundle.size}
            </span>
            <Chip tone={STATUS_TONE[data.bundle.status]}>{w(`fin.status.${data.bundle.status}`)}</Chip>
          </div>
          <ol className="relative ml-2 border-l border-border">
            {data.steps.map((s, i) => (
              <li key={i} className="mb-4 ml-5 last:mb-0">
                <span className="absolute -left-[7px] mt-1 size-3.5 rounded-full border-2 border-surface bg-brand" />
                <p className="text-sm font-semibold">
                  {w(`fin.trace.${s.stage}`)} <span className="ml-1 font-normal text-foreground-muted">{at(s.at)}</span>
                </p>
                <p className="text-[13px] text-foreground-muted">
                  {w(`fin.trace.${s.stage}_d`, Object.fromEntries(Object.entries(s.params).map(([k, v]) => [k, v ?? "—"])))}
                  {s.by ? ` · ${w("fin.history.by", { name: s.by })}` : ""}
                </p>
              </li>
            ))}
          </ol>
        </>
      ) : null}
    </Modal>
  );
}

/**
 * A finishing desk — Bundle IN (receive a bundle out of sewing onto the
 * line) or Bundle OUT (scan it off with Good + Reject + Rework). Pick the
 * location, scan, check what loads, confirm; today's scans below.
 */
function FinishingDesk({ kind, options, initial, canManage, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const scanRef = useRef(null);
  const [section, setSection] = useState("");
  const [floor, setFloor] = useState("");
  const [line, setLine] = useState("");
  const [lineInfo, setLineInfo] = useState(null);
  const [code, setCode] = useState("");
  const [snap, setSnap] = useState(null);
  const [qty, setQty] = useState({ good: "0", reject: "0", rework: "0" });
  const [banner, setBanner] = useState(null);
  const [rows, setRows] = useState(initial);
  const history = useBundleHistory(act);
  const [pending, startTransition] = useTransition();
  const floors = useMemo(() => options.floors.filter((f) => f.section_id === section), [options.floors, section]);
  const lines = useMemo(() => options.lines.filter((l) => l.section_id === section && l.floor_id === floor), [options.lines, section, floor]);
  const located = Boolean(section && floor && line);
  const isOut = kind === "out";
  const sum = Number(qty.good || 0) + Number(qty.reject || 0) + Number(qty.rework || 0);
  const sumOk = Boolean(snap) && sum === snap.bundle.qty;
  const ready = canManage && located && snap && !snap.blocker && (!isOut || sumOk);
  const step = !located ? 1 : !snap ? 2 : isOut && !sumOk ? 3 : 4;

  const reloadRecent = () =>
    startTransition(async () => {
      const res = await act.recent(kind);
      if (res?.status === "success") setRows(res.data.rows);
    });
  const loadLine = (id) => {
    setLineInfo(null);
    if (!id) return;
    startTransition(async () => {
      const res = await act.line(id);
      if (res?.status === "success") setLineInfo(res.data);
    });
  };
  const resetScan = () => {
    setCode("");
    setSnap(null);
    setQty({ good: "0", reject: "0", rework: "0" });
  };
  const pickLine = (id) => {
    setLine(id);
    resetScan();
    setBanner(null);
    loadLine(id);
  };
  const scan = () => {
    if (!located || !code.trim()) return;
    startTransition(async () => {
      const res = await act.resolve({ code: code.trim(), line_id: line, kind });
      if (res?.status !== "success") {
        setSnap(null);
        return setBanner({ tone: "danger", title: w("fin.banner.failed"), text: firstError(res) });
      }
      setSnap(res.data);
      setLineInfo(res.data.line);
      setBanner(res.data.blocker ? { tone: "danger", title: w("fin.banner.failed"), text: res.data.blocker } : { tone: "info", title: w("fin.banner.loaded", { no: res.data.bundle.bundle_no }), text: null });
    });
  };
  const confirm = () => {
    if (!ready) return;
    startTransition(async () => {
      const base = { code: snap.bundle.bundle_no, section_id: section, floor_id: floor, line_id: line };
      const res = isOut ? await act.sendOut({ ...base, good_qty: Number(qty.good || 0), reject_qty: Number(qty.reject || 0), rework_qty: Number(qty.rework || 0) }) : await act.receive(base);
      if (res?.status !== "success") return setBanner({ tone: "danger", title: w("fin.banner.not_saved"), text: firstError(res) });
      const d = res.data;
      setBanner({ tone: "success", title: w(isOut ? "fin.banner.out_done" : "fin.banner.received"), text: isOut ? w("toast.fin_out", { no: d.bundle_no, good: d.good, reject: d.reject, rework: d.rework }) : w("toast.fin_received", { no: d.bundle_no, line: d.line }) });
      toastManager.add({ title: isOut ? w("toast.fin_out", { no: d.bundle_no, good: d.good, reject: d.reject, rework: d.rework }) : w("toast.fin_received", { no: d.bundle_no, line: d.line }), type: "success" });
      resetScan();
      reloadRecent();
      loadLine(line);
      scanRef.current?.focus();
    });
  };
  const clear = () => {
    resetScan();
    setBanner(null);
    scanRef.current?.focus();
  };

  const lineLabel = options.lines.find((l) => l.value === line)?.label;
  const sectionLabel = options.sections.find((s) => s.value === section)?.label;
  const floorLabel = options.floors.find((f) => f.value === floor)?.label;
  const b = snap?.bundle;
  const plan = snap?.plan;
  const li = lineInfo;
  const bannerTone = { danger: "border-danger/40 bg-danger/10 text-danger", success: "border-success/40 bg-success/10 text-success", info: "border-brand/40 bg-brand-subtle/40" };

  return (
    <>
      <section className="mb-4 rounded-lg border border-border bg-surface">
        <ol className="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((n) => (
            <li key={n} className="flex items-center gap-3">
              <span className={cn("grid size-9 shrink-0 place-items-center rounded-full text-sm font-semibold", n === step ? "bg-brand text-brand-foreground" : n < step ? "bg-success text-white" : "bg-surface-muted text-foreground-muted")}>{n}</span>
              <span>
                <span className="block text-sm font-semibold">{w(`fin.steps.${kind}.${n}.title`)}</span>
                <span className="block text-xs text-foreground-muted">{w(`fin.steps.${kind}.${n}.sub`)}</span>
              </span>
            </li>
          ))}
        </ol>
        <div className="grid items-end gap-4 px-5 py-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1.3fr]">
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">
              {w("fin.section")} <span className="text-danger">*</span>
            </span>
            <select
              className={nativeSelect}
              value={section}
              onChange={(e) => {
                setSection(e.target.value);
                setFloor("");
                pickLine("");
              }}
              aria-label={w("fin.section")}
            >
              <option value="">{options.sections.length ? w("fin.select_section") : w("fin.no_sections")}</option>
              {options.sections.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">
              {w("fin.floor")} <span className="text-danger">*</span>
            </span>
            <select
              className={nativeSelect}
              value={floor}
              disabled={!section}
              onChange={(e) => {
                setFloor(e.target.value);
                pickLine("");
              }}
              aria-label={w("fin.floor")}
            >
              <option value="">{section ? w("fin.select_floor") : w("fin.section_first")}</option>
              {floors.map((f) => (
                <option key={f.value} value={f.value}>
                  {f.label}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">
              {w("fin.line")} <span className="text-danger">*</span>
            </span>
            <select className={nativeSelect} value={line} disabled={!floor} onChange={(e) => pickLine(e.target.value)} aria-label={w("fin.line")}>
              <option value="">{floor ? w("fin.select_line") : w("fin.floor_first")}</option>
              {lines.map((l) => (
                <option key={l.value} value={l.value}>
                  {l.label}
                </option>
              ))}
            </select>
          </label>
          <div className={cn("flex items-center gap-3 rounded-lg border px-4 py-2.5", located ? "border-success/40 bg-success/10" : "border-border bg-surface-muted")}>
            <Layers className={cn("size-6 shrink-0", located ? "text-success" : "text-foreground-muted")} />
            <div>
              <p className="text-sm font-semibold">{w(located ? "fin.loc_ready" : "fin.loc_title")}</p>
              <p className="text-xs text-foreground-muted">{located ? w("fin.loc_ready_hint", { line: lineLabel, section: sectionLabel, floor: floorLabel }) : canManage ? w("fin.loc_hint") : w("fin.read_only")}</p>
            </div>
          </div>
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,24rem)_1fr]">
        <section className="flex flex-col items-center rounded-lg border border-border bg-surface p-5">
          <Barcode className="size-9 text-foreground-muted" />
          <p className="mt-2 text-[15px] font-semibold">
            {w("fin.scan_label")} <span className="text-danger">*</span>
          </p>
          <div className="mt-4 w-full rounded-lg border border-dashed border-border bg-surface-muted/40 p-4 text-center">
            <ScanLine className="mx-auto size-10 text-foreground-muted" />
            <Input
              ref={scanRef}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), scan())}
              placeholder={located ? w("fin.scan_here") : w("fin.scan_locked")}
              aria-label={w("fin.scan_label")}
              className="mt-3 h-12 text-center font-mono text-base tracking-widest"
              disabled={!located || pending}
              autoComplete="off"
            />
            <p className="mt-2 text-xs text-foreground-muted">{w("fin.scan_hint")}</p>
          </div>
          <Button variant="outline" className="mt-3 w-full" onClick={scan} disabled={!located || !code.trim() || pending}>
            {pending && code ? w("fin.reading") : w("fin.load")}
          </Button>
        </section>

        <div className="flex flex-col gap-4">
          <div className={cn("flex items-start gap-3 rounded-lg border border-dashed px-4 py-3 text-sm", banner ? bannerTone[banner.tone] : "border-border")}>
            {banner?.tone === "danger" ? <CircleX className="mt-0.5 size-4 shrink-0" /> : banner?.tone === "success" ? <CircleCheck className="mt-0.5 size-4 shrink-0" /> : <ScanLine className="mt-0.5 size-4 shrink-0 text-foreground-muted" />}
            <div>
              <p className="font-semibold">{banner ? banner.title : located ? w("fin.banner.wait") : w("fin.banner.begin")}</p>
              {banner?.text ? <p className="text-[13px]">{banner.text}</p> : null}
            </div>
          </div>

          <div className={cn("grid gap-4", isOut && "xl:grid-cols-2")}>
            <Panel icon={PackageOpen} title={w("fin.info_title")}>
              <div className="p-5">
                {b ? (
                  <Facts
                    items={[
                      [w("fin.f.bundle_no"), <span key="n" className="font-mono">{b.bundle_no}</span>],
                      [w("fin.f.order_po"), [b.order_no, b.po_no].filter(Boolean).join(" / ")],
                      [w("fin.f.buyer"), b.buyer],
                      [w("fin.f.style"), b.style],
                      [w("fin.f.colour"), b.colour],
                      [w("fin.f.size"), b.size],
                      [w("fin.f.bundle_qty"), num(b.qty)],
                      [w("fin.f.cut_no"), b.cutting_no],
                      [w("fin.f.previous"), b.previous ? w(`fin.prev.${b.previous.stage}`, { at: at(b.previous.at) }) : "—"],
                      [w("fin.f.in_at"), at(b.in_at)],
                      [w("fin.f.cur_section"), b.section],
                      [w("fin.f.cur_floor"), b.floor],
                      [w("fin.f.cur_line"), b.line],
                      [w("fin.f.cur_status"), <Chip key="s" tone={STATUS_TONE[b.status]}>{w(`fin.status.${b.status}`)}</Chip>],
                    ]}
                  />
                ) : (
                  <p className="py-6 text-center text-sm text-foreground-muted">{w("fin.info_empty")}</p>
                )}
              </div>
            </Panel>

            {isOut ? (
              <Panel icon={ClipboardList} title={w("fin.qty_title")}>
                <div className="flex flex-col gap-3 p-5">
                  <div className="flex items-center justify-between rounded-md border border-border bg-surface-muted px-4 py-2.5 text-sm">
                    <span>{w("fin.qty_auto")}</span>
                    <span className="font-semibold tabular-nums">{b ? num(b.qty) : "—"}</span>
                  </div>
                  {[
                    ["good", "fin.good", "text-success"],
                    ["reject", "fin.reject", "text-danger"],
                    ["rework", "fin.rework", "text-warning"],
                  ].map(([k, label, tone]) => (
                    <label key={k} className="grid grid-cols-[1fr_minmax(0,12rem)] items-center gap-3">
                      <span className={cn("text-sm font-semibold", tone)}>{w(label)}</span>
                      <Input type="number" min={0} value={qty[k]} onChange={(e) => setQty((q) => ({ ...q, [k]: e.target.value }))} disabled={!snap || Boolean(snap?.blocker) || !canManage} aria-label={w(label)} className="text-right tabular-nums" />
                    </label>
                  ))}
                  <p className={cn("rounded-md border px-4 py-2 text-sm", !snap ? "border-border" : sumOk ? "border-success/40 bg-success/10 text-success" : "border-danger/40 bg-danger/10 text-danger")}>
                    {snap ? w(sumOk ? "fin.qty_ok" : "fin.qty_bad", { sum: num(sum), qty: num(b.qty) }) : w("fin.qty_rule")}
                  </p>
                </div>
              </Panel>
            ) : null}
          </div>

          <Panel icon={Target} title={w("fin.plan_title")}>
            <div className="p-5">
              {!li ? (
                <p className="py-4 text-center text-sm text-foreground-muted">{w("fin.plan_pick")}</p>
              ) : (
                <div className="grid gap-x-8 gap-y-4 md:grid-cols-2">
                  <Facts
                    items={[
                      [w("fin.plan.line"), li.line],
                      [w("fin.plan.section_floor"), `${li.section ?? "—"} / ${li.floor ?? "—"}`],
                      [w("fin.plan.smv"), plan ? `${plan.smv ?? "—"} / ${plan.sam ?? "—"}` : "—"],
                      [w("fin.plan.manpower"), num(li.manpower)],
                      [w("fin.plan.hourly"), li.hourly_target ? num(li.hourly_target) : "—"],
                      [w("fin.plan.daily"), li.daily_target ? num(li.daily_target) : w("fin.plan.no_target")],
                      [w("fin.plan.start"), plan ? at(plan.start) : "—"],
                      [w("fin.plan.end"), plan ? at(plan.end) : "—"],
                      [w("fin.plan.ship"), plan ? at(plan.ship_date) : "—"],
                    ]}
                  />
                  <Facts
                    items={[
                      [w("fin.plan.planned"), plan ? num(plan.planned_qty) : "—"],
                      [w("fin.plan.completed"), plan ? num(plan.completed) : "—"],
                      [w("fin.plan.remaining"), plan ? num(plan.remaining) : "—"],
                      [w("fin.plan.today"), `${num(li.today_in)} / ${num(li.today_out)}`],
                      [w("fin.plan.grr"), `${num(li.good)} / ${num(li.reject)} / ${num(li.rework)}`],
                      [w("fin.plan.wip"), w("fin.dash.pcs", { n: num(li.wip_qty) })],
                      [w("fin.plan.achv"), li.achievement === null ? w("fin.plan.no_target") : `${li.achievement}%`],
                    ]}
                  />
                  {snap && !plan ? <p className="text-sm text-warning md:col-span-2">{w("fin.plan.no_plan")}</p> : null}
                  {!snap ? <p className="text-xs text-foreground-muted md:col-span-2">{w("fin.plan.scan")}</p> : null}
                </div>
              )}
            </div>
          </Panel>
        </div>
      </div>

      <section className="mt-4 rounded-lg border border-border bg-surface">
        <header className="flex items-center justify-between gap-3 px-5 pt-4">
          <h2 className="text-[15px] font-semibold">
            {w("fin.recent")} <span className="font-normal text-foreground-muted">· {rows.length}</span>
          </h2>
          <Button variant="outline" size="sm" onClick={reloadRecent} disabled={pending}>
            <RefreshCw /> {w("fin.refresh")}
          </Button>
        </header>
        <div className="px-5 pb-2">
          {rows.length === 0 ? (
            <p className="py-4 text-sm text-foreground-muted">{w(isOut ? "fin.recent_empty_out" : "fin.recent_empty_in")}</p>
          ) : (
            <Table
              w={w}
              name={`finishing-bundle-${kind}-today`}
              head={[
                w("fin.col.time"),
                w("fin.col.bundle_no"),
                w("fin.col.order"),
                w("fin.col.style"),
                w("fin.col.qty"),
                w("fin.col.section"),
                w("fin.col.floor"),
                w("fin.col.line"),
                ...(isOut ? [w("fin.col.good"), w("fin.col.reject"), w("fin.col.rework")] : []),
                w("fin.col.action"),
              ]}
              rows={rows.map((r) => [
                r.time,
                { text: r.bundle_no, node: <span className="font-mono">{r.bundle_no}</span> },
                r.order_no,
                r.style,
                r.qty,
                r.section,
                r.floor,
                r.line,
                ...(isOut ? [r.good, r.reject, r.rework] : []),
                {
                  text: "",
                  node: (
                    <Button variant="ghost" size="sm" onClick={() => history.open(r.bundle_no)} aria-label={w("fin.view_history", { no: r.bundle_no })}>
                      <Eye /> {w("fin.view")}
                    </Button>
                  ),
                },
              ])}
              empty=""
            />
          )}
        </div>
      </section>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
        <Button variant="danger" size="lg" onClick={clear}>
          <Trash2 /> {w("fin.clear")}
        </Button>
        {canManage ? (
          <Button size="lg" onClick={confirm} disabled={!ready || pending} className={cn("min-w-60 justify-center", ready && "bg-success hover:bg-success/90")}>
            <CircleCheck /> {pending && snap ? w("fin.saving") : w(isOut ? "fin.confirm_out" : "fin.confirm_in")}
          </Button>
        ) : null}
      </div>
      {history.dialog}
    </>
  );
}

const VIEWS = ["line", "floor", "section", "unit"];

/** The Finishing Dashboard: filters, key figures, Target vs Actual, the seven tabs and Bundle History. */
function FinishingBoard({ options, initial, canSupervise, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [f, setF] = useState({ from: initial.from, to: initial.to, shift_id: "", section_id: "", floor_id: "", line_id: "", view: initial.view });
  const [data, setData] = useState(initial);
  const [moving, setMoving] = useState(null);
  const history = useBundleHistory(act);
  const [traceCode, setTraceCode] = useState("");
  const [pending, startTransition] = useTransition();
  const floors = useMemo(() => options.floors.filter((x) => !f.section_id || x.section_id === f.section_id).filter((x, i, a) => a.findIndex((y) => y.value === x.value) === i), [options.floors, f.section_id]);
  const lines = useMemo(() => options.lines.filter((l) => (!f.section_id || l.section_id === f.section_id) && (!f.floor_id || l.floor_id === f.floor_id)), [options.lines, f.section_id, f.floor_id]);

  const apply = (q = f) =>
    startTransition(async () => {
      const res = await act.board(q);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setData(res.data);
    });
  const quick = (days) => {
    const to = options.today;
    const q = { ...f, from: addDays(to, 1 - days), to };
    setF(q);
    apply(q);
  };
  const set = (k, v) => setF((x) => ({ ...x, [k]: v, ...(k === "section_id" ? { floor_id: "", line_id: "" } : k === "floor_id" ? { line_id: "" } : {}) }));
  const move = (row, status) => {
    setMoving(row.bundle_no);
    startTransition(async () => {
      const res = await act.rework({ bundle_no: row.bundle_no, status });
      setMoving(null);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w("toast.rework_moved", { no: row.bundle_no, status: w(`rework.${res.data.rework_status}`) }), type: "success" });
      apply();
    });
  };

  const s = data.summary;
  const range = data.from === data.to ? dayLabel(data.from) : `${dayLabel(data.from)} – ${dayLabel(data.to)}`;
  const viewName = w(`fin.dash.view.${data.view}`);
  const stamp = data.from === data.to ? data.from : `${data.from}_${data.to}`;
  const pct = (v) => (v === null || v === undefined ? "—" : `${v}%`);
  const outHead = [w("fin.col.out_at"), w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("fin.col.bundle_qty"), w("fin.col.good"), w("fin.col.reject"), w("fin.col.rework"), w("fin.col.by")];
  const outRow = (r) => [at(r.out_at), r.bundle_no, r.order_no, r.style, r.section, r.floor, r.line, r.qty, r.good, r.reject, r.rework, r.by];
  const outTotals = (rows) => (rows.length ? [w("fin.dash.total"), "", "", "", "", "", "", ...sumCols(rows.map((r) => [r.qty, r.good, r.reject, r.rework])), ""] : undefined);
  const rejects = data.outs.filter((r) => r.reject > 0);
  const openRework = data.rework.filter((r) => r.rework_status !== "COMPLETED").length;

  const tabs = [
    ["wip", data.wip.length, <Table key="wip" w={w} name={`finishing-wip-${stamp}`} head={[w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.qty"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("fin.col.in_at"), w("fin.col.status")]} rows={data.wip.map((r) => [r.bundle_no, r.order_no, r.style, r.qty, r.section, r.floor, r.line, at(r.in_at), w(`fin.status.${r.status}`)])} empty={w("fin.dash.empty.wip")} />],
    ["in", data.ins.length, <Table key="in" w={w} name={`finishing-bundle-in-${stamp}`} head={[w("fin.col.in_at"), w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.colour"), w("fin.col.size"), w("fin.col.qty"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("fin.col.by")]} rows={data.ins.map((r) => [at(r.in_at), r.bundle_no, r.order_no, r.style, r.colour, r.size, r.qty, r.section, r.floor, r.line, r.by])} empty={w("fin.dash.empty.in")} />],
    ["out", data.outs.length, <Table key="out" w={w} name={`finishing-bundle-out-${stamp}`} head={outHead} rows={data.outs.map(outRow)} totals={outTotals(data.outs)} empty={w("fin.dash.empty.out")} />],
    ["reject", rejects.length, <Table key="reject" w={w} name={`finishing-reject-${stamp}`} head={outHead} rows={rejects.map(outRow)} totals={outTotals(rejects)} empty={w("fin.dash.empty.reject")} />],
    [
      "rework",
      openRework,
      <Table
        key="rework"
        w={w}
        name={`finishing-rework-${stamp}`}
        note={`${w("fin.dash.rework_note")}${canSupervise ? "" : ` ${w("fin.dash.rework_sup")}`}`}
        head={[w("fin.col.out_at"), w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.line"), w("fin.col.rework_qty"), w("fin.col.status"), w("fin.col.action")]}
        rows={data.rework.map((r) => [
          at(r.out_at),
          r.bundle_no,
          r.order_no,
          r.style,
          r.line,
          r.rework,
          { text: w(`rework.${r.rework_status}`), node: <Chip tone={REWORK_TONE[r.rework_status]}>{w(`rework.${r.rework_status}`)}</Chip> },
          {
            text: "",
            node:
              canSupervise && r.rework_status !== "COMPLETED" ? (
                <div className="flex gap-1.5">
                  {r.rework_status === "PENDING" ? (
                    <Button size="sm" variant="outline" disabled={moving === r.bundle_no} onClick={() => move(r, "PROCESSING")}>
                      {w("fin.dash.start")}
                    </Button>
                  ) : null}
                  <Button size="sm" disabled={moving === r.bundle_no} onClick={() => move(r, "COMPLETED")}>
                    {w("fin.dash.complete")}
                  </Button>
                </div>
              ) : (
                <span className="text-foreground-muted">—</span>
              ),
          },
        ])}
        csv={data.rework.map((r) => [...outRow(r), w(`rework.${r.rework_status}`)])}
        empty={w("fin.dash.empty.rework")}
      />,
    ],
    [
      "pending",
      data.pending.length,
      <Table
        key="pending"
        w={w}
        name="finishing-pending-bundles"
        note={w("fin.dash.pending_note")}
        head={[w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.colour"), w("fin.col.size"), w("fin.col.qty"), w("fin.col.sewing_line"), w("fin.col.sewing_out")]}
        rows={data.pending.map((r) => [r.bundle_no, r.order_no, r.style, r.colour, r.size, r.qty, r.sewing_line, at(r.sewing_out_at)])}
        empty={w("fin.dash.empty.pending")}
      />,
    ],
    [
      "daily",
      null,
      <Table
        key="daily"
        w={w}
        name={`finishing-daily-${stamp}`}
        head={[w("fin.col.date"), w("fin.col.bundle_in"), w("fin.col.bundle_out"), w("fin.col.good"), w("fin.col.reject"), w("fin.col.rework")]}
        rows={data.daily.map((r) => [dayLabel(r.day), r.bundle_in, r.bundle_out, r.good, r.reject, r.rework])}
        totals={[w("fin.dash.total"), ...sumCols(data.daily.map((r) => [r.bundle_in, r.bundle_out, r.good, r.reject, r.rework]))]}
        empty={w("fin.dash.empty.daily")}
      />,
    ],
  ];

  const sel = (k, label, all, opts) => (
    <label className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <select className={cn(nativeSelect, "h-9")} value={f[k]} onChange={(e) => set(k, e.target.value)} aria-label={label}>
        {all !== null ? <option value="">{all}</option> : null}
        {opts.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );

  return (
    <div className="flex flex-col gap-4">
      <form
        className="grid gap-3 rounded-lg border border-border bg-surface p-5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8"
        onSubmit={(e) => {
          e.preventDefault();
          apply();
        }}
      >
        <label className="flex flex-col gap-1">
          <span className="text-xs font-medium text-foreground-muted">{w("fin.dash.from")}</span>
          <Input type="date" className="h-9" value={f.from} onChange={(e) => set("from", e.target.value)} aria-label={w("fin.dash.from")} />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-xs font-medium text-foreground-muted">{w("fin.dash.to")}</span>
          <Input type="date" className="h-9" value={f.to} onChange={(e) => set("to", e.target.value)} aria-label={w("fin.dash.to")} />
        </label>
        {sel("shift_id", w("fin.dash.shift"), w("fin.dash.all_shifts"), options.shifts)}
        {sel("section_id", w("fin.dash.section"), w("fin.dash.all_sections"), options.sections)}
        {sel("floor_id", w("fin.dash.floor"), w("fin.dash.all_floors"), floors)}
        {sel("line_id", w("fin.dash.line"), w("fin.dash.all_lines"), lines)}
        {sel("view", w("fin.dash.view_by"), null, VIEWS.map((v) => ({ value: v, label: w(`fin.dash.view.${v}`) })))}
        <div className="flex items-end">
          <Button type="submit" className="w-full justify-center" disabled={pending}>
            {pending ? w("fin.dash.loading") : w("fin.dash.apply")}
          </Button>
        </div>
        <div className="col-span-full flex flex-wrap items-center gap-2">
          <Button type="button" size="sm" variant="ghost" onClick={() => quick(1)}>
            {w("fin.dash.today")}
          </Button>
          <Button type="button" size="sm" variant="ghost" onClick={() => quick(7)}>
            {w("fin.dash.last7")}
          </Button>
          <Button type="button" size="sm" variant="ghost" onClick={() => quick(30)}>
            {w("fin.dash.last30")}
          </Button>
          {data.unfiled.length ? <span className="text-xs text-warning">{w("fin.dash.unfiled", { lines: data.unfiled.join(", ") })}</span> : null}
        </div>
      </form>

      <section aria-label={w("fin.dash.t.lines")} className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
        <StatCard label={w("fin.dash.t.bundle_in")} value={num(s.bundle_in)} supportingText={w("fin.dash.t.bundle_in_cap", { qty: num(s.bundle_in_qty), range })} icon={<PackagePlus />} tone="info" />
        <StatCard label={w("fin.dash.t.bundle_out")} value={num(s.bundle_out)} supportingText={range} icon={<PackageCheck />} tone="success" />
        <StatCard label={w("fin.dash.t.wip")} value={num(s.wip_bundles)} supportingText={w("fin.dash.t.wip_cap", { qty: num(s.wip_qty) })} icon={<Boxes />} tone="warning" />
        <StatCard label={w("fin.dash.t.good")} value={w("fin.dash.pcs", { n: num(s.good) })} supportingText={s.target ? w("fin.dash.t.good_cap", { t: num(s.target) }) : w("fin.dash.t.no_target")} icon={<CircleCheck />} tone="success" />
        <StatCard label={w("fin.dash.t.achv")} value={pct(s.achievement)} supportingText={w("fin.dash.t.achv_cap")} icon={<Target />} tone="brand" />
        <StatCard label={w("fin.dash.t.reject")} value={w("fin.dash.pcs", { n: num(s.reject) })} supportingText={range} icon={<Ban />} tone="danger" />
        <StatCard label={w("fin.dash.t.rework")} value={w("fin.dash.pcs", { n: num(s.rework) })} supportingText={range} icon={<Hammer />} tone="warning" />
        <StatCard label={w("fin.dash.t.rework_pending")} value={w("fin.dash.pcs", { n: num(s.rework_pending) })} supportingText={w("fin.dash.t.rework_pending_cap")} icon={<Timer />} tone="danger" />
        <StatCard label={w("fin.dash.t.pending")} value={num(s.pending_bundles)} supportingText={w("fin.dash.t.pending_cap", { qty: num(s.pending_qty) })} icon={<PackageOpen />} tone="info" />
        <StatCard label={w("fin.dash.t.lines")} value={num(s.lines)} supportingText={data.shift ? w("fin.dash.t.shift", { s: data.shift }) : w("fin.dash.t.all_shifts")} icon={<Clock />} tone="brand" />
      </section>

      <Panel
        icon={Target}
        title={w("fin.dash.perf_title", { view: viewName })}
        sub={w("fin.dash.perf_sub")}
        action={
          <Button
            size="sm"
            variant="outline"
            disabled={!data.performance.length}
            onClick={() =>
              downloadCsv(
                `finishing-${data.view}-wise-${stamp}`,
                [w(`fin.dash.head.${data.view}`), w("fin.col.lines"), w("fin.col.target"), w("fin.col.actual"), `${w("fin.col.achv")} %`, w("fin.col.bundle_in"), w("fin.col.bundle_out"), w("fin.col.wip"), w("fin.col.wip_qty"), w("fin.col.reject"), w("fin.col.rework")],
                data.performance.map((r) => [r.label, r.lines, r.target ?? "", r.actual, r.achievement ?? "", r.bundle_in, r.bundle_out, r.wip, r.wip_qty, r.reject, r.rework]),
              )
            }
          >
            <Download /> {w("fin.dash.csv")}
          </Button>
        }
      >
        {data.performance.length === 0 ? (
          <p className="px-5 py-5 text-sm text-foreground-muted">{w("fin.dash.perf_empty")}</p>
        ) : (
          <div className="overflow-x-auto px-5 py-4">
            <table className="w-full min-w-[52rem] border border-border text-sm">
              <thead className="bg-brand text-xs text-brand-foreground">
                <tr>
                  <th scope="col" className="border border-border px-2 py-2 text-left font-medium">
                    {w(`fin.dash.head.${data.view}`)}
                  </th>
                  {data.view !== "line" ? (
                    <th scope="col" className="border border-border px-2 py-2 text-left font-medium">
                      {w("fin.col.lines")}
                    </th>
                  ) : null}
                  {["target", "actual", "achv", "bundle_in", "bundle_out", "wip", "reject", "rework"].map((k) => (
                    <th key={k} scope="col" className="border border-border px-2 py-2 text-left font-medium">
                      {w(`fin.col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.performance.map((r) => (
                  <tr key={r.label} className="hover:bg-surface-muted">
                    <td className="border border-border px-2 py-1.5">
                      <span className="font-semibold">{r.label}</span>
                      {r.sub ? <span className="block text-xs text-foreground-muted">{r.sub}</span> : null}
                    </td>
                    {data.view !== "line" ? <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.lines)}</td> : null}
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.target)}</td>
                    <td className="border border-border px-2 py-1.5 text-right font-semibold tabular-nums">{num(r.actual)}</td>
                    <td className={cn("border border-border px-2 py-1.5 text-right tabular-nums", r.achievement === null ? "" : r.achievement >= 90 ? "text-success" : r.achievement >= 75 ? "text-warning" : "text-danger")}>{pct(r.achievement)}</td>
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.bundle_in)}</td>
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.bundle_out)}</td>
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">
                      {num(r.wip)} <span className="text-xs text-foreground-muted">({num(r.wip_qty)})</span>
                    </td>
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.reject)}</td>
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(r.rework)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <section className="rounded-lg border border-border bg-surface px-5 pt-3">
        <Tabs defaultValue="wip">
          <TabsList>
            {tabs.map(([key, badge]) => (
              <TabsTab key={key} value={key}>
                {w(`fin.dash.tab.${key}`)}
                {badge !== null ? <span className="ml-1.5 rounded-full bg-surface-muted px-1.5 text-xs text-foreground-muted tabular-nums">{badge}</span> : null}
              </TabsTab>
            ))}
            <TabsIndicator />
          </TabsList>
          {tabs.map(([key, , content]) => (
            <TabsPanel key={key} value={key}>
              {content}
            </TabsPanel>
          ))}
        </Tabs>
      </section>

      <Panel icon={History} title={w("fin.history.card_title")} sub={w("fin.history.card_sub")}>
        <form
          className="flex flex-wrap items-end gap-3 px-5 py-4"
          onSubmit={(e) => {
            e.preventDefault();
            if (traceCode.trim()) history.open(traceCode.trim());
          }}
        >
          <label className="flex w-72 flex-col gap-1">
            <span className="text-xs font-medium text-foreground-muted">{w("fin.history.number")}</span>
            <Input value={traceCode} onChange={(e) => setTraceCode(e.target.value)} placeholder={w("fin.history.placeholder")} className="font-mono" aria-label={w("fin.history.number")} autoComplete="off" />
          </label>
          <Button type="submit" disabled={!traceCode.trim()}>
            <History /> {w("fin.history.trace")}
          </Button>
        </form>
      </Panel>
      {history.dialog}
    </div>
  );
}

export { FinishingNav, FinishingDesk, FinishingBoard };
// Shared with Quality Management (20/03), which lays its desks and dashboard out the same way.
export { Panel, Facts, Chip, Table, downloadCsv, at, num, firstError, nativeSelect, sumCols, addDays, useWords };
