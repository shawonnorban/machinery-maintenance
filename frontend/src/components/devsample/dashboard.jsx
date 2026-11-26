"use client";

import Link from "next/link";
import { CalendarClock, ListChecks } from "lucide-react";
import { SummaryCards } from "@/components/devsample/sample-sheet";
import { useT } from "@/lib/i18n";

/*
 * Dev Sample Status → Dashboard (user, 2026-11-21), laid out like Order
 * Summary's: Norban's ten summary cards over every sample this user reads,
 * the samples still to submit by days left to the Submission Deadline, the
 * samples by status, buyers and requested months, and the latest saves —
 * each linking to View All filtered to it.
 */

const n0 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 });
const usd = (v) => `$${Number(v ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const listOf = (key, value) => (value ? `/dev-sample-status/list?f[${key}][]=${encodeURIComponent(value)}` : "/dev-sample-status/list");

function DsDashboard({ data }) {
  const t = useT("devsample");
  const maxSamples = Math.max(1, ...data.by_buyer.map((b) => b.samples));
  const maxMonth = Math.max(1, ...data.by_month.map((m) => m.samples));
  const maxStatus = Math.max(1, ...data.by_status.map((s) => s.samples));
  return (
    <div className="flex flex-col gap-4">
      <SummaryCards stats={data.stats} />

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title={t("dash.deadline")} icon={<CalendarClock className="size-4" />}>
          <div className="grid grid-cols-5 gap-2 text-center">
            {[["overdue", "bg-danger text-white"], ["week", "bg-[#fde047]"], ["month", "bg-[#fef9c3]"], ["later", "bg-success-subtle text-success"], ["none", "bg-surface-muted"]].map(([k, cls]) => (
              <div key={k} className={`rounded-md px-2 py-3 ${cls}`}>
                <div className="text-2xl font-bold tabular-nums">{data.deadline[k] ?? 0}</div>
                <div className="text-[11px] font-semibold">{t(`dash.bucket.${k}`)}</div>
              </div>
            ))}
          </div>
        </Card>
        <Card title={t("dash.by_status")} icon={<ListChecks className="size-4" />}>
          <ul className="flex flex-col gap-1.5 text-[12px]">
            {data.by_status.map((s) => (
              <li key={s.status || "-"}>
                <Link href={listOf("sample_status", s.status)} className="grid grid-cols-[9rem_1fr_3rem] items-center gap-2 hover:text-brand">
                  <span className="truncate font-medium">{s.status || t("dash.no_status")}</span>
                  <span className="h-2 rounded bg-brand" style={{ width: `${(s.samples / maxStatus) * 100}%`, minWidth: s.samples ? 4 : 0 }} />
                  <span className="text-right tabular-nums">{s.samples}</span>
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title={t("dash.by_buyer")}>
          <table className="w-full text-[12px]">
            <thead>
              <tr className="text-left text-foreground-muted">
                <th className="py-1">{t("dash.buyer")}</th>
                <th className="w-1/3 py-1">{t("dash.samples")}</th>
                <th className="py-1 text-right">{t("dash.pending")}</th>
                <th className="py-1 text-right">{t("dash.qty")}</th>
                <th className="py-1 text-right">{t("dash.value")}</th>
              </tr>
            </thead>
            <tbody>
              {data.by_buyer.map((b) => (
                <tr key={b.buyer || "-"} className="border-t border-border">
                  <td className="py-1.5 font-medium">
                    <Link href={listOf("buyer", b.buyer)} className="hover:text-brand">
                      {b.buyer || "—"}
                    </Link>
                  </td>
                  <td className="py-1.5">
                    <div className="flex items-center gap-2">
                      <div className="h-2 rounded bg-brand" style={{ width: `${(b.samples / maxSamples) * 100}%` }} />
                      <span className="tabular-nums">{b.samples}</span>
                    </div>
                  </td>
                  <td className="py-1.5 text-right tabular-nums">{b.pending}</td>
                  <td className="py-1.5 text-right tabular-nums">{n0(b.qty)}</td>
                  <td className="py-1.5 text-right tabular-nums whitespace-nowrap">{usd(b.value)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
        <Card title={t("dash.by_month")}>
          <div className="flex h-48 items-end gap-2">
            {data.by_month.map((m) => (
              <div key={m.month || "-"} className="flex flex-1 flex-col items-center gap-1" title={`${m.month || "—"}: ${n0(m.samples)} · ${n0(m.qty)} pcs`}>
                <span className="text-[10px] tabular-nums text-foreground-muted">{n0(m.samples)}</span>
                <div className="w-full rounded-t bg-info" style={{ height: `${Math.max(4, (m.samples / maxMonth) * 140)}px` }} />
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
                <b>{r.by ?? "—"}</b> · {t(`dash.kind.${r.kind}`)} · {t("dash.recent_line", { samples: r.samples, cells: r.cells })}
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

export { DsDashboard };
