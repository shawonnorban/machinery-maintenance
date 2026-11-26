import { RecordsGrid } from "@/components/erp/records-grid";
import { ActionBoard, BandBars, FactorBoard, KpiRow, Panel, RankList, SegmentedGrid } from "@/components/merchandise/ai/ai-kit";
import { num } from "@/components/merchandise/ai/ai-format";
import { BANDS, day, grid, orderRow, taskRows, view } from "./ai-merch";

/*
 * Merchandise Desk → AI ORDER RISK & HEALTH (AnnotechERP 06/03) and AI ORDER
 * PROGRESS & TASK MONITOR (06/04): the same last run as the dashboard, read
 * factor by factor and task by task, as Brickland lays each screen out.
 */

const qty = new Intl.NumberFormat("en-US");
const FACTORS = ["shipment", "material", "production", "tna", "approval"];
// The four sub-scores Brickland prints beside each order; time & action shows only as a weighted factor.
const SUBS = ["material", "production", "approval", "shipment"];
const pct = (v) => `${num.format(v)}%`;
const fixed = (v) => Number(v).toFixed(1);
const when = (m, o) => (o.days_left === null ? "—" : o.days_left >= 0 ? m("mai.in_days_long", { n: o.days_left }) : m("mai.days_late_long", { n: -o.days_left }));
const bandSegments = (m, withLow = false) => [
  { key: "all", label: `${m("mai.seg.all")} ({n})` },
  { key: "critical", label: m("mai.band.CRITICAL"), field: "band", values: ["CRITICAL"] },
  { key: "high", label: m("mai.band.HIGH"), field: "band", values: ["HIGH"] },
  { key: "medium", label: m("mai.band.MEDIUM"), field: "band", values: ["MEDIUM"] },
  ...(withLow ? [{ key: "low", label: m("mai.band.LOW"), field: "band", values: ["LOW"] }] : []),
];
const bucket = (rows, field) =>
  [0, 20, 40, 60, 80].map((lo) => ({ key: String(lo), label: `${lo}–${lo + 20}%`, value: rows.filter((r) => r[field] >= lo && (r[field] < lo + 20 || (lo === 80 && r[field] <= 100))).length }));
const overdueOf = (s) => s.tasks.reduce((acc, t) => (t.days_late > 0 ? { ...acc, [t.order_no]: (acc[t.order_no] ?? 0) + 1 } : acc), {});
const statement = (big, tail) => (
  <div className="flex flex-col gap-1">
    <p className="text-3xl font-semibold text-foreground tabular-nums">{big}</p>
    <p className="text-[13px] text-foreground-muted">{tail}</p>
  </div>
);
const note = (key, text) => (
  <p key={key} className="rounded-lg border border-dashed border-border px-4 py-3 text-[13px] text-foreground-muted">
    {text}
  </p>
);
const scoreCols = (m) => ({
  order: { key: "order_label", label: m("mai.col.order") },
  ship: { key: "ship_label", label: m("mai.col.ship_date") },
  qty: { key: "qty", label: m("mai.col.qty"), type: "number" },
  band: { key: "band_label", label: m("mai.col.band") },
  action: { key: "action_label", label: m("mai.col.action") },
});

/** 06/03 · 1 — Order Risk Rating: the composite of every live order, by band, with the four factors beside it. */
const riskScore = view((s, m) => {
  const c = scoreCols(m);
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi2.scored"), value: s.totals.live }, { label: m("mai.kpi2.avg_risk"), value: fixed(s.totals.avg_risk) }, { label: m("mai.kpi2.avg_confidence"), value: pct(s.totals.avg_confidence) }]} />,
    <div key="c" className="grid gap-4 lg:grid-cols-2">
      <Panel title={m("mai.risk_bands")} hint={m("mai.risk_bands_hint")}>
        <BandBars risk unit={m("mai.orders_unit")} data={[...BANDS].reverse().map((b) => ({ key: b, label: m(`mai.band.${b}`), value: s.orders.filter((o) => o.band === b).length }))} />
      </Panel>
      <Panel title={m("mai.highest")} hint={m("mai.highest_hint")}>
        <RankList
          max={100}
          empty={m("mai.nothing")}
          rows={s.orders.slice(0, 15).map((o) => ({ key: o.order_no, title: o.order_no, value: o.risk, label: fixed(o.risk), sub: m("mai.health_sub", { buyer: o.buyer ?? "—", health: fixed(o.health), slip: m("mai.slip_days", { n: o.expected_delay }) }) }))}
        />
      </Panel>
    </div>,
    <SegmentedGrid
      key="g"
      segments={bandSegments(m)}
      rows={s.orders.map((o) => ({ ...orderRow(m, o), risk_v: fixed(o.risk), health_v: fixed(o.health), m_v: fixed(o.material_risk), p_v: fixed(o.production_risk), a_v: fixed(o.approval_risk), s_v: fixed(o.shipment_risk) }))}
      {...grid(m, "ai-order-risk-score", m("mai.risk_table_hint"))}
      rowKey="order_no"
      columns={[
        c.order,
        { key: "risk_v", label: m("mai.col.risk"), align: "right" },
        { key: "health_v", label: m("mai.col2.health"), align: "right" },
        { key: "probability_label", label: m("mai.col2.delay_pct"), align: "right" },
        { key: "m_v", label: m("mai.col2.material"), align: "right" },
        { key: "p_v", label: m("mai.col2.production"), align: "right" },
        { key: "a_v", label: m("mai.col2.approval"), align: "right" },
        { key: "s_v", label: m("mai.col2.shipment"), align: "right" },
        { key: "slip_label", label: m("mai.col2.exp_delay"), align: "right" },
        c.band,
        c.action,
      ]}
      emptyText={m("mai.nothing")}
    />,
  ];
});

/** 06/03 · 2 — Order Health Rating: the other side of risk, weakest first. */
const healthScore = view((s, m) => {
  const c = scoreCols(m);
  const weakest = [...s.orders].sort((a, b) => a.health - b.health);
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi.avg_health"), value: fixed(s.totals.avg_health) }, { label: m("mai.kpi2.scored"), value: s.totals.live }, { label: m("mai.kpi2.avg_risk"), value: fixed(s.totals.avg_risk) }]} />,
    <Panel key="c" title={m("mai.weakest")} hint={m("mai.weakest_hint")}>
      <RankList
        max={100}
        empty={m("mai.nothing")}
        rows={weakest.slice(0, 15).map((o) => ({ key: o.order_no, title: o.order_no, value: o.health, label: fixed(o.health), sub: `${o.buyer ?? "—"} · ${m("mai.ships", { date: day(o.ship_date) })}` }))}
      />
    </Panel>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-order-health-score", m("mai.health_table_hint"))}
      rowKey="order_no"
      rows={weakest.map((o) => ({ ...orderRow(m, o), health_v: fixed(o.health), risk_v: fixed(o.risk) }))}
      columns={[c.order, c.qty, c.ship, { key: "health_v", label: m("mai.col2.health_score"), align: "right" }, { key: "progress_label", label: m("mai.col.progress"), align: "right" }, { key: "risk_v", label: m("mai.col.risk"), align: "right" }, c.band]}
      emptyText={m("mai.nothing")}
    />,
  ];
});

/** 06/03 · 3 — Delay Likelihood Rating: how likely each order is to miss, and how sure the engine is. */
const delayProbability = view((s, m) => {
  const c = scoreCols(m);
  return [
    <KpiRow
      key="k"
      items={[
        { label: m("mai.kpi2.avg_probability"), value: pct(s.totals.avg_probability) },
        { label: m("mai.kpi2.expected_total"), value: m("mai.days_total", { n: qty.format(s.totals.expected_delay) }) },
        { label: m("mai.kpi2.avg_confidence"), value: pct(s.totals.avg_confidence) },
      ]}
    />,
    <Panel key="c" title={m("mai.prob_bands")} hint={m("mai.prob_bands_hint")}>
      <BandBars unit={m("mai.orders_unit")} data={bucket(s.orders, "delay_probability")} />
    </Panel>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-delay-probability", m("mai.prob_table_hint"))}
      rowKey="order_no"
      rows={[...s.orders].sort((a, b) => b.delay_probability - a.delay_probability).map((o) => ({ ...orderRow(m, o), confidence_label: pct(o.confidence) }))}
      columns={[c.order, c.ship, { key: "probability_label", label: m("mai.col2.probability"), align: "right" }, { key: "slip_label", label: m("mai.col2.expected"), align: "right" }, { key: "confidence_label", label: m("mai.col2.confidence"), align: "right" }, c.band]}
      emptyText={m("mai.nothing")}
    />,
  ];
});

/** 06/03 · 4–7 — one factor watched order by order: how many it drives, the worst exposed and the full list. */
const watch = (factor, slug) =>
  view((s, m) => {
    const c = scoreCols(m);
    const field = `${factor}_risk`;
    const title = m(`mai.factor.${factor}.title`);
    const worst = [...s.orders].sort((a, b) => b[field] - a[field]);
    return [
      <div key="c" className="grid gap-4 lg:grid-cols-2">
        <Panel title={title} hint={m(`mai.factor.${factor}.hint`)}>
          {statement(m("mai.watch_hot", { n: s.totals.factor_hot[factor] ?? 0, total: s.totals.live }), m("mai.watch_hot_tail"))}
        </Panel>
        <Panel title={m("mai.worst")} hint={m("mai.worst_hint", { factor: m(`mai.factor.${factor}.noun`) })}>
          <RankList
            max={100}
            empty={m("mai.nothing")}
            rows={worst.slice(0, 15).map((o) => ({ key: o.order_no, title: o.order_no, value: o[field], label: fixed(o[field]), sub: m("mai.watch_sub", { buyer: o.buyer ?? "—", risk: fixed(o.risk), date: day(o.ship_date) }) }))}
          />
        </Panel>
      </div>,
      <RecordsGrid
        key="g"
        exportName={slug}
        header={{ ...grid(m, slug, m(`mai.factor.${factor}.table_hint`)).header, title: m("mai.by_order", { factor: title }) }}
        rowKey="order_no"
        rows={worst.map((o) => ({ ...orderRow(m, o), factor_v: fixed(o[field]), risk_v: fixed(o.risk) }))}
        columns={[c.order, c.ship, { key: "factor_v", label: title, align: "right" }, { key: "risk_v", label: m("mai.col2.composite"), align: "right" }, c.band, c.action]}
        emptyText={m("mai.nothing")}
      />,
    ];
  });

/** One order as the Risk Driver card reads it: sub-scores, then each weighted factor and what it adds to the composite. */
const factorRow = (m, s, o) => ({
  ...orderRow(m, o),
  head: m("mai.card_head", { buyer: o.buyer ?? "—", style: o.style_no ?? "—", qty: qty.format(o.qty) }),
  ship: m("mai.card_ship", { date: day(o.ship_date), when: when(m, o) }),
  source_label: m(`mai.source.${o.action_source}`),
  stats: [
    { label: m("mai.stat.health"), value: fixed(o.health) },
    { label: m("mai.stat.probability"), value: pct(o.delay_probability) },
    { label: m("mai.stat.expected"), value: m("mai.slip_days", { n: o.expected_delay }) },
    { label: m("mai.stat.confidence"), value: pct(o.confidence) },
    ...SUBS.map((f) => ({ label: m(`mai.stat.${f}`), value: fixed(o[`${f}_risk`] ?? 0) })),
    { label: m("mai.stat.progress"), value: pct(o.production_progress) },
  ],
  drivers: FACTORS.map((f) => ({
    key: f,
    label: m(`mai.driver.${f}`),
    value: o[`${f}_risk`] ?? 0,
    points: `+${fixed((o[`${f}_risk`] ?? 0) * s.weights[f])}`,
    weight: `${Math.round(s.weights[f] * 100)}%`,
    text: o.factors?.[f] ?? "—",
  })),
  ...Object.fromEntries(FACTORS.map((f) => [`${f}_v`, fixed(o[`${f}_risk`] ?? 0)])),
  risk_v: fixed(o.risk),
});

/** 06/03 · 8 — Risk Driver Analysis: where the exposure sits across the book, then each score taken apart. */
const riskFactors = view((s, m) => [
  <Panel key="c" title={m("mai.drivers_title")} hint={m("mai.drivers_hint")}>
    <BandBars unit="" data={SUBS.map((f) => ({ key: f, label: m(`mai.factor.${f}.title`), value: s.totals.factor_avg[f] ?? 0 }))} />
  </Panel>,
  <section key="h">
    <h2 className="text-base font-semibold text-foreground">{m("mai.made_of")}</h2>
    <p className="mt-0.5 text-[13px] text-foreground-muted">{m("mai.made_of_hint")}</p>
  </section>,
  <FactorBoard
    key="b"
    segments={bandSegments(m)}
    rows={s.orders.map((o) => factorRow(m, s, o))}
    views={{ cards: m("mai.view.cards"), table: m("mai.view.table") }}
    labels={{ analysis: m("mai.factor_analysis"), recommended: m("mai.recommended"), risk: m("mai.risk_word") }}
    grid={{
      ...grid(m, "ai-risk-factor-analysis", m("mai.made_of_hint")),
      rowKey: "order_no",
      columns: [
        { key: "order_label", label: m("mai.col.order") },
        { key: "risk_v", label: m("mai.col.risk"), align: "right" },
        ...FACTORS.map((f) => ({ key: `${f}_v`, label: m(`mai.col2.${f}`), align: "right" })),
        { key: "band_label", label: m("mai.col.band") },
      ],
      emptyText: m("mai.nothing"),
    }}
  />,
]);

/** 06/03 · 9 — Expected Delay Forecast: the slip each order carries if nothing changes, longest first. */
const expectedDelay = view((s, m) => {
  const c = scoreCols(m);
  return [
    <KpiRow
      key="k"
      items={[
        { label: m("mai.kpi2.expected_total"), value: m("mai.days_total", { n: qty.format(s.totals.expected_delay) }) },
        { label: m("mai.kpi2.avg_probability"), value: pct(s.totals.avg_probability) },
        { label: m("mai.kpi2.scored"), value: s.totals.live },
      ]}
    />,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-expected-delay", m("mai.expected_hint"))}
      rowKey="order_no"
      rows={[...s.orders].sort((a, b) => b.expected_delay - a.expected_delay).map((o) => orderRow(m, o))}
      columns={[c.order, c.qty, c.ship, { key: "slip_label", label: m("mai.col.forecast_slip"), align: "right" }, { key: "probability_label", label: m("mai.col.delay_pct"), align: "right" }, c.band, c.action]}
      emptyText={m("mai.nothing")}
    />,
  ];
});

/** 06/03 · 10 — Recommended Action Advice: the next move per order and which half of the engine wrote it. */
const recommended = view((s, m) => [
  <Panel key="a" title={m("mai.actions_title")} hint={m("mai.actions_hint")}>
    <ActionBoard
      segments={[
        { key: "all", label: `${m("mai.seg.all")} ({n})` },
        { key: "claude", label: `${m("mai.seg_source.claude")} ({n})`, field: "action_source", values: ["CLAUDE"] },
        { key: "rule", label: `${m("mai.seg_source.rule")} ({n})`, field: "action_source", values: ["RULE"] },
      ]}
      rows={s.orders.map((o) => ({
        order_no: o.order_no,
        band: o.band,
        band_label: m(`mai.band.${o.band}`),
        buyer: o.buyer ?? "—",
        style: o.style_no ? m("mai.style_of", { style: o.style_no }) : "",
        action_source: o.action_source,
        source_label: m(`mai.source.${o.action_source}`),
        action: o.action,
        summary: o.summary,
        meta: m("mai.action_meta", { risk: fixed(o.risk), health: fixed(o.health), slip: m("mai.slip_days", { n: o.expected_delay }), date: day(o.ship_date) }),
      }))}
      emptyText={m("mai.none_in_view")}
    />
  </Panel>,
]);

/** 06/04 · 1 — Overall Order Progress: each order against the floor stages and its calendar. */
const overallProgress = view((s, m) => [
  <KpiRow
    key="k"
    items={[
      { label: m("mai.kpi2.overall"), value: pct(s.totals.completion) },
      { label: m("mai.kpi2.task_completion"), value: pct(s.totals.task_completion) },
      { label: m("mai.kpi2.forecast"), value: m("mai.days_total", { n: qty.format(s.totals.expected_delay) }) },
    ]}
  />,
  <Panel key="p" title={m("screens.ai-overall-order-progress")} hint={m("mai.progress_hint")}>
    {s.orders.length === 0 ? <p className="py-6 text-center text-[13px] text-foreground-muted">{m("mai.nothing")}</p> : null}
    <div className="grid gap-3 xl:grid-cols-2">
      {[...s.orders]
        .sort((a, b) => b.expected_delay - a.expected_delay)
        .map((o) => (
          <article key={o.order_no} className="flex flex-col gap-2.5 rounded-lg border border-border p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-foreground">
                  {o.order_no} <span className="ml-1 rounded-md bg-surface-muted px-1.5 py-0.5 text-[11px] font-medium">{m(`mai.band.${o.band}`)}</span>
                </p>
                <p className="mt-0.5 text-xs text-foreground-muted">
                  {m("mai.progress_head", { buyer: o.buyer ?? "—", style: o.style_no ?? "—", qty: qty.format(o.qty), date: day(o.ship_date), when: o.days_left === null ? "—" : o.days_left >= 0 ? m("mai.in_days", { n: o.days_left }) : m("mai.past_short", { n: -o.days_left }) })}
                </p>
              </div>
              <div className="text-right">
                <p className="text-[11px] text-foreground-muted">{m("mai.col.delay_prediction")}</p>
                <p className="text-sm font-semibold text-foreground tabular-nums">{o.expected_delay > 0 ? m("mai.plus_days", { n: o.expected_delay }) : m("mai.on_time")}</p>
              </div>
            </div>
            <div>
              <div className="flex items-baseline justify-between text-xs">
                <span className="text-foreground-muted">{m("mai.col.production")}</span>
                <span className="font-semibold text-foreground tabular-nums">{pct(o.production_progress)}</span>
              </div>
              <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                <div className="h-full rounded-full bg-brand" style={{ width: `${Math.max(1, o.production_progress)}%` }} />
              </div>
              <p className="mt-1 flex flex-wrap gap-x-3 text-[11px] text-foreground-muted">
                {["cutting", "input", "sewing", "iron", "finishing", "shipped"].map((st) => (
                  <span key={st}>
                    {m(`mai.stage.${st}`)} {pct(0)}
                  </span>
                ))}
              </p>
            </div>
            <div className="flex items-baseline justify-between text-xs">
              <span className="text-foreground-muted">{m("mai.col.tasks")}</span>
              <span className="font-semibold text-foreground tabular-nums">
                {pct(o.task_completion)} ({o.tasks_done}/{o.tasks_total})
              </span>
            </div>
            <p className="text-xs text-foreground-muted">{m("mai.next_milestone", { what: o.next_milestone ? `${o.next_milestone} · ${day(o.next_milestone_date)}` : m("mai.no_milestone") })}</p>
          </article>
        ))}
    </div>
  </Panel>,
]);

/** 06/04 · 2 — Task Completion Rate: the calendar closed against planned, beside the floor. */
const taskCompletion = view((s, m) => {
  const overdue = overdueOf(s);
  const behind = [...s.orders].sort((a, b) => a.task_completion - b.task_completion);
  return [
    <div key="c" className="grid gap-4 lg:grid-cols-2">
      <Panel title={m("mai.tc_bands")} hint={m("mai.tc_bands_hint")}>
        {statement(m("mai.tc_hot", { done: s.totals.tasks_done, total: s.totals.tasks_total }), m("mai.tc_hot_tail"))}
        <div className="mt-3">
          <BandBars unit={m("mai.orders_unit")} data={bucket(s.orders, "task_completion")} />
        </div>
      </Panel>
      <Panel title={m("mai.behind")} hint={m("mai.behind_hint")}>
        <RankList
          max={100}
          empty={m("mai.nothing")}
          rows={behind.slice(0, 15).map((o) => ({ key: o.order_no, title: o.order_no, value: o.task_completion, label: pct(o.task_completion), sub: m("mai.tc_sub", { done: o.tasks_done, total: o.tasks_total, overdue: overdue[o.order_no] ?? 0, date: day(o.ship_date) }) }))}
        />
      </Panel>
    </div>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-task-completion", m("mai.tc_table_hint"))}
      rowKey="order_no"
      rows={behind.map((o) => orderRow(m, o))}
      columns={[
        { key: "order_label", label: m("mai.col.order") },
        { key: "ship_label", label: m("mai.col.ship_date") },
        { key: "progress_label", label: m("mai.col.production") },
        { key: "task_label", label: m("mai.col.tasks") },
        { key: "milestone_label", label: m("mai.col.next_milestone") },
        { key: "slip_label", label: m("mai.col.delay_prediction") },
      ]}
      emptyText={m("mai.nothing")}
    />,
  ];
});

const deadlineRows = (m, s, filter) => {
  const buyers = Object.fromEntries(s.orders.map((o) => [o.order_no, o.buyer]));
  return s.deadlines.filter(filter).map((d, i) => ({
    ...d,
    id: `${d.kind}-${d.order_no}-${i}`,
    date_label: `${day(d.date)} · ${m("mai.in_days", { n: d.days })}`,
    kind_label: m(`mai.kind.${d.kind}`),
    order_label: `${d.order_no} · ${buyers[d.order_no] ?? "—"}`,
    risk_label: d.risk === null ? "—" : `${fixed(d.risk)} · ${m(`mai.band.${d.band}`)}`,
  }));
};
const deadlineCols = (m) => [
  { key: "date_label", label: m("mai.col.date") },
  { key: "what", label: m("mai.col.what") },
  { key: "kind_label", label: m("mai.col2.kind") },
  { key: "order_label", label: m("mai.col.order") },
  { key: "risk_label", label: m("mai.col.order_risk") },
];

/** 06/04 · 3 — Milestone Tracker: the calendar's opening and closing events, six weeks out. */
const milestones = view((s, m) => [
  <RecordsGrid
    key="g"
    exportName="ai-milestone-tracking"
    header={{ ...grid(m, "ai-milestone-tracking", m("mai.milestones_hint")).header, title: m("mai.milestones_title") }}
    rowKey="id"
    rows={deadlineRows(m, s, (d) => d.kind === "MILESTONE")}
    columns={deadlineCols(m)}
    emptyText={m("mai.nothing_milestones")}
  />,
  note("n", m("mai.milestones_note")),
]);

/** 06/04 · 4 — Department-Wise Progress: closed, open and past plan, by the section that owns the task. */
const departments = view((s, m) => {
  const depts = [...s.departments].sort((a, b) => b.overdue - a.overdue || b.tasks - a.tasks);
  const top = depts.slice(0, 12);
  const max = Math.max(1, ...top.map((d) => d.tasks));
  const legend = [
    ["done", m("mai.col2.done"), "var(--brand)"],
    ["open", m("mai.col.pending"), "var(--border)"],
    ["overdue", m("mai.col.overdue"), "var(--risk-high)"],
  ];
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi2.task_completion"), value: pct(s.totals.task_completion) }, { label: m("mai.kpi.pending"), value: s.totals.pending }, { label: m("mai.kpi2.overdue"), value: s.totals.overdue }]} />,
    <Panel key="c" title={m("mai.dept_chart")} hint={m("mai.dept_chart_hint")}>
      {top.length === 0 ? <p className="py-6 text-center text-[13px] text-foreground-muted">{m("mai.no_departments")}</p> : null}
      <div className="mb-3 flex flex-wrap gap-4 text-xs text-foreground-muted">
        {legend.map(([k, label, color]) => (
          <span key={k} className="inline-flex items-center gap-1.5">
            <span className="size-2.5 rounded-sm" style={{ background: color }} aria-hidden="true" /> {label}
          </span>
        ))}
      </div>
      <ul className="flex flex-col gap-2.5">
        {top.map((d) => (
          <li key={d.name} title={`${d.name} · ${m("mai.col2.done")} ${d.done} · ${m("mai.col.pending")} ${d.pending - d.overdue} · ${m("mai.col.overdue")} ${d.overdue}`}>
            <div className="flex items-baseline justify-between gap-2 text-[13px]">
              <span className="font-medium text-foreground">{d.name}</span>
              <span className="text-xs text-foreground-muted tabular-nums">
                {d.done} · {d.pending - d.overdue} · {d.overdue}
              </span>
            </div>
            <div className="mt-1 flex h-2 gap-0.5 overflow-hidden" style={{ width: `${(d.tasks / max) * 100}%` }}>
              {[
                [d.done, "var(--brand)"],
                [d.pending - d.overdue, "var(--border)"],
                [d.overdue, "var(--risk-high)"],
              ]
                .filter(([v]) => v > 0)
                .map(([v, color], i) => (
                  <span key={i} className="h-full first:rounded-l-full last:rounded-r-full" style={{ flex: v, background: color }} />
                ))}
            </div>
          </li>
        ))}
      </ul>
    </Panel>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-department-wise-progress", m("mai.dept_table_hint"))}
      rowKey="name"
      rows={depts.map((d) => ({ ...d, completion_label: pct(d.completion) }))}
      columns={[
        { key: "name", label: m("mai.col2.department") },
        { key: "tasks", label: m("mai.col2.tasks"), type: "number" },
        { key: "done", label: m("mai.col2.done"), type: "number" },
        { key: "pending", label: m("mai.col.pending"), type: "number" },
        { key: "overdue", label: m("mai.col.overdue"), type: "number" },
        { key: "completion_label", label: m("mai.col.completion"), align: "right" },
      ]}
      emptyText={m("mai.no_departments")}
    />,
  ];
});

const taskKpis = (s, m) => [
  { label: m("mai.kpi.pending"), value: s.totals.pending },
  { label: m("mai.kpi2.overdue"), value: s.totals.overdue },
  { label: m("mai.kpi2.due_week"), value: s.totals.due_week },
];
const taskRow = (m, t) => ({
  ...t,
  label_line: `${t.task} — ${t.order_no}`,
  slip_label: t.slip_days > 0 ? m("mai.slip_days", { n: t.slip_days }) : "—",
  slip_risk_label: pct(t.slip_risk),
  priority_label: fixed(t.priority),
  late_label: t.days_late > 0 ? m("mai.late_short", { n: t.days_late }) : m("mai.due_short", { n: -t.days_late }),
});
const taskCols = (m) => ({
  rank: { key: "rank", label: "#", type: "number" },
  task: { key: "task_label", label: m("mai.col.task") },
  order: { key: "order_no", label: m("mai.col.order"), type: "mono" },
  plan: { key: "plan_label", label: m("mai.col.plan_date") },
  slip: { key: "slip_label", label: m("mai.col2.delay_prediction"), align: "right" },
  slipRisk: { key: "slip_risk_label", label: m("mai.col2.slip_risk"), align: "right" },
  priority: { key: "priority_label", label: m("mai.col2.priority"), align: "right" },
  advice: { key: "recommendation", label: m("mai.col.task_advice") },
});

/** 06/04 · 5 — Pending Task List: everything still open, earliest plan date first. */
const pendingTasks = view((s, m) => {
  const c = taskCols(m);
  return [
    <KpiRow key="k" items={taskKpis(s, m)} />,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-pending-tasks", m("mai.pending_tasks_hint"))}
      rowKey="id"
      rows={taskRows(m, s).sort((a, b) => String(a.plan_date).localeCompare(String(b.plan_date)))}
      columns={[c.task, c.order, c.plan, { key: "status_label", label: m("mai.col.status") }, { key: "owner_label", label: m("mai.col.owner") }, c.rank, c.advice]}
      emptyText={m("mai.nothing_pending")}
    />,
  ];
});

/** 06/04 · 6 — Overdue Task List: past plan with no actual date, with the slip each forecasts. */
const overdueTasks = view((s, m) => {
  const c = taskCols(m);
  const late = taskRows(m, s)
    .filter((t) => t.days_late > 0)
    .map((t) => taskRow(m, t));
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi2.overdue"), value: s.totals.overdue }, { label: m("mai.kpi.pending"), value: s.totals.pending }, { label: m("mai.kpi2.forecast"), value: m("mai.days_total", { n: qty.format(s.totals.expected_delay) }) }]} />,
    <Panel key="c" title={m("mai.overdue_title")} hint={m("mai.overdue_title_hint")}>
      <RankList
        max={Math.max(0, ...late.map((t) => t.days_late))}
        empty={m("mai.nothing_overdue")}
        rows={[...late]
          .sort((a, b) => b.days_late - a.days_late)
          .slice(0, 15)
          .map((t) => ({ key: t.id, title: t.label_line, value: t.days_late, label: m("mai.late_short", { n: t.days_late }), sub: `${t.department ?? m("mai.unassigned")} · ${t.owner_label}` }))}
      />
    </Panel>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-overdue-tasks", m("mai.overdue_hint"))}
      rowKey="id"
      rows={late}
      columns={[c.rank, c.task, c.order, c.plan, c.slip, c.slipRisk, c.priority, c.advice]}
      emptyText={m("mai.nothing_overdue")}
    />,
  ];
});

/** 06/04 · 7 — Deadline Monitor: milestones and shipments in the next three weeks, in one list on purpose. */
const deadlineMonitor = view((s, m) => [
  <SegmentedGrid
    key="g"
    segments={[
      { key: "all", label: `${m("mai.seg.all")} ({n})` },
      { key: "milestones", label: m("mai.seg.milestones"), field: "kind", values: ["MILESTONE"] },
      { key: "shipments", label: m("mai.seg.shipments"), field: "kind", values: ["SHIPMENT"] },
    ]}
    rows={deadlineRows(m, s, (d) => d.days <= 21)}
    {...grid(m, "ai-deadline-monitoring", m("mai.deadline_hint"))}
    rowKey="id"
    columns={deadlineCols(m)}
    emptyText={m("mai.nothing_upcoming")}
  />,
  note("n", m("mai.deadline_note")),
]);

/** 06/04 · 8 — Delay Prediction: the slip each open task is forecast to cause, largest first. */
const delayPrediction = view((s, m) => {
  const c = taskCols(m);
  const rows = taskRows(m, s)
    .map((t) => taskRow(m, t))
    .sort((a, b) => b.slip_days - a.slip_days || b.slip_risk - a.slip_risk);
  const slipping = rows.filter((t) => t.slip_days > 0);
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi2.forecast"), value: m("mai.days_total", { n: qty.format(s.totals.expected_delay) }) }, { label: m("mai.kpi2.overdue"), value: s.totals.overdue }, { label: m("mai.kpi.pending"), value: s.totals.pending }]} />,
    <Panel key="c" title={m("mai.slip_chart")} hint={m("mai.slip_chart_hint")}>
      <RankList
        max={slipping[0]?.slip_days ?? 0}
        empty={m("mai.nothing_overdue")}
        rows={slipping.slice(0, 15).map((t) => ({ key: t.id, title: t.label_line, value: t.slip_days, label: t.slip_label, sub: `${t.department ?? m("mai.unassigned")} · ${t.owner_label} · ${t.slip_risk_label}` }))}
      />
    </Panel>,
    <RecordsGrid key="g" {...grid(m, "ai-delay-prediction", m("mai.slip_table_hint"))} rowKey="id" rows={rows} columns={[c.task, c.order, c.plan, c.slip, c.slipRisk, c.advice]} emptyText={m("mai.nothing_pending")} />,
  ];
});

/** 06/04 · 9 — Task Priority Advice: every open task ranked against every other one. */
const taskPriority = view((s, m) => {
  const c = taskCols(m);
  const rows = taskRows(m, s).map((t) => taskRow(m, t));
  return [
    <KpiRow key="k" items={taskKpis(s, m)} />,
    <Panel key="c" title={m("mai.priority_title")} hint={m("mai.priority_title_hint")}>
      <RankList
        max={100}
        empty={m("mai.nothing_pending")}
        rows={rows.slice(0, 15).map((t) => ({ key: t.id, title: t.label_line, value: t.priority, label: t.priority_label, sub: m("mai.priority_sub", { rank: t.rank, owner: t.owner_label, late: t.late_label }) }))}
      />
    </Panel>,
    <RecordsGrid key="g" {...grid(m, "ai-task-priority-recommendation", m("mai.priority_hint"))} rowKey="id" rows={rows} columns={[c.rank, c.task, c.order, c.plan, c.slip, c.slipRisk, c.priority, c.advice]} emptyText={m("mai.nothing_pending")} />,
    note("n", m("mai.priority_note")),
  ];
});

const AI_MONITOR_SCREENS = {
  "ai-order-risk-score": riskScore,
  "ai-order-health-score": healthScore,
  "ai-delay-probability": delayProbability,
  "ai-material-risk": watch("material", "ai-material-risk"),
  "ai-production-risk": watch("production", "ai-production-risk"),
  "ai-approval-risk": watch("approval", "ai-approval-risk"),
  "ai-shipment-risk": watch("shipment", "ai-shipment-risk"),
  "ai-risk-factor-analysis": riskFactors,
  "ai-expected-delay": expectedDelay,
  "ai-recommended-action": recommended,
  "ai-overall-order-progress": overallProgress,
  "ai-task-completion": taskCompletion,
  "ai-milestone-tracking": milestones,
  "ai-department-wise-progress": departments,
  "ai-pending-tasks": pendingTasks,
  "ai-overdue-tasks": overdueTasks,
  "ai-deadline-monitoring": deadlineMonitor,
  "ai-delay-prediction": delayPrediction,
  "ai-task-priority-recommendation": taskPriority,
};
const AI_MONITOR_MANAGE = Object.fromEntries(Object.keys(AI_MONITOR_SCREENS).map((slug) => [slug, "merchandise.order.manage"]));

export { AI_MONITOR_MANAGE, AI_MONITOR_SCREENS };
