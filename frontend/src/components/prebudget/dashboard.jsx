"use client";

import Link from "next/link";
import { AlertTriangle, BadgeCheck, Calculator, DollarSign, FilePen, Layers, Package, Ruler, Scale } from "lucide-react";
import { StatCard } from "@/components/ui/stat-card";
import { useT } from "@/lib/i18n";

/*
 * Pre-Budget → Dashboard (user, 2026-11-21), laid out like Order Summary's:
 * totals over the requisitions this user reaches, the budgets by status, the
 * work still open, buyers and delivery months by value, and the latest saves
 * — each linking to the screen behind it.
 */

const n0 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 });
const n2 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 2 });
const usd = (v) => `$${Number(v ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Where each open-work tile and budget status leads. */
const OPEN_HREF = {
  free: "/pre-budget/create-budget",
  costing: "/pre-budget/knit-dying-costing",
  stock: "/pre-budget/stock-yarn-use",
  accessories: "/pre-budget/accessories-budget-pending",
};
const STATUS_TONE = {
  DRAFT: "bg-surface-muted",
  PENDING: "bg-[#fef9c3]",
  APPROVED: "bg-success-subtle text-success",
  RETURNED: "bg-danger-subtle text-danger",
};

function PbDashboard({ data }) {
  const t = useT("prebudget");
  const tot = data.totals;
  const maxValue = Math.max(1, ...data.by_buyer.map((b) => b.value));
  const maxMonth = Math.max(1, ...data.by_month.map((m) => m.value));
  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <StatCard label={t("dash.requisitions")} value={n0(tot.requisitions)} icon={<Layers />} />
        <StatCard label={t("dash.knit_woven")} value={`${n0(tot.knit)} / ${n0(tot.woven)}`} icon={<Scale />} tone="info" />
        <StatCard label={t("dash.order_qty")} value={`${n0(tot.order_qty)} pcs`} icon={<Package />} tone="warning" />
        <StatCard label={t("dash.fob_value")} value={usd(tot.fob_value)} icon={<DollarSign />} tone="success" />
        <StatCard label={t("dash.req_kg")} value={n2(tot.req_kg)} icon={<Scale />} tone="info" />
        <StatCard label={t("dash.req_yds")} value={n2(tot.req_yds)} icon={<Ruler />} tone="info" />
        <StatCard label={t("dash.budgets")} value={n0(tot.budgets)} icon={<Calculator />} tone="brand" />
        <StatCard label={t("dash.drafts")} value={n0(tot.drafts)} icon={<FilePen />} tone="warning" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title={t("dash.budget_status")} icon={<BadgeCheck className="size-4" />}>
          <div className="grid grid-cols-4 gap-2 text-center">
            {Object.entries(data.budget_status).map(([k, n]) => (
              <Link key={k} href={k === "DRAFT" || k === "RETURNED" ? "/pre-budget/create-budget" : "/pre-budget/budget-finalized"} className={`rounded-md px-2 py-3 hover:ring-1 hover:ring-brand ${STATUS_TONE[k] ?? ""}`}>
                <div className="text-2xl font-bold tabular-nums">{n}</div>
                <div className="text-[11px] font-semibold">{t(`budget.status.${k}`)}</div>
              </Link>
            ))}
          </div>
        </Card>
        <Card title={t("dash.pending")} icon={<AlertTriangle className="size-4" />}>
          <div className="grid grid-cols-2 gap-2">
            {Object.entries(data.pending).map(([k, n]) => (
              <Link key={k} href={OPEN_HREF[k]} className="rounded-md border border-border px-3 py-2 hover:border-brand">
                <div className="text-xl font-bold tabular-nums">{n}</div>
                <div className="text-[11px] text-foreground-muted">{t(`dash.open.${k}`)}</div>
              </Link>
            ))}
          </div>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title={t("dash.by_buyer")}>
          <table className="w-full text-[12px]">
            <thead>
              <tr className="text-left text-foreground-muted">
                <th className="py-1">{t("dash.buyer")}</th>
                <th className="py-1 text-right">{t("dash.requisitions")}</th>
                <th className="py-1 text-right">{t("dash.order_qty")}</th>
                <th className="w-2/5 py-1 pl-4">{t("dash.fob_value")}</th>
              </tr>
            </thead>
            <tbody>
              {data.by_buyer.map((b) => (
                <tr key={b.buyer || "-"} className="border-t border-border">
                  <td className="py-1.5 font-medium">
                    <Link href={b.buyer ? `/pre-budget/all-requisition?f[buyer][]=${encodeURIComponent(b.buyer)}` : "/pre-budget/all-requisition"} className="hover:text-brand">
                      {b.buyer || "—"}
                    </Link>
                  </td>
                  <td className="py-1.5 text-right tabular-nums">{b.requisitions}</td>
                  <td className="py-1.5 text-right tabular-nums">{n0(b.qty)}</td>
                  <td className="py-1.5 pl-4">
                    <div className="flex items-center gap-2">
                      <div className="h-2 rounded bg-brand" style={{ width: `${(b.value / maxValue) * 100}%` }} />
                      <span className="tabular-nums whitespace-nowrap">{usd(b.value)}</span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
        <Card title={t("dash.by_month")}>
          <div className="flex h-48 items-end gap-2">
            {data.by_month.map((m) => (
              <div key={m.month || "-"} className="flex flex-1 flex-col items-center gap-1" title={`${m.month || "—"}: ${usd(m.value)} · ${n0(m.qty)} pcs`}>
                <span className="text-[10px] tabular-nums text-foreground-muted">{n0(m.requisitions)}</span>
                <div className="w-full rounded-t bg-info" style={{ height: `${Math.max(4, (m.value / maxMonth) * 140)}px` }} />
                <span className="text-[10px]">{m.month || "—"}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>

      <Card title={t("dash.recent")}>
        <ul className="divide-y divide-border text-[12px]">
          {data.recent.length === 0 ? <li className="py-2 text-foreground-muted">{t("dash.no_recent")}</li> : null}
          {data.recent.map((r, i) => (
            <li key={i} className="flex justify-between gap-3 py-2">
              <span>
                <b>{r.by ?? "—"}</b> · {t(`dash.subject.${r.subject}`)} · {t(`dash.kind.${r.kind}`)} · {t("dash.recent_line", { rows: r.rows, cells: r.cells })}
              </span>
              <span className="text-foreground-muted">{new Date(r.at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase())}</span>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}

function Card({ title, icon, children }) {
  return (
    <section className="rounded-lg border border-border bg-surface p-4">
      <h3 className="mb-3 flex items-center gap-2 text-[14px] font-semibold">
        {icon}
        {title}
      </h3>
      {children}
    </section>
  );
}

export { PbDashboard };
