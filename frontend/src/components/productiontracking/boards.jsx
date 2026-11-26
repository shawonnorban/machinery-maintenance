"use client";

import { useState } from "react";
import { Boxes, FileBarChart, Layers, Percent, Printer, RotateCcw, Scissors, Shirt, Shuffle, Sparkles, Target, Users, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { usePt, num, pct, Panel, PeriodBar, FilterSelect, FilterSearch, LiveBar, Kpi, Notice, LineCard, Legend, CsvButton, Table, BAND } from "./common";

/*
 * Production Tracking's dashboards, order progress and report (AnnotechERP
 * 30/02, 30/03, 30/05), as Brickland lays them out.
 */

const buyerOptions = (lookups) => lookups.buyers.map((b) => ({ value: b, label: b }));

/** Sewing or Finishing Dashboard: totals, a card per line, and buyer / style / PO breakdowns. */
function LineBoard({ view, data, lookups, query }) {
  const p = usePt();
  const lines = view === "finishing" ? lookups.finishing_lines : lookups.sewing_lines;
  const t = data.totals;
  const dims = [
    ["buyer", p(`board.${view}_buyer`)],
    ["style", p(`board.${view}_style`)],
    ["po", p(`board.${view}_po`)],
  ];
  return (
    <>
      <PeriodBar query={query} extra={{ line: query.line ?? "", buyer: query.buyer ?? "", search: query.search ?? "" }}>
        {({ values, set }) => (
          <>
            <FilterSelect label={p("f.line")} value={values.line} onChange={set("line")} options={lines} all={p("all_lines")} />
            <FilterSelect label={p("f.buyer")} value={values.buyer} onChange={set("buyer")} options={buyerOptions(lookups)} all={p("all_buyers")} />
            <FilterSearch label={p("f.search_line")} value={values.search} onChange={set("search")} placeholder={p("f.search_line_hint")} />
          </>
        )}
      </PeriodBar>
      <LiveBar meta={data.meta} />
      <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi label={p("kpi.total_target")} value={num(t.target)} sub={p("kpi.lines_of", { n: t.lines_with_target, of: t.lines })} icon={Target} />
        <Kpi label={p("kpi.total_actual")} value={num(t.actual)} sub={p("kpi.achieved", { pct: pct(t.pct) })} tone="success" icon={view === "finishing" ? Sparkles : Shirt} />
        <Kpi label={p("kpi.total_reject")} value={num(t.reject)} sub={p("kpi.pcs")} tone="danger" icon={XCircle} />
        <Kpi label={p("kpi.total_rework")} value={num(t.rework)} sub={p("kpi.pcs")} tone="warning" icon={RotateCcw} />
      </div>
      <p className="mb-3 text-[13px] text-foreground-muted">{p(`board.${view}_defects_note`)}</p>
      {data.missing.length ? <Notice>{p("board.no_target_lines", { n: data.missing.length })}</Notice> : null}
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-[15px] font-bold tracking-wide text-foreground uppercase">{p(`board.all_${view}_lines`, { n: data.lines.length })}</h2>
        <Legend meta={data.meta} />
      </div>
      <div className="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        {data.lines.map((line) => (
          <LineCard key={line.id} line={line} />
        ))}
      </div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {dims.map(([k, title]) => (
          <Panel key={k} title={title} icon={Users}>
            <Breakdown rows={data[k]} dimension={k} />
          </Panel>
        ))}
      </div>
    </>
  );
}

function Breakdown({ rows, dimension }) {
  const p = usePt();
  if (!rows.length) return <p className="px-5 py-8 text-center text-sm text-foreground-muted">{p("empty.period")}</p>;
  return (
    <ul className="divide-y divide-border">
      {rows.map((r) => (
        <li key={r.key} className="px-5 py-3">
          <div className="flex items-center justify-between gap-2 text-sm">
            <span className="min-w-0 truncate font-semibold text-foreground">{dimension === "buyer" ? r.buyer || "—" : dimension === "style" ? `${r.style || "—"} · ${r.buyer || "—"}` : `${r.po || "—"} · ${r.style || "—"}`}</span>
            <span className={cn("font-semibold tabular-nums", BAND[r.band]?.text)}>{pct(r.pct)}</span>
          </div>
          <Bar value={r.pct} band={r.band} />
          <p className="mt-1 text-xs text-foreground-muted">
            {num(r.actual)} / {num(r.target)} · {p("card.reject")} {num(r.reject)} · {p("card.rework")} {num(r.rework)}
          </p>
        </li>
      ))}
    </ul>
  );
}

function Bar({ value, band }) {
  return (
    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-muted">
      <div className={cn("h-full rounded-full", BAND[band]?.dot ?? "bg-brand")} style={{ width: `${Math.max(0, Math.min(100, value ?? 0))}%` }} />
    </div>
  );
}

/** Cutting Dashboard: planned / ready / remaining / ready %, and the bundles by list, buyer, style, PO or date. */
function CuttingBoard({ data, query }) {
  const p = usePt();
  const [tab, setTab] = useState("list");
  const k = data.kpi;
  const tabs = ["list", "buyer", "style", "po", "date"];
  const groupCols = [
    { key: "key", label: p(`col.${tab}`) },
    { key: "bundles", label: p("col.bundles"), align: "right", render: (r) => num(r.bundles) },
    { key: "pcs", label: p("col.pcs"), align: "right", render: (r) => num(r.pcs) },
    { key: "ready", label: p("col.ready"), align: "right", render: (r) => num(r.ready) },
    { key: "issued", label: p("col.issued"), align: "right", render: (r) => num(r.issued) },
  ];
  const listCols = [
    { key: "bundle_no", label: p("col.barcode"), render: (r) => <span className="font-mono text-xs">{r.bundle_no}</span> },
    { key: "cut_date", label: p("col.cut_date") },
    { key: "buyer", label: p("col.buyer") },
    { key: "style", label: p("col.style") },
    { key: "po", label: p("col.po") },
    { key: "colour", label: p("col.colour") },
    { key: "size", label: p("col.size") },
    { key: "qty", label: p("col.pcs"), align: "right", render: (r) => num(r.qty) },
    { key: "status", label: p("col.status"), render: (r) => <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", r.status === "READY" ? "bg-success-subtle text-success" : "bg-info-subtle text-info")}>{p(`bundle.${r.status}`)}</span> },
  ];
  const rows = tab === "list" ? data.bundles : data[tab];
  const cols = tab === "list" ? listCols : groupCols;
  return (
    <>
      <PeriodBar query={query} extra={{ barcode: query.barcode ?? "" }}>
        {({ values, set }) => <FilterSearch label={p("f.barcode")} value={values.barcode} onChange={set("barcode")} placeholder={p("f.barcode_hint")} />}
      </PeriodBar>
      <LiveBar meta={data.meta} />
      <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi label={p("kpi.planned_bundles")} value={k.planned === null ? "N/A" : num(k.planned)} sub={p("kpi.cut_in_period", { n: num(k.cut_bundles) })} icon={Scissors} />
        <Kpi label={p("kpi.bundle_ready")} value={num(k.ready_pcs)} sub={p("kpi.waiting_sewing")} tone="success" icon={Boxes} />
        <Kpi label={p("kpi.remaining")} value={k.remaining === null ? "N/A" : num(k.remaining)} sub={p("kpi.received_sewing", { n: num(k.issued_bundles) })} tone="warning" icon={Shuffle} />
        <Kpi label={p("kpi.ready_pct")} value={pct(k.ready_pct)} sub={p("kpi.ready_over_planned")} tone="info" icon={Percent} />
      </div>
      <Notice tone="info">{p("board.cutting_note")}</Notice>
      <Panel title={p("board.cutting_bundles")} subtitle={p("board.cutting_ready_rule")} icon={Layers} action={<CsvButton rows={rows} columns={cols.map((c) => ({ key: c.key, label: c.label }))} name={`cutting-${tab}`} />}>
        <div className="flex gap-1 border-b border-border px-4">
          {tabs.map((x) => (
            <button key={x} type="button" onClick={() => setTab(x)} className={cn("-mb-px border-b-2 px-3 py-2.5 text-sm font-semibold", tab === x ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
              {p(`tabs.${x}`)}
            </button>
          ))}
        </div>
        <Table columns={cols} rows={rows} empty={p("empty.bundles")} rowKey={(r, i) => r.bundle_no ?? r.key ?? i} />
      </Panel>
    </>
  );
}

/** All Dashboard: every section's KPIs, section achievement, the sewing trend, and buyer / style / PO progress. */
function AllBoard({ data, query }) {
  const p = usePt();
  const s = data.sewing;
  const f = data.finishing;
  const c = data.cutting;
  const sections = [
    ["cutting", c.ready_pct],
    ["sewing", s.pct],
    ["finishing", f.pct],
  ];
  return (
    <>
      <PeriodBar query={query} extra={{ search: query.search ?? "" }}>
        {({ values, set }) => <FilterSearch label={p("f.line_or_style")} value={values.search} onChange={set("search")} placeholder={p("f.search_line_hint")} />}
      </PeriodBar>
      <LiveBar meta={data.meta} />
      <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <Kpi label={p("kpi.active_lines")} value={num(s.lines + f.lines)} sub={p("kpi.active_lines_sub", { s: s.lines, f: f.lines, n: s.producing + f.producing })} icon={Layers} />
        <Kpi label={p("kpi.cutting_ready")} value={num(c.ready_pcs)} sub={p("kpi.issued_to_sewing", { n: num(c.issued_bundles) })} tone="success" icon={Scissors} />
        <Kpi label={p("kpi.sewing_target")} value={num(s.target)} sub={p("kpi.lines_of", { n: s.lines_with_target, of: s.lines })} tone="info" icon={Target} />
        <Kpi label={p("kpi.sewing_actual")} value={num(s.actual)} sub={p("kpi.achieved", { pct: pct(s.pct) })} tone="success" icon={Shirt} />
        <Kpi label={p("kpi.sewing_reject")} value={num(s.reject)} sub={p("kpi.found_quality")} tone="danger" icon={XCircle} />
        <Kpi label={p("kpi.sewing_rework")} value={num(s.rework)} sub={p("kpi.found_quality")} tone="warning" icon={RotateCcw} />
      </div>
      <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi label={p("kpi.finishing_target")} value={f.target ? num(f.target) : "—"} sub={f.target ? p("kpi.lines_of", { n: f.lines_with_target, of: f.lines }) : p("card.no_target")} tone="info" icon={Target} />
        <Kpi label={p("kpi.finishing_actual")} value={num(f.actual)} sub={p("kpi.achieved", { pct: pct(f.pct) })} tone="success" icon={Sparkles} />
        <Kpi label={p("kpi.finishing_reject")} value={num(f.reject)} sub={p("kpi.pcs")} tone="danger" icon={XCircle} />
        <Panel title={p("board.section_achievement")} className="p-0">
          <div className="space-y-2 px-5 py-3">
            {sections.map(([k, v]) => (
              <div key={k} className="flex items-center gap-3 text-sm">
                <span className="w-20 text-foreground">{p(`section.${k.toUpperCase()}`)}</span>
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted">
                  <div className="h-full rounded-full bg-brand" style={{ width: `${Math.max(0, Math.min(100, v ?? 0))}%` }} />
                </div>
                <span className="w-12 text-right font-semibold tabular-nums">{pct(v)}</span>
              </div>
            ))}
            <p className="text-xs text-foreground-muted">{p("board.sections_apart")}</p>
          </div>
        </Panel>
      </div>
      {data.missing.sewing ? <Notice>{p("board.sewing_missing", { n: data.missing.sewing })}</Notice> : null}
      {data.missing.finishing ? <Notice>{p("board.finishing_missing", { n: data.missing.finishing })}</Notice> : null}
      <div className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <Panel title={p("board.trend")} subtitle={p("board.trend_hint")}>
          <Trend points={data.trend} />
        </Panel>
        <Panel title={p("board.buyer_top")} subtitle={p("board.buyer_top_hint")} className="xl:col-span-2">
          <Breakdown rows={data.buyer} dimension="buyer" />
        </Panel>
      </div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Panel title={p("board.style_progress")}>
          <Breakdown rows={data.style} dimension="style" />
        </Panel>
        <Panel title={p("board.po_progress")}>
          <Breakdown rows={data.po} dimension="po" />
        </Panel>
      </div>
    </>
  );
}

/** Cumulative output against target, hour by hour. */
function Trend({ points }) {
  const p = usePt();
  const w = 360;
  const h = 180;
  const pad = 28;
  const max = Math.max(1, ...points.map((x) => Math.max(x.actual, x.target)));
  const x = (i) => pad + (i * (w - pad * 2)) / Math.max(1, points.length - 1);
  const y = (v) => h - pad - (v / max) * (h - pad * 2);
  const path = (k) => points.map((pt, i) => `${i ? "L" : "M"}${x(i)},${y(pt[k])}`).join(" ");
  return (
    <div className="px-4 pb-4">
      <div className="mb-2 flex gap-4 text-xs text-foreground-muted">
        <span className="inline-flex items-center gap-1.5">
          <span className="size-2 rounded-full bg-info" /> {p("chart.target")}
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-2 rounded-full bg-success" /> {p("chart.actual")}
        </span>
      </div>
      <svg viewBox={`0 0 ${w} ${h}`} className="w-full" role="img" aria-label={p("board.trend")}>
        {[0, 0.5, 1].map((g) => (
          <g key={g}>
            <line x1={pad} x2={w - pad} y1={y(max * g)} y2={y(max * g)} stroke="var(--color-border)" />
            <text x={pad - 4} y={y(max * g)} textAnchor="end" dominantBaseline="middle" className="fill-foreground-muted text-[9px]">
              {Math.round(max * g)}
            </text>
          </g>
        ))}
        <path d={path("target")} fill="none" stroke="var(--color-info)" strokeWidth="2" />
        <path d={path("actual")} fill="none" stroke="var(--color-success)" strokeWidth="2" />
        {points.map((pt, i) =>
          i % 3 === 0 ? (
            <text key={pt.hour} x={x(i)} y={h - 8} textAnchor="middle" className="fill-foreground-muted text-[9px]">
              {pt.hour}
            </text>
          ) : null,
        )}
      </svg>
    </div>
  );
}

/** Buyer-wise, Style-wise or PO-wise: sewing and finishing against target, side by side. */
function ProgressBoard({ view, data, lookups, query }) {
  const p = usePt();
  const cols = [
    ...(view !== "buyer" ? [] : [{ key: "buyer", label: p("col.buyer"), render: (r) => <b>{r.buyer || "—"}</b> }]),
    ...(view === "buyer" ? [] : [{ key: "buyer", label: p("col.buyer") }]),
    ...(view === "buyer" ? [] : [{ key: "style", label: p("col.style"), render: (r) => <b>{r.style || "—"}</b> }]),
    ...(view === "po" ? [{ key: "po", label: p("col.po"), render: (r) => <b>{r.po || "—"}</b> }] : []),
    { key: "sewing_target", label: p("col.sewing_target"), align: "right", render: (r) => num(r.sewing_target) },
    { key: "sewing_actual", label: p("col.sewing_actual"), align: "right", render: (r) => num(r.sewing_actual) },
    { key: "sewing_pct", label: p("col.sewing_pct"), align: "right", render: (r) => <span className={cn("font-semibold", BAND[r.sewing_band]?.text)}>{pct(r.sewing_pct)}</span> },
    { key: "finishing_target", label: p("col.finishing_target"), align: "right", render: (r) => num(r.finishing_target) },
    { key: "finishing_actual", label: p("col.finishing_actual"), align: "right", render: (r) => num(r.finishing_actual) },
    { key: "finishing_pct", label: p("col.finishing_pct"), align: "right", render: (r) => pct(r.finishing_pct) },
    { key: "reject", label: p("col.reject"), align: "right", render: (r) => num(r.reject) },
    { key: "rework", label: p("col.rework"), align: "right", render: (r) => num(r.rework) },
  ];
  return (
    <>
      <PeriodBar query={query} extra={{ buyer: query.buyer ?? "", search: query.search ?? "" }}>
        {({ values, set }) => (
          <>
            <FilterSelect label={p("f.buyer")} value={values.buyer} onChange={set("buyer")} options={buyerOptions(lookups)} all={p("all_buyers")} />
            <FilterSearch label={p(`f.search_${view}`)} value={values.search} onChange={set("search")} placeholder={p(`f.search_${view}`)} />
          </>
        )}
      </PeriodBar>
      <LiveBar meta={data.meta} />
      <Panel title={p(`board.${view}_production`)} subtitle={p("board.progress_hint")} icon={Users} action={<CsvButton rows={data.rows} columns={cols.map((c) => ({ key: c.key, label: c.label }))} name={`${view}-wise`} />}>
        <Table columns={cols} rows={data.rows} empty={p("empty.period")} />
      </Panel>
    </>
  );
}

/** Daily Production Report: line by line, day by day, with CSV and print. */
function ReportBoard({ data, lookups, query }) {
  const p = usePt();
  const lines = [...lookups.finishing_lines, ...lookups.sewing_lines];
  const cols = [
    { key: "date", label: p("col.date") },
    { key: "section", label: p("col.section"), render: (r) => p(`section.${r.section}`) },
    { key: "code", label: p("col.line"), render: (r) => <b>{r.code}</b> },
    { key: "style", label: p("col.style") },
    { key: "target", label: p("col.target"), align: "right", render: (r) => num(r.target) },
    { key: "actual", label: p("col.production"), align: "right", render: (r) => num(r.actual) },
    { key: "balance", label: p("col.balance"), align: "right", render: (r) => num(r.balance) },
    { key: "pct", label: p("col.achievement"), align: "right", render: (r) => <span className={cn("font-semibold", BAND[r.band]?.text)}>{pct(r.pct)}</span> },
    { key: "reject", label: p("col.reject"), align: "right", render: (r) => num(r.reject) },
    { key: "rework", label: p("col.rework"), align: "right", render: (r) => num(r.rework) },
  ];
  return (
    <>
      <PeriodBar query={query} extra={{ line: query.line ?? "", buyer: query.buyer ?? "" }}>
        {({ values, set }) => (
          <>
            <FilterSelect label={p("f.line")} value={values.line} onChange={set("line")} options={lines} all={p("all_lines")} />
            <FilterSelect label={p("f.buyer")} value={values.buyer} onChange={set("buyer")} options={buyerOptions(lookups)} all={p("all_buyers")} />
          </>
        )}
      </PeriodBar>
      <Panel
        title={p("board.daily_report")}
        subtitle={`${data.meta.from} → ${data.meta.to}`}
        icon={FileBarChart}
        action={
          <div className="flex gap-2">
            <CsvButton rows={data.rows} columns={cols.map((c) => ({ key: c.key, label: c.label }))} name={`daily-production-${data.meta.from}-${data.meta.to}`} />
            <Button type="button" variant="outline" size="sm" onClick={() => window.print()}>
              <Printer /> {p("print")}
            </Button>
          </div>
        }
      >
        <Table columns={cols} rows={data.rows} empty={p("empty.period")} rowKey={(r) => `${r.date}-${r.section}-${r.id}`} />
      </Panel>
    </>
  );
}

export { LineBoard, CuttingBoard, AllBoard, ProgressBoard, ReportBoard };
