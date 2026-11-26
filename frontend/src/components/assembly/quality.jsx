"use client";

import { useCallback, useEffect, useMemo, useRef, useState, useTransition } from "react";
import Link from "next/link";
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import {
  Ban,
  Barcode,
  BarChart3,
  Boxes,
  CircleCheck,
  CircleX,
  ClipboardList,
  Eye,
  Gauge,
  Hammer,
  History,
  Hourglass,
  Layers,
  PackageCheck,
  PackageOpen,
  PackagePlus,
  Percent,
  RefreshCw,
  ScanLine,
  SearchCheck,
  Table2,
  Target,
  Timer,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { StatCard } from "@/components/ui/stat-card";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { Tabs, TabsIndicator, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { useToastManager } from "@/components/ui/toast";
import { Chip, Facts, Panel, Table, addDays, at, downloadCsv, firstError, nativeSelect, num, sumCols, useWords } from "@/components/assembly/finishing";

/*
 * Assembly Floor → Quality Management (AnnotechERP 20/03), as Brickland lays
 * its four screens out under the Dashboard / Quality IN / Inspection /
 * Quality OUT buttons: the three desks (four steps, Section › Floor › Line
 * remembered per desk, the line's day, the scan box, Bundle Information, the
 * Inspection Result or the Remarks, the open rework a supervisor moves on,
 * Today's scans, Clear and Confirm) and the Quality Management Dashboard
 * (filters, twelve key figures, the trend, the inspection result, Target vs
 * Actual, the ten report tabs and Bundle Quality History).
 */

const STAGE_TONE = {
  NOT_READY: "bg-danger/15 text-danger",
  READY: "bg-info-subtle text-info",
  IN_QUALITY: "bg-warning/15 text-warning",
  REWORK_PENDING: "bg-warning/15 text-warning",
  REWORK_PROCESSING: "bg-info-subtle text-info",
  READY_REINSPECT: "bg-info-subtle text-info",
  INSPECTED: "bg-success/15 text-success",
  QUALITY_OUT: "bg-surface-muted text-foreground-muted",
};
const FINAL_TONE = { PASS: "bg-success/15 text-success", REJECT: "bg-danger/15 text-danger", REWORK_PENDING: "bg-warning/15 text-warning", NOT_INSPECTED: "bg-surface-muted text-foreground-muted" };
const REWORK_TONE = { PENDING: "bg-warning/15 text-warning", PROCESSING: "bg-info-subtle text-info", COMPLETED: "bg-success/15 text-success" };
const QM_STAGES = new Set(["QM_IN", "QM_INSPECT", "QM_REWORK_START", "QM_REWORK_DONE", "QM_OUT"]);
const SERIES = [
  { key: "in_qty", color: "var(--brand)", label: "qm.dash.s.in" },
  { key: "passed", color: "var(--success)", label: "qm.dash.s.passed" },
  { key: "reject", color: "var(--danger)", label: "qm.dash.s.reject" },
  { key: "rework", color: "var(--warning)", label: "qm.dash.s.rework" },
];
const pctOf = (v, of) => (of > 0 ? Math.round((v / of) * 1000) / 10 : null);
const pct = (v) => (v === null || v === undefined ? "—" : `${v}%`);
const roundLabel = (w, round) => (round > 1 ? w("qm.round_n", { n: round }) : w("qm.round_first"));
const storeKey = (kind) => `quality-desk-location:${kind}`;

/** The Dashboard / Quality IN / Inspection / Quality OUT buttons over each quality screen. */
function QualityNav({ active }) {
  const w = useWords();
  const items = [
    ["dashboard", "/assembly-floor/quality-dashboard", "qm.nav.dashboard", Gauge],
    ["in", "/assembly-floor/quality-in", "qm.nav.in", ScanLine],
    ["inspect", "/assembly-floor/quality-inspection", "qm.nav.inspect", SearchCheck],
    ["out", "/assembly-floor/quality-out", "qm.nav.out", PackageCheck],
  ];
  return (
    <nav className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      {items.map(([key, href, label, Icon]) => (
        <Link
          key={key}
          href={href}
          aria-current={key === active ? "page" : undefined}
          className={cn(
            "flex h-11 items-center justify-center gap-2 rounded-lg border text-sm font-semibold transition-colors",
            key === active ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          <Icon className="size-4" /> {w(label)}
        </Link>
      ))}
    </nav>
  );
}

/** Bundle Quality History — a bundle's way from cutting through sewing, finishing and quality. */
function useQualityHistory(act) {
  const [state, setState] = useState(null);
  const [, startTransition] = useTransition();
  const open = (code) => {
    setState({ code, data: null, error: null });
    startTransition(async () => {
      const res = await act.trace(code);
      setState((cur) => (cur?.code === code ? { code, data: res?.status === "success" ? res.data : null, error: res?.status === "success" ? null : firstError(res) } : cur));
    });
  };
  return { open, dialog: <QualityHistoryDialog state={state} onClose={() => setState(null)} /> };
}

function QualityHistoryDialog({ state, onClose }) {
  const w = useWords();
  const data = state?.data;
  const key = (stage) => (QM_STAGES.has(stage) ? `qm.trace.${stage}` : `fin.trace.${stage}`);
  return (
    <Modal open={Boolean(state)} onOpenChange={(o) => !o && onClose()} title={w("fin.history.title", { no: data?.bundle.bundle_no ?? state?.code ?? "" })} description={w("qm.history.sub")} className="max-w-2xl">
      {state?.error ? <p className="text-sm text-danger">{state.error}</p> : null}
      {state && !data && !state.error ? <p className="text-sm text-foreground-muted">{w("fin.history.tracing")}</p> : null}
      {data ? (
        <>
          <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <span className="font-mono font-semibold">{data.bundle.bundle_no}</span>
            <span className="text-foreground-muted">
              {data.bundle.order_no} · {data.bundle.style} · {data.bundle.colour} · {data.bundle.size}
            </span>
            <Chip tone={STAGE_TONE[data.bundle.stage]}>{w(`qm.stage.${data.bundle.stage}`)}</Chip>
          </div>
          <ol className="relative ml-2 border-l border-border">
            {data.steps.map((s, i) => (
              <li key={i} className="mb-4 ml-5 last:mb-0">
                <span className={cn("absolute -left-[7px] mt-1 size-3.5 rounded-full border-2 border-surface", QM_STAGES.has(s.stage) ? "bg-success" : "bg-brand")} />
                <p className="text-sm font-semibold">
                  {w(key(s.stage), s.params)} <span className="ml-1 font-normal text-foreground-muted">{at(s.at)}</span>
                </p>
                <p className="text-[13px] text-foreground-muted">
                  {w(`${key(s.stage)}_d`, Object.fromEntries(Object.entries(s.params).map(([k, v]) => [k, v ?? "—"])))}
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

/** The picked line's day: received, judged, released, and what stands on it to judge. */
function LineToday({ info }) {
  const w = useWords();
  const items = [
    [w("qm.today.in"), `${num(info.today_in)} · ${w("fin.dash.pcs", { n: num(info.today_in_qty) })}`],
    [w("qm.today.inspected"), num(info.inspected)],
    [w("qm.today.passed"), num(info.passed)],
    [w("qm.today.reject"), num(info.reject)],
    [w("qm.today.rework"), num(info.rework)],
    [w("qm.today.out"), num(info.today_out)],
    [w("qm.today.on_line"), w("qm.today.on_line_v", { b: num(info.wip_bundles), q: num(info.pending_qty) })],
    [w("qm.today.target"), info.daily_target ? num(info.daily_target) : "—"],
  ];
  return (
    <section className="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-border bg-surface px-5 py-3">
      <span className="flex items-center gap-2 text-sm font-semibold">
        <Gauge className="size-4 text-brand" /> {w("qm.today.title", { line: info.line })}
      </span>
      {items.map(([k, v]) => (
        <span key={k} className="text-sm">
          <span className="text-foreground-muted">{k}: </span>
          <span className="font-semibold tabular-nums">{v}</span>
        </span>
      ))}
    </section>
  );
}

/**
 * A quality desk — Quality IN (receive a bundle out of Finishing onto the
 * line), Inspection (judge it into Pass + Reject + Rework, round after round)
 * or Quality OUT (release it once nothing is left to judge). Pick the
 * location, scan, check what loads, confirm; today's scans below.
 */
function QualityDesk({ kind, options, initial, canManage, canSupervise, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const scanRef = useRef(null);
  const passRef = useRef(null);
  const [loc, setLoc] = useState({ section: "", floor: "", line: "" });
  const [lineInfo, setLineInfo] = useState(null);
  const [code, setCode] = useState("");
  const [snap, setSnap] = useState(null);
  const [qty, setQty] = useState({ pass: "", reject: "", rework: "" });
  const [defect, setDefect] = useState("");
  const [remark, setRemark] = useState("");
  const [banner, setBanner] = useState(null);
  const [saved, setSaved] = useState(null);
  const [rows, setRows] = useState(initial);
  const history = useQualityHistory(act);
  const [pending, startTransition] = useTransition();
  const [moving, startMoving] = useTransition();
  const { section, floor, line } = loc;
  const floors = useMemo(() => options.floors.filter((f) => f.section_id === section), [options.floors, section]);
  const lines = useMemo(() => options.lines.filter((l) => l.section_id === section && l.floor_id === floor), [options.lines, section, floor]);
  const located = Boolean(section && floor && line);
  const isInspect = kind === "inspect";
  const toInspect = snap?.inspection?.qty ?? 0;
  const q = { pass: Number(qty.pass || 0), reject: Number(qty.reject || 0), rework: Number(qty.rework || 0) };
  const sum = q.pass + q.reject + q.rework;
  const defective = q.reject + q.rework > 0;
  const sumOk = Boolean(snap?.inspection) && toInspect > 0 && sum === toInspect;
  const defectOk = !defective || (defect !== "" && (defect !== "OTHER" || remark.trim() !== ""));
  const judged = !isInspect || (sumOk && defectOk);
  const ready = canManage && located && snap && !snap.blocker && judged;
  const step = !located ? 1 : !snap ? 2 : snap.blocker || !judged ? 3 : 4;

  const loadLine = useCallback(
    (id) => {
      setLineInfo(null);
      if (!id) return;
      act.line(id).then((res) => res?.status === "success" && setLineInfo(res.data));
    },
    [act],
  );
  const resetScan = () => {
    setCode("");
    setSnap(null);
    setQty({ pass: "", reject: "", rework: "" });
    setDefect("");
    setRemark("");
  };
  const place = useCallback(
    (next) => {
      setLoc(next);
      try {
        if (next.section && next.floor && next.line) window.localStorage.setItem(storeKey(kind), JSON.stringify(next));
        else window.localStorage.removeItem(storeKey(kind));
      } catch {}
      loadLine(next.line);
      if (next.line) setTimeout(() => scanRef.current?.focus(), 0);
    },
    [kind, loadLine],
  );
  // Each desk remembers its last Section › Floor › Line, as Brickland's do, while that line is still offered.
  useEffect(() => {
    let saved = null;
    try {
      saved = JSON.parse(window.localStorage.getItem(storeKey(kind)) ?? "null");
    } catch {}
    if (saved && options.lines.some((l) => l.value === saved.line && l.section_id === saved.section && l.floor_id === saved.floor)) {
      const timer = setTimeout(() => place(saved), 0);
      return () => clearTimeout(timer);
    }
  }, [kind, options.lines, place]);

  const reloadRecent = () =>
    startTransition(async () => {
      const res = await act.recent(kind);
      if (res?.status === "success") setRows(res.data.rows);
    });
  const relocate = (next) => {
    place(next);
    resetScan();
    setBanner(null);
    setSaved(null);
  };
  const resolve = (bundleNo) =>
    act.resolve({ code: bundleNo, line_id: line, kind }).then((res) => {
      if (res?.status !== "success") {
        setSnap(null);
        setBanner({ tone: "danger", title: w("fin.banner.failed"), text: firstError(res) });
        scanRef.current?.select();
        return;
      }
      const d = res.data;
      setSnap(d);
      setLineInfo(d.line);
      setBanner(d.blocker ? { tone: "danger", title: w("fin.banner.failed"), text: d.blocker } : { tone: "info", title: w("fin.banner.loaded", { no: d.bundle.bundle_no }), text: null });
      if (d.inspection) {
        setQty({ pass: String(d.inspection.qty), reject: "0", rework: "0" });
        setTimeout(() => passRef.current?.select(), 0);
      } else if (d.blocker) scanRef.current?.select();
    });
  const scan = (e) => {
    e?.preventDefault();
    if (!located || !code.trim()) return;
    setSaved(null);
    startTransition(() => resolve(code.trim()));
  };
  const confirm = () => {
    if (!ready) return;
    startTransition(async () => {
      const base = { code: snap.bundle.bundle_no, section_id: section, floor_id: floor, line_id: line };
      const res =
        kind === "in"
          ? await act.receive(base)
          : isInspect
            ? await act.inspect({ ...base, pass_qty: q.pass, reject_qty: q.reject, rework_qty: q.rework, defect_type: defective ? defect : null, remark: remark.trim() || null })
            : await act.sendOut({ ...base, remarks: remark.trim() || null });
      if (res?.status !== "success") return setSaved({ ok: false, text: firstError(res) });
      const d = res.data;
      const text =
        kind === "in"
          ? w("toast.qm_received", { no: d.bundle_no, line: d.line })
          : isInspect
            ? w("toast.qm_inspected", { no: d.bundle_no, round: roundLabel(w, d.round), pass: d.pass, reject: d.reject, rework: d.rework })
            : w("toast.qm_out", { no: d.bundle_no, status: w(`qm.final.${d.final_status}`) });
      setSaved({ ok: true, text });
      setBanner(null);
      toastManager.add({ title: text, type: "success" });
      resetScan();
      reloadRecent();
      loadLine(line);
      scanRef.current?.focus();
    });
  };
  const moveRework = (status) => {
    const r = snap?.rework;
    if (!r) return;
    startMoving(async () => {
      const res = await act.rework({ inspection_id: r.inspection_id, status });
      if (res?.status !== "success") return setSaved({ ok: false, text: firstError(res) });
      setSaved({ ok: true, title: w("qm.rework_saved"), text: w("toast.rework_moved", { no: res.data.bundle_no, status: w(`rework.${res.data.rework_status}`) }) });
      await resolve(snap.bundle.bundle_no);
      loadLine(line);
    });
  };
  const clear = () => {
    resetScan();
    setBanner(null);
    setSaved(null);
    scanRef.current?.focus();
  };

  const sectionLabel = options.sections.find((s) => s.value === section)?.label;
  const floorLabel = options.floors.find((f) => f.value === floor)?.label;
  const lineCode = options.lines.find((l) => l.value === line)?.code;
  const b = snap?.bundle;
  const bannerTone = { danger: "border-danger/40 bg-danger/10 text-danger", success: "border-success/40 bg-success/10 text-success", info: "border-brand/40 bg-brand-subtle/40" };
  const stageChip = b ? <Chip key="s" tone={STAGE_TONE[b.stage]}>{w(`qm.stage.${b.stage}`)}</Chip> : null;
  const facts = !b
    ? []
    : [
        [w("fin.f.bundle_no"), <span key="n" className="font-mono text-brand">{b.bundle_no}</span>],
        [w("fin.f.order_po"), [b.order_no, b.po_no].filter(Boolean).filter((v, i, a) => a.indexOf(v) === i).join(" / ")],
        [w("fin.f.buyer"), b.buyer],
        [w("fin.f.style"), b.style],
        [w("fin.f.colour"), b.colour],
        [w("fin.f.size"), b.size],
        [w("fin.f.bundle_qty"), w("fin.dash.pcs", { n: num(b.qty) })],
        ...(kind === "in"
          ? [
              [w("fin.f.cut_no"), b.cutting_no],
              [w("fin.f.previous"), b.previous ? w("qm.prev.FIN_OUT", { at: at(b.previous.at) }) : "—"],
              [w("fin.f.cur_status"), stageChip],
            ]
          : isInspect
            ? [
                [w("fin.f.previous"), b.previous ? w("qm.prev.FIN_OUT", { at: at(b.previous.at) }) : "—"],
                [w("fin.f.in_at"), at(b.in_at)],
                [w("fin.f.cur_section"), b.section],
                [w("fin.f.cur_floor"), b.floor],
                [w("fin.f.cur_line"), b.line],
                [w("qm.f.inspecting"), snap.inspection ? `${w("fin.dash.pcs", { n: num(snap.inspection.qty) })} · ${roundLabel(w, snap.inspection.round)}` : "—"],
                [w("fin.f.cur_status"), stageChip],
              ]
            : [
                [w("qm.f.inspected"), w("fin.dash.pcs", { n: num(b.inspected) })],
                [w("qm.f.pass"), num(b.pass), "text-success"],
                [w("qm.f.reject"), num(b.reject), "text-danger"],
                [w("qm.f.rework"), b.open_rework ? w("qm.f.rework_open", { qty: num(b.rework), open: num(b.open_rework) }) : num(b.rework), "text-warning"],
                [w("fin.f.cur_section"), b.section],
                [w("fin.f.cur_floor"), b.floor],
                [w("fin.f.cur_line"), b.line],
                [w("qm.f.status"), stageChip],
              ]),
      ];

  const recentHead = [
    w("qm.col.sl"),
    w("fin.col.time"),
    w("fin.col.bundle_no"),
    w("fin.col.order"),
    w("qm.col.buyer"),
    w("fin.col.style"),
    w("fin.col.colour"),
    w("fin.col.size"),
    w("fin.col.qty"),
    w("fin.col.line"),
    ...(kind !== "in" ? [w("qm.col.pass"), w("fin.col.reject"), w("fin.col.rework")] : []),
    w(kind === "in" ? "qm.col.previous" : isInspect ? "qm.col.defect_round" : "qm.col.final"),
    w("fin.col.by"),
    w("fin.col.action"),
  ];
  const note = (r) => (kind === "in" ? (r.previous ? w("qm.prev.FIN_OUT", { at: at(r.previous) }) : "—") : isInspect ? `${r.defect_type ? w(`defect.${r.defect_type}`) : "—"} · ${roundLabel(w, r.round)}` : w(`qm.final.${r.final_status}`));

  return (
    <>
      <section className="mb-4 rounded-lg border border-border bg-surface">
        <ol className="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((n) => (
            <li key={n} className="flex items-center gap-3" aria-current={n === step ? "step" : undefined}>
              <span className={cn("grid size-9 shrink-0 place-items-center rounded-full text-sm font-semibold", n === step ? "bg-brand text-brand-foreground" : n < step ? "bg-success text-white" : "bg-surface-muted text-foreground-muted")}>{n < step ? <CircleCheck className="size-4" /> : n}</span>
              <span>
                <span className="block text-sm font-semibold">{w(`qm.steps.${kind}.${n}.title`)}</span>
                <span className="block text-xs text-foreground-muted">{w(`qm.steps.${kind}.${n}.sub`)}</span>
              </span>
            </li>
          ))}
        </ol>
        <div className="grid items-end gap-4 px-5 py-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1.3fr]">
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">
              {w("fin.section")} <span className="text-danger">*</span>
            </span>
            <select className={nativeSelect} value={section} onChange={(e) => relocate({ section: e.target.value, floor: "", line: "" })} aria-label={w("fin.section")}>
              <option value="">{options.sections.length ? w("fin.select_section") : w("qm.no_sections_short")}</option>
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
            <select className={nativeSelect} value={floor} disabled={!section} onChange={(e) => relocate({ section, floor: e.target.value, line: "" })} aria-label={w("fin.floor")}>
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
            <select className={nativeSelect} value={line} disabled={!floor} onChange={(e) => relocate({ section, floor, line: e.target.value })} aria-label={w("fin.line")}>
              <option value="">{floor ? w("fin.select_line") : w("fin.floor_first")}</option>
              {lines.map((l) => (
                <option key={l.value} value={l.value}>
                  {l.label}
                </option>
              ))}
            </select>
          </label>
          <div className={cn("flex items-center gap-3 rounded-lg border px-4 py-2.5", located ? "border-success/40 bg-success/10" : "border-border bg-surface-muted")} role="status">
            {located ? <CircleCheck className="size-6 shrink-0 text-success" /> : <Layers className="size-6 shrink-0 text-foreground-muted" />}
            <div className="min-w-0">
              <p className={cn("text-sm font-semibold", located && "text-success")}>{w(located ? "fin.loc_ready" : "fin.loc_title")}</p>
              <p className="truncate text-xs text-foreground-muted">{located ? w("qm.loc_ready_hint", { section: sectionLabel, floor: floorLabel, line: lineCode }) : w("fin.loc_hint")}</p>
            </div>
          </div>
        </div>
        {options.sections.length === 0 ? <p className="border-t border-border px-5 py-2 text-xs text-warning">{w("qm.no_sections")}</p> : null}
        {options.unfiled.length ? <p className="border-t border-border px-5 py-2 text-xs text-warning">{w("qm.unfiled", { n: options.unfiled.length, lines: options.unfiled.join(", ") })}</p> : null}
        {!canManage ? <p className="border-t border-border px-5 py-2 text-xs text-warning">{w("qm.read_only")}</p> : null}
      </section>

      {lineInfo && located ? <LineToday info={lineInfo} /> : null}

      <div className="grid gap-4 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,2fr)]">
        <form onSubmit={scan} className={cn("flex flex-col items-center rounded-lg border bg-surface p-5 text-center", located ? "border-brand/50" : "border-border")}>
          <Barcode className={cn("size-10", located ? "text-brand" : "text-foreground-muted")} />
          <label htmlFor={`qm-${kind}-scan`} className="mt-2 text-[15px] font-semibold">
            {w("fin.scan_label")} <span className="text-danger">*</span>
          </label>
          <div className={cn("mt-4 w-full rounded-lg border-2 border-dashed border-border p-4", located ? "bg-surface" : "bg-surface-muted/40 opacity-70")}>
            <ScanLine className="mx-auto size-12 text-foreground-muted" />
            <Input
              id={`qm-${kind}-scan`}
              ref={scanRef}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              placeholder={located ? w("fin.scan_here") : w("qm.scan_locked")}
              className="mt-3 h-12 text-center font-mono text-base tracking-widest"
              disabled={!located || pending}
              autoComplete="off"
              spellCheck={false}
              maxLength={60}
            />
            <p className="mt-2 text-xs text-foreground-muted">{w("fin.scan_hint")}</p>
          </div>
          <Button type="submit" variant="outline" className="mt-3 w-full justify-center" disabled={!located || !code.trim() || pending}>
            {pending && code ? w("fin.reading") : w("fin.load")}
          </Button>
        </form>

        <div className="flex min-w-0 flex-col gap-4">
          {saved ? (
            <div role={saved.ok ? "status" : "alert"} className={cn("flex items-start gap-3 rounded-lg border px-4 py-3 text-sm", saved.ok ? bannerTone.success : bannerTone.danger)}>
              {saved.ok ? <CircleCheck className="mt-0.5 size-4 shrink-0" /> : <CircleX className="mt-0.5 size-4 shrink-0" />}
              <div>
                <p className="font-semibold">{saved.title ?? (saved.ok ? w(`qm.done.${kind}`) : w("fin.banner.not_saved"))}</p>
                <p className="text-[13px]">{saved.text}</p>
              </div>
            </div>
          ) : null}
          {banner || !saved ? (
            <div className={cn("flex items-start gap-3 rounded-lg border border-dashed px-4 py-3 text-sm", banner ? bannerTone[banner.tone] : "border-border text-foreground-muted")}>
              {banner?.tone === "danger" ? <CircleX className="mt-0.5 size-4 shrink-0" /> : <ScanLine className="mt-0.5 size-4 shrink-0" />}
              <div>
                <p className="font-semibold">{banner ? banner.title : located ? w("fin.banner.wait") : w("fin.banner.begin")}</p>
                {banner?.text ? <p className="text-[13px]">{banner.text}</p> : null}
              </div>
            </div>
          ) : null}

          {snap?.rework && canSupervise ? (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface-muted px-4 py-3">
              <p className="text-sm text-foreground-muted">
                <Chip tone={REWORK_TONE[snap.rework.status]}>{w(`rework.${snap.rework.status}`)}</Chip> {w("qm.rework_strip", { qty: num(snap.rework.qty) })}
              </p>
              <div className="flex gap-2">
                {snap.rework.status === "PENDING" ? (
                  <Button size="sm" variant="outline" disabled={moving} onClick={() => moveRework("PROCESSING")}>
                    {w("qm.rework_start")}
                  </Button>
                ) : null}
                <Button size="sm" disabled={moving} onClick={() => moveRework("COMPLETED")}>
                  {w("qm.rework_done")}
                </Button>
              </div>
            </div>
          ) : null}

          <Panel icon={PackageOpen} title={w("qm.info_title")}>
            {b ? (
              <>
                <div className="p-5">
                  <Facts items={facts} />
                </div>
                {kind === "out" ? (
                  <div className="flex flex-wrap items-center gap-3 border-t border-border px-5 py-3">
                    <span className="text-sm text-foreground-muted">{w("qm.f.final")}</span>
                    <Chip tone={FINAL_TONE[b.final_status]}>{w(`qm.final.${b.final_status}`)}</Chip>
                  </div>
                ) : null}
              </>
            ) : (
              <p className="px-5 py-8 text-center text-sm text-foreground-muted">{w("qm.info_empty")}</p>
            )}
          </Panel>

          {isInspect ? (
            <Panel icon={ClipboardList} title={w("qm.result_title")}>
              <div className="flex flex-col gap-3 p-5">
                <div className="grid grid-cols-3 gap-3">
                  {[
                    ["pass", "qm.pass_qty", "text-success"],
                    ["reject", "fin.reject", "text-danger"],
                    ["rework", "fin.rework", "text-warning"],
                  ].map(([k, label, tone], i) => (
                    <label key={k} className="flex flex-col gap-1">
                      <span className={cn("text-sm font-semibold", tone)}>
                        {w(label)} <span className="text-danger">*</span>
                      </span>
                      <Input
                        ref={i === 0 ? passRef : undefined}
                        inputMode="numeric"
                        value={qty[k]}
                        placeholder="0"
                        onChange={(e) => setQty((x) => ({ ...x, [k]: e.target.value.replace(/\D/g, "").slice(0, 6) }))}
                        disabled={!snap?.inspection || !canManage}
                        className="h-11 text-right text-lg font-semibold tabular-nums"
                      />
                    </label>
                  ))}
                </div>
                <p role="status" className={cn("rounded-md border px-4 py-2 text-center text-sm font-semibold", !snap?.inspection ? "border-border font-normal text-foreground-muted" : sumOk ? "border-success/40 bg-success/10 text-success" : "border-danger/40 bg-danger/10 text-danger")}>
                  {!snap?.inspection ? w("qm.sum_rule") : sumOk ? w("qm.sum_ok", { qty: num(toInspect), pass: q.pass, reject: q.reject, rework: q.rework }) : w("qm.sum_bad", { sum: num(sum), qty: num(toInspect) })}
                </p>
                <div className="grid gap-3 sm:grid-cols-[1fr_1.4fr]">
                  <label className="flex flex-col gap-1">
                    <span className="text-sm font-semibold">
                      {w("qm.defect")} {defective ? <span className="text-danger">*</span> : null}
                    </span>
                    <select className={nativeSelect} value={defect} onChange={(e) => setDefect(e.target.value)} disabled={!snap?.inspection || !defective}>
                      <option value="">{defective ? w("qm.defect_pick") : w("qm.defect_none")}</option>
                      {options.defect_types.map((d) => (
                        <option key={d} value={d}>
                          {w(`defect.${d}`)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="flex flex-col gap-1">
                    <span className="text-sm font-semibold">
                      {w("qm.remark")} {defective && defect === "OTHER" ? <span className="text-danger">*</span> : null}
                    </span>
                    <Input value={remark} maxLength={300} onChange={(e) => setRemark(e.target.value)} placeholder={defect === "OTHER" ? w("qm.remark_other") : w("qm.optional")} disabled={!snap?.inspection} />
                  </label>
                </div>
              </div>
            </Panel>
          ) : null}

          {kind === "out" ? (
            <Panel icon={ClipboardList} title={w("qm.remarks")}>
              <div className="p-5">
                <textarea
                  aria-label={w("qm.remarks")}
                  className="min-h-20 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-brand disabled:opacity-60"
                  placeholder={w("qm.remarks_hint")}
                  value={remark}
                  maxLength={300}
                  onChange={(e) => setRemark(e.target.value)}
                  disabled={!snap || Boolean(snap.blocker)}
                />
              </div>
            </Panel>
          ) : null}
        </div>
      </div>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
        <Button variant="outline" size="lg" onClick={clear}>
          <Trash2 /> {w("fin.clear")}
        </Button>
        {canManage ? (
          <Button size="lg" onClick={confirm} disabled={!ready || pending} className={cn("min-w-60 justify-center", ready && "bg-success hover:bg-success/90")}>
            <CircleCheck /> {pending && snap ? w("fin.saving") : w(`qm.confirm.${kind}`)}
          </Button>
        ) : null}
      </div>

      <section className="mt-4 rounded-lg border border-border bg-surface">
        <header className="flex items-center justify-between gap-3 px-5 pt-4">
          <h2 className="text-[15px] font-semibold">
            {w(`qm.recent.${kind}`)} <span className="font-normal text-foreground-muted">· {rows.length}</span>
          </h2>
          <Button variant="outline" size="sm" onClick={reloadRecent} disabled={pending}>
            <RefreshCw /> {w("fin.refresh")}
          </Button>
        </header>
        <div className="px-5 pb-2">
          {rows.length === 0 ? (
            <p className="py-4 text-sm text-foreground-muted">{w("qm.recent_empty")}</p>
          ) : (
            <Table
              w={w}
              name={`quality-${kind}-today`}
              head={recentHead}
              rows={rows.map((r, i) => [
                i + 1,
                r.time,
                { text: r.bundle_no, node: <span className="font-mono font-semibold">{r.bundle_no}</span> },
                r.order_no,
                r.buyer,
                r.style,
                r.colour,
                r.size,
                kind === "inspect" ? r.inspected : r.qty,
                r.line,
                ...(kind !== "in" ? [r.pass, r.reject, r.rework] : []),
                note(r),
                r.by,
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
      {history.dialog}
    </>
  );
}

const VIEWS = ["line", "floor", "section", "unit"];

/** A card's chart / table switch, as Brickland's chart cards carry it. */
function ViewSwitch({ table, onChange }) {
  const w = useWords();
  return (
    <div className="flex rounded-md border border-border p-0.5">
      <button type="button" onClick={() => onChange(false)} aria-pressed={!table} aria-label={w("qm.dash.as_chart")} className={cn("rounded px-1.5 py-1", !table ? "bg-surface-muted text-foreground" : "text-foreground-muted")}>
        <BarChart3 className="size-4" />
      </button>
      <button type="button" onClick={() => onChange(true)} aria-pressed={table} aria-label={w("qm.dash.as_table")} className={cn("rounded px-1.5 py-1", table ? "bg-surface-muted text-foreground" : "text-foreground-muted")}>
        <Table2 className="size-4" />
      </button>
    </div>
  );
}

const tooltipStyle = { background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 6, fontSize: 12 };

/** The Quality Management Dashboard: filters, key figures, trend, inspection result, Target vs Actual, the ten tabs and Bundle Quality History. */
function QualityBoard({ options, initial, canSupervise, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [f, setF] = useState({ from: initial.from, to: initial.to, shift_id: "", section_id: "", floor_id: "", line_id: "", view: initial.view, trend: String(initial.trend_days) });
  const [data, setData] = useState(initial);
  const [moving, setMoving] = useState(null);
  const [trendTable, setTrendTable] = useState(false);
  const [resultTable, setResultTable] = useState(false);
  const history = useQualityHistory(act);
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
  const trend = (days) => {
    const q = { ...f, trend: String(days) };
    setF(q);
    apply(q);
  };
  const set = (k, v) => setF((x) => ({ ...x, [k]: v, ...(k === "section_id" ? { floor_id: "", line_id: "" } : k === "floor_id" ? { line_id: "" } : {}) }));
  const move = (row, status) => {
    setMoving(row.id);
    startTransition(async () => {
      const res = await act.rework({ inspection_id: row.id, status });
      setMoving(null);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w("toast.rework_moved", { no: res.data.bundle_no, status: w(`rework.${res.data.rework_status}`) }), type: "success" });
      apply();
    });
  };

  const s = data.summary;
  const range = data.from === data.to ? at(data.from) : `${at(data.from)} – ${at(data.to)}`;
  const viewName = w(`fin.dash.view.${data.view}`);
  const stamp = data.from === data.to ? data.from : `${data.from}_${data.to}`;
  const defectName = (d) => (d ? w(`defect.${d}`) : "—");

  const inspHead = [w("qm.col.at"), w("fin.col.bundle_no"), w("fin.col.order"), w("qm.col.buyer"), w("fin.col.style"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("qm.col.round"), w("qm.col.inspected"), w("qm.col.pass"), w("fin.col.reject"), w("fin.col.rework"), w("qm.defect"), w("qm.remark"), w("fin.col.by")];
  const inspRow = (r) => [at(r.at), r.bundle_no, r.order_no, r.buyer, r.style, r.section, r.floor, r.line, r.round, r.inspected, r.pass, r.reject, r.rework, defectName(r.defect_type), r.remark ?? "—", r.by];
  const inspTotals = (rows) => (rows.length ? [w("fin.dash.total"), "", "", "", "", "", "", "", "", ...sumCols(rows.map((r) => [r.inspected, r.pass, r.reject, r.rework])), "", "", ""] : undefined);
  const standHead = [w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.bundle_qty"), w("qm.col.to_inspect"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("fin.col.in_at"), w("fin.col.status")];
  const standRow = (r) => [r.bundle_no, r.order_no, r.style, r.qty, r.to_inspect, r.section, r.floor, r.line, at(r.in_at), { text: w(`qm.stage.${r.stage}`), node: <Chip tone={STAGE_TONE[r.stage]}>{w(`qm.stage.${r.stage}`)}</Chip> }];
  const passes = data.inspections.filter((r) => r.pass > 0);
  const rejects = data.inspections.filter((r) => r.reject > 0);
  const openRework = data.rework.filter((r) => r.rework_status !== "COMPLETED").length;
  const trendRows = [...data.trend].reverse();

  const tabs = [
    [
      "in",
      data.ins.length,
      <Table
        key="in"
        w={w}
        name={`quality-in-${stamp}`}
        head={[w("fin.col.in_at"), w("fin.col.bundle_no"), w("fin.col.order"), w("qm.col.buyer"), w("fin.col.style"), w("fin.col.colour"), w("fin.col.size"), w("fin.col.qty"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("qm.col.previous"), w("fin.col.by")]}
        rows={data.ins.map((r) => [at(r.in_at), r.bundle_no, r.order_no, r.buyer, r.style, r.colour, r.size, r.qty, r.section, r.floor, r.line, r.previous ? w("qm.prev.FIN_OUT", { at: at(r.previous) }) : "—", r.by])}
        totals={data.ins.length ? [w("fin.dash.total"), "", "", "", "", "", "", ...sumCols(data.ins.map((r) => [r.qty])), "", "", "", "", ""] : undefined}
        empty={w("qm.dash.empty.in")}
      />,
    ],
    ["inspection", data.inspections.length, <Table key="insp" w={w} name={`quality-inspection-${stamp}`} head={inspHead} rows={data.inspections.map(inspRow)} totals={inspTotals(data.inspections)} empty={w("qm.dash.empty.inspection")} />],
    [
      "out",
      data.outs.length,
      <Table
        key="out"
        w={w}
        name={`quality-out-${stamp}`}
        head={[w("fin.col.out_at"), w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.section"), w("fin.col.floor"), w("fin.col.line"), w("fin.col.bundle_qty"), w("qm.col.pass"), w("fin.col.reject"), w("fin.col.rework"), w("qm.col.final"), w("qm.remarks"), w("fin.col.by")]}
        rows={data.outs.map((r) => [at(r.at), r.bundle_no, r.order_no, r.style, r.section, r.floor, r.line, r.qty, r.pass, r.reject, r.rework, { text: w(`qm.final.${r.final_status}`), node: <Chip tone={FINAL_TONE[r.final_status]}>{w(`qm.final.${r.final_status}`)}</Chip> }, r.remarks ?? "—", r.by])}
        totals={data.outs.length ? [w("fin.dash.total"), "", "", "", "", "", "", ...sumCols(data.outs.map((r) => [r.qty, r.pass, r.reject, r.rework])), "", "", ""] : undefined}
        empty={w("qm.dash.empty.out")}
      />,
    ],
    ["pass", passes.length, <Table key="pass" w={w} name={`quality-pass-${stamp}`} head={inspHead} rows={passes.map(inspRow)} totals={inspTotals(passes)} empty={w("qm.dash.empty.pass")} />],
    ["reject", rejects.length, <Table key="reject" w={w} name={`quality-reject-${stamp}`} head={inspHead} rows={rejects.map(inspRow)} totals={inspTotals(rejects)} empty={w("qm.dash.empty.reject")} />],
    [
      "rework",
      openRework,
      <Table
        key="rework"
        w={w}
        name={`quality-rework-${stamp}`}
        note={`${w("qm.dash.rework_note")}${canSupervise ? "" : ` ${w("fin.dash.rework_sup")}`}`}
        head={[w("qm.col.at"), w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.line"), w("fin.col.rework_qty"), w("qm.col.completed"), w("qm.col.pending"), w("qm.defect"), w("fin.col.status"), w("qm.col.completed_at"), w("fin.col.action")]}
        rows={data.rework.map((r) => {
          const done = r.rework_status === "COMPLETED" ? r.rework : 0;
          return [
            at(r.at),
            r.bundle_no,
            r.order_no,
            r.style,
            r.line,
            r.rework,
            done,
            r.rework - done,
            defectName(r.defect_type),
            { text: w(`rework.${r.rework_status}`), node: <Chip tone={REWORK_TONE[r.rework_status]}>{w(`rework.${r.rework_status}`)}</Chip> },
            at(r.rework_completed_at),
            {
              text: "",
              node:
                canSupervise && r.rework_status !== "COMPLETED" ? (
                  <div className="flex gap-1.5">
                    {r.rework_status === "PENDING" ? (
                      <Button size="sm" variant="outline" disabled={moving === r.id} onClick={() => move(r, "PROCESSING")}>
                        {w("fin.dash.start")}
                      </Button>
                    ) : null}
                    <Button size="sm" disabled={moving === r.id} onClick={() => move(r, "COMPLETED")}>
                      {w("fin.dash.complete")}
                    </Button>
                  </div>
                ) : (
                  <span className="text-foreground-muted">—</span>
                ),
            },
          ];
        })}
        csv={data.rework.map((r) => {
          const done = r.rework_status === "COMPLETED" ? r.rework : 0;
          return [at(r.at), r.bundle_no, r.order_no, r.style, r.line, r.rework, done, r.rework - done, defectName(r.defect_type), w(`rework.${r.rework_status}`), at(r.rework_completed_at), r.round];
        })}
        empty={w("fin.dash.empty.rework")}
      />,
    ],
    ["pending", data.pending.length, <Table key="pending" w={w} name="quality-pending-inspection" note={w("qm.dash.pending_note")} head={standHead} rows={data.pending.map(standRow)} empty={w("qm.dash.empty.pending")} />],
    ["wip", data.wip.length, <Table key="wip" w={w} name="quality-wip" note={w("qm.dash.wip_note")} head={standHead} rows={data.wip.map(standRow)} empty={w("qm.dash.empty.wip")} />],
    [
      "ready",
      data.ready.length,
      <Table
        key="ready"
        w={w}
        name="quality-ready-bundles"
        note={w("qm.dash.ready_note")}
        head={[w("fin.col.bundle_no"), w("fin.col.order"), w("fin.col.style"), w("fin.col.colour"), w("fin.col.size"), w("fin.col.qty"), w("qm.col.finishing_line"), w("qm.col.finishing_out")]}
        rows={data.ready.map((r) => [r.bundle_no, r.order_no, r.style, r.colour, r.size, r.qty, r.finishing_line, at(r.finishing_out_at)])}
        empty={w("qm.dash.empty.ready")}
      />,
    ],
    [
      "daily",
      null,
      <Table
        key="daily"
        w={w}
        name={`quality-daily-${data.trend_days}d-${data.to}`}
        note={w("qm.dash.daily_note", { n: data.trend_days, to: at(data.to) })}
        head={[w("fin.col.date"), w("qm.dash.s.in"), w("qm.col.inspected"), w("qm.dash.s.passed"), w("fin.col.reject"), w("fin.col.rework"), w("qm.col.pass_pct"), w("qm.col.reject_pct"), w("qm.col.rework_pct")]}
        rows={trendRows.map((r) => [at(r.day), r.in_qty, r.inspected, r.passed, r.reject, r.rework, pct(pctOf(r.passed, r.inspected)), pct(pctOf(r.reject, r.inspected)), pct(pctOf(r.rework, r.inspected))])}
        totals={[w("fin.dash.total"), ...sumCols(data.trend.map((r) => [r.in_qty, r.inspected, r.passed, r.reject, r.rework])), "", "", ""]}
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
  const trendData = data.trend.map((r) => ({ ...r, label: at(r.day).slice(0, 6) }));
  const results = [
    { key: "passed", label: w("qm.dash.s.passed"), value: s.passed, color: "var(--success)" },
    { key: "reject", label: w("qm.dash.s.reject"), value: s.reject, color: "var(--danger)" },
    { key: "rework", label: w("qm.dash.s.rework"), value: s.rework, color: "var(--warning)" },
  ];

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
            {pending ? w("fin.dash.loading") : w("qm.dash.search")}
          </Button>
        </div>
        <div className="col-span-full flex flex-wrap items-center gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => quick(1)}>
            {w("fin.dash.today")}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => quick(7)}>
            {w("fin.dash.last7")}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => quick(30)}>
            {w("fin.dash.last30")}
          </Button>
          {options.sections.length === 0 ? <span className="text-xs text-warning">{w("qm.dash.no_lines")}</span> : null}
          {data.unfiled.length ? <span className="text-xs text-warning">{w("fin.dash.unfiled", { lines: data.unfiled.join(", ") })}</span> : null}
        </div>
      </form>

      <section aria-label={w("qm.dash.key_figures")} className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <StatCard label={w("qm.dash.t.in")} value={num(s.in_qty)} supportingText={w("qm.dash.t.in_cap", { n: num(s.in_bundles), range })} icon={<PackagePlus />} tone="info" />
        <StatCard label={w("qm.dash.t.inspected")} value={num(s.inspected)} supportingText={s.target ? w("fin.dash.t.good_cap", { t: num(s.target) }) : range} icon={<SearchCheck />} tone="brand" />
        <StatCard label={w("qm.dash.t.passed")} value={num(s.passed)} supportingText={w("qm.dash.t.pass_cap", { p: pct(s.pass_pct) })} icon={<CircleCheck />} tone="success" />
        <StatCard label={w("qm.dash.t.reject")} value={num(s.reject)} supportingText={w("qm.dash.t.reject_cap", { p: pct(s.reject_pct) })} icon={<Ban />} tone="danger" />
        <StatCard label={w("qm.dash.t.rework")} value={num(s.rework)} supportingText={w("qm.dash.t.rework_cap", { p: pct(s.rework_pct) })} icon={<Hammer />} tone="warning" />
        <StatCard label={w("qm.dash.t.pending")} value={num(s.pending_qty)} supportingText={w("qm.dash.t.pending_cap", { n: num(s.pending_bundles) })} icon={<Hourglass />} tone="danger" />
      </section>
      <section aria-label={w("qm.dash.rates")} className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <StatCard label={w("qm.dash.t.pass_pct")} value={pct(s.pass_pct)} supportingText={w("qm.dash.t.pass_pct_cap")} icon={<Percent />} tone="success" />
        <StatCard label={w("qm.dash.t.reject_pct")} value={pct(s.reject_pct)} supportingText={w("qm.dash.t.reject_pct_cap")} icon={<Percent />} tone="danger" />
        <StatCard label={w("qm.dash.t.rework_pct")} value={pct(s.rework_pct)} supportingText={w("qm.dash.t.rework_pct_cap")} icon={<Percent />} tone="warning" />
        <StatCard label={w("qm.dash.t.out")} value={num(s.out_bundles)} supportingText={w("qm.dash.t.out_cap", { range })} icon={<PackageCheck />} tone="info" />
        <StatCard label={w("qm.dash.t.rework_open")} value={num(s.rework_open)} supportingText={w("fin.dash.t.rework_pending_cap")} icon={<Timer />} tone="warning" />
        <StatCard label={w("qm.dash.t.ready")} value={num(s.ready_qty)} supportingText={w("qm.dash.t.ready_cap", { n: num(s.ready_bundles) })} icon={<Boxes />} tone="brand" />
      </section>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <Panel
          icon={BarChart3}
          title={w("qm.dash.trend_title", { n: data.trend_days })}
          sub={w("qm.dash.trend_sub", { to: at(data.to) })}
          action={
            <div className="flex items-center gap-2">
              {[7, 30].map((d) => (
                <Button key={d} type="button" size="sm" variant={data.trend_days === d ? "primary" : "outline"} onClick={() => trend(d)} disabled={pending}>
                  {w("qm.dash.days", { n: d })}
                </Button>
              ))}
              <ViewSwitch table={trendTable} onChange={setTrendTable} />
            </div>
          }
        >
          {trendTable ? (
            <div className="px-5">
              <Table w={w} name={`quality-trend-${data.trend_days}d-${data.to}`} head={[w("fin.col.date"), ...SERIES.map((x) => w(x.label))]} rows={trendRows.map((r) => [at(r.day), r.in_qty, r.passed, r.reject, r.rework])} empty={w("fin.dash.empty.daily")} />
            </div>
          ) : (
            <div className="h-72 px-3 py-4">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={trendData} margin={{ top: 8, right: 16, left: -8, bottom: 0 }}>
                  <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 3" />
                  <XAxis dataKey="label" tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} tickLine={false} axisLine={{ stroke: "var(--border)" }} interval="preserveStartEnd" minTickGap={16} />
                  <YAxis allowDecimals={false} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} tickLine={false} axisLine={false} width={44} />
                  <Tooltip contentStyle={tooltipStyle} />
                  <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                  {SERIES.map((x) => (
                    <Line key={x.key} type="monotone" dataKey={x.key} name={w(x.label)} stroke={x.color} strokeWidth={2} dot={{ r: 3 }} isAnimationActive={false} />
                  ))}
                </LineChart>
              </ResponsiveContainer>
            </div>
          )}
        </Panel>

        <Panel icon={SearchCheck} title={w("qm.dash.result_title")} sub={w("qm.dash.result_sub")} action={<ViewSwitch table={resultTable} onChange={setResultTable} />}>
          {s.inspected === 0 ? (
            <p className="px-5 py-16 text-center text-sm text-foreground-muted">{w("qm.dash.result_empty")}</p>
          ) : resultTable ? (
            <div className="px-5">
              <Table w={w} name={`quality-result-${stamp}`} head={[w("qm.dash.result_col"), w("fin.col.qty"), "%"]} rows={results.map((r) => [r.label, r.value, pct(pctOf(r.value, s.inspected))])} totals={[w("fin.dash.total"), s.inspected, ""]} empty="" />
            </div>
          ) : (
            <div className="flex flex-wrap items-center justify-center gap-6 px-5 py-6">
              <StatusDonut segments={results} total={num(s.inspected)} totalLabel={w("qm.dash.inspected_pcs")} />
              <ul className="flex flex-col gap-3 text-sm">
                {results.map((r) => (
                  <li key={r.key} className="flex items-start gap-2">
                    <span className="mt-1 size-3 shrink-0 rounded-full" style={{ background: r.color }} aria-hidden="true" />
                    <span>
                      <span className="block text-foreground-muted">{r.label}</span>
                      <span className="font-semibold tabular-nums">
                        {num(r.value)} ({pct(pctOf(r.value, s.inspected))})
                      </span>
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </Panel>
      </div>

      <Panel
        icon={Target}
        title={w("qm.dash.perf_title", { view: viewName })}
        sub={w("qm.dash.perf_sub")}
        action={
          <Button
            size="sm"
            variant="outline"
            disabled={!data.performance.length}
            onClick={() =>
              downloadCsv(
                `quality-${data.view}-wise-${stamp}`,
                [w(`fin.dash.head.${data.view}`), w("fin.col.lines"), w("fin.col.target"), w("qm.col.in_qty"), w("qm.col.inspected"), w("qm.col.passed"), w("fin.col.reject"), w("fin.col.rework"), w("qm.col.pending"), w("qm.col.pass_pct")],
                data.performance.map((r) => [r.label, r.lines, r.target ?? "", r.in_qty, r.inspected, r.passed, r.reject, r.rework, r.pending, r.pass_pct ?? ""]),
              )
            }
          >
            {w("fin.dash.csv")}
          </Button>
        }
      >
        {data.performance.length === 0 ? (
          <p className="px-5 py-5 text-sm text-foreground-muted">{w("qm.dash.perf_empty")}</p>
        ) : (
          <>
            <div className="px-3 pt-4" style={{ height: Math.max(220, 56 * data.performance.length) }}>
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data.performance} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 4 }}>
                  <CartesianGrid horizontal={false} stroke="var(--border)" strokeDasharray="3 3" />
                  <XAxis type="number" allowDecimals={false} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} axisLine={false} tickLine={false} />
                  <YAxis type="category" dataKey="label" width={150} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} axisLine={false} tickLine={false} />
                  <Tooltip contentStyle={tooltipStyle} />
                  <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                  <Bar dataKey="target" name={w("fin.col.target")} fill="var(--border)" radius={[0, 4, 4, 0]} isAnimationActive={false} />
                  <Bar dataKey="inspected" name={w("qm.col.inspected")} fill="var(--brand)" radius={[0, 4, 4, 0]} isAnimationActive={false} />
                  <Bar dataKey="passed" name={w("qm.col.passed")} fill="var(--success)" radius={[0, 4, 4, 0]} isAnimationActive={false} />
                </BarChart>
              </ResponsiveContainer>
            </div>
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
                    {["fin.col.target", "qm.col.in_qty", "qm.col.inspected", "qm.col.passed", "fin.col.reject", "fin.col.rework", "qm.col.pending", "qm.col.pass_pct"].map((k) => (
                      <th key={k} scope="col" className="border border-border px-2 py-2 text-left font-medium">
                        {w(k)}
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
                      {[r.target, r.in_qty, r.inspected, r.passed, r.reject, r.rework, r.pending].map((v, i) => (
                        <td key={i} className={cn("border border-border px-2 py-1.5 text-right tabular-nums", i === 2 && "font-semibold")}>
                          {num(v)}
                        </td>
                      ))}
                      <td className={cn("border border-border px-2 py-1.5 text-right tabular-nums", r.pass_pct === null ? "" : r.pass_pct >= 90 ? "text-success" : r.pass_pct >= 75 ? "text-warning" : "text-danger")}>{pct(r.pass_pct)}</td>
                    </tr>
                  ))}
                  <tr className="bg-surface-muted font-semibold">
                    <td className="border border-border px-2 py-1.5">{w("fin.dash.total")}</td>
                    {data.view !== "line" ? <td className="border border-border px-2 py-1.5 text-right tabular-nums">{num(data.performance.reduce((a, r) => a + r.lines, 0))}</td> : null}
                    {sumCols(data.performance.map((r) => [r.target ?? 0, r.in_qty, r.inspected, r.passed, r.reject, r.rework, r.pending])).map((v, i) => (
                      <td key={i} className="border border-border px-2 py-1.5 text-right tabular-nums">
                        {num(v)}
                      </td>
                    ))}
                    <td className="border border-border px-2 py-1.5 text-right tabular-nums">{pct(s.pass_pct)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </>
        )}
      </Panel>

      <section className="rounded-lg border border-border bg-surface px-5 pt-3">
        <Tabs defaultValue="in">
          <TabsList>
            {tabs.map(([key, badge]) => (
              <TabsTab key={key} value={key}>
                {w(`qm.dash.tab.${key}`)}
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

      <Panel icon={History} title={w("qm.history.card_title")} sub={w("qm.history.card_sub")}>
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

export { QualityNav, QualityDesk, QualityBoard };
