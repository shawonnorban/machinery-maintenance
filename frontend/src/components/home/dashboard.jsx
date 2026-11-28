"use client";

import { useEffect, useRef } from "react";
import Link from "next/link";
import {
  AlertTriangle,
  ArrowRight,
  BadgeCheck,
  Calculator,
  CalendarClock,
  ChevronRight,
  ClipboardList,
  DollarSign,
  FilePlus2,
  History,
  Layers,
  ListChecks,
  Package,
  Sheet,
  Ship,
  Users,
} from "lucide-react";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/*
 * The home page (user, 2026-11-28): what a person sees first after signing
 * in — Order Summary and Pre-Budget side by side, not Machinery's dashboard
 * (that moved to /machinery-dashboard). Built from the two modules' own
 * dashboard answers; a part the person may not see is simply left out.
 * More modules can be added here later.
 */

const n0 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 });
const usd = (v) => `$${Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 })}`;
const usd2 = (v) => `$${Number(v ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const pct = (a, b) => (b > 0 ? Math.round((a / b) * 100) : null);
// Written out by hand, not toLocaleString: the server's and the browser's ICU differ ("Sept" / "Sep"), and the page must read the same on both.
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const MONTHS_LONG = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
/** A moment in Dhaka (UTC+6, no daylight saving) as its parts. */
const dhaka = (at) => {
  const d = new Date(new Date(at).getTime() + 6 * 3600 * 1000);
  return { y: d.getUTCFullYear(), m: d.getUTCMonth(), day: d.getUTCDate(), wd: d.getUTCDay(), h: d.getUTCHours(), min: d.getUTCMinutes() };
};
const when = (at) => {
  const p = dhaka(at);
  return `${p.day} ${MONTHS[p.m]} ${p.y}, ${String(p.h % 12 || 12).padStart(2, "0")}:${String(p.min).padStart(2, "0")} ${p.h < 12 ? "AM" : "PM"}`;
};

const TONE = {
  brand: { icon: "bg-brand-subtle text-brand", bar: "bg-brand", border: "border-l-brand" },
  info: { icon: "bg-info-subtle text-info", bar: "bg-info", border: "border-l-info" },
  success: { icon: "bg-success-subtle text-success", bar: "bg-success", border: "border-l-success" },
  warning: { icon: "bg-warning-subtle text-warning", bar: "bg-warning", border: "border-l-warning" },
  danger: { icon: "bg-danger-subtle text-danger", bar: "bg-danger", border: "border-l-danger" },
};

/** A days-left bucket's sheet filters (query pairs from the API) as the list's address. */
function bucketHref(pairs) {
  const p = new URLSearchParams();
  for (const [k, v] of pairs ?? []) p.append(k, v);
  return `/order-summary/list?${p.toString() || "all=1"}`;
}

function HomeDashboard({ os, pb, name, now }) {
  const t = useT("dashboard");
  const o = useT("ordersummary");
  const b = useT("prebudget");
  // `now` is the server's moment, so the greeting and the date read the same on both sides.
  const at = dhaka(now);
  const greet = at.h < 12 ? "home.morning" : at.h < 17 ? "home.afternoon" : "home.evening";
  const today = `${DAYS[at.wd]} ${at.day} ${MONTHS_LONG[at.m]} ${at.y}`;

  return (
    <div className="flex flex-col gap-4">
      {/* Welcome: who, which day, and the ways in. */}
      <section className="relative overflow-hidden rounded-lg bg-gradient-to-r from-[#1e3a8a] via-[#1d4ed8] to-[#0ea5e9] px-5 py-5 text-white shadow-sm sm:px-6">
        <div aria-hidden className="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/10" />
        <div aria-hidden className="pointer-events-none absolute -bottom-20 right-40 size-40 rounded-full bg-white/5" />
        <div className="relative flex flex-wrap items-end justify-between gap-4">
          <div className="min-w-0">
            <p className="text-[13px] text-blue-100">{today}</p>
            <h2 className="mt-1 text-xl font-semibold sm:text-2xl">{t(greet, { name: name || "" }).replace(/,\s*$/, "")}</h2>
            <p className="mt-1 text-[13px] text-blue-100">{t("home.welcome_sub")}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {os ? <QuickLink href="/order-summary/list" icon={<Sheet />} label={t("home.go_orders")} /> : null}
            {pb ? <QuickLink href="/pre-budget/all-requisition" icon={<ClipboardList />} label={t("home.go_requisitions")} /> : null}
            {pb ? <QuickLink href="/pre-budget/create-budget" icon={<FilePlus2 />} label={t("home.go_budget")} /> : null}
          </div>
        </div>
      </section>

      {/* The headline figures. */}
      <div className={cn("grid grid-cols-2 gap-3 md:grid-cols-3", os && pb ? "xl:grid-cols-6" : "xl:grid-cols-3")}>
        {os ? (
          <>
            <Kpi label={o("dash.orders")} value={n0(os.totals.orders)} caption={t("home.styles_n", { n: n0(os.totals.styles) })} icon={<Layers />} tone="brand" href="/order-summary/list?all=1" />
            <Kpi label={t("home.order_qty")} value={`${n0(os.totals.qty)}`} caption={t("home.pcs")} icon={<Package />} tone="warning" />
            <Kpi label={o("dash.value")} value={usd(os.totals.value)} caption={t("home.fob_total")} icon={<DollarSign />} tone="success" />
            <Kpi label={o("dash.shipped")} value={n0(os.totals.shipped)} caption={t("home.shipout", { v: usd(os.totals.shipout_value) })} icon={<Ship />} tone="info" />
          </>
        ) : null}
        {pb ? (
          <>
            <Kpi label={b("dash.requisitions")} value={n0(pb.totals.requisitions)} caption={t("home.knit_woven", { k: n0(pb.totals.knit), w: n0(pb.totals.woven) })} icon={<ClipboardList />} tone="brand" href="/pre-budget/all-requisition" />
            <Kpi label={b("dash.budgets")} value={n0(pb.totals.budgets)} caption={t("home.approved_n", { n: n0(pb.budget_status.APPROVED ?? 0) })} icon={<Calculator />} tone="success" href="/pre-budget/budget-finalized" />
          </>
        ) : null}
      </div>

      {os && pb ? <Pipeline os={os} pb={pb} /> : null}

      {os ? (
        <>
          <SectionTitle icon={<Sheet />} title={t("home.os_title")} href="/order-summary/dashboard" link={t("home.open_dashboard")} />
          <div className="grid gap-4 lg:grid-cols-2">
            <Panel title={o("dash.days_left")} icon={<CalendarClock />}>
              <div className="grid grid-cols-5 gap-2 text-center">
                {[
                  ["overdue", "bg-danger text-white"],
                  ["week", "bg-[#fde047] text-[#713f12]"],
                  ["month", "bg-[#fef9c3] text-[#854d0e]"],
                  ["later", "bg-success-subtle text-success"],
                  ["none", "bg-surface-muted text-foreground-muted"],
                ].map(([k, cls]) => (
                  <Link key={k} href={bucketHref(os.days_left_filters?.[k])} className={`rounded-md px-1 py-3 ring-brand transition hover:ring-2 ${cls}`}>
                    <div className="text-2xl font-bold tabular-nums">{os.days_left[k] ?? 0}</div>
                    <div className="text-[11px] font-semibold">{o(`dash.bucket.${k}`)}</div>
                  </Link>
                ))}
              </div>
            </Panel>
            <Panel title={o("dash.pending")} icon={<AlertTriangle />}>
              <CountList rows={Object.entries(os.pending).map(([k, n]) => ({ key: k, n, label: o(`report.type.${k}`), href: `/order-summary/ldd?type=${k}` }))} />
            </Panel>
          </div>
        </>
      ) : null}

      {pb ? (
        <>
          <SectionTitle icon={<Calculator />} title={t("home.pb_title")} href="/pre-budget/dashboard" link={t("home.open_dashboard")} />
          <div className="grid gap-4 lg:grid-cols-2">
            <Panel title={b("dash.budget_status")} icon={<BadgeCheck />}>
              <div className="grid grid-cols-4 gap-2 text-center">
                {[
                  ["DRAFT", "bg-surface-muted text-foreground"],
                  ["PENDING", "bg-[#fef9c3] text-[#854d0e]"],
                  ["APPROVED", "bg-success-subtle text-success"],
                  ["RETURNED", "bg-danger-subtle text-danger"],
                ].map(([k, cls]) => (
                  <Link key={k} href={k === "DRAFT" || k === "RETURNED" ? "/pre-budget/create-budget" : "/pre-budget/budget-finalized"} className={`rounded-md px-1 py-3 ring-brand transition hover:ring-2 ${cls}`}>
                    <div className="text-2xl font-bold tabular-nums">{pb.budget_status[k] ?? 0}</div>
                    <div className="text-[11px] font-semibold">{b(`budget.status.${k}`)}</div>
                  </Link>
                ))}
              </div>
              <div className="mt-3 grid grid-cols-2 gap-2 text-[12px] text-foreground-muted">
                <span>
                  {b("dash.req_kg")}: <b className="text-foreground tabular-nums">{n0(pb.totals.req_kg)}</b>
                </span>
                <span>
                  {b("dash.req_yds")}: <b className="text-foreground tabular-nums">{n0(pb.totals.req_yds)}</b>
                </span>
              </div>
            </Panel>
            <Panel title={b("dash.pending")} icon={<ListChecks />}>
              <CountList
                rows={[
                  ...Object.entries(pb.pending).map(([k, n]) => ({ key: k, n, label: b(`dash.open.${k}`), href: OPEN_HREF[k] })),
                  { key: "drafts", n: pb.totals.drafts, label: b("dash.drafts"), href: "/pre-budget/draft-requisition" },
                ]}
              />
            </Panel>
          </div>
        </>
      ) : null}

      {os || pb ? (
        <>
          <SectionTitle icon={<Users />} title={t("home.buyers_months")} />
          <div className="grid gap-4 lg:grid-cols-2">
            <BuyerCard os={os} pb={pb} />
            <MonthCard os={os} pb={pb} moment={now} />
          </div>
          <RecentCard os={os} pb={pb} />
        </>
      ) : null}
    </div>
  );
}

const OPEN_HREF = {
  free: "/pre-budget/create-budget",
  costing: "/pre-budget/knit-dying-costing",
  stock: "/pre-budget/stock-yarn-use",
  accessories: "/pre-budget/accessories-budget-pending",
};

function QuickLink({ href, icon, label }) {
  return (
    <Link href={href} className="inline-flex h-9 items-center gap-1.5 rounded-full bg-white/15 px-3.5 text-[13px] font-medium text-white ring-1 ring-white/30 transition hover:bg-white/25 [&_svg]:size-4">
      {icon}
      {label}
    </Link>
  );
}

function Kpi({ label, value, caption, icon, tone, href }) {
  const body = (
    <Card className={cn("h-full min-w-0 border-l-4 p-4 transition-shadow hover:shadow-sm", TONE[tone].border)}>
      <div className="flex items-center gap-2">
        <span className={cn("flex size-8 shrink-0 items-center justify-center rounded-full [&_svg]:size-4", TONE[tone].icon)}>{icon}</span>
        <span className="truncate text-xs font-medium text-foreground-muted">{label}</span>
      </div>
      <div className="mt-2.5 truncate text-2xl font-bold tracking-tight text-foreground tabular-nums">{value}</div>
      {caption ? <p className="mt-0.5 truncate text-xs text-foreground-muted">{caption}</p> : null}
    </Card>
  );
  return href ? (
    <Link href={href} className="block min-w-0">
      {body}
    </Link>
  ) : (
    body
  );
}

/** Orders → requisitions → budgets → approved: how far the season's orders have come. */
function Pipeline({ os, pb }) {
  const t = useT("dashboard");
  const steps = [
    { key: "orders", n: os.totals.orders, label: t("home.step_orders"), href: "/order-summary/list?all=1", tone: "brand", icon: <Sheet /> },
    { key: "reqs", n: pb.totals.requisitions, label: t("home.step_reqs"), href: "/pre-budget/all-requisition", tone: "info", icon: <ClipboardList /> },
    { key: "budgets", n: pb.totals.budgets, label: t("home.step_budgets"), href: "/pre-budget/create-budget", tone: "warning", icon: <Calculator /> },
    { key: "approved", n: pb.budget_status.APPROVED ?? 0, label: t("home.step_approved"), href: "/pre-budget/budget-finalized", tone: "success", icon: <BadgeCheck /> },
  ];
  const top = Math.max(1, ...steps.map((s) => s.n));

  return (
    <Panel title={t("home.pipeline")} hint={t("home.pipeline_hint")} icon={<ArrowRight />}>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {steps.map((s, i) => {
          const share = i === 0 ? null : pct(s.n, steps[i - 1].n);
          return (
            <Link key={s.key} href={s.href} className="group relative rounded-md border border-border p-3 transition hover:border-brand hover:shadow-sm">
              <div className="flex items-center gap-2">
                <span className={cn("flex size-7 items-center justify-center rounded-full [&_svg]:size-3.5", TONE[s.tone].icon)}>{s.icon}</span>
                <span className="text-xs font-medium text-foreground-muted">{s.label}</span>
                {i < steps.length - 1 ? <ChevronRight className="ml-auto hidden size-4 text-foreground-subtle md:block" /> : null}
              </div>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-2xl font-bold tabular-nums">{n0(s.n)}</span>
                {share !== null ? <span className="text-xs text-foreground-muted">{t("home.of_prev", { pct: share })}</span> : null}
              </div>
              <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                <div className={cn("h-full rounded-full", TONE[s.tone].bar)} style={{ width: `${Math.max(3, (s.n / top) * 100)}%` }} />
              </div>
            </Link>
          );
        })}
      </div>
    </Panel>
  );
}

/** Buyers by FOB value: the orders' (Order Summary) beside the requisitions' (Pre-Budget). */
function BuyerCard({ os, pb }) {
  const t = useT("dashboard");
  const o = useT("ordersummary");
  const map = new Map();
  for (const r of os?.by_buyer ?? []) map.set(r.buyer || "", { buyer: r.buyer, orders: r.orders, osValue: r.value, reqs: 0, pbValue: 0 });
  for (const r of pb?.by_buyer ?? []) {
    const row = map.get(r.buyer || "") ?? { buyer: r.buyer, orders: 0, osValue: 0, reqs: 0, pbValue: 0 };
    map.set(r.buyer || "", { ...row, reqs: r.requisitions, pbValue: r.value });
  }
  const rows = [...map.values()].sort((a, c) => Math.max(c.osValue, c.pbValue) - Math.max(a.osValue, a.pbValue)).slice(0, 8);
  const max = Math.max(1, ...rows.map((r) => Math.max(r.osValue, r.pbValue)));

  return (
    <Panel title={t("home.top_buyers")} icon={<Users />} aside={<Legend os={Boolean(os)} pb={Boolean(pb)} />}>
      {rows.length === 0 ? (
        <p className="py-6 text-center text-sm text-foreground-muted">{t("home.no_data")}</p>
      ) : (
        <ul className="flex flex-col gap-2.5">
          {rows.map((r) => (
            <li key={r.buyer || "-"} className="grid grid-cols-[minmax(0,9rem)_1fr] items-center gap-3 text-[12px]">
              <div className="min-w-0">
                <Link href={r.buyer ? `/order-summary/list?f[buyer][]=${encodeURIComponent(r.buyer)}` : "/order-summary/list"} className="block truncate font-semibold hover:text-brand">
                  {r.buyer || "—"}
                </Link>
                <span className="text-[11px] text-foreground-muted">
                  {[os ? `${n0(r.orders)} ${o("dash.orders").toLowerCase()}` : null, pb ? t("home.reqs_n", { n: n0(r.reqs) }) : null].filter(Boolean).join(" · ")}
                </span>
              </div>
              <div className="flex min-w-0 flex-col gap-1">
                {os ? <Bar value={r.osValue} max={max} cls="bg-brand" /> : null}
                {pb ? <Bar value={r.pbValue} max={max} cls="bg-info" /> : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

function Bar({ value, max, cls }) {
  return (
    <div className="flex items-center gap-2">
      <div className="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-surface-muted">
        <div className={cn("h-full rounded-full", cls)} style={{ width: `${value > 0 ? Math.max(2, (value / max) * 100) : 0}%` }} />
      </div>
      <span className="w-24 shrink-0 text-right tabular-nums whitespace-nowrap">{usd(value)}</span>
    </div>
  );
}

function Legend({ os, pb }) {
  const t = useT("dashboard");
  return (
    <div className="flex items-center gap-3 text-[11px] text-foreground-muted">
      {os ? (
        <span className="inline-flex items-center gap-1">
          <span className="size-2.5 rounded-[3px] bg-brand" />
          {t("home.legend_orders")}
        </span>
      ) : null}
      {pb ? (
        <span className="inline-flex items-center gap-1">
          <span className="size-2.5 rounded-[3px] bg-info" />
          {t("home.legend_reqs")}
        </span>
      ) : null}
    </div>
  );
}

/** FOB value by delivery month, orders (LDD) beside requisitions (delivery date); opened at this month. */
function MonthCard({ os, pb, moment }) {
  const t = useT("dashboard");
  const box = useRef(null);
  const months = new Map();
  for (const m of os?.by_month ?? []) if (m.month) months.set(m.month, { month: m.month, os: m.value, osN: m.orders, pb: 0, pbN: 0 });
  for (const m of pb?.by_month ?? []) {
    if (!m.month) continue;
    const row = months.get(m.month) ?? { month: m.month, os: 0, osN: 0, pb: 0, pbN: 0 };
    months.set(m.month, { ...row, pb: m.value, pbN: m.requisitions });
  }
  const rows = [...months.values()].sort((a, c) => a.month.localeCompare(c.month));
  const max = Math.max(1, ...rows.map((r) => Math.max(r.os, r.pb)));
  const d = dhaka(moment);
  const now = `${d.y}-${String(d.m + 1).padStart(2, "0")}`;

  useEffect(() => {
    const el = box.current;
    if (!el) return;
    const at = el.querySelector(`[data-month="${now}"]`);
    el.scrollLeft = at ? at.offsetLeft - el.offsetLeft - el.clientWidth / 2 + at.clientWidth / 2 : el.scrollWidth;
  }, [now, rows.length]);

  const label = (m) => {
    const [y, mo] = m.split("-");
    return `${MONTHS[Number(mo) - 1] ?? mo} ${y.slice(2)}`;
  };

  return (
    <Panel title={t("home.by_month")} icon={<CalendarClock />} aside={<Legend os={Boolean(os)} pb={Boolean(pb)} />}>
      {rows.length === 0 ? (
        <p className="py-6 text-center text-sm text-foreground-muted">{t("home.no_data")}</p>
      ) : (
        <div ref={box} className="flex h-56 items-end gap-3 overflow-x-auto pb-1">
          {rows.map((r) => (
            <div key={r.month} data-month={r.month} className="flex min-w-[52px] flex-1 flex-col items-center gap-1">
              <div className="flex h-44 w-full items-end justify-center gap-1">
                {os ? <div className="w-1/2 max-w-5 rounded-t bg-brand" style={{ height: `${r.os > 0 ? Math.max(3, (r.os / max) * 100) : 0}%` }} title={`${r.month} · ${t("home.legend_orders")}: ${usd2(r.os)} (${r.osN})`} /> : null}
                {pb ? <div className="w-1/2 max-w-5 rounded-t bg-info" style={{ height: `${r.pb > 0 ? Math.max(3, (r.pb / max) * 100) : 0}%` }} title={`${r.month} · ${t("home.legend_reqs")}: ${usd2(r.pb)} (${r.pbN})`} /> : null}
              </div>
              <span className={cn("rounded px-1 text-[10px] whitespace-nowrap", r.month === now ? "bg-brand text-white" : "text-foreground-muted")}>{label(r.month)}</span>
            </div>
          ))}
        </div>
      )}
    </Panel>
  );
}

/** The latest saves of both modules, newest first. */
function RecentCard({ os, pb }) {
  const t = useT("dashboard");
  const o = useT("ordersummary");
  const b = useT("prebudget");
  const rows = [
    ...(os?.recent ?? []).map((r) => ({ ...r, mod: "os", what: `${o(`audit.kind.${r.kind}`)} · ${o("dash.recent_line", { orders: r.orders, cells: r.cells })}` })),
    ...(pb?.recent ?? []).map((r) => ({ ...r, mod: "pb", what: `${b(`dash.subject.${r.subject}`)} · ${b(`dash.kind.${r.kind}`)} · ${b("dash.recent_line", { rows: r.rows, cells: r.cells })}` })),
  ]
    .filter((r) => r.at)
    .sort((a, c) => c.at.localeCompare(a.at))
    .slice(0, 10);

  return (
    <Panel title={t("home.recent")} icon={<History />}>
      {rows.length === 0 ? (
        <p className="py-4 text-center text-sm text-foreground-muted">{t("home.no_recent")}</p>
      ) : (
        <ul className="divide-y divide-border text-[13px]">
          {rows.map((r, i) => (
            <li key={i} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
              <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-semibold", r.mod === "os" ? "bg-brand-subtle text-brand" : "bg-info-subtle text-info")}>{r.mod === "os" ? t("home.mod_os") : t("home.mod_pb")}</span>
              <span className="min-w-0 flex-1">
                <b>{r.by ?? "—"}</b> <span className="text-foreground-muted">· {r.what}</span>
              </span>
              <span className="text-xs text-foreground-muted">{when(r.at)}</span>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

function CountList({ rows }) {
  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
      {rows.map((r) => (
        <Link key={r.key} href={r.href} className={cn("group rounded-md border px-3 py-2 transition hover:border-brand hover:shadow-sm", r.n > 0 ? "border-warning/40 bg-warning-subtle/30" : "border-border")}>
          <div className={cn("text-xl font-bold tabular-nums", r.n > 0 ? "text-foreground" : "text-foreground-muted")}>{n0(r.n)}</div>
          <div className="text-[11px] leading-snug text-foreground-muted">{r.label}</div>
        </Link>
      ))}
    </div>
  );
}

function SectionTitle({ icon, title, href, link }) {
  return (
    <div className="mt-2 flex items-center gap-2">
      <span className="flex size-7 items-center justify-center rounded-md bg-brand-subtle text-brand [&_svg]:size-4">{icon}</span>
      <h2 className="text-[15px] font-semibold text-foreground">{title}</h2>
      <span className="h-px flex-1 bg-border" />
      {href ? (
        <Link href={href} className="inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline">
          {link} <ArrowRight className="size-3.5" />
        </Link>
      ) : null}
    </div>
  );
}

function Panel({ title, hint, icon, aside, children }) {
  return (
    <Card className="min-w-0 p-4">
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <span className="text-foreground-muted [&_svg]:size-4">{icon}</span>
        <div className="min-w-0 flex-1">
          <h3 className="text-[14px] font-semibold">{title}</h3>
          {hint ? <p className="text-xs text-foreground-muted">{hint}</p> : null}
        </div>
        {aside}
      </div>
      {children}
    </Card>
  );
}

export { HomeDashboard };
