"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import {
  AlarmClock,
  AlertTriangle,
  BadgeCheck,
  Ban,
  BarChart3,
  Boxes,
  CalendarClock,
  CheckCheck,
  CircleCheck,
  ClipboardCheck,
  ClipboardList,
  FileCheck,
  FilePlus,
  GitBranch,
  GitCompare,
  Hourglass,
  Info,
  Layers,
  MessageSquare,
  OctagonAlert,
  PackageCheck,
  PackageSearch,
  ScanLine,
  Scissors,
  Stamp,
  Table2,
  Truck,
  Wrench,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { RelativeTime } from "@/components/ui/relative-time";
import { Panel, RankList } from "@/components/merchandise/ai/ai-kit";
import { useT } from "@/lib/i18n";
import { card } from "./proto-kit";
import { clockFormat } from "@/lib/clock";

/*
 * Prototype Desk → Prototype Dashboard (AnnotechERP 07/02), as Brickland draws
 * it: the period picker and what it covers, the desk's screens, sixteen
 * tiles, then the breakdowns, the sample room, the trend, the performance
 * indicators, the open alerts, the latest timeline and the delayed samples.
 * Changing the period reads the dashboard again in place; nothing reloads.
 */

const RANGES = ["7d", "30d", "90d", "year", "all"];
const SCREENS = [
  ["prototype-requisition", FilePlus],
  ["sample-development", Wrench],
  ["sample-revision", GitBranch],
  ["material-requisition", ClipboardList],
  ["bundle-card-create", Boxes],
  ["daily-target-setting", CalendarClock],
  ["bundle-movement", ScanLine],
  ["sample-quality-check", ClipboardCheck],
  ["sample-approval", Stamp],
  ["buyer-feedback", MessageSquare],
  ["sample-handover", Truck],
];
// Each tile's icon and the tint of its square; the tint only marks the tile, its label names it.
const TILES = {
  total: [Layers, "text-info bg-info-subtle"],
  new_requests: [FilePlus, "text-warning bg-warning-subtle"],
  pending_requests: [Hourglass, "text-warning bg-warning-subtle"],
  approved_requests: [FileCheck, "text-success bg-success-subtle"],
  in_development: [Wrench, "text-brand bg-brand-subtle"],
  material_pending: [PackageSearch, "text-warning bg-warning-subtle"],
  material_issued: [PackageCheck, "text-success bg-success-subtle"],
  in_production: [Scissors, "text-brand bg-brand-subtle"],
  pending_qc: [ClipboardCheck, "text-info bg-info-subtle"],
  pending_approval: [Stamp, "text-warning bg-warning-subtle"],
  approved_samples: [BadgeCheck, "text-success bg-success-subtle"],
  rejected_samples: [Ban, "text-danger bg-danger-subtle"],
  revision_required: [GitCompare, "text-danger bg-danger-subtle"],
  delayed: [AlarmClock, "text-danger bg-danger-subtle"],
  completed_today: [CheckCheck, "text-success bg-success-subtle"],
  delivered: [Truck, "text-info bg-info-subtle"],
};
// Severity is a status: its colour always travels with its icon and its word.
const SEVERITY = {
  CRITICAL: [OctagonAlert, "bg-danger-subtle text-danger"],
  WARNING: [AlertTriangle, "bg-warning-subtle text-warning"],
  INFO: [Info, "bg-info-subtle text-info"],
  SUCCESS: [CircleCheck, "bg-success-subtle text-success"],
};
const SERIES = ["var(--series-1)", "var(--series-2)", "var(--series-3)"];
const tooltipStyle = { background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 6, fontSize: 12, color: "var(--foreground)" };
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Dhaka" });
const dateTimeFmt = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const monthFmt = new Intl.DateTimeFormat("en-GB", { month: "short", year: "2-digit", timeZone: "UTC" });
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v).toFixed(1)}%`);
const entries = (map) => Object.entries(map ?? {}).map(([key, value]) => ({ key, value }));

/** A severity badge: icon, word and tint together. */
function SeverityBadge({ severity, label }) {
  const [Icon, tone] = SEVERITY[severity] ?? SEVERITY.INFO;
  return (
    <span className={cn("inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium", tone)}>
      <Icon className="size-3.5" aria-hidden /> {label}
    </span>
  );
}

/** Two small toggles in a panel's corner: which breakdown, and chart or table. */
function Toggle({ options, value, onChange, label }) {
  return (
    <div role="radiogroup" aria-label={label} className="inline-flex rounded-md border border-border p-0.5">
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          role="radio"
          aria-checked={value === o.value}
          aria-label={o.aria}
          title={o.aria}
          onClick={() => onChange(o.value)}
          className={cn("inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium [&_svg]:size-3.5", value === o.value ? "bg-brand-subtle text-brand" : "text-foreground-muted hover:text-foreground")}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

/** A count breakdown, as ranked bars or a table. */
function Breakdown({ rows, view, empty, unit }) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  if (view === "table") {
    if (rows.length === 0) return <p className="py-6 text-center text-[13px] text-foreground-muted">{empty}</p>;
    return (
      <table className="w-full text-sm">
        <tbody>
          {rows.map((r) => (
            <tr key={r.key} className="border-b border-border last:border-0">
              <td className="py-1.5 text-foreground">{r.title}</td>
              <td className="py-1.5 text-right tabular-nums font-semibold text-foreground">{r.value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  }
  return <RankList rows={rows.map((r) => ({ ...r, label: r.label ?? `${r.value}`, sub: r.sub ?? unit }))} max={max} empty={empty} />;
}

/** A labelled meter: a figure, what it is of, and the bar. */
function Meter({ label, value, detail, hint, tone = "bg-brand" }) {
  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex items-baseline justify-between gap-2">
        <span className="text-sm font-medium text-foreground">{label}</span>
        <span className="text-lg font-semibold tabular-nums text-foreground">{pct(value)}</span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-surface-muted" role="meter" aria-label={label} aria-valuenow={value ?? 0} aria-valuemin={0} aria-valuemax={100}>
        <div className={cn("h-full rounded-full", tone)} style={{ width: `${Math.min(100, value ?? 0)}%` }} />
      </div>
      <p className="text-xs text-foreground-muted">
        {detail} · {hint}
      </p>
    </div>
  );
}

/**
 * @param {{ initial: object }} props the dashboard as `GET /prototype/dashboard` returns it
 * @param {{ load: (range: string) => Promise<object> }} props.actions
 */
function PrototypeDashboard({ initial, load }) {
  const t = useT("prototype");
  const d = (k, p) => t(`ui.dash.${k}`, p);
  const [data, setData] = useState(initial);
  const [pending, startTransition] = useTransition();
  const [statusBy, setStatusBy] = useState("status");
  const [statusView, setStatusView] = useState("chart");
  const [delayBy, setDelayBy] = useState("by_reason");
  const period = d(`range.${data.range}`);

  function pick(range) {
    window.history.replaceState(null, "", range === "30d" ? "?" : `?range=${range}`);
    startTransition(async () => {
      const result = await load(range);
      if (result?.status === "success") setData(result.data);
    });
  }

  const scope = (tile) => (tile.scope === "open" ? d("scope.open") : tile.scope === "today" ? day(new Date().toISOString()) : period);
  const statusRows = entries(statusBy === "status" ? data.by_status : data.by_stage).map((r) => ({ ...r, title: t(`${statusBy === "status" ? "life_status" : "life_stage"}.${r.key}`) }));
  const named = (map, group) => entries(map).map((r) => ({ ...r, title: group ? t(`${group}.${r.key}`) : r.key === "—" ? "—" : r.key }));
  const delayRows = delayBy === "by_reason" ? named(data.delay.by_reason) : named(data.delay.by_stage, "life_stage");
  const cap = data.capacity;
  const viewToggle = [
    { value: "chart", label: <BarChart3 />, aria: d("chart_view") },
    { value: "table", label: <Table2 />, aria: d("table_view") },
  ];

  return (
    <div className={cn("flex flex-col gap-4 transition-opacity", pending && "opacity-60")}>
      <section className="flex flex-wrap items-start justify-between gap-3">
        <p className="max-w-4xl text-[13px] text-foreground-muted">
          {data.since ? d("scope_note", { period, since: day(data.since), updated: dateTimeFmt.format(new Date(data.updated_at)) }) : d("scope_note_all", { updated: dateTimeFmt.format(new Date(data.updated_at)) })}
        </p>
        <div role="radiogroup" aria-label={d("period")} className="inline-flex rounded-lg border border-border bg-surface p-0.5">
          {RANGES.map((r) => (
            <button
              key={r}
              type="button"
              role="radio"
              aria-checked={data.range === r}
              disabled={pending}
              onClick={() => pick(r)}
              className={cn("rounded-md px-3 py-1.5 text-[13px] font-medium", data.range === r ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}
            >
              {d(`range.${r}`)}
            </button>
          ))}
        </div>
      </section>

      <nav aria-label={d("screens")} className="flex flex-wrap gap-2">
        {SCREENS.map(([key, Icon]) => (
          <Link key={key} href={`/prototype-desk/${key}`} className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] font-medium text-foreground hover:bg-surface-muted">
            <Icon className="size-3.5 text-foreground-muted" aria-hidden /> {t(`screens.${key}`)}
          </Link>
        ))}
      </nav>

      <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {data.tiles.map((tile) => {
          const [Icon, tone] = TILES[tile.key];
          return (
            <div key={tile.key} className={cn(card, "flex flex-col gap-2 p-4")}>
              <div className="flex items-start justify-between gap-2">
                <span className="flex items-center gap-2 text-[13px] font-medium text-foreground">
                  <span className={cn("flex size-8 items-center justify-center rounded-lg", tone)}>
                    <Icon className="size-4" aria-hidden />
                  </span>
                  {d(`tile.${tile.key}`)}
                </span>
                {tile.key === "delayed" && tile.value > 0 ? (
                  <span className="inline-flex items-center gap-1 rounded-md bg-danger-subtle px-1.5 py-0.5 text-xs font-medium text-danger">
                    <AlarmClock className="size-3" aria-hidden /> {d("late")}
                  </span>
                ) : null}
              </div>
              <p className="text-2xl font-semibold tabular-nums text-foreground">{tile.value}</p>
              <p className="text-xs text-foreground-muted">{scope(tile)}</p>
            </div>
          );
        })}
      </section>

      <div className="grid gap-4 lg:grid-cols-3">
        <Panel title={d("status")} hint={d("status_hint", { period })} className="min-w-0">
          <div className="mb-3 flex flex-wrap justify-end gap-2">
            <Toggle
              label={d("status")}
              value={statusBy}
              onChange={setStatusBy}
              options={[
                { value: "status", label: d("by_status"), aria: d("by_status") },
                { value: "stage", label: d("by_stage"), aria: d("by_stage") },
              ]}
            />
            <Toggle label={d("table_view")} value={statusView} onChange={setStatusView} options={viewToggle} />
          </div>
          <Breakdown rows={statusRows} view={statusView} empty={d("nothing")} unit={d("samples")} />
        </Panel>
        <Panel title={d("rates")} hint={period} className="min-w-0">
          <div className="flex flex-col gap-5">
            <Meter label={d("approval_rate")} value={data.approval_rate.rate} detail={d("approval_of", data.approval_rate)} hint={d("approval_rate_hint")} tone="bg-success" />
            <Meter label={d("revision_rate")} value={data.revision_rate.rate} detail={d("revision_of", data.revision_rate)} hint={d("revision_rate_hint")} tone="bg-warning" />
          </div>
        </Panel>
        <Panel title={d("capacity")} hint={cap ? d("capacity_hint", { date: day(cap.date), room: cap.room ?? "—" }) : period} className="min-w-0">
          {cap ? (
            <div className="flex flex-col gap-3">
              <span className={cn("inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium", cap.over ? "bg-danger-subtle text-danger" : "bg-success-subtle text-success")}>
                {cap.over ? <AlertTriangle className="size-3.5" aria-hidden /> : <CircleCheck className="size-3.5" aria-hidden />} {cap.over ? d("over") : d("within")}
              </span>
              <Meter label={d("used")} value={cap.used} detail={d("hours_of", { a: cap.planned, b: cap.capacity })} hint={`${t(`day_state.${cap.state}`)} · ${t(`shift.${cap.shift}`)}`} tone={cap.over ? "bg-danger" : "bg-brand"} />
            </div>
          ) : (
            <p className="py-6 text-center text-[13px] text-foreground-muted">{d("no_sheet")}</p>
          )}
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Panel title={d("daily")} hint={d("daily_hint", { n: data.daily.days.length })} className="min-w-0 lg:col-span-2">
          {data.daily.days.length ? (
            <>
              <div className="h-60">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.daily.days.map((x) => ({ ...x, label: day(x.date) }))} margin={{ top: 8, right: 8, left: -16, bottom: 0 }} barGap={2} barCategoryGap="24%">
                    <CartesianGrid vertical={false} stroke="var(--border)" />
                    <XAxis dataKey="label" tickLine={false} axisLine={{ stroke: "var(--border)" }} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} minTickGap={16} />
                    <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} />
                    <Tooltip cursor={{ fill: "var(--surface-muted)" }} contentStyle={tooltipStyle} />
                    <Legend itemSorter={null} iconType="circle" wrapperStyle={{ fontSize: 12, color: "var(--foreground-muted)" }} />
                    <Bar dataKey="target" name={d("target")} fill={SERIES[0]} radius={[4, 4, 0, 0]} maxBarSize={28} isAnimationActive={false} />
                    <Bar dataKey="achieved" name={d("achieved")} fill={SERIES[1]} radius={[4, 4, 0, 0]} maxBarSize={28} isAnimationActive={false} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
              <p className="mt-2 text-[13px] text-foreground-muted">{d("achievement", { pct: pct(data.daily.pct), a: data.daily.achieved, b: data.daily.target })}</p>
            </>
          ) : (
            <p className="py-6 text-center text-[13px] text-foreground-muted">{d("no_sheet")}</p>
          )}
        </Panel>
        <Panel title={d("desk")} hint={d("desk_hint")} className="min-w-0">
          <Breakdown rows={named(data.by_desk, "desk")} empty={d("nothing")} unit={d("samples")} />
        </Panel>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Panel title={d("buyer")} hint={d("buyer_hint", { period })} className="min-w-0">
          <Breakdown rows={data.by_buyer.map((b) => ({ key: b.label, title: b.label, value: b.samples, sub: `${d("approved")} ${b.approved}` }))} empty={d("nothing")} />
        </Panel>
        <Panel title={d("style")} hint={d("style_hint", { period })} className="min-w-0">
          <Breakdown rows={data.by_style.map((s) => ({ key: `${s.label}|${s.buyer}`, title: s.label, value: s.samples, sub: s.buyer ?? "" }))} empty={d("nothing")} />
        </Panel>
        <Panel title={d("type")} hint={d("type_hint", { period })} className="min-w-0">
          <Breakdown rows={named(data.by_type)} empty={d("nothing")} unit={d("samples")} />
        </Panel>
        <Panel title={d("sample_stage")} hint={d("sample_stage_hint", { period })} className="min-w-0">
          <Breakdown rows={entries(data.by_sample_stage).map((r) => ({ ...r, title: r.key === "—" ? "—" : t(`stage.${r.key}`) }))} empty={d("nothing")} unit={d("samples")} />
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Panel title={d("delay")} hint={d("delay_hint", { n: data.delay.count })} className="min-w-0">
          <div className="mb-3 flex justify-end">
            <Toggle
              label={d("delay")}
              value={delayBy}
              onChange={setDelayBy}
              options={[
                { value: "by_reason", label: d("by_reason"), aria: d("by_reason") },
                { value: "by_stage", label: d("by_stage"), aria: d("by_stage") },
              ]}
            />
          </div>
          <Breakdown rows={delayRows} empty={d("no_delayed")} unit={d("samples")} />
        </Panel>
        <Panel title={d("performance")} hint={d("performance_hint")} className="min-w-0 lg:col-span-2">
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={data.performance.map((p) => ({ ...p, label: monthFmt.format(new Date(p.date)) }))} margin={{ top: 8, right: 12, left: -16, bottom: 0 }}>
                <CartesianGrid vertical={false} stroke="var(--border)" />
                <XAxis dataKey="label" tickLine={false} axisLine={{ stroke: "var(--border)" }} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} />
                <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} />
                <Tooltip cursor={{ stroke: "var(--border-strong)" }} contentStyle={tooltipStyle} />
                <Legend itemSorter={null} iconType="circle" wrapperStyle={{ fontSize: 12, color: "var(--foreground-muted)" }} />
                {["requested", "completed", "approved"].map((k, i) => (
                  <Line key={k} dataKey={k} name={d(k)} stroke={SERIES[i]} strokeWidth={2} dot={{ r: 4, strokeWidth: 2, stroke: "var(--surface)", fill: SERIES[i] }} activeDot={{ r: 5 }} isAnimationActive={false} />
                ))}
              </LineChart>
            </ResponsiveContainer>
          </div>
        </Panel>
      </div>

      <Panel title={d("kpis")} hint={d("kpis_hint", { period })}>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {data.kpis.map((k) => (
            <div key={k.key} className="rounded-lg border border-border p-3">
              <div className="flex items-start justify-between gap-2">
                <span className="text-[13px] font-medium text-foreground">{d(`kpi.${k.key}.label`)}</span>
                <span className="shrink-0 text-[11px] text-foreground-muted">{k.better === "higher" ? d("higher") : d("lower")}</span>
              </div>
              <p className="mt-1 text-xl font-semibold tabular-nums text-foreground">{k.value === null ? "—" : k.unit === "days" ? d("days", { n: k.value }) : k.key === "avg_revisions" ? Number(k.value).toFixed(2) : pct(k.value)}</p>
              <p className="mt-0.5 text-xs text-foreground-muted">{d(`kpi.${k.key}.detail`, { a: k.a ?? 0, b: k.b ?? 0, c: k.c ?? 0 })}</p>
            </div>
          ))}
        </div>
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title={d("alerts")} hint={d("alerts_hint")} className="min-w-0">
          <div className="mb-3 flex gap-2 text-xs font-medium">
            <span className="rounded-md bg-danger-subtle px-1.5 py-0.5 text-danger">{d("critical_n", { n: data.alerts.critical })}</span>
            <span className="rounded-md bg-surface-muted px-1.5 py-0.5 text-foreground">{d("open_n", { n: data.alerts.open })}</span>
          </div>
          {data.alerts.top.length ? (
            <ul className="flex flex-col divide-y divide-border">
              {data.alerts.top.map((a) => (
                <li key={a.id} className="flex items-start gap-3 py-2.5">
                  <SeverityBadge severity={a.severity} label={t(`alert_severity.${a.severity}`)} />
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground">{a.title}</p>
                    <p className="text-xs text-foreground-muted">{a.message}</p>
                  </div>
                  <span className="shrink-0 text-xs text-foreground-muted">
                    <RelativeTime value={a.raised_at} />
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="py-6 text-center text-[13px] text-foreground-muted">{d("no_alerts")}</p>
          )}
          <Link href="/prototype-desk/sample-alerts" className="mt-2 text-[13px] font-medium text-brand hover:underline">
            {t("screens.sample-alerts")}
          </Link>
        </Panel>
        <Panel title={d("timeline")} hint={d("timeline_hint")} className="min-w-0">
          {data.timeline.length ? (
            <ul className="flex flex-col divide-y divide-border">
              {data.timeline.map((e, i) => (
                <li key={`${e.at}-${i}`} className="flex items-start gap-3 py-2.5">
                  <span className="shrink-0 rounded-md bg-brand-subtle px-1.5 py-0.5 text-xs font-medium text-brand">{e.stage ? t(`life_stage.${e.stage}`) : "—"}</span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm text-foreground">
                      <span className="font-mono text-xs text-foreground-muted">{e.sample_no}</span> <span className="font-medium">{e.title}</span> — {e.summary}
                    </p>
                    <p className="text-xs text-foreground-muted">
                      {e.by ?? d("desk_actor")} · <RelativeTime value={e.at} />
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="py-6 text-center text-[13px] text-foreground-muted">{d("no_timeline")}</p>
          )}
        </Panel>
      </div>

      <Panel title={d("delayed")} hint={d("delayed_hint")}>
        <div className="mb-3 text-xs font-medium">
          <span className="rounded-md bg-danger-subtle px-1.5 py-0.5 text-danger">{d("delayed_n", { n: data.delayed.length })}</span>
        </div>
        {data.delayed.length ? (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs text-foreground-muted">
                  {["sample_no", "style", "buyer", "stage", "required", "days_late", "reason"].map((c) => (
                    <th key={c} className={cn("py-2 pr-3 font-medium", c === "days_late" && "text-right")}>
                      {d(`col.${c}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.delayed.map((s) => (
                  <tr key={s.sample_no} className="border-b border-border last:border-0">
                    <td className="py-2 pr-3">
                      <Link href={`/prototype-desk/sample-360-view?tab=view&sample=${encodeURIComponent(s.sample_no)}`} className="font-mono text-xs text-brand hover:underline">
                        {s.sample_no}
                      </Link>
                    </td>
                    <td className="py-2 pr-3 text-foreground">{s.style ?? "—"}</td>
                    <td className="py-2 pr-3 text-foreground">{s.buyer ?? "—"}</td>
                    <td className="py-2 pr-3 text-foreground">{t(`life_stage.${s.stage}`)}</td>
                    <td className="py-2 pr-3 text-foreground">{day(s.required)}</td>
                    <td className="py-2 pr-3 text-right tabular-nums font-semibold text-danger">{s.days_late}</td>
                    <td className="py-2 pr-3 text-foreground-muted">{s.reason ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{d("no_delayed")}</p>
        )}
      </Panel>
    </div>
  );
}

export { PrototypeDashboard, SeverityBadge };
