import { StatCard, TrendBadge } from "@/components/ui/stat-card";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { RadialProgress } from "@/components/ui/radial-progress";
import { ProgressBar } from "@/components/ui/progress-bar";
import { TrendAreaChart } from "@/components/dashboard/trend-area-chart";
import { DashboardSectionHeader } from "@/components/dashboard/section-header";
import { getT } from "@/lib/i18n-server";
import { Wrench, CalendarClock, AlertTriangle, ClipboardList, AlertOctagon } from "lucide-react";

function initials(name) {
  return name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

const WORKLOAD_TONE = {
  danger: { avatar: "bg-danger-subtle text-danger ring-danger/20", bar: "danger", chip: "text-danger" },
  warning: { avatar: "bg-warning-subtle text-warning ring-warning/20", bar: "warning", chip: "text-warning" },
  brand: { avatar: "bg-brand-subtle text-brand-hover ring-brand/15", bar: "brand", chip: "text-foreground-muted" },
};

/** At capacity is its own tone; short of that, nearing capacity (70%+) is worth flagging before it becomes a problem. */
function workloadTone(row) {
  if (row.at_capacity) return "danger";
  if (row.max_concurrent_work_orders && row.open_count / row.max_concurrent_work_orders >= 0.7) return "warning";
  return "brand";
}

/** Mirrors `dashboard/_maintenance.blade.php` — unacknowledged, not merely active: a machine down that nobody has picked up is the number worth watching. */
async function MaintenancePanel({ data, trend }) {
  const t = await getT("dashboard");

  const onDutyCount = data.workload.filter((row) => row.open_count > 0).length;
  const sortedWorkload = [...data.workload].sort((a, b) => {
    const pctOf = (row) => (row.max_concurrent_work_orders ? row.open_count / row.max_concurrent_work_orders : row.open_count > 0 ? 1 : 0);
    return pctOf(b) - pctOf(a) || b.open_count - a.open_count;
  });
  const compliancePercent = data.pm_compliance_percent;
  const complianceTone = compliancePercent === null ? "brand" : compliancePercent >= 80 ? "success" : compliancePercent >= 50 ? "warning" : "danger";
  const complianceStatus = compliancePercent === null ? null : compliancePercent >= 80 ? t("on_track") : compliancePercent >= 50 ? t("needs_attention") : t("at_risk");

  return (
    <section className="flex flex-col gap-4">
      <DashboardSectionHeader icon={<Wrench />} title={t("maintenance_dashboard")} tone="warning" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("todays_tasks")} icon={<CalendarClock />} tone="brand" value={data.today} />
        <StatCard label={t("overdue_maintenance")} icon={<AlertTriangle />} tone={data.overdue > 0 ? "danger" : "success"} value={data.overdue} supportingText={t("overdue_hint")} />
        <StatCard label={t("open_work_orders")} icon={<ClipboardList />} tone="info" value={data.open_work_orders} trend={data.completed_work_orders_trend} />
        <StatCard label={t("unacknowledged")} icon={<AlertOctagon />} tone={data.unacknowledged_breakdowns > 0 ? "danger" : "success"} value={data.unacknowledged_breakdowns} supportingText={t("unacknowledged_hint")} />
      </div>

      {trend ? (
        <Card>
          <CardHeader>
            <div>
              <CardTitle>{t("trend_title")}</CardTitle>
              <p className="mt-1 text-xs text-foreground-muted">{t("trend_hint")}</p>
            </div>
          </CardHeader>
          <CardBody>
            <TrendAreaChart
              series={trend}
              lines={[
                { key: "breakdowns", label: t("breakdowns_reported"), color: "var(--danger)" },
                { key: "completed_work_orders", label: t("work_orders_completed"), color: "var(--success)" },
              ]}
            />
          </CardBody>
        </Card>
      ) : null}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Card className="lg:col-span-5">
          <CardHeader>
            <CardTitle>{t("pm_compliance")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col items-center gap-5">
            {compliancePercent === null ? (
              <div className="flex h-[136px] items-center justify-center">
                <span className="text-2xl font-semibold text-foreground-muted">{t("na")}</span>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2">
                <RadialProgress value={compliancePercent} tone={complianceTone} size={136} strokeWidth={11} />
                <Badge variant={complianceTone}>{complianceStatus}</Badge>
              </div>
            )}

            <div className="grid w-full grid-cols-2 gap-3">
              <div className="rounded-sm bg-surface-muted p-3">
                <span className="text-xs font-medium text-foreground-muted">{t("due")}</span>
                <div className="tabular mt-1 text-xl font-semibold text-foreground">{data.due}</div>
              </div>
              <div className="rounded-sm bg-surface-muted p-3">
                <span className="text-xs font-medium text-foreground-muted">{t("active_breakdowns")}</span>
                <div className="mt-1 flex items-baseline gap-1.5">
                  <span className="tabular text-xl font-semibold text-foreground">{data.active_breakdowns}</span>
                  <TrendBadge value={data.active_breakdowns_trend} />
                </div>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card className="lg:col-span-7">
          <CardHeader>
            <div className="flex w-full items-center justify-between">
              <CardTitle>{t("technician_workload")}</CardTitle>
              {onDutyCount > 0 ? (
                <Badge variant="brand">{t("on_duty_count", { count: onDutyCount, total: data.workload.length })}</Badge>
              ) : null}
            </div>
          </CardHeader>
          <CardBody>
            {data.workload.length === 0 ? (
              <p className="text-sm text-foreground-muted">{t("no_technicians")}</p>
            ) : (
              <div className="flex flex-col gap-1">
                {sortedWorkload.map((row) => {
                  const tone = WORKLOAD_TONE[workloadTone(row)];

                  return (
                    <div key={row.technician_id} className="flex items-center gap-3 rounded-sm px-2 py-2.5 transition-colors hover:bg-surface-muted">
                      <span className={`flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold ring-4 ${tone.avatar}`}>
                        {initials(row.name)}
                      </span>

                      <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between gap-2">
                          <span className="truncate text-sm font-medium text-foreground">{row.name}</span>
                          <div className="flex shrink-0 items-center gap-2">
                            <span className="tabular text-sm">
                              <span className="font-semibold text-foreground">{row.open_count}</span>
                              {row.max_concurrent_work_orders !== null ? (
                                <span className="text-foreground-muted"> / {row.max_concurrent_work_orders}</span>
                              ) : null}
                            </span>
                            {row.at_capacity ? <Badge variant="danger">{t("at_capacity")}</Badge> : null}
                          </div>
                        </div>
                        <div className="mt-1.5 flex items-center gap-2">
                          <span className="shrink-0 text-xs text-foreground-muted">{row.employee_id}</span>
                          {row.max_concurrent_work_orders ? (
                            <ProgressBar value={row.open_count} max={row.max_concurrent_work_orders} tone={tone.bar} className="ml-auto" />
                          ) : null}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </section>
  );
}

export { MaintenancePanel };
