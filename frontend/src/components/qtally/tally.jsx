"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { CheckCircle2, CloudOff, RefreshCw, ShieldAlert, Undo2, Wrench, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useQt, num, pct, dhuTone, achievementTone, Field, Select, Notice, SectionPill } from "./common";
import { CuttingPad } from "./cutting-pad";
import { hour12 } from "@/lib/clock";

/*
 * Quality Tally → Tally Entry (AnnotechERP 36): the QC's tablet, held standing
 * beside a line. Four enormous targets, no scrolling to reach them, and a
 * running total that moves on every tap — a QC does this hundreds of times a
 * shift and must never have to aim.
 *
 * The four actions are independent: rectify and reject do not offset defect.
 * A DEFECT tap needs at least one defect tag of the line's section.
 *
 * The wifi drops on a factory floor. A tap the server cannot be reached for
 * is kept on this device (localStorage) with its own id and the time it was
 * made, and replayed every 30 s and when the connection returns; the server
 * refuses repeats, so replaying is always safe. A tap the server REFUSES (a
 * closed job, a line taken away) is shown and not queued — it would be
 * refused again on every retry.
 */

const QUEUE = "qtally.queue.v1";
const PICK = "qtally.pick.v1";
const API = "/api/quality-tally";

const ACTIONS = [
  { key: "RFT", icon: CheckCircle2, cls: "bg-success text-white hover:bg-success/90", ring: "ring-success" },
  { key: "DEFECT", icon: ShieldAlert, cls: "bg-warning text-white hover:bg-warning/90", ring: "ring-warning" },
  { key: "RECTIFY", icon: Wrench, cls: "bg-info text-white hover:bg-info/90", ring: "ring-info" },
  { key: "REJECT", icon: XCircle, cls: "bg-danger text-white hover:bg-danger/90", ring: "ring-danger" },
];

const read = (key, fallback) => {
  try {
    return JSON.parse(localStorage.getItem(key) ?? "null") ?? fallback;
  } catch {
    return fallback;
  }
};
const write = (key, value) => {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* storage full or blocked: the caller already warned */
  }
};
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : "10000000-1000-4000-8000-100000000000".replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));

class ServerRefused extends Error {}

async function call(path, options = {}) {
  const res = await fetch(`${API}/${path}`, { ...options, headers: { Accept: "application/json", "Content-Type": "application/json" }, cache: "no-store" });
  const body = await res.json().catch(() => ({}));
  if (res.status >= 400 && res.status < 500) {
    const first = body?.errors ? Object.values(body.errors).flat()[0] : null;
    throw new ServerRefused(first ?? body?.message ?? `HTTP ${res.status}`);
  }
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return body.data;
}

/** A cutting tap's share of the bundle's panel counts and the lot, until the server answers. */
function optimisticCut(summary, action, panelId) {
  const c = summary.cutting ?? { lot_checked: 0, lot_reject: 0, lot_pass: 0, bundles: 1, panels: {} };
  const cur = { checked: 0, pass: 0, defect: 0, rectify: 0, reject: 0, ...(c.panels?.[panelId] ?? {}) };
  const first = action === "RFT" || action === "DEFECT" ? 1 : 0;
  const next = { ...cur, checked: cur.checked + first, defect: cur.defect + (action === "DEFECT" ? 1 : 0), rectify: cur.rectify + (action === "RECTIFY" ? 1 : 0), reject: cur.reject + (action === "REJECT" ? 1 : 0) };
  next.pass = Math.min(next.checked - next.defect + next.rectify, next.checked);
  return { ...summary, cutting: { ...c, lot_checked: c.lot_checked + first, lot_reject: c.lot_reject + (action === "REJECT" ? 1 : 0), panels: { ...c.panels, [panelId]: next } } };
}

/** The counts a queued tap adds, until the server answers again. */
function optimistic(summary, action, tags) {
  const s = { ...(summary ?? { rft: 0, defect: 0, rectify: 0, reject: 0, defect_tags: 0, mine: 0, target: null }) };
  s[action.toLowerCase()] = (s[action.toLowerCase()] ?? 0) + 1;
  s.defect_tags = (s.defect_tags ?? 0) + tags;
  s.mine = (s.mine ?? 0) + 1;
  s.checked = s.rft + s.defect;
  if (s.ready > 0 && (action === "RFT" || action === "DEFECT")) s.ready -= 1;
  s.pass = Math.min(s.rft + s.rectify, s.checked);
  s.dhu = s.checked ? Math.round((s.defect_tags / s.checked) * 10000) / 100 : null;
  s.rft_pct = s.checked ? Math.round((s.rft / s.checked) * 10000) / 100 : null;
  s.achievement = s.target ? Math.round((s.pass / s.target) * 10000) / 100 : null;
  return s;
}

function TallyEntry({ locale, section }) {
  // Each section's tablet remembers its own line and job.
  const pickKeyName = `${PICK}:${section}`;
  const p = useQt();
  const toast = useToastManager();
  const [ctx, setCtx] = useState(null);
  const [loadError, setLoadError] = useState(null);
  // This device's own: the last line and job, and the taps waiting to sync. Read in the initialiser —
  // the server render shows "Loading…" until the context arrives either way, so nothing differs on hydration.
  const [pick, setPick] = useState(() => (typeof window === "undefined" ? { line: "", job: "" } : read(pickKeyName, { line: "", job: "" })));
  // The running total, kept with the line and job it belongs to (a new pick shows nothing until its own arrives).
  const [summaryOf, setSummaryOf] = useState({ key: "", data: null });
  const [defectOpen, setDefectOpen] = useState(false);
  const [chosen, setChosen] = useState([]);
  const [undoOpen, setUndoOpen] = useState(false);
  const [queue, setQueue] = useState(() => (typeof window === "undefined" ? [] : read(QUEUE, [])));
  const [syncing, setSyncing] = useState(false);
  const [flash, setFlash] = useState(null);
  const syncingRef = useRef(false);
  const bn = locale === "bn";

  const load = useCallback(() => {
    call("tally/context")
      .then((all) => {
        // This screen's section only: its lines, and its own jobs.
        const next = { ...all, lines: all.lines.filter((l) => l.section === section) };
        // Keep the pick valid (a line taken away, a job closed); one line given = that line.
        setPick((cur) => {
          const lineOk = next.lines.some((l) => `${l.section}:${l.line_id}` === cur.line);
          const nextLine = lineOk ? cur.line : next.lines.length === 1 ? `${next.lines[0].section}:${next.lines[0].line_id}` : "";
          const jobOk = next.jobs.some((j) => j.id === cur.job);
          const picked = { ...cur, line: nextLine, job: jobOk ? cur.job : "" };
          write(pickKeyName, picked);
          return picked;
        });
        setLoadError(null);
        setCtx(next);
      })
      .catch((e) => setLoadError(e.message));
  }, [section, pickKeyName]);
  useEffect(load, [load]);

  const line = ctx?.lines.find((l) => `${l.section}:${l.line_id}` === pick.line) ?? null;
  const targeted = useMemo(() => (line ? (ctx?.targets?.[`${line.section}:${line.line_id}`] ?? []) : []), [ctx, line]);
  const jobOptions = useMemo(() => {
    if (!ctx) return [];
    const label = (j) => `${j.job_no} · ${j.buyer_name} · ${j.style}${j.color ? ` · ${j.color}` : ""}`;
    // The line target is the input: only the jobs targeted on this line today.
    const ids = new Set(targeted.map((t) => t.job_id));
    return ctx.jobs.filter((j) => ids.has(j.id)).map((j) => ({ value: j.id, label: label(j) }));
  }, [ctx, targeted]);
  const cutting = section === "CUTTING";
  const job = ctx?.jobs.find((j) => j.id === pick.job && (cutting || targeted.some((t) => t.job_id === j.id))) ?? null;
  const before = cutting ? "" : p(`section.${section === "SEWING" ? "CUTTING" : "SEWING"}`);
  const defects = line ? (ctx?.defects?.[line.section] ?? []) : [];
  // Cutting (user, 2026-11-25): the QC scans a bundle card the cutting manager gave as input and taps its panels.
  const cutExtra = cutting ? { cut_bundle_id: pick.bundle?.id ?? null, panel_id: pick.panel || null } : {};
  const ready = Boolean(line && job && (!cutting || (pick.bundle && pick.panel)));

  // A new bundle (or lot) starts its own counts on a cutting table.
  const pickKey = line && job ? `${line.section}:${line.line_id}:${job.id}${cutting ? `:${pick.bundle?.id ?? ""}` : ""}` : "";
  const summary = summaryOf.key === pickKey ? summaryOf.data : null;
  // A reply for a bundle or job the QC has left is dropped: it must not blank the one now in hand.
  const pickNow = useRef(pickKey);
  useEffect(() => {
    pickNow.current = pickKey;
  });
  const setSummary = useCallback(
    (next) => pickNow.current === pickKey && setSummaryOf((cur) => ({ key: pickKey, data: typeof next === "function" ? next(cur.key === pickKey ? cur.data : null) : next })),
    [pickKey],
  );
  // Taps still on their way to the server: the server's own total is shown only once none are left,
  // so a reply for tap 3 never rolls the screen back past taps 4 and 5.
  const inflight = useRef(0);
  const burst = useRef(0);

  const refresh = useCallback(() => {
    if (!line || !job) return;
    const extra = line.section === "CUTTING" ? { cut_bundle_id: pick.bundle?.id ?? "" } : {};
    call(`tally/summary?${new URLSearchParams({ section: line.section, line_id: line.line_id, job_id: job.id, ...extra })}`)
      .then((s) => inflight.current === 0 && setSummary(s))
      .catch(() => {
        /* offline: the counted total stays */
      });
  }, [line, job, pick.bundle, setSummary]);
  useEffect(refresh, [refresh]);

  const choose = (k) => (val) => {
    const next = { ...pick, [k]: val, ...(k === "line" ? { bundle: null, panel: "" } : {}) };
    setPick(next);
    write(pickKeyName, next);
  };
  const choosePatch = (patch) => {
    const next = { ...pick, ...patch };
    setPick(next);
    write(pickKeyName, next);
  };

  // The latest words, toast and refresh, read by sync without making it a new function each render
  // (a new sync re-runs the effect below, which would retry in a loop while offline).
  const latest = useRef({ p, toast, refresh });
  useEffect(() => {
    latest.current = { p, toast, refresh };
  });

  const sync = useCallback(async () => {
    const { p, toast, refresh } = latest.current;
    const pending = read(QUEUE, []);
    if (!pending.length || syncingRef.current) return;
    syncingRef.current = true;
    setSyncing(true);
    try {
      const res = await call("tally/bulk", { method: "POST", body: JSON.stringify({ entries: pending }) });
      // Everything sent is done with: recorded, a repeat, or refused for good.
      const left = read(QUEUE, []).filter((e) => !pending.some((x) => x.client_uuid === e.client_uuid));
      write(QUEUE, left);
      setQueue(left);
      toast.add({ title: p("tally.synced", { n: res.recorded + res.duplicate }), type: "success" });
      if (res.rejected?.length) toast.add({ title: p("tally.sync_rejected", { n: res.rejected.length, msg: res.rejected[0].message }), type: "danger" });
      refresh();
    } catch {
      /* still offline: keep the queue */
    } finally {
      syncingRef.current = false;
      setSyncing(false);
    }
  }, []);

  useEffect(() => {
    const online = () => sync();
    window.addEventListener("online", online);
    const timer = setInterval(() => navigator.onLine && sync(), 30_000);
    // Taps left from before a reload go out as soon as the screen is up.
    const first = setTimeout(sync, 0);
    return () => {
      window.removeEventListener("online", online);
      clearInterval(timer);
      clearTimeout(first);
    };
  }, [sync]);

  /** The last tap of a burst is answered: one tap's own total is right; after several (or a refusal) it is read again. */
  const landed = (own) => {
    inflight.current -= 1;
    if (inflight.current > 0) return;
    if (own && burst.current === 1) setSummary(own);
    else latest.current.refresh();
    burst.current = 0;
  };

  /**
   * One tap, instantly: the count moves on screen at once and the tap goes to the server in the
   * background (a QC taps hundreds of times an hour and must never wait for a reply). A tap the
   * server refuses is shown and the total re-read; one that cannot reach it is queued on this device.
   */
  const record = (action, tags = []) => {
    if (!ready) return false;
    // Nothing left to check: said at once, not after a round trip (the server refuses it too).
    if (action === "RFT" || action === "DEFECT") {
      const done = summary?.cutting?.panels?.[pick.panel]?.checked ?? 0;
      // A paired part (Sleeve) is checked twice a piece.
      const part = cutting ? ctx.garment_types?.find((g) => g.name === pick.bundle.garment_type)?.panels.find((x) => x.id === pick.panel) : null;
      const holds = cutting ? pick.bundle.qty * (part?.per_piece ?? 1) : 0;
      const full = cutting ? done >= holds : (summary?.ready ?? 1) <= 0;
      if (summary && full) {
        toast.add({ title: cutting ? p("v.bundle_done", { n: holds }) : p("v.none_ready", { from: before }), type: "danger" });
        return false;
      }
    }
    // No shift is asked for or sent: HR gives it to the QC, and the server reads it for the moment of the tap.
    const entry = { section: line.section, line_id: line.line_id, job_id: job.id, action, defects: tags, occurred_at: new Date().toISOString(), client_uuid: uuid(), ...cutExtra };
    setFlash(action);
    setTimeout(() => setFlash(null), 250);
    setSummary((s) => (cutting ? optimisticCut(optimistic(s, action, tags.length), action, pick.panel) : optimistic(s, action, tags.length)));
    inflight.current += 1;
    burst.current += 1;
    call("tally", { method: "POST", body: JSON.stringify(entry) })
      .then((res) => landed(res.summary))
      .catch((e) => {
        if (e instanceof ServerRefused) {
          toast.add({ title: e.message, type: "danger" });
          landed(null);
          return;
        }
        inflight.current -= 1;
        burst.current = 0;
        const next = [...read(QUEUE, []), entry];
        write(QUEUE, next);
        setQueue(next);
      });
    return true;
  };

  const saveDefect = () => {
    if (!chosen.length) return;
    if (record("DEFECT", chosen)) {
      setChosen([]);
      setDefectOpen(false);
    }
  };

  const undo = async () => {
    try {
      const res = await call("tally/undo", { method: "POST", body: JSON.stringify({ section: line.section, line_id: line.line_id, job_id: job.id }) });
      if (cutting) refresh();
      else setSummary(res.summary);
      toast.add({ title: p("tally.undone", { action: p(`action.${res.undone.action}`) }), type: "success" });
    } catch (e) {
      toast.add({ title: e instanceof ServerRefused ? e.message : p("tally.undo_offline"), type: "danger" });
    } finally {
      setUndoOpen(false);
    }
  };

  if (loadError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <Notice tone="danger">{p("tally.load_failed", { msg: loadError })}</Notice>
        <Button variant="outline" onClick={load}>
          <RefreshCw /> {p("btn.retry")}
        </Button>
      </div>
    );
  }
  if (!ctx) return <p className="py-10 text-center text-sm text-foreground-muted">{p("loading")}</p>;
  if (!ctx.inspector || !ctx.inspector.active || ctx.lines.length === 0) {
    return <Notice tone="warning">{!ctx.inspector ? p("tally.not_inspector") : !ctx.inspector.active ? p("tally.inactive") : p("tally.no_lines")}</Notice>;
  }

  const target = targeted.find((t) => t.job_id === job?.id);
  const s = summary;
  const hourNow = new Date().toLocaleString("en-US", { hour: "numeric", hour12: false, timeZone: "Asia/Dhaka" });
  const thisHour = s?.hourly?.find((h) => h.hour === Number(hourNow) % 24);

  return (
    <div className="flex flex-col gap-4">
      {/* Line and job, chosen once per shift. A QC given one line is not asked for it. */}
      <div className={cn("flex flex-wrap items-end gap-3", ctx.lines.length > 1 || !cutting ? "rounded-lg border border-border bg-surface p-4" : "-mb-1")}>
        {ctx.lines.length > 1 ? (
          <Field label={p("f.line")} className="min-w-56">
            <Select
              value={pick.line}
              onChange={choose("line")}
              options={ctx.lines.map((l) => ({ value: `${l.section}:${l.line_id}`, label: l.label }))}
              placeholder={p("f.select_line")}
              className="h-11 text-base"
            />
          </Field>
        ) : null}
        {cutting ? null : (
          <Field label={p("f.job")} className="min-w-72 flex-1">
            <Select value={pick.job} onChange={choose("job")} options={jobOptions} placeholder={p("f.select_job")} className="h-11 text-base" disabled={!line} />
          </Field>
        )}
        <div className="ml-auto flex items-center gap-2">
          {queue.length ? (
            <Button variant="outline" loading={syncing} onClick={sync} className="h-11 border-warning text-warning">
              <CloudOff /> {p("tally.queued", { n: queue.length })}
            </Button>
          ) : null}
          <Button variant="outline" onClick={() => { load(); refresh(); }} aria-label={p("btn.refresh")} className="size-11 p-0">
            <RefreshCw />
          </Button>
        </div>
        {/* On its own line, so it never pushes one field taller than the others. */}
        {line && !cutting && !targeted.length ? <p className="-mt-1 w-full text-sm text-warning">{p("tally.no_target")}</p> : null}
      </div>

      {cutting && line ? (
        <CuttingPad
          ctx={ctx}
          line={line}
          pick={pick}
          onPick={choosePatch}
          summary={s}
          ready={ready}
          flash={flash}
          onAction={(key) => (key === "DEFECT" ? setDefectOpen(true) : record(key))}
          onUndo={() => setUndoOpen(true)}
          call={call}
        />
      ) : !ready ? (
        <Notice tone="info">{p("tally.pick_first")}</Notice>
      ) : (
        <>
          {/* The line target is the input; what the section before has ready is what can be checked. */}
          {s ? (
            <Notice tone={s.ready > 0 ? "info" : "warning"}>
              {p("tally.ready", { target: num(s.target), left: num(Math.max(0, (s.target ?? 0) - s.pass)), from: before, ready: num(s.ready) })}
            </Notice>
          ) : null}
          {/* The running total. */}
          <div className="grid grid-cols-3 gap-3 sm:grid-cols-5 xl:grid-cols-9">
            <Stat label={p("kpi.target")} value={num(s?.target ?? target?.target_qty ?? null)} />
            <Stat label={p("kpi.hourly_target")} value={num(s?.hourly_target ?? target?.hourly_target ?? null)} sub={thisHour ? p("tally.this_hour", { n: thisHour.pass }) : null} />
            <Stat label={p("kpi.checked")} value={num(s?.checked ?? 0)} />
            <Stat label={p("kpi.pass")} value={num(s?.pass ?? 0)} valueClass="text-success" />
            <Stat label={p("kpi.achievement")} value={pct(s?.achievement)} valueClass={achievementTone(s?.achievement)} />
            <Stat label={p("kpi.dhu")} value={pct(s?.dhu)} valueClass={dhuTone(s?.dhu)} />
            <Stat label={p("kpi.rft_pct")} value={pct(s?.rft_pct)} />
            <Stat label={p("kpi.efficiency")} value={pct(s?.efficiency)} />
            <Stat label={p("kpi.mine")} value={num(s?.mine ?? 0)} />
          </div>

          {/* Four enormous buttons. */}
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {ACTIONS.map(({ key, icon: Icon, cls, ring }) => (
              <button
                key={key}
                type="button"
                onClick={() => (key === "DEFECT" ? setDefectOpen(true) : record(key))}
                className={cn(
                  "flex min-h-40 flex-col items-center justify-center gap-2 rounded-2xl p-4 shadow-sm transition select-none active:scale-[0.98] disabled:opacity-80",
                  cls,
                  flash === key && `ring-4 ring-offset-2 ${ring}`,
                )}
              >
                <Icon className="size-10" aria-hidden />
                <span className="text-2xl font-bold tracking-wide">{p(`action.${key}`)}</span>
                <span className="text-4xl font-black tabular-nums">{num(s?.[key.toLowerCase()] ?? 0)}</span>
                <span className="text-xs opacity-90">{p(`action_hint.${key}`)}</span>
              </button>
            ))}
          </div>

          <div className="flex flex-wrap items-start gap-4">
            <Button variant="outline" size="lg" onClick={() => setUndoOpen(true)} disabled={!s?.mine}>
              <Undo2 /> {p("btn.undo")}
            </Button>
            <div className="flex items-center gap-2 text-sm text-foreground-muted">
              <SectionPill section={line.section} /> {line.label} · <b className="text-foreground">{job.job_no}</b> {job.style}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <MiniTable
              title={p("tally.top_defects")}
              empty={p("empty.no_defects")}
              rows={(s?.top_defects ?? []).map((d) => [`${d.code} — ${bn && d.name_bn ? d.name_bn : d.name}`, num(d.count)])}
            />
            <MiniTable
              title={p("tally.by_hour")}
              empty={p("empty.no_taps")}
              head={[p("col.hour"), p("col.checked"), p("col.pass"), p("col.dhu")]}
              rows={(s?.hourly ?? []).map((h) => [hour12(h.hour), num(h.checked), num(h.pass), pct(h.dhu)])}
            />
          </div>
        </>
      )}

      {defectOpen ? (
        <Modal
          open
          onOpenChange={(o) => {
            if (!o) {
              setDefectOpen(false);
              setChosen([]);
            }
          }}
          title={p("tally.pick_defects")}
          className="max-w-3xl"
          footer={
            <>
              <Button variant="outline" onClick={() => { setDefectOpen(false); setChosen([]); }}>
                {p("btn.close")}
              </Button>
              <Button size="lg" disabled={!chosen.length} onClick={saveDefect} className="bg-warning text-white hover:bg-warning/90">
                <ShieldAlert /> {p("tally.save_defect", { n: chosen.length })}
              </Button>
            </>
          }
        >
          {defects.length === 0 ? (
            <Notice tone="warning">{p("tally.no_defect_types", { section: p(`section.${line.section}`) })}</Notice>
          ) : (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {defects.map((d) => {
                const on = chosen.includes(d.id);
                return (
                  <button
                    key={d.id}
                    type="button"
                    onClick={() => setChosen((all) => (on ? all.filter((x) => x !== d.id) : [...all, d.id]))}
                    className={cn(
                      "flex min-h-16 flex-col items-start justify-center rounded-lg border-2 px-3 py-2 text-left transition",
                      on ? "border-warning bg-warning-subtle" : "border-border bg-surface hover:border-border-strong",
                    )}
                  >
                    <span className="text-xs font-bold text-foreground-muted">{d.code}</span>
                    <span className="text-sm font-semibold text-foreground">{bn && d.name_bn ? d.name_bn : d.name}</span>
                  </button>
                );
              })}
            </div>
          )}
        </Modal>
      ) : null}

      <ConfirmDialog
        open={undoOpen}
        onOpenChange={setUndoOpen}
        title={p("tally.undo_title")}
        description={p("tally.undo_body")}
        confirmLabel={p("btn.undo")}
        cancelLabel={p("btn.close")}
        onConfirm={undo}
      />
    </div>
  );
}

function Stat({ label, value, sub, valueClass }) {
  return (
    <div className="rounded-lg border border-border bg-surface px-3 py-2.5">
      <p className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{label}</p>
      <p className={cn("mt-1 text-2xl font-bold text-foreground tabular-nums", valueClass)}>{value}</p>
      {sub ? <p className="text-xs text-foreground-muted">{sub}</p> : null}
    </div>
  );
}

function MiniTable({ title, rows, head, empty }) {
  return (
    <section className="rounded-lg border border-border bg-surface">
      <h3 className="border-b border-border px-4 py-2.5 text-sm font-semibold text-foreground">{title}</h3>
      {rows.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-foreground-muted">{empty}</p>
      ) : (
        <table className="w-full text-sm">
          {head ? (
            <thead>
              <tr className="text-left text-xs text-foreground-muted">
                {head.map((h, i) => (
                  <th key={h} className={cn("px-4 py-1.5 font-semibold", i > 0 && "text-right")}>
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
          ) : null}
          <tbody>
            {rows.map((r, i) => (
              <tr key={i} className="border-t border-border">
                {r.map((c, j) => (
                  <td key={j} className={cn("px-4 py-1.5", j > 0 && "text-right tabular-nums font-semibold")}>
                    {c}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  );
}

export { TallyEntry };
