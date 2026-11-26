"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { BellRing, Check, Eye, RotateCcw, Sparkles, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { num } from "./ai-format";
import { clockFormat } from "@/lib/clock";

const card = "rounded-xl border border-border bg-surface";
const dateTime = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
// Risk bands are an ordered magnitude, so one hue light → dark (always labelled), not a status set.
const BAND_FILL = { LOW: "var(--risk-low)", MEDIUM: "var(--risk-medium)", HIGH: "var(--risk-high)", CRITICAL: "var(--risk-critical)" };

/**
 * The line every AI screen opens with (AnnotechERP 06/02–06/04), as Brickland
 * shows it: when the last run was and what it read, the engine that wrote the
 * figures (the rule engine, or Claude's written analysis on top when a key is
 * set) and Run analysis, which scores the live book again without a reload.
 *
 * @param {{ run: ?object, claude: boolean, canRun: boolean, onRun: () => Promise<object> }} props
 */
function AiRunBar({ run, claude, canRun, onRun }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.mai.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState(null);

  function analyse() {
    startTransition(async () => {
      const result = await onRun();
      if (result?.status !== "success") return setError(result?.message ?? "");
      setError(null);
      toastManager.add({ title: m("ran", { orders: result.data.run.orders, tasks: result.data.run.tasks, alerts: result.data.run.alerts }), type: "success" });
      router.refresh();
    });
  }

  return (
    <section className={cn(card, "flex flex-col gap-3 p-4")}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm font-medium text-foreground">
          {run ? m("run_line", { at: dateTime.format(new Date(run.run_at)), orders: run.orders, tasks: run.tasks, alerts: run.alerts }) : m("no_run")}
        </p>
        {canRun ? (
          <Button size="sm" onClick={analyse} loading={pending}>
            <Sparkles /> {m("run")}
          </Button>
        ) : null}
      </div>
      {run?.narrative ? (
        <p className="rounded-lg bg-brand-subtle px-3 py-2 text-[13px] text-foreground">
          <span className="font-semibold text-brand">{m("claude_label")}</span> {run.narrative}
        </p>
      ) : (
        <p className="text-xs text-foreground-muted">{claude ? m("claude_pending") : m("rule_only")}</p>
      )}
      {error ? <Alert variant="danger">{error}</Alert> : null}
    </section>
  );
}

/** A row of headline figures. @param {{ items: { label: string, value: string|number, tone?: string }[] }} props */
function KpiRow({ items }) {
  return (
    <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
      {items.map((k) => (
        <div key={k.label} className={cn(card, "px-4 py-3")}>
          <p className="text-xs font-medium text-foreground-muted">{k.label}</p>
          <p className="mt-1 text-2xl font-semibold tabular-nums text-foreground">{k.value}</p>
        </div>
      ))}
    </section>
  );
}

/** A titled card. */
function Panel({ title, hint, children, className }) {
  return (
    <section className={cn(card, "flex flex-col p-4", className)}>
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      {hint ? <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p> : null}
      <div className="mt-3 min-h-0 flex-1">{children}</div>
    </section>
  );
}

/**
 * Counts by a band, as bars with a hover tooltip: risk bands in one hue light
 * → dark, anything else in the brand hue. Labels sit on the axis, so identity
 * never rests on colour.
 *
 * @param {{ data: { key: string, label: string, value: number }[], risk?: boolean, unit: string }} props
 */
function BandBars({ data, risk = false, unit }) {
  return (
    <div className="h-56">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 8, left: -16, bottom: 0 }} barCategoryGap="28%">
          <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="0" />
          <XAxis dataKey="label" tickLine={false} axisLine={{ stroke: "var(--border)" }} tick={{ fill: "var(--foreground-muted)", fontSize: 12 }} />
          <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fill: "var(--foreground-muted)", fontSize: 12 }} />
          <Tooltip
            cursor={{ fill: "var(--surface-muted)" }}
            formatter={(v) => [`${v} ${unit}`, ""]}
            separator=""
            contentStyle={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 6, fontSize: 12, color: "var(--foreground)" }}
          />
          <Bar dataKey="value" radius={[4, 4, 0, 0]} isAnimationActive={false}>
            {data.map((d) => (
              <Cell key={d.key} fill={risk ? BAND_FILL[d.key] : "var(--brand)"} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

/**
 * A ranked list with a bar each — "where the exposure sits", "longest slips".
 *
 * @param {{ rows: { key: string, title: string, sub: string, value: number, label: string }[], max: number }} props
 */
function RankList({ rows, max, empty }) {
  if (rows.length === 0) return <p className="py-6 text-center text-[13px] text-foreground-muted">{empty}</p>;
  return (
    <ol className="flex flex-col gap-2.5">
      {rows.map((r) => (
        <li key={r.key} title={`${r.title} · ${r.label}`}>
          <div className="flex items-baseline justify-between gap-2 text-sm">
            <span className="font-medium text-foreground">{r.title}</span>
            <span className="tabular-nums font-semibold text-foreground">{r.label}</span>
          </div>
          <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
            <div className="h-full rounded-full bg-brand" style={{ width: `${max > 0 ? Math.max(2, (r.value / max) * 100) : 0}%` }} />
          </div>
          <p className="mt-0.5 text-xs text-foreground-muted">{r.sub}</p>
        </li>
      ))}
    </ol>
  );
}

/**
 * A records grid with Brickland's segmented filter above it ("All (33) ·
 * Critical · High"); switching never reloads.
 *
 * A segment is plain data — the field it reads and the values it keeps (none:
 * every row) — since it is handed over from a server component.
 *
 * @param {{ segments: { key: string, label: string, field?: string, values?: string[] }[], rows: object[] } & object} props
 */
function SegmentedGrid({ segments, rows, ...grid }) {
  const [active, setActive] = useState(segments[0].key);
  const segment = segments.find((s) => s.key === active) ?? segments[0];
  const shown = useMemo(() => rows.filter(keep(segment)), [rows, segment]);
  return (
    <div className="flex flex-col gap-3">
      <Segments segments={segments} rows={rows} active={active} onChange={setActive} />
      <RecordsGrid rows={shown} {...grid} />
    </div>
  );
}

/**
 * Assisted Alerts & Advice: each finding of the last runs — the order, the
 * reason and the next move — with Acknowledge, Resolve and Dismiss; Open,
 * High & critical and All, switched in place.
 *
 * @param {{ alerts: object[], canAct: boolean, act: (id: string, action: string) => Promise<object> }} props
 */
function AlertsBoard({ alerts: initial, canAct, act }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.mai.${k}`, p);
  const toastManager = useToastManager();
  const [alerts, setAlerts] = useState(initial);
  const [view, setView] = useState("open");
  const [pending, startTransition] = useTransition();
  const views = {
    open: (a) => a.status === "OPEN",
    high: (a) => ["CRITICAL", "HIGH"].includes(a.severity) && a.status !== "DISMISSED",
    all: () => true,
  };
  const shown = alerts.filter(views[view]);

  function run(id, action) {
    startTransition(async () => {
      const result = await act(id, action);
      if (result?.status !== "success") return toastManager.add({ title: result?.message ?? "", type: "error" });
      setAlerts((all) => all.map((a) => (a.id === id ? result.data : a)));
      toastManager.add({ title: m(`alert_done.${action}`), type: "success" });
    });
  }

  return (
    <section className={cn(card, "p-4")}>
      <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
        <BellRing className="size-4" /> {m("alerts_title")}
      </h2>
      <p className="mt-0.5 text-[13px] text-foreground-muted">{m("alerts_hint")}</p>
      <div className="mt-3 flex flex-wrap gap-1.5" role="tablist">
        {Object.keys(views).map((k) => (
          <button
            key={k}
            type="button"
            role="tab"
            aria-selected={view === k}
            onClick={() => setView(k)}
            className={cn("rounded-md border px-3 py-1 text-[13px] font-medium", view === k ? "border-brand bg-brand-subtle text-brand" : "border-border text-foreground hover:bg-surface-muted")}
          >
            {m(`alert_view.${k}`, { n: alerts.filter(views[k]).length })}
          </button>
        ))}
      </div>
      <ul className="mt-3 flex flex-col gap-2.5">
        {shown.map((a) => (
          <li key={a.id} className="rounded-lg border border-border p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-foreground">{a.title}</p>
                <p className="mt-0.5 text-[13px] text-foreground-muted">{a.detail}</p>
              </div>
              <span className="flex items-center gap-1.5 text-xs">
                <span className="rounded-md bg-surface-muted px-1.5 py-0.5 font-medium text-foreground">{m(`band.${a.severity}`)}</span>
                <span className="rounded-md bg-surface-muted px-1.5 py-0.5 font-medium text-foreground-muted">{m(`alert_status.${a.status}`)}</span>
              </span>
            </div>
            <p className="mt-2 text-[13px] text-foreground">{a.advice}</p>
            {canAct ? (
              <div className="mt-2 flex flex-wrap gap-1.5">
                {a.status === "OPEN" ? (
                  <Button variant="outline" size="sm" onClick={() => run(a.id, "acknowledge")} disabled={pending}>
                    <Eye /> {m("alert_action.acknowledge")}
                  </Button>
                ) : null}
                {["OPEN", "ACKNOWLEDGED"].includes(a.status) ? (
                  <>
                    <Button variant="outline" size="sm" onClick={() => run(a.id, "resolve")} disabled={pending}>
                      <Check /> {m("alert_action.resolve")}
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => run(a.id, "dismiss")} disabled={pending}>
                      <X /> {m("alert_action.dismiss")}
                    </Button>
                  </>
                ) : (
                  <Button variant="outline" size="sm" onClick={() => run(a.id, "reopen")} disabled={pending}>
                    <RotateCcw /> {m("alert_action.reopen")}
                  </Button>
                )}
              </div>
            ) : null}
          </li>
        ))}
        {shown.length === 0 ? <li className="py-6 text-center text-[13px] text-foreground-muted">{m("no_alerts")}</li> : null}
      </ul>
    </section>
  );
}

/** Brickland's segmented filter row ("All (53) · Critical · High"); "{n}" in a label becomes the count it keeps. */
function Segments({ segments, rows, active, onChange }) {
  return (
    <div className="flex flex-wrap gap-1.5" role="tablist">
      {segments.map((s) => (
        <button
          key={s.key}
          type="button"
          role="tab"
          aria-selected={active === s.key}
          onClick={() => onChange(s.key)}
          className={cn("rounded-md border px-3 py-1 text-[13px] font-medium", active === s.key ? "border-brand bg-brand-subtle text-brand" : "border-border text-foreground hover:bg-surface-muted")}
        >
          {s.label.replace("{n}", rows.filter(keep(s)).length)}
        </button>
      ))}
    </div>
  );
}

const keep = (segment) => (r) => !segment.field || (segment.values ?? []).includes(r[segment.field]);

/** The engine's sentence and which half of it wrote it. */
function ActionNote({ title, source, action, summary }) {
  return (
    <div className="rounded-lg bg-surface-muted/60 p-3">
      <p className="flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
        <Sparkles className="size-3.5" /> {title}
        <span className="rounded-md border border-border bg-surface px-1.5 py-0.5 text-[11px] font-medium tracking-normal text-foreground normal-case">{source}</span>
      </p>
      <p className="mt-1.5 text-[13px] text-foreground">{action}</p>
      {summary ? <p className="mt-1 text-xs text-foreground-muted">{summary}</p> : null}
    </div>
  );
}

/** A band chip: the band's own step of the risk ramp, with its name on it. */
function BandChip({ band, label }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-md bg-surface-muted px-1.5 py-0.5 text-[11px] font-medium text-foreground">
      <span className="size-2 rounded-full" style={{ background: BAND_FILL[band] }} aria-hidden="true" />
      {label}
    </span>
  );
}

/**
 * Risk Driver Analysis: every order's composite, its sub-scores and the
 * weighted factors that add up to it — as cards or as a table, filtered by
 * band, both switched in place.
 *
 * @param {{ segments: object[], rows: object[], views: { cards: string, table: string }, labels: { analysis: string, recommended: string, risk: string }, grid: object }} props
 */
function FactorBoard({ segments, rows, views, labels, grid }) {
  const [active, setActive] = useState(segments[0].key);
  const [view, setView] = useState("cards");
  const segment = segments.find((s) => s.key === active) ?? segments[0];
  const shown = useMemo(() => rows.filter(keep(segment)), [rows, segment]);
  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Segments segments={segments} rows={rows} active={active} onChange={setActive} />
        <div className="flex gap-1.5" role="tablist">
          {["cards", "table"].map((v) => (
            <button
              key={v}
              type="button"
              role="tab"
              aria-selected={view === v}
              onClick={() => setView(v)}
              className={cn("rounded-md border px-3 py-1 text-[13px] font-medium", view === v ? "border-brand bg-brand-subtle text-brand" : "border-border text-foreground hover:bg-surface-muted")}
            >
              {views[v]}
            </button>
          ))}
        </div>
      </div>
      {view === "table" ? (
        <RecordsGrid rows={shown} {...grid} />
      ) : shown.length === 0 ? (
        <p className={cn(card, "py-10 text-center text-[13px] text-foreground-muted")}>{grid.emptyText}</p>
      ) : (
        <div className="grid gap-3 xl:grid-cols-2">
          {shown.map((r) => (
            <article key={r.order_no} className={cn(card, "flex flex-col gap-3 p-4")}>
              <header className="flex items-start gap-3">
                <div className="flex w-14 shrink-0 flex-col items-center rounded-lg border border-border py-1.5">
                  <span className="text-xl font-semibold text-foreground tabular-nums">{num.format(r.risk)}</span>
                  <span className="text-[11px] text-foreground-muted">{labels.risk}</span>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-foreground">
                    {r.order_no}
                    <BandChip band={r.band} label={r.band_label} />
                  </p>
                  <p className="mt-0.5 text-xs text-foreground-muted">{r.head}</p>
                  <p className="text-xs text-foreground-muted">{r.ship}</p>
                </div>
              </header>
              <dl className="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs sm:grid-cols-4">
                {r.stats.map((x) => (
                  <div key={x.label}>
                    <dt className="text-foreground-muted">{x.label}</dt>
                    <dd className="font-semibold text-foreground tabular-nums">{x.value}</dd>
                  </div>
                ))}
              </dl>
              <div>
                <p className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{labels.analysis}</p>
                <ul className="mt-2 flex flex-col gap-2">
                  {r.drivers.map((d) => (
                    <li key={d.key} title={`${d.label} · ${d.points}`}>
                      <div className="flex items-baseline justify-between gap-2 text-[13px]">
                        <span className="font-medium text-foreground">{d.label}</span>
                        <span className="text-foreground tabular-nums">
                          <span className="font-semibold">{d.points}</span> <span className="text-foreground-muted">({d.weight})</span>
                        </span>
                      </div>
                      <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                        <div className="h-full rounded-full bg-brand" style={{ width: `${Math.max(2, d.value)}%` }} />
                      </div>
                      <p className="mt-0.5 text-xs text-foreground-muted">{d.text}</p>
                    </li>
                  ))}
                </ul>
              </div>
              <ActionNote title={labels.recommended} source={r.source_label} action={r.action} summary={r.summary} />
            </article>
          ))}
        </div>
      )}
    </div>
  );
}

/**
 * Recommended Action Advice: the next move for each order, worst first, with
 * All · Claude · Rule engine switched in place.
 *
 * @param {{ segments: object[], rows: object[], emptyText: string }} props
 */
function ActionBoard({ segments, rows, emptyText }) {
  const [active, setActive] = useState(segments[0].key);
  const segment = segments.find((s) => s.key === active) ?? segments[0];
  const shown = rows.filter(keep(segment));
  return (
    <div className="flex flex-col gap-3">
      <Segments segments={segments} rows={rows} active={active} onChange={setActive} />
      {shown.length === 0 ? <p className="py-8 text-center text-[13px] text-foreground-muted">{emptyText}</p> : null}
      <ul className="flex flex-col gap-2.5">
        {shown.map((r) => (
          <li key={r.order_no} className="rounded-lg border border-border p-3">
            <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-foreground">
              {r.order_no}
              <BandChip band={r.band} label={r.band_label} />
              <span className="text-xs font-normal text-foreground-muted">{r.buyer}</span>
              <span className="text-xs font-normal text-foreground-muted">{r.style}</span>
              <span className="ml-auto rounded-md border border-border px-1.5 py-0.5 text-[11px] font-medium text-foreground">{r.source_label}</span>
            </p>
            <p className="mt-2 text-[13px] text-foreground">{r.action}</p>
            <p className="mt-1 text-xs text-foreground-muted">{r.summary}</p>
            <p className="mt-1 text-xs text-foreground-muted tabular-nums">{r.meta}</p>
          </li>
        ))}
      </ul>
    </div>
  );
}

export { ActionBoard, AiRunBar, AlertsBoard, BandBars, FactorBoard, KpiRow, Panel, RankList, SegmentedGrid };
