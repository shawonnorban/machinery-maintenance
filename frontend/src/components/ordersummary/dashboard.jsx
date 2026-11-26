"use client";

import { useEffect, useRef } from "react";
import Link from "next/link";
import { AlertTriangle, CalendarClock, CheckCircle2, DollarSign, Layers, Package, Shirt, Ship } from "lucide-react";
import { StatCard } from "@/components/ui/stat-card";
import { useT } from "@/lib/i18n";

/*
 * Order Summary → Dashboard (AnnotechERP 32), the module's own main page:
 * totals over the orders of the user's buyers, open orders by days left to
 * RFI/SD, what is pending against LDD/ETD, buyers and months by value, and
 * the latest saves — each linking to the sheet or report behind it.
 */

const n0 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 });
const usd = (v) => `$${Number(v ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** A days-left bucket's sheet filters (query pairs from the API) as the list's address. */
function bucketHref(pairs) {
  const p = new URLSearchParams();
  for (const [k, v] of pairs ?? []) p.append(k, v);
  return `/order-summary/list?${p.toString() || "all=1"}`;
}

function OsDashboard({ data }) {
  const t = useT("ordersummary");
  const monthsRef = useRef(null);
  useEffect(() => {
    // Open the month chart at this month (Dhaka), else at its end.
    const box = monthsRef.current;
    if (!box) return;
    const now = new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" }).slice(0, 7);
    const el = box.querySelector(`[data-month="${now}"]`);
    box.scrollLeft = el ? el.offsetLeft - box.offsetLeft - box.clientWidth / 2 + el.clientWidth / 2 : box.scrollWidth;
  }, [data.by_month]);
  const tot = data.totals;
  const maxValue = Math.max(1, ...data.by_buyer.map((b) => b.value));
  const maxMonth = Math.max(1, ...data.by_month.map((m) => m.value));
  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <StatCard label={t("dash.orders")} value={n0(tot.orders)} icon={<Layers />} />
        <StatCard label={t("dash.styles")} value={n0(tot.styles)} icon={<Shirt />} tone="info" />
        <StatCard label={t("dash.qty")} value={`${n0(tot.qty)} pcs`} icon={<Package />} tone="warning" />
        <StatCard label={t("dash.value")} value={usd(tot.value)} icon={<DollarSign />} tone="success" />
        <StatCard label={t("dash.shipped")} value={n0(tot.shipped)} icon={<Ship />} tone="info" />
        <StatCard label={t("dash.shipout_value")} value={usd(tot.shipout_value)} icon={<CheckCircle2 />} tone="success" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title={t("dash.days_left")} icon={<CalendarClock className="size-4" />}>
          <div className="grid grid-cols-5 gap-2 text-center">
            {[["overdue", "bg-danger text-white"], ["week", "bg-[#fde047]"], ["month", "bg-[#fef9c3]"], ["later", "bg-success-subtle text-success"], ["none", "bg-surface-muted"]].map(([k, cls]) => (
              <Link
                key={k}
                href={bucketHref(data.days_left_filters?.[k])}
                className={`rounded-md px-2 py-3 ring-brand transition hover:ring-2 ${cls}`}
              >
                <div className="text-2xl font-bold tabular-nums">{data.days_left[k] ?? 0}</div>
                <div className="text-[11px] font-semibold">{t(`dash.bucket.${k}`)}</div>
              </Link>
            ))}
          </div>
        </Card>
        <Card title={t("dash.pending")} icon={<AlertTriangle className="size-4" />}>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
            {Object.entries(data.pending).map(([k, n]) => (
              <Link key={k} href={`/order-summary/ldd?type=${k}`} className="rounded-md border border-border px-3 py-2 hover:border-brand">
                <div className="text-xl font-bold tabular-nums">{n}</div>
                <div className="text-[11px] text-foreground-muted">{t(`report.type.${k}`)}</div>
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
                <th className="py-1 text-right">{t("dash.orders")}</th>
                <th className="py-1 text-right">{t("dash.qty")}</th>
                <th className="w-2/5 py-1 pl-4">{t("dash.value")}</th>
              </tr>
            </thead>
            <tbody>
              {data.by_buyer.map((b) => (
                <tr key={b.buyer || "-"} className="border-t border-border">
                  <td className="py-1.5 font-medium">
                    <Link href={b.buyer ? `/order-summary/list?f[buyer][]=${encodeURIComponent(b.buyer)}` : "/order-summary/list"} className="hover:text-brand">
                      {b.buyer || "—"}
                    </Link>
                  </td>
                  <td className="py-1.5 text-right tabular-nums">{b.orders}</td>
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
          {/* Many months scroll inside the card (opened at the current month) instead of widening the page. */}
          <div ref={monthsRef} className="flex h-52 items-end gap-2 overflow-x-auto pb-2">
            {data.by_month.map((m) => (
              <div
                key={m.month || "-"}
                data-month={m.month}
                className="flex min-w-[44px] flex-1 flex-col items-center gap-1"
                title={`${m.month}: ${usd(m.value)} · ${n0(m.qty)} pcs`}
              >
                <span className="text-[10px] tabular-nums text-foreground-muted">{n0(m.orders)}</span>
                <div className="w-full rounded-t bg-info" style={{ height: `${Math.max(4, (m.value / maxMonth) * 140)}px` }} />
                <span className="whitespace-nowrap text-[10px]">{m.month || "—"}</span>
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
                <b>{r.by ?? "—"}</b> · {t(`audit.kind.${r.kind}`)} · {t("dash.recent_line", { orders: r.orders, cells: r.cells })}
              </span>
              <span className="text-foreground-muted">{new Date(r.at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase())}</span>
            </li>
          ))}
        </ul>
        <Link href="/order-summary/audit" className="mt-2 inline-block text-[12px] font-semibold text-brand">
          {t("dash.all_changes")}
        </Link>
      </Card>
    </div>
  );
}

function Card({ title, icon, children }) {
  return (
    <section className="min-w-0 rounded-lg border border-border bg-surface p-4">
      <h3 className="mb-3 flex items-center gap-2 text-[14px] font-semibold">
        {icon}
        {title}
      </h3>
      {children}
    </section>
  );
}

export { OsDashboard };
