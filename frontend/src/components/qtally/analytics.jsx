"use client";

import { useState } from "react";
import { useLocale } from "next-intl";
import { BarChart3, ShieldAlert, TrendingDown, Users } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { SECTIONS, useQt, useFilters, num, pct, day, dhuTone, achievementTone, Panel, Table, Field, Select, Kpi, FilterCard } from "./common";

/*
 * Quality Tally → Reports → Quality Analytics (AnnotechERP 36, phase B): a
 * range of days for one section — each day's pass against target and its DHU,
 * each line, the costliest defects and each QC. One axis per chart (pieces
 * and percentages never share one); every chart has its table.
 */

const PRESETS = [7, 14, 30, 90];

function isoMinus(to, days) {
  const d = new Date(`${to}T00:00:00`);
  d.setDate(d.getDate() - days + 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function QtAnalytics({ data, query }) {
  const p = useQt();
  const bn = useLocale() === "bn";
  const { apply, pending } = useFilters(query);
  const [from, setFrom] = useState(data.from);
  const [to, setTo] = useState(data.to);
  const [section, setSection] = useState(data.section);
  const t = data.totals;

  return (
    <>
      <FilterCard onApply={() => apply({ from, to, section })} pending={pending} applyLabel={p("apply")}>
        <Field label={p("f.section")}>
          <Select value={section} onChange={setSection} options={SECTIONS.map((s) => ({ value: s, label: p(`section.${s}`) }))} />
        </Field>
        <Field label={p("f.from")}>
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("f.to")}>
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
        </Field>
        <div>
          <p className="mb-1.5 text-[13px] font-semibold text-foreground">{p("an.quick")}</p>
          <div className="flex rounded-md border border-border-strong p-0.5">
            {PRESETS.map((n) => (
              <button
                key={n}
                type="button"
                onClick={() => {
                  setFrom(isoMinus(to, n));
                  apply({ from: isoMinus(to, n), to, section });
                }}
                className="rounded px-2.5 py-1.5 text-[13px] font-semibold text-foreground-muted hover:text-foreground"
              >
                {p("an.days", { n })}
              </button>
            ))}
          </div>
        </div>
      </FilterCard>

      <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        <Kpi label={p("kpi.checked")} value={num(t.checked)} tone="info" sub={p("an.days_worked", { n: t.days_worked })} />
        <Kpi label={p("kpi.pass")} value={num(t.pass)} tone="success" />
        <Kpi label={p("kpi.target")} value={num(t.target)} tone="muted" />
        <Kpi label={p("kpi.achievement")} value={pct(t.achievement)} valueClass={achievementTone(t.achievement)} />
        <Kpi label={p("kpi.dhu")} value={pct(t.dhu)} tone="warning" valueClass={dhuTone(t.dhu)} />
        <Kpi label={p("kpi.rft_pct")} value={pct(t.rft_pct)} tone="success" />
        <Kpi label={p("kpi.reject")} value={num(t.reject)} tone="danger" />
      </div>

      <div className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Panel title={p("an.pass_vs_target")} subtitle={p("an.pass_vs_target_hint")} icon={BarChart3}>
          <PassChart days={data.days} p={p} />
        </Panel>
        <Panel title={p("an.dhu_trend")} subtitle={p("an.dhu_trend_hint")} icon={TrendingDown}>
          <DhuChart days={data.days} p={p} />
        </Panel>
      </div>

      <Panel title={p("an.by_day")} icon={BarChart3} className="mb-4">
        <Table
          dense
          rowKey={(r) => r.date}
          rows={[...data.days].reverse().filter((r) => r.checked > 0 || r.target)}
          empty={p("empty.no_taps")}
          columns={[
            { key: "date", label: p("f.date"), render: (r) => day(r.date) },
            { key: "target", label: p("col.target"), align: "right", render: (r) => num(r.target) },
            { key: "checked", label: p("col.checked"), align: "right", render: (r) => num(r.checked) },
            { key: "pass", label: p("col.pass"), align: "right", render: (r) => <b>{num(r.pass)}</b> },
            { key: "achievement", label: p("col.achievement"), align: "right", render: (r) => <span className={achievementTone(r.achievement)}>{pct(r.achievement)}</span> },
            { key: "dhu", label: p("col.dhu"), align: "right", render: (r) => <span className={dhuTone(r.dhu)}>{pct(r.dhu)}</span> },
            { key: "rft_pct", label: p("col.rft_pct"), align: "right", render: (r) => pct(r.rft_pct) },
            { key: "reject", label: p("action.REJECT"), align: "right", render: (r) => num(r.reject) },
          ]}
        />
      </Panel>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <Panel title={p("an.by_line")} icon={BarChart3} className="xl:col-span-2">
          <Table
            dense
            rowKey={(r) => r.line_id}
            rows={data.lines}
            empty={p("empty.no_taps")}
            columns={[
              { key: "line", label: p("col.line"), render: (r) => <b>{r.line}</b> },
              { key: "target", label: p("col.target"), align: "right", render: (r) => num(r.target) },
              { key: "checked", label: p("col.checked"), align: "right", render: (r) => num(r.checked) },
              { key: "pass", label: p("col.pass"), align: "right", render: (r) => num(r.pass) },
              { key: "achievement", label: p("col.achievement"), align: "right", render: (r) => <span className={achievementTone(r.achievement)}>{pct(r.achievement)}</span> },
              { key: "dhu", label: p("col.dhu"), align: "right", render: (r) => <span className={dhuTone(r.dhu)}>{pct(r.dhu)}</span> },
              { key: "rft_pct", label: p("col.rft_pct"), align: "right", render: (r) => pct(r.rft_pct) },
            ]}
          />
        </Panel>
        <Panel title={p("dash.top_defects")} icon={ShieldAlert}>
          {data.top_defects.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-foreground-muted">{p("empty.no_defects")}</p>
          ) : (
            <ul className="divide-y divide-border">
              {data.top_defects.map((d, i) => (
                <li key={d.code} className="flex items-center gap-3 px-5 py-2.5 text-sm">
                  <span className="w-5 text-right text-xs font-bold text-foreground-muted">{i + 1}</span>
                  <span className="min-w-0 flex-1 truncate">
                    <b>{d.code}</b> — {bn && d.name_bn ? d.name_bn : d.name}
                  </span>
                  <span className="hidden h-2 w-24 overflow-hidden rounded-full bg-surface-muted sm:block">
                    <span className="block h-full rounded-full bg-brand" style={{ width: `${d.share ?? 0}%` }} />
                  </span>
                  <span className="w-20 text-right tabular-nums">
                    <b>{num(d.count)}</b> <span className="text-xs text-foreground-muted">{d.share}%</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      </div>

      <Panel title={p("an.by_qc")} icon={Users} className="mt-4">
        <Table
          dense
          rowKey={(r) => r.name}
          rows={data.inspectors}
          empty={p("empty.no_taps")}
          columns={[
            { key: "name", label: p("col.qc"), render: (r) => <b>{r.name}</b> },
            { key: "checked", label: p("col.checked"), align: "right", render: (r) => num(r.checked) },
            { key: "defect", label: p("action.DEFECT"), align: "right", render: (r) => num(r.defect) },
            { key: "reject", label: p("action.REJECT"), align: "right", render: (r) => num(r.reject) },
            { key: "dhu", label: p("col.dhu"), align: "right", render: (r) => <span className={dhuTone(r.dhu)}>{pct(r.dhu)}</span> },
          ]}
        />
      </Panel>
    </>
  );
}

const W = 640;
const H = 220;
const PAD = { l: 44, r: 12, t: 12, b: 28 };

/** Rounded-up axis maximum with ~4 ticks. */
function niceMax(v) {
  if (!v) return 1;
  const pow = 10 ** Math.floor(Math.log10(v));
  const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((s) => v / s <= 4) ?? 10 * pow;
  return Math.ceil(v / step) * step;
}

function Axes({ max, ticks = 4, format = (v) => v, n, labels }) {
  const ih = H - PAD.t - PAD.b;
  const iw = W - PAD.l - PAD.r;
  const every = Math.max(1, Math.ceil(n / 8));
  return (
    <g className="text-foreground-muted">
      {Array.from({ length: ticks + 1 }, (_, i) => {
        const v = (max / ticks) * i;
        const y = PAD.t + ih - (v / max) * ih;
        return (
          <g key={i}>
            <line x1={PAD.l} x2={W - PAD.r} y1={y} y2={y} stroke="currentColor" strokeOpacity={i === 0 ? 0.35 : 0.12} />
            <text x={PAD.l - 6} y={y + 3} textAnchor="end" fontSize="10" fill="currentColor">
              {format(v)}
            </text>
          </g>
        );
      })}
      {labels.map((l, i) =>
        (n - 1 - i) % every === 0 ? (
          <text key={i} x={PAD.l + (iw / n) * (i + 0.5)} y={H - 8} textAnchor="middle" fontSize="10" fill="currentColor">
            {l}
          </text>
        ) : null,
      )}
    </g>
  );
}

const short = (iso) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}`;

function PassChart({ days, p }) {
  const [hover, setHover] = useState(null);
  const max = niceMax(Math.max(...days.map((d) => Math.max(d.pass, d.target ?? 0)), 0));
  const ih = H - PAD.t - PAD.b;
  const iw = W - PAD.l - PAD.r;
  const bw = iw / days.length;
  const y = (v) => PAD.t + ih - (v / max) * ih;
  const targetPts = days.map((d, i) => (d.target ? `${PAD.l + bw * (i + 0.5)},${y(d.target)}` : null));
  const h = hover !== null ? days[hover] : null;

  return (
    <div className="relative px-3 pt-3 pb-1">
      <div className="mb-1 flex gap-4 px-2 text-xs text-foreground-muted">
        <span className="flex items-center gap-1.5"><span className="inline-block size-2.5 rounded-sm bg-brand" /> {p("col.pass")}</span>
        <span className="flex items-center gap-1.5"><span className="inline-block h-0.5 w-4 border-t-2 border-dashed border-foreground-muted" /> {p("col.target")}</span>
      </div>
      <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label={p("an.pass_vs_target")} onMouseLeave={() => setHover(null)}>
        <Axes max={max} n={days.length} labels={days.map((d) => short(d.date))} format={(v) => num(Math.round(v))} />
        {days.map((d, i) => {
          const x = PAD.l + bw * i;
          const top = y(d.pass);
          return (
            <g key={d.date} onMouseEnter={() => setHover(i)}>
              <rect x={x} y={PAD.t} width={bw} height={ih} fill="transparent" />
              {d.pass > 0 ? <rect x={x + Math.max(2, bw * 0.18)} y={top} width={Math.max(2, bw * 0.64)} height={PAD.t + ih - top} rx={Math.min(4, bw * 0.2)} className={cn("fill-brand", hover === i ? "opacity-100" : "opacity-85")} /> : null}
            </g>
          );
        })}
        {/* The target as a dashed step line: drawn only where a day had one. */}
        {targetPts.map((pt, i) => (pt && targetPts[i + 1] ? <line key={i} x1={pt.split(",")[0]} y1={pt.split(",")[1]} x2={targetPts[i + 1].split(",")[0]} y2={targetPts[i + 1].split(",")[1]} className="stroke-foreground-muted" strokeWidth="2" strokeDasharray="5 4" /> : null))}
        {targetPts.map((pt, i) => (pt ? <circle key={`t${i}`} cx={pt.split(",")[0]} cy={pt.split(",")[1]} r="3.5" className="fill-surface stroke-foreground-muted" strokeWidth="2" /> : null))}
      </svg>
      {h ? <Tip x={((PAD.l + bw * (hover + 0.5)) / W) * 100} rows={[[day(h.date), ""], [p("col.pass"), num(h.pass)], [p("col.target"), num(h.target)], [p("col.achievement"), pct(h.achievement)]]} /> : null}
    </div>
  );
}

function DhuChart({ days, p }) {
  const [hover, setHover] = useState(null);
  const max = niceMax(Math.max(...days.map((d) => d.dhu ?? 0), 0));
  const ih = H - PAD.t - PAD.b;
  const iw = W - PAD.l - PAD.r;
  const bw = iw / days.length;
  const y = (v) => PAD.t + ih - (v / max) * ih;
  const pts = days.map((d, i) => (d.dhu === null ? null : [PAD.l + bw * (i + 0.5), y(d.dhu)]));
  // A gap where nothing was checked (null is not 0).
  const segments = [];
  let cur = [];
  for (const pt of pts) {
    if (pt) cur.push(pt);
    else if (cur.length) {
      segments.push(cur);
      cur = [];
    }
  }
  if (cur.length) segments.push(cur);
  const h = hover !== null ? days[hover] : null;

  return (
    <div className="relative px-3 pt-3 pb-1">
      <div className="mb-1 h-4" />
      <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label={p("an.dhu_trend")} onMouseLeave={() => setHover(null)}>
        <Axes max={max} n={days.length} labels={days.map((d) => short(d.date))} format={(v) => `${Math.round(v * 10) / 10}%`} />
        {hover !== null ? <line x1={PAD.l + bw * (hover + 0.5)} x2={PAD.l + bw * (hover + 0.5)} y1={PAD.t} y2={PAD.t + ih} className="stroke-foreground-muted" strokeOpacity="0.4" /> : null}
        {segments.map((seg, i) => (
          <polyline key={i} points={seg.map((pt) => pt.join(",")).join(" ")} fill="none" className="stroke-brand" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
        ))}
        {pts.map((pt, i) => (pt ? <circle key={i} cx={pt[0]} cy={pt[1]} r={hover === i ? 5 : 4} className="fill-brand stroke-surface" strokeWidth="2" /> : null))}
        {days.map((d, i) => (
          <rect key={d.date} x={PAD.l + bw * i} y={PAD.t} width={bw} height={ih} fill="transparent" onMouseEnter={() => setHover(i)} />
        ))}
      </svg>
      {h ? <Tip x={((PAD.l + bw * (hover + 0.5)) / W) * 100} rows={[[day(h.date), ""], [p("col.dhu"), pct(h.dhu)], [p("col.rft_pct"), pct(h.rft_pct)], [p("col.checked"), num(h.checked)]]} /> : null}
    </div>
  );
}

function Tip({ x, rows }) {
  return (
    <div className="pointer-events-none absolute top-8 z-10 -translate-x-1/2 rounded-md border border-border bg-surface px-3 py-2 text-xs shadow-md" style={{ left: `${Math.min(88, Math.max(12, x))}%` }}>
      {rows.map(([k, v], i) => (
        <div key={k} className={cn("flex justify-between gap-4", i === 0 && "mb-1 font-semibold text-foreground")}>
          <span className={i === 0 ? "" : "text-foreground-muted"}>{k}</span>
          <span className="font-semibold tabular-nums text-foreground">{v}</span>
        </div>
      ))}
    </div>
  );
}

export { QtAnalytics };
