import Link from "next/link";
import { Card, CardHeader, CardTitle, CardDescription, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { TrendBadge } from "@/components/ui/stat-card";
import { RadialProgress } from "@/components/ui/radial-progress";
import { ProgressBar } from "@/components/ui/progress-bar";
import { RelativeTime } from "@/components/ui/relative-time";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { TrendBarChart } from "@/components/dashboard/trend-bar-chart";
import { refOf } from "@/lib/ref-path";
import { getT } from "@/lib/i18n-server";
import { cn } from "@/lib/utils";
import {
  AlertOctagon,
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  ClipboardList,
  Clock,
  Gauge,
  PackageX,
  Siren,
  Timer,
  Users,
  Wrench,
} from "lucide-react";

/*
 * The Machinery dashboard's cards. One audience-agnostic grid rather than
 * three stacked panels: page.js drops the cards a role can't see (each
 * backing panel 403s on its own permission) and the rows close up around
 * what's left.
 */

const TONE = {
  brand: { icon: "bg-brand-subtle text-brand-hover", solid: "bg-brand text-white", chip: "border-brand/30 bg-brand-subtle/50" },
  success: { icon: "bg-success-subtle text-success", solid: "bg-success text-white", chip: "border-success/30 bg-success-subtle/50" },
  warning: { icon: "bg-warning-subtle text-warning", solid: "bg-warning text-white", chip: "border-warning/40 bg-warning-subtle/60" },
  danger: { icon: "bg-danger-subtle text-danger", solid: "bg-danger text-white", chip: "border-danger/30 bg-danger-subtle/60" },
  info: { icon: "bg-info-subtle text-info", solid: "bg-info text-white", chip: "border-info/30 bg-info-subtle/50" },
};

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const PRIORITY_EDGE = { CRITICAL: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
const qty = (value) => {
  const n = Number(value ?? 0);
  return Number.isInteger(n) ? n.toLocaleString() : n.toLocaleString(undefined, { maximumFractionDigits: 2 });
};

/** A titled card with an optional "View all" link in its corner. */
function Panel({ title, hint, href, linkLabel, aside, className, bodyClassName, children }) {
  return (
    <Card className={cn("flex min-w-0 flex-col", className)}>
      <CardHeader className="items-center">
        <div className="min-w-0 space-y-0.5">
          <CardTitle>{title}</CardTitle>
          {hint ? <CardDescription>{hint}</CardDescription> : null}
        </div>
        <div className="flex shrink-0 items-center gap-2">
          {aside}
          {href ? (
            <Link href={href} className="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover hover:underline">
              {linkLabel} <ArrowRight className="size-3.5" />
            </Link>
          ) : null}
        </div>
      </CardHeader>
      <CardBody className={cn("flex-1", bodyClassName)}>{children}</CardBody>
    </Card>
  );
}

function EmptyLine({ children }) {
  return (
    <div className="flex h-full min-h-28 flex-col items-center justify-center gap-2 text-center text-sm text-foreground-muted">
      <CheckCircle2 className="size-6 text-success" />
      {children}
    </div>
  );
}

/** A small legend swatch: identity is never colour alone, the label sits beside it. */
function Swatch({ color }) {
  return <span className="inline-block size-2.5 shrink-0 rounded-[3px]" style={{ background: color }} />;
}

// ---------------------------------------------------------------- attention

async function AttentionStrip({ maintenance, store }) {
  const t = await getT("dashboard");

  const items = [
    maintenance && { key: "unack", label: t("attention_unacknowledged"), count: maintenance.unacknowledged_breakdowns, href: "/breakdowns", icon: <AlertOctagon />, tone: "danger" },
    maintenance && { key: "overdue", label: t("attention_overdue"), count: maintenance.overdue, href: "/maintenance/schedule", icon: <AlertTriangle />, tone: "warning" },
    store && { key: "critical", label: t("attention_critical"), count: store.critical_low, href: "/inventory/stock", icon: <Siren />, tone: "danger" },
    store && { key: "out", label: t("attention_out"), count: store.out_of_stock, href: "/inventory/stock", icon: <PackageX />, tone: "warning" },
    maintenance && {
      key: "capacity",
      label: t("attention_capacity"),
      count: maintenance.workload.filter((row) => row.at_capacity).length,
      href: "/technicians",
      icon: <Users />,
      tone: "info",
    },
  ].filter(Boolean);

  const open = items.filter((item) => item.count > 0).length;

  return (
    <Card className={cn("border-l-4 p-4", open > 0 ? "border-l-danger" : "border-l-success")}>
      <div className="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h2 className="text-sm font-semibold text-foreground">{t("attention_title")}</h2>
        <span className="text-xs text-foreground-muted">{t("attention_hint")}</span>
        {open === 0 ? (
          <Badge variant="success" className="ml-auto">
            {t("all_clear")}
          </Badge>
        ) : null}
      </div>
      <div className={cn("grid grid-cols-1 gap-3 sm:grid-cols-2", items.length >= 5 ? "lg:grid-cols-5" : items.length === 4 ? "lg:grid-cols-4" : "lg:grid-cols-3")}>
        {items.map((item) => {
          const hot = item.count > 0;
          const tone = TONE[item.tone];

          return (
            <Link
              key={item.key}
              href={item.href}
              className={cn(
                "group flex items-center gap-3 rounded-sm border px-3 py-2.5 transition-shadow hover:shadow-sm",
                hot ? tone.chip : "border-border bg-surface",
              )}
            >
              <span className={cn("flex size-9 shrink-0 items-center justify-center rounded-full [&_svg]:size-4.5", hot ? tone.solid : "bg-success-subtle text-success")}>
                {hot ? item.icon : <CheckCircle2 />}
              </span>
              <span className="min-w-0">
                <span className={cn("tabular block text-xl leading-tight font-bold", hot ? "text-foreground" : "text-foreground-muted")}>{item.count}</span>
                <span className="line-clamp-2 block text-xs leading-snug text-foreground-muted">{item.label}</span>
              </span>
              <ArrowRight className="ml-auto size-4 shrink-0 text-foreground-subtle opacity-0 transition-opacity group-hover:opacity-100" />
            </Link>
          );
        })}
      </div>
    </Card>
  );
}

// ---------------------------------------------------------------- KPI tiles

function KpiTile({ label, value, icon, tone = "brand", trend, caption, title }) {
  return (
    <Card className="min-w-0 p-4" title={title}>
      <div className="flex items-center gap-2">
        <span className={cn("flex size-7 shrink-0 items-center justify-center rounded-sm [&_svg]:size-4", TONE[tone].icon)}>{icon}</span>
        <span className="truncate text-xs font-medium text-foreground-muted">{label}</span>
      </div>
      <div className="mt-2.5 flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
        <span className="tabular text-2xl font-bold tracking-tight text-foreground">{value}</span>
        <TrendBadge value={trend} />
      </div>
      {caption ? <p className="mt-1 truncate text-xs text-foreground-subtle">{caption}</p> : null}
    </Card>
  );
}

async function KpiRow({ management, maintenance }) {
  const t = await getT("dashboard");
  const k = management?.kpis;

  const hours = (minutes) => {
    if (minutes === null || minutes === undefined) return t("na");
    const h = minutes / 60;
    return `${h >= 100 ? Math.round(h).toLocaleString() : h.toFixed(1)} ${t("hours")}`;
  };
  const mins = (minutes) => (minutes === null || minutes === undefined ? t("na") : `${Math.round(minutes).toLocaleString()} ${t("minutes")}`);

  const tiles = [];

  if (k) {
    const availabilityTone = k.availability_percent === null ? "brand" : k.availability_percent >= 90 ? "success" : k.availability_percent >= 75 ? "warning" : "danger";

    tiles.push(
      <Card key="availability" className="min-w-0 p-4" title={t("availability_hint")}>
        <div className="flex items-center justify-between gap-2">
          <span className="truncate text-xs font-medium text-foreground-muted">{t("availability")}</span>
          <RadialProgress value={k.availability_percent ?? 0} tone={availabilityTone} size={28} strokeWidth={4} showValue={false} />
        </div>
        <div className="mt-2.5 flex flex-wrap items-baseline gap-x-2 gap-y-1">
          <span className="tabular text-2xl font-bold tracking-tight text-foreground">{k.availability_percent === null ? t("na") : `${k.availability_percent}%`}</span>
          <TrendBadge value={k.availability_trend} />
        </div>
        <p className="mt-1 truncate text-xs text-foreground-subtle">
          {t("unplanned_downtime")}: {hours(k.unplanned_downtime_minutes)}
        </p>
      </Card>,
      <KpiTile key="mtbf" label={t("mtbf")} icon={<Clock />} tone="info" value={hours(k.mtbf_minutes)} trend={k.mtbf_trend} caption={`${t("failures")}: ${k.failure_count}`} title={t("mtbf_hint")} />,
      <KpiTile key="mttr" label={t("mttr")} icon={<Wrench />} tone="warning" value={mins(k.mttr_minutes)} trend={k.mttr_trend} caption={t("mttr_hint")} title={t("mttr_hint")} />,
      <KpiTile key="mtta" label={t("mtta")} icon={<Timer />} tone="danger" value={mins(k.mtta_minutes)} trend={k.mtta_trend} caption={t("mtta_hint")} title={t("mtta_hint")} />,
    );
  }

  if (maintenance) {
    tiles.push(
      <KpiTile
        key="wo"
        label={t("open_work_orders")}
        icon={<ClipboardList />}
        tone="brand"
        value={maintenance.open_work_orders}
        caption={t("completed_in_period", { count: maintenance.completed_work_orders ?? 0 })}
      />,
      <KpiTile
        key="bd"
        label={t("active_breakdowns")}
        icon={<Gauge />}
        tone={maintenance.active_breakdowns > 0 ? "danger" : "success"}
        value={maintenance.active_breakdowns}
        caption={t("reported_in_period", { count: maintenance.breakdowns_reported ?? 0 })}
      />,
    );
  }

  if (tiles.length === 0) return null;

  return <div className={cn("grid grid-cols-1 gap-4 sm:grid-cols-2", tiles.length >= 6 ? "md:grid-cols-3 2xl:grid-cols-6" : "lg:grid-cols-4")}>{tiles}</div>;
}

// ---------------------------------------------------------------- trend & fleet

async function TrendCard({ trend, className }) {
  const t = await getT("dashboard");
  const lines = [
    { key: "breakdowns", label: t("breakdowns_reported"), color: "var(--danger)" },
    { key: "completed_work_orders", label: t("work_orders_completed"), color: "var(--success)" },
  ];
  const total = (key) => trend.reduce((sum, point) => sum + (point[key] ?? 0), 0);

  return (
    <Panel
      className={className}
      title={t("trend_title")}
      hint={trend.length > 31 ? t("weekly") : t("daily")}
      aside={
        <div className="hidden flex-wrap items-center gap-x-4 gap-y-1 sm:flex">
          {lines.map((line) => (
            <span key={line.key} className="inline-flex items-center gap-1.5 text-xs text-foreground-muted">
              <Swatch color={line.color} />
              {line.label}
              <span className="tabular font-semibold text-foreground">{total(line.key)}</span>
            </span>
          ))}
        </div>
      }
    >
      {total("breakdowns") + total("completed_work_orders") === 0 ? (
        <div className="h-60">
          <EmptyLine>{t("no_failures")}</EmptyLine>
        </div>
      ) : (
        <TrendBarChart series={trend} lines={lines} />
      )}
    </Panel>
  );
}

const ASSET_ROWS = [
  { key: "running", color: "var(--success)" },
  { key: "idle", color: "var(--foreground-subtle)" },
  { key: "under_maintenance", color: "var(--warning)" },
  { key: "under_repair", color: "var(--info)" },
  { key: "breakdown", color: "var(--danger)" },
];

async function MachineStatusCard({ management, className }) {
  const t = await getT("dashboard");
  const assets = management.assets;

  return (
    <Panel className={className} title={t("asset_status")} href="/assets" linkLabel={t("view_all")}>
      <div className="flex flex-col items-center gap-5 sm:flex-row lg:flex-col xl:flex-row">
        <StatusDonut
          segments={ASSET_ROWS.map((row) => ({ key: row.key, label: t(row.key), value: assets[row.key] ?? 0, color: row.color }))}
          total={assets.total}
          totalLabel={t("total_assets")}
        />
        <ul className="flex w-full flex-1 flex-col gap-2.5 text-sm">
          {ASSET_ROWS.map((row) => {
            const value = assets[row.key] ?? 0;
            const pct = assets.total > 0 ? Math.round((value / assets.total) * 100) : 0;

            return (
              <li key={row.key} className="flex items-center gap-2">
                <Swatch color={row.color} />
                <span className="flex-1 truncate text-foreground-muted">{t(row.key)}</span>
                <span className="tabular font-semibold text-foreground">{value}</span>
                <span className="tabular w-10 text-right text-xs text-foreground-subtle">{pct}%</span>
              </li>
            );
          })}
        </ul>
      </div>
    </Panel>
  );
}

// ---------------------------------------------------------------- live lists

async function OpenBreakdownsCard({ maintenance, className }) {
  const t = await getT("dashboard");
  const tb = await getT("breakdown");
  const rows = maintenance.open_breakdown_list ?? [];

  return (
    <Panel
      className={className}
      title={t("open_breakdowns")}
      hint={t("open_breakdowns_hint")}
      href="/breakdowns"
      linkLabel={t("view_all")}
      aside={maintenance.active_breakdowns > 0 ? <Badge variant="danger">{maintenance.active_breakdowns}</Badge> : null}
    >
      {rows.length === 0 ? (
        <EmptyLine>{t("no_open_breakdowns")}</EmptyLine>
      ) : (
        <ul className="flex flex-col gap-2">
          {rows.map((row) => (
            <li key={row.id}>
              <Link
                href={`/breakdowns/${refOf(row, "breakdown_number")}`}
                className={cn("flex items-center gap-3 rounded-sm border border-l-4 border-border bg-surface px-3 py-2 transition-colors hover:bg-surface-muted", PRIORITY_EDGE[row.priority] ?? "border-l-border-strong")}
              >
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 text-sm">
                    <span className="shrink-0 font-semibold text-foreground">{row.asset_code}</span>
                    <span className="truncate text-foreground-muted">{row.asset_name}</span>
                  </div>
                  <div className="mt-0.5 truncate text-xs text-foreground-subtle">
                    {row.breakdown_number}
                    {row.line ? ` · ${row.line}` : ""} · <RelativeTime value={row.reported_at} />
                  </div>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center">
                  {row.priority ? <Badge variant={PRIORITY_TONE[row.priority] ?? "neutral"}>{tb(`priority_${row.priority.toLowerCase()}`)}</Badge> : null}
                  <StatusBadge status={row.status} label={tb(`status_${row.status.toLowerCase()}`)} />
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

async function PmCard({ maintenance, className }) {
  const t = await getT("dashboard");
  const tm = await getT("maintenance");
  const rows = maintenance.pm_queue ?? [];
  const pct = maintenance.pm_compliance_percent;
  const tone = pct === null ? "brand" : pct >= 80 ? "success" : pct >= 50 ? "warning" : "danger";
  const status = pct === null ? null : pct >= 80 ? t("on_track") : pct >= 50 ? t("needs_attention") : t("at_risk");

  const stats = [
    { key: "today", label: t("todays_tasks"), value: maintenance.today, hot: false },
    { key: "week", label: t("next_7_days"), value: maintenance.upcoming_week ?? 0, hot: false },
    { key: "overdue", label: t("overdue_badge"), value: maintenance.overdue, hot: maintenance.overdue > 0 },
  ];

  return (
    <Panel className={className} title={t("pm_queue")} hint={t("pm_queue_hint")} href="/maintenance/schedule" linkLabel={t("view_all")}>
      <div className="flex flex-wrap items-center gap-4 rounded-sm bg-surface-muted p-3" title={t("pm_compliance_hint")}>
        <div className="flex items-center gap-3">
          {pct === null ? (
            <span title={t("not_available_reason")} className="flex size-14 items-center justify-center rounded-full border-[6px] border-border text-base font-semibold text-foreground-muted">—</span>
          ) : (
            <RadialProgress value={pct} tone={tone} size={56} strokeWidth={6} />
          )}
          <div>
            <div className="text-xs font-medium text-foreground-muted">{t("pm_compliance")}</div>
            {status ? <Badge variant={tone} className="mt-1">{status}</Badge> : null}
          </div>
        </div>
        <div className="ml-auto grid grid-cols-3 gap-4">
          {stats.map((stat) => (
            <div key={stat.key} className="text-right">
              <div className={cn("tabular text-lg font-bold", stat.hot ? "text-danger" : "text-foreground")}>{stat.value}</div>
              <div className="text-xs whitespace-nowrap text-foreground-muted">{stat.label}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="mt-3">
        {rows.length === 0 ? (
          <EmptyLine>{t("no_pm_queue")}</EmptyLine>
        ) : (
          <ul className="divide-y divide-border">
            {rows.map((row) => (
              <li key={row.id} className="flex items-center gap-3 py-2">
                <span className={cn("size-2 shrink-0 rounded-full", row.overdue ? "bg-danger" : row.status === "IN_PROGRESS" ? "bg-info" : "bg-warning")} />
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 text-sm">
                    <span className="shrink-0 font-semibold text-foreground">{row.asset_code}</span>
                    <span className="truncate text-foreground-muted">{row.asset_name}</span>
                  </div>
                  <div className="truncate text-xs text-foreground-subtle">
                    {row.plan_number ? `${row.plan_number} · ` : ""}
                    {row.plan_name}
                  </div>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-0.5">
                  {row.overdue ? <Badge variant="danger">{t("overdue_badge")}</Badge> : <span className="text-xs text-foreground-muted">{tm(`status_${row.status.toLowerCase()}`)}</span>}
                  <span className={cn("text-xs", row.overdue ? "text-danger" : "text-foreground-subtle")}>
                    <RelativeTime value={row.due_at} />
                  </span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Panel>
  );
}

// ---------------------------------------------------------------- people, machines, money

function initials(name) {
  return name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

function workloadTone(row) {
  if (row.at_capacity) return "danger";
  if (row.max_concurrent_work_orders && row.open_count / row.max_concurrent_work_orders >= 0.7) return "warning";
  return "brand";
}

async function WorkloadCard({ maintenance, className }) {
  const t = await getT("dashboard");
  const onDuty = maintenance.workload.filter((row) => row.open_count > 0).length;
  const rows = [...maintenance.workload].sort((a, b) => {
    const load = (row) => (row.max_concurrent_work_orders ? row.open_count / row.max_concurrent_work_orders : row.open_count > 0 ? 1 : 0);
    return load(b) - load(a) || b.open_count - a.open_count;
  });

  return (
    <Panel
      className={className}
      title={t("technician_workload")}
      href="/technicians"
      linkLabel={t("view_all")}
      aside={maintenance.workload.length > 0 ? <Badge variant="brand">{t("on_duty_count", { count: onDuty, total: maintenance.workload.length })}</Badge> : null}
    >
      {rows.length === 0 ? (
        <p className="text-sm text-foreground-muted">{t("no_technicians")}</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {rows.slice(0, 7).map((row) => {
            const tone = workloadTone(row);

            return (
              <li key={row.technician_id} className="flex items-center gap-3">
                <span className={cn("flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold", TONE[tone].icon)}>{initials(row.name)}</span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="truncate font-medium text-foreground">{row.name}</span>
                    <span className="tabular shrink-0">
                      <span className="font-semibold text-foreground">{row.open_count}</span>
                      {row.max_concurrent_work_orders !== null ? <span className="text-foreground-muted"> / {row.max_concurrent_work_orders}</span> : null}
                    </span>
                  </div>
                  {row.max_concurrent_work_orders ? (
                    <ProgressBar value={row.open_count} max={row.max_concurrent_work_orders} tone={tone} className="mt-1.5" />
                  ) : (
                    <div className="mt-1 text-xs text-foreground-subtle">{row.employee_id}</div>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </Panel>
  );
}

async function TopFailingCard({ management, className }) {
  const t = await getT("dashboard");
  const rows = management.top_failing_assets ?? [];
  const max = Math.max(1, ...rows.map((row) => row.failures));

  return (
    <Panel className={className} title={t("top_failing")} hint={t("top_failing_hint")}>
      {rows.length === 0 ? (
        <EmptyLine>{t("no_failures")}</EmptyLine>
      ) : (
        <ol className="flex flex-col gap-3">
          {rows.map((row, index) => (
            <li key={row.asset_id}>
              <Link href={`/assets/${refOf(row, "asset_code")}`} className="group flex items-center gap-3">
                <span className="tabular flex size-6 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-foreground-muted">{index + 1}</span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="truncate">
                      <span className="font-semibold text-foreground group-hover:text-brand">{row.asset_code}</span>
                      <span className="text-foreground-muted"> {row.name}</span>
                    </span>
                    <span className="tabular shrink-0 font-semibold text-foreground">{t("times", { count: row.failures })}</span>
                  </div>
                  <ProgressBar value={row.failures} max={max} tone="danger" className="mt-1.5" />
                </div>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </Panel>
  );
}

async function CostDowntimeCard({ management, className }) {
  const t = await getT("dashboard");
  const { cost, kpis: k } = management;
  const total = Number(cost.total) || 0;
  const share = (value) => (total > 0 ? (Number(value) / total) * 100 : 0);
  const hours = (minutes) => (minutes === null || minutes === undefined ? t("na") : `${(minutes / 60).toFixed(1)} ${t("hours")}`);

  return (
    <Panel className={className} title={t("cost_downtime")} hint={t("cost_hint")}>
      <div className="flex items-baseline justify-between">
        <span className="text-xs font-medium text-foreground-muted">{t("total_cost")}</span>
        <span className="tabular text-2xl font-bold text-foreground">{money(cost.total)}</span>
      </div>
      <div className="mt-2 flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-surface-muted">
        {share(cost.maintenance) > 0 ? <div className="h-full bg-brand" style={{ width: `${share(cost.maintenance)}%` }} /> : null}
        {share(cost.breakdown) > 0 ? <div className="h-full bg-danger" style={{ width: `${share(cost.breakdown)}%` }} /> : null}
      </div>
      <dl className="mt-2 grid grid-cols-1 gap-1.5 text-xs sm:grid-cols-2 sm:gap-x-6">
        <div className="flex items-center gap-1.5">
          <Swatch color="var(--brand)" />
          <dt className="text-foreground-muted">{t("maintenance_cost")}</dt>
          <dd className="tabular ml-auto font-semibold text-foreground">{money(cost.maintenance)}</dd>
        </div>
        <div className="flex items-center gap-1.5">
          <Swatch color="var(--danger)" />
          <dt className="text-foreground-muted">{t("breakdown_cost")}</dt>
          <dd className="tabular ml-auto font-semibold text-foreground">{money(cost.breakdown)}</dd>
        </div>
      </dl>

      <dl className="mt-4 flex flex-col gap-2 border-t border-border pt-3 text-sm">
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("scheduled_minutes")}</dt>
          <dd className="tabular">{hours(k.scheduled_operating_minutes)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("operating_minutes")}</dt>
          <dd className="tabular">{hours(k.operating_minutes)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("unplanned_downtime")}</dt>
          <dd className="tabular font-semibold text-danger">{hours(k.unplanned_downtime_minutes)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("failures")}</dt>
          <dd className="tabular font-semibold">{k.failure_count}</dd>
        </div>
      </dl>
    </Panel>
  );
}

// ---------------------------------------------------------------- store

async function StockOverviewCard({ store, className }) {
  const t = await getT("dashboard");
  const healthy = Math.max(store.total_parts - store.low_stock, 0);
  const low = Math.max(store.low_stock - store.out_of_stock, 0);
  const segments = [
    { key: "healthy", label: t("healthy"), value: healthy, color: "var(--success)" },
    { key: "low", label: t("low_stock"), value: low, color: "var(--warning)" },
    { key: "out", label: t("out_of_stock"), value: store.out_of_stock, color: "var(--danger)" },
  ];

  return (
    <Panel className={className} title={t("stock_overview")} href="/inventory/stock" linkLabel={t("view_all")}>
      <div className="text-xs font-medium text-foreground-muted">{t("stock_value")}</div>
      <div className="tabular text-3xl font-bold tracking-tight text-foreground">{money(store.stock_value)}</div>

      <div className="mt-4 flex items-center justify-between text-xs text-foreground-muted">
        <span>{t("part_health")}</span>
        <span className="tabular">
          {store.total_parts} {t("total_parts")}
        </span>
      </div>
      <div className="mt-1.5 flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-surface-muted">
        {segments
          .filter((segment) => segment.value > 0)
          .map((segment) => (
            <div key={segment.key} className="h-full" style={{ width: `${(segment.value / Math.max(store.total_parts, 1)) * 100}%`, background: segment.color }} />
          ))}
      </div>
      <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
        {segments.map((segment) => (
          <li key={segment.key} className="inline-flex items-center gap-1.5 text-foreground-muted">
            <Swatch color={segment.color} />
            {segment.label}
            <span className="tabular font-semibold text-foreground">{segment.value}</span>
          </li>
        ))}
      </ul>

      <dl className="mt-4 flex flex-col gap-2 border-t border-border pt-3 text-sm">
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("parts_issued")}</dt>
          <dd className="tabular font-semibold">{money(store.issued_value)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("reserved")}</dt>
          <dd className="tabular">{qty(store.reserved_quantity)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-foreground-muted">{t("active_reservations")}</dt>
          <dd className="tabular">{store.active_reservations}</dd>
        </div>
      </dl>
    </Panel>
  );
}

async function ReorderCard({ store, className }) {
  const t = await getT("dashboard");
  const rows = store.low_stock_items ?? [];

  return (
    <Panel
      className={className}
      title={t("reorder_list")}
      hint={t("reorder_list_hint")}
      href="/inventory/stock"
      linkLabel={t("view_all")}
      aside={store.low_stock > 0 ? <Badge variant="warning">{store.low_stock}</Badge> : null}
      bodyClassName="pt-3"
    >
      {rows.length === 0 ? (
        <EmptyLine>{t("no_low_stock")}</EmptyLine>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs text-foreground-muted">
                <th className="py-2 pr-3 font-medium">{t("total_parts")}</th>
                <th className="w-[40%] py-2 pr-3 font-medium">{t("on_hand")}</th>
                <th className="py-2 text-right font-medium">{t("reorder_level")}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rows.map((row) => {
                const onHand = Number(row.on_hand);
                const reorder = Number(row.reorder_level);

                return (
                  <tr key={row.id}>
                    <td className="py-2 pr-3">
                      <Link href={`/inventory/parts/${refOf(row, "part_number")}`} className="group block min-w-0">
                        <span className="flex items-center gap-2">
                          <span className="font-semibold text-foreground group-hover:text-brand">{row.part_number}</span>
                          {row.is_critical_spare ? <Badge variant="danger">{t("critical")}</Badge> : null}
                        </span>
                        <span className="block max-w-64 truncate text-xs text-foreground-muted">{row.name}</span>
                      </Link>
                    </td>
                    <td className="py-2 pr-3">
                      <div className="flex items-center gap-2">
                        <span className={cn("tabular w-16 shrink-0 font-semibold", onHand <= 0 ? "text-danger" : "text-warning")}>
                          {qty(row.on_hand)} <span className="text-xs font-normal text-foreground-subtle">{row.unit}</span>
                        </span>
                        <ProgressBar value={onHand} max={Math.max(reorder, 1)} tone={onHand <= 0 ? "danger" : "warning"} />
                      </div>
                    </td>
                    <td className="tabular py-2 text-right text-foreground-muted">{qty(row.reorder_level)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </Panel>
  );
}

export {
  AttentionStrip,
  KpiRow,
  TrendCard,
  MachineStatusCard,
  OpenBreakdownsCard,
  PmCard,
  WorkloadCard,
  TopFailingCard,
  CostDowntimeCard,
  StockOverviewCard,
  ReorderCard,
};
