import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { AiRunBar, AlertsBoard, BandBars, KpiRow, Panel, RankList, SegmentedGrid } from "@/components/merchandise/ai/ai-kit";
import { num } from "@/components/merchandise/ai/ai-format";
import * as actions from "./actions";

/*
 * Merchandise Desk → AI MERCHANDISING DASHBOARD (AnnotechERP 06/02), as
 * Brickland lays it out: every screen opens on the last run (when, what it
 * read, which engine wrote it) with Run analysis, then its headline figures
 * and its own reading of the same snapshot — the portfolio, completion,
 * orders at risk, delays, pending activities, deadlines, buyers,
 * merchandisers and the alerts. Filters switch in place.
 */

const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
const qty = new Intl.NumberFormat("en-US");
const money = new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 });
const BANDS = ["CRITICAL", "HIGH", "MEDIUM", "LOW"];

/** The snapshot and the opening line every screen shares. */
async function aiBase(m, canManage) {
  const snap = await apiFetch("/merchandise/ai/snapshot");
  const bar = <AiRunBar key="run" run={snap.run} claude={snap.claude} canRun={canManage} onRun={actions.runMerchAi} />;
  const empty = snap.run ? null : (
    <div key="empty" className="rounded-xl border border-border bg-surface px-5 py-12 text-center">
      <p className="text-sm font-semibold text-foreground">{m("mai.empty_title")}</p>
      <p className="mt-1 text-xs text-foreground-muted">{m("mai.empty_hint")}</p>
    </div>
  );
  return { snap, bar, empty };
}

const shipLabel = (m, o) => (o.days_left === null ? day(o.ship_date) : `${day(o.ship_date)} · ${o.days_left >= 0 ? m("mai.in_days", { n: o.days_left }) : m("mai.days_late", { n: -o.days_left })}`);
const orderRow = (m, o) => ({
  ...o,
  order_label: `${o.order_no} · ${o.buyer ?? "—"} · ${o.style_no ?? "—"}`,
  ship_label: shipLabel(m, o),
  risk_label: `${num.format(o.risk)} · ${m(`mai.band.${o.band}`)}`,
  band_label: m(`mai.band.${o.band}`),
  progress_label: `${num.format(o.production_progress)}%`,
  task_label: `${num.format(o.task_completion)}% · ${m("mai.closed_of", { done: o.tasks_done, total: o.tasks_total })}`,
  milestone_label: o.next_milestone ? `${o.next_milestone} · ${day(o.next_milestone_date)}` : m("mai.no_milestone"),
  slip_label: o.expected_delay > 0 ? m("mai.slip_days", { n: o.expected_delay }) : m("mai.on_time"),
  probability_label: `${num.format(o.delay_probability)}%`,
  action_label: `${o.action} — ${m(`mai.source.${o.action_source}`)}`,
});
const grid = (m, slug, caption) => ({ exportName: slug, header: { title: m(`screens.${slug}`), subtitle: caption, searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") } });
const bandSegments = (m) => [
  { key: "all", label: m("mai.seg.all") },
  { key: "critical", label: m("mai.band.CRITICAL"), field: "band", values: ["CRITICAL"] },
  { key: "high", label: m("mai.band.HIGH"), field: "band", values: ["HIGH"] },
];
const page = (bar, rest) => (
  <div className="flex flex-col gap-4">
    {bar}
    {rest}
  </div>
);
const view = (render) => async ({ m, canManage }) => {
  const base = await aiBase(m, canManage);
  return { tabs: [], body: async () => page(base.bar, base.empty ?? render(base.snap, m, canManage)) };
};

/** 1 — Order Portfolio Overview: the headline count, the risk mix and the ten orders carrying the most risk. */
const overview = view((s, m) => {
  const t = s.totals;
  return [
    <KpiRow
      key="k"
      items={[
        { label: m("mai.kpi.live"), value: t.live },
        { label: m("mai.kpi.completion"), value: `${num.format(t.completion)}%` },
        { label: m("mai.kpi.at_risk"), value: t.at_risk },
        { label: m("mai.kpi.delayed"), value: t.delayed },
        { label: m("mai.kpi.pending"), value: t.pending },
        { label: m("mai.kpi.value"), value: `${money.format(t.value)} USD` },
        { label: m("mai.kpi.avg_health"), value: num.format(t.avg_health) },
        { label: m("mai.kpi.open_alerts"), value: t.open_alerts },
      ]}
    />,
    <div key="c" className="grid gap-4 lg:grid-cols-2">
      <Panel title={m("mai.risk_mix")} hint={m("mai.risk_mix_hint")}>
        <BandBars risk unit={m("mai.orders_unit")} data={[...BANDS].reverse().map((b) => ({ key: b, label: m(`mai.band.${b}`), value: s.orders.filter((o) => o.band === b).length }))} />
      </Panel>
      <Panel title={m("mai.exposure")} hint={m("mai.exposure_hint")}>
        <RankList
          max={100}
          empty={m("mai.nothing")}
          rows={s.orders.slice(0, 10).map((o) => ({ key: o.order_no, title: o.order_no, value: o.risk, label: num.format(o.risk), sub: `${o.buyer ?? "—"} · ${m("mai.ships", { date: day(o.ship_date) })}` }))}
        />
      </Panel>
    </div>,
  ];
});

/** 2 — Order Completion Rate: completion bands and every order's floor progress beside its calendar. */
const completion = view((s, m) => {
  const bands = [0, 20, 40, 60, 80].map((lo) => ({ key: String(lo), label: `${lo}–${lo + 20}%`, value: s.orders.filter((o) => o.production_progress >= lo && (o.production_progress < lo + 20 || (lo === 80 && o.production_progress <= 100))).length }));
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi.live"), value: s.totals.live }, { label: m("mai.kpi.completion"), value: `${num.format(s.totals.completion)}%` }, { label: m("mai.kpi.pending"), value: s.totals.pending }]} />,
    <Panel key="c" title={m("mai.completion_bands")} hint={m("mai.completion_bands_hint")}>
      <BandBars unit={m("mai.orders_unit")} data={bands} />
    </Panel>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-order-completion", m("mai.completion_table_hint"))}
      rowKey="order_no"
      rows={[...s.orders].sort((a, b) => b.expected_delay - a.expected_delay).map((o) => orderRow(m, o))}
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

/** 3 — Critical & At-Risk Orders: every order scoring high or critical, worst first, with the next move. */
const atRisk = view((s, m) => [
  <KpiRow key="k" items={[{ label: m("mai.kpi.at_risk"), value: s.totals.at_risk }, { label: m("mai.kpi.delayed"), value: s.totals.delayed }, { label: m("mai.kpi.avg_health"), value: num.format(s.totals.avg_health) }, { label: m("mai.kpi.open_alerts"), value: s.totals.open_alerts }]} />,
  <SegmentedGrid
    key="g"
    segments={bandSegments(m).map((x) => (x.key === "all" ? { ...x, label: `${x.label} ({n})`, field: "band", values: ["CRITICAL", "HIGH"] } : x))}
    rows={s.orders.filter((o) => ["CRITICAL", "HIGH"].includes(o.band)).map((o) => orderRow(m, o))}
    {...grid(m, "ai-at-risk-critical-orders", m("mai.at_risk_hint"))}
    rowKey="order_no"
    columns={[
      { key: "order_label", label: m("mai.col.order") },
      { key: "qty", label: m("mai.col.qty"), type: "number" },
      { key: "ship_label", label: m("mai.col.ship_date") },
      { key: "risk_label", label: m("mai.col.risk") },
      { key: "progress_label", label: m("mai.col.progress") },
      { key: "action_label", label: m("mai.col.action") },
    ]}
    emptyText={m("mai.nothing_at_risk")}
  />,
]);

/** 4 — Delayed Order Register: orders forecast to miss their date, longest slip first. */
const delayed = view((s, m) => {
  const late = [...s.orders].filter((o) => o.expected_delay > 0).sort((a, b) => b.expected_delay - a.expected_delay);
  return [
    <KpiRow key="k" items={[{ label: m("mai.kpi.delayed"), value: s.totals.delayed }, { label: m("mai.kpi.at_risk"), value: s.totals.at_risk }, { label: m("mai.kpi.live"), value: s.totals.live }]} />,
    <Panel key="c" title={m("mai.longest_slips")} hint={m("mai.longest_slips_hint")}>
      <RankList
        max={late[0]?.expected_delay ?? 0}
        empty={m("mai.nothing_delayed")}
        rows={late.slice(0, 10).map((o) => ({ key: o.order_no, title: o.order_no, value: o.expected_delay, label: m("mai.slip_days", { n: o.expected_delay }), sub: `${o.buyer ?? "—"} · ${m("mai.ships", { date: day(o.ship_date) })}` }))}
      />
    </Panel>,
    <RecordsGrid
      key="g"
      {...grid(m, "ai-delayed-orders", m("mai.delayed_hint"))}
      rowKey="order_no"
      rows={late.map((o) => orderRow(m, o))}
      columns={[
        { key: "order_label", label: m("mai.col.order") },
        { key: "qty", label: m("mai.col.qty"), type: "number" },
        { key: "ship_label", label: m("mai.col.ship_date") },
        { key: "slip_label", label: m("mai.col.forecast_slip") },
        { key: "probability_label", label: m("mai.col.delay_pct") },
        { key: "band_label", label: m("mai.col.band") },
        { key: "action_label", label: m("mai.col.action") },
      ]}
      emptyText={m("mai.nothing_delayed")}
    />,
  ];
});

const taskRows = (m, s) =>
  s.tasks.map((t) => ({
    ...t,
    task_label: `${t.task} · ${t.department ?? m("mai.unassigned")} · ${t.owner ?? m("mai.unassigned")}`,
    plan_label: t.plan_date ? `${day(t.plan_date)} · ${t.days_late > 0 ? m("mai.days_late", { n: t.days_late }) : m("mai.in_days", { n: -t.days_late })}` : "—",
    owner_label: t.owner ?? m("mai.unassigned"),
    status_label: m("mai.pending"),
  }));

/** 5 — Pending Activity Board: every open T&A task, read by plan date, with the run's rank and advice. */
const pendingActivities = view((s, m) => [
  <KpiRow key="k" items={[{ label: m("mai.kpi.pending"), value: s.totals.pending }, { label: m("mai.kpi.live"), value: s.totals.live }, { label: m("mai.kpi.at_risk"), value: s.totals.at_risk }]} />,
  <RecordsGrid
    key="g"
    {...grid(m, "ai-pending-activities", m("mai.pending_hint"))}
    rowKey="id"
    rows={taskRows(m, s).sort((a, b) => String(a.plan_date).localeCompare(String(b.plan_date)))}
    columns={[
      { key: "task_label", label: m("mai.col.task") },
      { key: "order_no", label: m("mai.col.order"), type: "mono" },
      { key: "plan_label", label: m("mai.col.plan_date") },
      { key: "status_label", label: m("mai.col.status") },
      { key: "owner_label", label: m("mai.col.owner") },
      { key: "rank", label: "#", type: "number" },
      { key: "recommendation", label: m("mai.col.task_advice") },
    ]}
    emptyText={m("mai.nothing_pending")}
  />,
]);

/** 6 — Upcoming Deadline Board: milestones and shipments in the next three weeks, as one list. */
const upcoming = view((s, m) => [
  <SegmentedGrid
    key="g"
    segments={[
      { key: "all", label: `${m("mai.seg.all")} ({n})` },
      { key: "milestones", label: m("mai.seg.milestones"), field: "kind", values: ["MILESTONE"] },
      { key: "shipments", label: m("mai.seg.shipments"), field: "kind", values: ["SHIPMENT"] },
    ]}
    rows={s.deadlines.filter((d) => d.days <= 21).map((d, i) => ({ ...d, id: `${d.kind}-${d.order_no}-${i}`, date_label: `${day(d.date)} · ${m("mai.in_days", { n: d.days })}`, risk_label: d.risk === null ? "—" : `${num.format(d.risk)} · ${m(`mai.band.${d.band}`)}` }))}
    {...grid(m, "ai-upcoming-deadlines", m("mai.upcoming_hint"))}
    rowKey="id"
    columns={[
      { key: "date_label", label: m("mai.col.date") },
      { key: "what", label: m("mai.col.what") },
      { key: "order_no", label: m("mai.col.order"), type: "mono" },
      { key: "risk_label", label: m("mai.col.order_risk") },
    ]}
    emptyText={m("mai.nothing_upcoming")}
  />,
]);

/** 7 — Buyer-Wise Order Status: where each buyer's exposure sits. */
const buyerWise = view((s, m) => [
  <Panel key="c" title={m("mai.buyer_exposure")} hint={m("mai.buyer_exposure_hint")}>
    <BandBars unit={m("mai.orders_unit")} data={s.buyers.slice(0, 12).map((b) => ({ key: b.buyer, label: b.buyer, value: b.at_risk }))} />
  </Panel>,
  <RecordsGrid
    key="g"
    {...grid(m, "ai-buyer-wise-status", m("mai.buyer_hint"))}
    rowKey="buyer"
    rows={s.buyers.map((b) => ({ ...b, value_label: `${money.format(b.value)} USD`, health_label: num.format(b.avg_health), progress_label: `${num.format(b.avg_progress)}%`, on_time_label: `${b.on_time}/${b.orders}` }))}
    columns={[
      { key: "buyer", label: m("mai.col.buyer") },
      { key: "orders", label: m("mai.col.orders"), type: "number" },
      { key: "qty", label: m("mai.col.qty"), type: "number" },
      { key: "value_label", label: m("mai.col.value"), align: "right" },
      { key: "at_risk", label: m("mai.col.at_risk"), type: "number" },
      { key: "health_label", label: m("mai.col.avg_health"), align: "right" },
      { key: "progress_label", label: m("mai.col.avg_progress"), align: "right" },
      { key: "on_time_label", label: m("mai.col.on_time"), align: "right" },
    ]}
    emptyText={m("mai.nothing")}
  />,
]);

/** 8 — Merchandiser Scorecard: who owns the T&A tasks, and how they stand. */
const merchandiser = view((s, m) =>
  s.owners.length === 0 ? (
    <div key="e" className="rounded-xl border border-border bg-surface px-5 py-10 text-center text-[13px] text-foreground-muted">{m("mai.no_owners")}</div>
  ) : (
    <RecordsGrid
      key="g"
      {...grid(m, "ai-merchandiser-performance", m("mai.merchandiser_hint"))}
      rowKey="name"
      rows={s.owners.map((o) => ({ ...o, completion_label: `${num.format(o.completion)}%` }))}
      columns={[
        { key: "name", label: m("mai.col.owner") },
        { key: "tasks", label: m("mai.col.tasks_n"), type: "number" },
        { key: "done", label: m("mai.col.closed"), type: "number" },
        { key: "on_time", label: m("mai.col.closed_on_time"), type: "number" },
        { key: "pending", label: m("mai.col.pending"), type: "number" },
        { key: "overdue", label: m("mai.col.overdue"), type: "number" },
        { key: "completion_label", label: m("mai.col.completion"), align: "right" },
      ]}
      emptyText={m("mai.no_owners")}
    />
  ),
);

/** 9 — Assisted Alerts & Advice: the findings, each with its order, reason and next move. */
const alerts = view((s, m, canManage) => [
  <KpiRow key="k" items={[{ label: m("mai.kpi.open_alerts"), value: s.totals.open_alerts }, { label: m("mai.kpi.at_risk"), value: s.totals.at_risk }, { label: m("mai.kpi.delayed"), value: s.totals.delayed }]} />,
  <AlertsBoard key="a" alerts={s.alerts} canAct={canManage} act={actions.actMerchAiAlert} />,
]);

const AI_SCREENS = {
  "ai-order-overview": overview,
  "ai-order-completion": completion,
  "ai-at-risk-critical-orders": atRisk,
  "ai-delayed-orders": delayed,
  "ai-pending-activities": pendingActivities,
  "ai-upcoming-deadlines": upcoming,
  "ai-buyer-wise-status": buyerWise,
  "ai-merchandiser-performance": merchandiser,
  "ai-alerts-recommendations": alerts,
};
const AI_MANAGE = Object.fromEntries(Object.keys(AI_SCREENS).map((slug) => [slug, "merchandise.order.manage"]));

export { AI_MANAGE, AI_SCREENS, BANDS, day, grid, orderRow, taskRows, view };
