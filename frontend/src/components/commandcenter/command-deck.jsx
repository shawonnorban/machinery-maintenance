"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Activity, AlertTriangle, BellRing, ChartColumn, ChartLine, Gauge, Handshake, Landmark, ListChecks, PieChart, Stamp, Table2, Target } from "lucide-react";
import { MenuIcon } from "@/components/layout/menu-icon";
import { cn } from "@/lib/utils";

/*
 * Chief Executive Command Deck (AnnotechERP 27/01), laid out as Brickland's
 * CEO Command Center: four headline tiles, four panels, the modules, recent
 * activity and the top alerts. Every figure comes from `/command-center/deck`.
 *
 * Charts follow the project's dataviz rules: one axis per chart, categorical
 * series in fixed token order (--series-1, -2, -3; validated light and dark),
 * thin marks, a legend for two or more series, a tooltip on hover, and a
 * table view of every chart — the light theme's green sits under 3:1 against
 * the surface, so the numbers must be readable without the colour.
 */

const S1 = "var(--series-1)";
const S2 = "var(--series-2)";
const S3 = "var(--series-3)";
const card = "rounded-lg border border-border bg-surface";

const taka = (v) => (v === null || v === undefined ? "—" : `৳${new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(Number(v))}`);
const takaFull = (v) => `৳${Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 })}`;
const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v).toFixed(1)}%`);
const monthLabel = (ym) => new Date(`${ym}-01T00:00:00`).toLocaleDateString("en-US", { month: "short", year: "2-digit" });

function Kpi({ icon: Icon, tone, label, value, sub }) {
  return (
    <div className={cn(card, "flex flex-col gap-3 px-4 py-4")}>
      <div className="flex items-center gap-3">
        <span className={cn("grid size-9 shrink-0 place-items-center rounded-lg", tone)}>
          <Icon className="size-4" aria-hidden />
        </span>
        <p className="text-sm font-semibold text-foreground">{label}</p>
      </div>
      <p className="text-2xl font-semibold tabular-nums text-foreground">{value}</p>
      <p className="text-xs text-foreground-muted">{sub}</p>
    </div>
  );
}

function Empty({ icon: Icon, w }) {
  return (
    <div className="flex h-full min-h-56 flex-col items-center justify-center gap-2 text-center">
      <span className="grid size-11 place-items-center rounded-lg border border-border bg-surface-muted text-foreground-muted">
        <Icon className="size-5" aria-hidden />
      </span>
      <p className="text-sm font-semibold text-foreground">{w.empty_title}</p>
      <p className="max-w-80 text-xs text-foreground-muted">{w.empty_text}</p>
    </div>
  );
}

/** A panel with its chart, or the same numbers as a table (the chart's table view). */
function ChartPanel({ title, sub, icon, empty, w, chart, table, className }) {
  const [asTable, setAsTable] = useState(false);
  return (
    <section className={cn(card, "flex min-w-0 flex-col p-5", className)}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="text-[15px] font-semibold text-foreground">{title}</h3>
          <p className="text-xs text-foreground-muted">{sub}</p>
        </div>
        {empty ? null : (
          <button type="button" onClick={() => setAsTable((v) => !v)} className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border px-2 py-1 text-xs text-foreground-muted hover:bg-surface-muted" aria-pressed={asTable}>
            {asTable ? <ChartColumn className="size-3.5" aria-hidden /> : <Table2 className="size-3.5" aria-hidden />}
            {asTable ? w.show_chart : w.show_table}
          </button>
        )}
      </div>
      <div className="mt-4 min-w-0 flex-1">{empty ? <Empty icon={icon} w={w} /> : asTable ? table : <div className="h-64 w-full">{chart}</div>}</div>
    </section>
  );
}

function DataTable({ head, rows }) {
  return (
    <div className="max-h-64 overflow-auto">
      <table className="w-full text-sm">
        <thead className="sticky top-0 bg-surface text-xs text-foreground-muted">
          <tr>
            {head.map((h, i) => (
              <th key={h} className={cn("py-1.5 font-medium", i === 0 ? "text-left" : "text-right")}>
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r[0]} className="border-t border-border">
              {r.map((c, i) => (
                <td key={i} className={cn("py-1.5 tabular-nums", i === 0 ? "text-left text-foreground" : "text-right text-foreground")}>
                  {c}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

const axis = { tick: { fontSize: 11, fill: "var(--foreground-muted)" }, tickLine: false, axisLine: false };
const tooltipStyle = { contentStyle: { borderRadius: 8, border: "1px solid var(--border)", fontSize: 12, background: "var(--surface)" }, cursor: { fill: "var(--surface-muted)" } };

/** "3 h ago": worked out after mount, so the server and the browser never disagree on "now". */
function useAgo() {
  const [now, setNow] = useState(null);
  useEffect(() => {
    const t = setTimeout(() => setNow(Date.now()), 0);
    return () => clearTimeout(t);
  }, []);
  return (iso, w) => {
    if (!now || !iso) return "";
    const m = Math.max(0, Math.round((now - new Date(iso).getTime()) / 60000));
    if (m < 60) return w.ago_m.replace(":n", m);
    if (m < 60 * 24) return w.ago_h.replace(":n", Math.round(m / 60));
    return w.ago_d.replace(":n", Math.round(m / 1440));
  };
}

function CommandDeck({ deck, modules, w }) {
  const ago = useAgo();
  const k = deck.kpis;
  const trendEmpty = deck.trend.every((m) => !m.revenue && !m.cost && !m.shipment);
  const trend = deck.trend.map((m) => ({ ...m, label: monthLabel(m.month) }));
  const buyers = deck.buyers.map((b) => ({ ...b, name: b.other ? w.other : b.buyer }));

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi
          icon={Landmark}
          tone="bg-brand-subtle text-brand"
          label={w.kpi.revenue}
          value={k.revenue.value === null ? "—" : taka(k.revenue.value)}
          sub={k.revenue.value === null ? w.no_data : k.revenue.change === null ? w.kpi.invoices.replace(":n", k.revenue.count) : w.kpi.vs_last.replace(":p", `${k.revenue.change > 0 ? "+" : ""}${k.revenue.change}%`)}
        />
        <Kpi icon={Handshake} tone="bg-danger-subtle text-danger" label={w.kpi.order_book} value={k.order_book.value === null ? "—" : taka(k.order_book.value)} sub={k.order_book.value === null ? w.no_data : w.kpi.orders.replace(":n", k.order_book.count)} />
        <Kpi icon={Gauge} tone="bg-success-subtle text-success" label={w.kpi.efficiency} value={pct(k.efficiency.value)} sub={k.efficiency.value === null ? w.no_data : w.kpi.target.replace(":p", pct(k.efficiency.target))} />
        <Kpi icon={Target} tone="bg-warning-subtle text-warning" label={w.kpi.on_time} value={pct(k.on_time.value)} sub={k.on_time.value === null ? w.no_data : w.kpi.shipped.replace(":a", k.on_time.on_time).replace(":n", k.on_time.shipped)} />
      </div>

      <div className="grid gap-4 lg:grid-cols-5">
        <ChartPanel
          className="lg:col-span-3"
          title={w.trend.title}
          sub={w.trend.sub}
          icon={ChartLine}
          empty={trendEmpty}
          w={w}
          chart={
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={trend} margin={{ top: 8, right: 12, left: 4, bottom: 0 }}>
                <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 3" />
                <XAxis dataKey="label" {...axis} />
                <YAxis {...axis} width={56} tickFormatter={taka} />
                <Tooltip {...tooltipStyle} cursor={{ stroke: "var(--border-strong)" }} formatter={(v) => takaFull(v)} />
                <Legend iconType="plainline" wrapperStyle={{ fontSize: 12 }} />
                <Line type="monotone" dataKey="revenue" name={w.trend.revenue} stroke={S1} strokeWidth={2} dot={false} activeDot={{ r: 4 }} isAnimationActive={false} />
                <Line type="monotone" dataKey="cost" name={w.trend.cost} stroke={S2} strokeWidth={2} dot={false} activeDot={{ r: 4 }} isAnimationActive={false} />
                <Line type="monotone" dataKey="shipment" name={w.trend.shipment} stroke={S3} strokeWidth={2} dot={false} activeDot={{ r: 4 }} isAnimationActive={false} />
              </LineChart>
            </ResponsiveContainer>
          }
          table={<DataTable head={[w.trend.month, w.trend.revenue, w.trend.cost, w.trend.shipment]} rows={trend.map((m) => [m.label, takaFull(m.revenue), takaFull(m.cost), takaFull(m.shipment)])} />}
        />
        <ChartPanel
          className="lg:col-span-2"
          title={w.buyers.title}
          sub={w.buyers.sub}
          icon={PieChart}
          empty={buyers.length === 0}
          w={w}
          chart={
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={buyers} layout="vertical" margin={{ top: 4, right: 12, left: 4, bottom: 0 }}>
                <CartesianGrid horizontal={false} stroke="var(--border)" strokeDasharray="3 3" />
                <XAxis type="number" {...axis} tickFormatter={taka} />
                <YAxis type="category" dataKey="name" {...axis} width={88} />
                <Tooltip {...tooltipStyle} formatter={(v) => takaFull(v)} />
                <Bar dataKey="value" name={w.buyers.value} fill={S1} radius={[0, 4, 4, 0]} barSize={14} isAnimationActive={false} />
              </BarChart>
            </ResponsiveContainer>
          }
          table={<DataTable head={[w.buyers.buyer, w.buyers.value]} rows={buyers.map((b) => [b.name, takaFull(b.value)])} />}
        />
        <ChartPanel
          className="lg:col-span-3"
          title={w.output.title}
          sub={w.output.sub}
          icon={ChartColumn}
          empty={deck.output.rows.length === 0}
          w={w}
          chart={
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={deck.output.rows} margin={{ top: 8, right: 12, left: 4, bottom: 0 }} barGap={2}>
                <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 3" />
                <XAxis dataKey="floor" {...axis} />
                <YAxis {...axis} width={48} />
                <Tooltip {...tooltipStyle} formatter={(v) => Number(v).toLocaleString("en-US")} />
                <Legend iconType="square" wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="plan" name={w.output.plan} fill={S1} radius={[4, 4, 0, 0]} maxBarSize={28} isAnimationActive={false} />
                <Bar dataKey="actual" name={w.output.actual} fill={S2} radius={[4, 4, 0, 0]} maxBarSize={28} isAnimationActive={false} />
              </BarChart>
            </ResponsiveContainer>
          }
          table={<DataTable head={[w.output.floor, w.output.plan, w.output.actual]} rows={deck.output.rows.map((r) => [r.floor, r.plan.toLocaleString("en-US"), r.actual.toLocaleString("en-US")])} />}
        />
        <ChartPanel
          className="lg:col-span-2"
          title={w.lines.title}
          sub={w.lines.sub}
          icon={Gauge}
          empty={deck.lines.length === 0}
          w={w}
          chart={
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={deck.lines} margin={{ top: 8, right: 12, left: 4, bottom: 0 }} barGap={2}>
                <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 3" />
                <XAxis dataKey="line" {...axis} />
                <YAxis {...axis} width={40} unit="%" />
                <Tooltip {...tooltipStyle} formatter={(v) => pct(v)} />
                <Legend iconType="square" wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="achieved" name={w.lines.achieved} fill={S1} radius={[4, 4, 0, 0]} maxBarSize={24} isAnimationActive={false} />
                <Bar dataKey="target" name={w.lines.target} fill={S2} radius={[4, 4, 0, 0]} maxBarSize={24} isAnimationActive={false} />
              </BarChart>
            </ResponsiveContainer>
          }
          table={<DataTable head={[w.lines.line, w.lines.achieved, w.lines.target]} rows={deck.lines.map((r) => [r.line, pct(r.achieved), pct(r.target)])} />}
        />
      </div>

      <section className={cn(card, "p-5")}>
        <h3 className="text-[15px] font-semibold text-foreground">{w.modules}</h3>
        <div className="mt-3 grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
          {modules.map((mod) => (
            <Link key={mod.code} href={mod.url} className="flex items-center gap-3 rounded-md px-2 py-2 text-sm font-medium text-foreground hover:bg-surface-muted">
              <span className="grid size-8 shrink-0 place-items-center rounded-md bg-brand-subtle text-brand [&_svg]:size-4">
                <MenuIcon name={mod.icon} />
              </span>
              {mod.label}
            </Link>
          ))}
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className={cn(card, "p-5")}>
          <h3 className="text-[15px] font-semibold text-foreground">{w.activity.title}</h3>
          <p className="text-xs text-foreground-muted">{w.activity.sub}</p>
          {deck.activity.length === 0 ? (
            <Empty icon={Activity} w={w} />
          ) : (
            <ul className="mt-3 divide-y divide-border">
              {deck.activity.map((a, i) => (
                <li key={`${a.at}-${i}`} className="flex items-start gap-3 py-2.5">
                  <span className="mt-0.5 grid size-7 shrink-0 place-items-center rounded-md bg-surface-muted text-foreground-muted">
                    <ListChecks className="size-3.5" aria-hidden />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm text-foreground">
                      <span className="font-medium">{a.user ?? w.activity.system}</span> {w.actions[a.action] ?? a.action} <span className="font-medium">{a.label ?? a.entity}</span>
                    </p>
                    <p className="text-xs text-foreground-muted">
                      {String(a.entity ?? "").replaceAll("_", " ").toLowerCase()} · {ago(a.at, w)}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
        <section className={cn(card, "p-5")}>
          <h3 className="text-[15px] font-semibold text-foreground">{w.alerts.title}</h3>
          <p className="text-xs text-foreground-muted">{w.alerts.sub}</p>
          {deck.alerts.length === 0 ? (
            <Empty icon={BellRing} w={w} />
          ) : (
            <ul className="mt-3 divide-y divide-border">
              {deck.alerts.map((a, i) => (
                <li key={`${a.at}-${i}`} className="flex items-start gap-3 py-2.5">
                  <span className={cn("mt-0.5 grid size-7 shrink-0 place-items-center rounded-md", a.severity === "HIGH" ? "bg-danger-subtle text-danger" : "bg-warning-subtle text-warning")}>
                    {a.kind === "APPROVAL" ? <Stamp className="size-3.5" aria-hidden /> : <AlertTriangle className="size-3.5" aria-hidden />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground">{a.kind === "APPROVAL" ? w.alerts.approval.replace(":what", String(a.title ?? "").replaceAll("_", " ").toLowerCase()) : a.title}</p>
                    <p className="text-xs text-foreground-muted">
                      {w.alerts.severity[a.severity] ?? a.severity}
                      {a.detail ? ` · ${a.detail}` : ""} · {ago(a.at, w)}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
      {deck.unconverted ? <p className="text-xs text-foreground-muted">{w.unconverted.replace(":n", deck.unconverted)}</p> : null}
    </div>
  );
}

export { CommandDeck };
