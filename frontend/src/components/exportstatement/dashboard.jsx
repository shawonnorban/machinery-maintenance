"use client";

import Link from "next/link";
import { useT } from "@/lib/i18n";
import { cn } from "@/lib/utils";

/*
 * Export Statement → Dashboard (AnnotechERP 35): Norban's KPI cards over every
 * statement, in its four groups — Payment & Collection, Cash Incentive &
 * Govt. Documents, Documents & Shipment, Quantity & Time — each a small card
 * with a coloured edge, its figure and what it counts; the totals above them
 * (Pending Approval links to the edits waiting).
 */

const usd = (v) => `${Number(v ?? 0) < 0 ? "-" : ""}$${Math.abs(Number(v ?? 0)).toLocaleString("en-US", { maximumFractionDigits: 0 })}`;
const n0 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 });

const EDGE = { blue: "border-l-[#3b82f6]", green: "border-l-[#22c55e]", red: "border-l-[#ef4444]", orange: "border-l-[#f59e0b]", teal: "border-l-[#14b8a6]", purple: "border-l-[#8b5cf6]" };
const TEXT = { blue: "text-[#2563eb]", green: "text-[#16a34a]", red: "text-[#dc2626]", orange: "text-[#d97706]", teal: "text-[#0d9488]", purple: "text-[#7c3aed]" };

function Kpi({ title, value, sub, tone = "blue" }) {
  return (
    <div className={cn("rounded border border-border border-l-4 bg-surface px-3 py-2", EDGE[tone])}>
      <div className="text-[12px] font-medium text-foreground">{title}</div>
      <div className={cn("text-[13px] font-bold tabular-nums", TEXT[tone])}>{value}</div>
      <div className="truncate text-[11px] text-foreground-muted" title={typeof sub === "string" ? sub : undefined}>{sub}</div>
    </div>
  );
}

function Group({ title, children, className }) {
  return (
    <section className={cn("rounded-lg border border-border bg-surface p-3", className)}>
      <h3 className="mb-2 border-l-4 border-l-[#14b8a6] pl-2 text-[12px] font-bold tracking-wide text-foreground uppercase">{title}</h3>
      <div className="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-7">{children}</div>
    </section>
  );
}

function EsDashboard({ data }) {
  const t = useT("exportstatement");
  const c = (k, p) => t(`dash.card.${k}`, p);
  const { payment: p, incentive: i, documents: d, quantity: q } = data;

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2 text-[12px] text-foreground-muted">
        <span>{t("dash.total", { n: data.total })}</span>·<span>{t("dash.approved", { n: data.approved })}</span>·
        <Link href="/export-statement/approvals" className="border-b border-dashed border-foreground-muted hover:text-brand">{t("dash.pending", { n: data.pending_approval })}</Link>
      </div>

      <Group title={t("dash.groups.payment")}>
        <Kpi title={c("total_net")} value={usd(p.total_net)} sub={c("total_net_sub", { n: p.net_count })} tone="orange" />
        <Kpi title={c("total_realized")} value={usd(p.total_realized)} sub={c("total_realized_sub", { n: p.realized_count })} tone="teal" />
        <Kpi title={c("due")} value={usd(p.due_amount)} sub={c("due_sub", { n: p.due_count })} tone="red" />
        <Kpi title={c("due_90")} value={usd(p.due_90)} sub={c("due_90_sub", { n: p.due_90_count })} tone="red" />
        <Kpi title={c("realization_pct")} value={`${p.realization_pct}%`} sub={c("realization_pct_sub")} tone="green" />
        <Kpi title={c("shortage")} value={usd(p.shortage)} sub={c("shortage_sub")} tone={p.shortage < 0 ? "red" : "green"} />
        <Kpi title={c("avg_days")} value={c("avg_days_value", { n: p.avg_realization_days })} sub={c("avg_days_sub", { n: p.avg_realization_count })} tone="blue" />
        <Kpi title={c("commission")} value={usd(p.commission)} sub={c("commission_sub")} tone="purple" />
        <Kpi title={c("freight")} value={usd(p.freight)} sub={c("freight_sub")} tone="blue" />
      </Group>

      <Group title={t("dash.groups.incentive")}>
        <Kpi title={c("claimed")} value={usd(i.claimed)} sub={c("claimed_sub", { n: i.claimed_count })} tone="green" />
        <Kpi title={c("potential")} value={usd(i.potential)} sub={c("potential_sub", { rate: i.rate })} tone="teal" />
        <Kpi title={c("open")} value={n0(i.open)} sub={c("open_sub", { days: i.claim_days })} tone="blue" />
        <Kpi title={c("due_soon")} value={n0(i.due_soon)} sub={c("due_soon_sub")} tone="orange" />
        <Kpi title={c("expired")} value={n0(i.expired)} sub={c("expired_sub", { days: i.claim_days })} tone="red" />
        <Kpi title={c("prc")} value={n0(i.prc_pending)} sub={c("prc_sub")} tone="orange" />
        <Kpi title={c("exp_dup")} value={n0(i.exp_duplicate_pending)} sub={c("exp_dup_sub")} tone="orange" />
        <Kpi title={c("rex")} value={n0(i.rex_pending)} sub={c("rex_sub")} tone="orange" />
      </Group>

      <div className="grid gap-3 lg:grid-cols-2">
        <section className="rounded-lg border border-border bg-surface p-3">
          <h3 className="mb-2 border-l-4 border-l-[#14b8a6] pl-2 text-[12px] font-bold tracking-wide text-foreground uppercase">{t("dash.groups.documents")}</h3>
          <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
            <Kpi title={c("docs_overdue")} value={n0(d.docs_overdue)} sub={c("docs_overdue_sub", { days: d.docs_days })} tone="red" />
            <Kpi title={c("late_docs")} value={n0(d.late_docs)} sub={c("late_docs_sub", { n: d.late_docs_avg })} tone="orange" />
            <Kpi title={c("bl_surrender")} value={n0(d.bl_surrender_pending)} sub={c("bl_surrender_sub")} tone="orange" />
            <Kpi title={c("cargo")} value={n0(d.cargo_arriving)} sub={c("cargo_sub")} tone="blue" />
          </div>
        </section>
        <section className="rounded-lg border border-border bg-surface p-3">
          <h3 className="mb-2 border-l-4 border-l-[#14b8a6] pl-2 text-[12px] font-bold tracking-wide text-foreground uppercase">{t("dash.groups.quantity")}</h3>
          <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
            <Kpi title={c("export_qty")} value={n0(q.export_qty)} sub={c("export_qty_sub", { n: q.export_qty_count })} tone="blue" />
            <Kpi title={c("month")} value={usd(q.month_shipped)} sub={c("month_sub", { n: q.month_shipped_count, month: data.month })} tone="teal" />
            <Kpi title={c("mode")} value={`${q.sea_pct}%`} sub={c("mode_sub", { air: q.air_pct, sea_air: q.sea_air_pct, road: q.road_pct })} tone="purple" />
          </div>
        </section>
      </div>
    </div>
  );
}

export { EsDashboard };
