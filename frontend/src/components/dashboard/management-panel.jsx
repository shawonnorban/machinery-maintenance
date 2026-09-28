import { StatCard, TrendBadge } from "@/components/ui/stat-card";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { RadialProgress } from "@/components/ui/radial-progress";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { DashboardSectionHeader } from "@/components/dashboard/section-header";
import { getT } from "@/lib/i18n-server";
import { LineChart, Gauge, Clock, Wrench, Timer } from "lucide-react";

const ASSET_ROWS = ["running", "idle", "breakdown", "under_maintenance", "under_repair"];
const ASSET_DONUT_COLOR = {
  running: "var(--success)",
  idle: "var(--warning)",
  breakdown: "var(--danger)",
  under_maintenance: "var(--warning)",
  under_repair: "var(--danger)",
};

/** Mirrors `dashboard/_management.blade.php` — every tile shows N/A with a reason rather than a fabricated zero when a figure genuinely isn't available yet. */
async function ManagementPanel({ data }) {
  const t = await getT("dashboard");
  const k = data.kpis;

  const asHours = (minutes) => (minutes === null || minutes === undefined ? t("na") : `${(minutes / 60).toFixed(1)} ${t("hours")}`);
  const currency = (value) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 });

  return (
    <section className="flex flex-col gap-4">
      <DashboardSectionHeader icon={<LineChart />} title={t("management")} tone="brand" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {/* Deliberately the same shape and size as the other three tiles —
            not a wider "hero" card. That earlier version used a responsive
            col-span (sm:col-span-2 xl:col-span-1) to widen it, which looked
            fine at the two breakpoints it was tested at but broke the grid
            at every width in between (lg and the top of md): at those
            widths the 2-column grid was still active but xl:col-span-1
            wasn't yet, so this card spanned both columns alone and pushed
            the fourth tile (Response time) onto its own half-empty row.
            Matching StatCard's own label/value/caption structure exactly,
            just with a small ring in place of the usual icon badge, keeps
            it in the same grid cell as everything else at every width. */}
        <Card className="border-l-4 border-l-success p-5 transition-shadow duration-150 hover:shadow-sm">
          <div className="flex items-start justify-between gap-3">
            <span className="text-xs font-medium text-foreground-muted">{t("availability")}</span>
            <span className="flex size-10 items-center justify-center rounded-full bg-success-subtle ring-4 ring-success/10">
              <RadialProgress value={k.availability_percent ?? 0} tone="success" size={26} strokeWidth={4} showValue={false} />
            </span>
          </div>
          <div className="mt-3 flex items-baseline gap-2">
            <span className="tabular text-3xl font-bold tracking-tight text-foreground">
              {k.availability_percent === null ? t("na") : `${k.availability_percent}%`}
            </span>
            <TrendBadge value={k.availability_trend} />
          </div>
          {k.availability_percent !== null ? <p className="mt-1.5 text-xs text-foreground-subtle">{t("availability_hint")}</p> : null}
        </Card>
        <StatCard label={t("mtbf")} icon={<Clock />} tone="info" value={asHours(k.mtbf_minutes)} trend={k.mtbf_trend} supportingText={t("mtbf_hint")} />
        <StatCard label={t("mttr")} icon={<Wrench />} tone="warning" value={k.mttr_minutes === null ? t("na") : `${Math.round(k.mttr_minutes)} ${t("minutes")}`} trend={k.mttr_trend} supportingText={t("mttr_hint")} />
        <StatCard label={t("mtta")} icon={<Timer />} tone="danger" value={k.mtta_minutes === null ? t("na") : `${Math.round(k.mtta_minutes)} ${t("minutes")}`} trend={k.mtta_trend} supportingText={t("mtta_hint")} />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Card className="lg:col-span-7">
          <CardHeader className="items-center">
            <CardTitle>{t("total_assets")}</CardTitle>
            <span className="text-sm font-semibold text-foreground">{data.assets.total}</span>
          </CardHeader>
          <CardBody className="flex flex-col gap-6 sm:flex-row sm:items-center">
            <StatusDonut
              segments={ASSET_ROWS.map((status) => ({
                key: status,
                label: t(status),
                value: data.assets[status] ?? 0,
                color: ASSET_DONUT_COLOR[status],
              }))}
              total={data.assets.total}
              totalLabel={t("total_assets")}
            />

            <dl className="flex flex-1 flex-col gap-2 text-sm">
              {ASSET_ROWS.map((status) => (
                <div key={status} className="flex items-center justify-between border-b border-border pb-2 last:border-b-0">
                  <dt><StatusBadge status={status.toUpperCase()} label={t(status)} /></dt>
                  <dd className="tabular font-semibold text-foreground">{data.assets[status] ?? 0}</dd>
                </div>
              ))}
              <div className="flex items-center justify-between pt-1">
                <dt className="text-foreground-muted">
                  {t("overdue_maintenance")}
                  <div className="text-xs text-foreground-subtle">{t("overdue_hint")}</div>
                </dt>
                <dd className={`tabular font-semibold ${data.overdue_maintenance > 0 ? "text-danger" : "text-foreground"}`}>
                  {data.overdue_maintenance}
                </dd>
              </div>
            </dl>
          </CardBody>
        </Card>

        <div className="flex flex-col gap-4 lg:col-span-5">
          <Card>
            <CardHeader>
              <CardTitle>{t("cost")}</CardTitle>
            </CardHeader>
            <CardBody>
              <dl className="flex flex-col gap-2 text-sm">
                <div className="flex justify-between">
                  <dt>{t("maintenance_cost")}</dt>
                  <dd className="tabular">{currency(data.cost.maintenance)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>{t("breakdown_cost")}</dt>
                  <dd className="tabular text-danger">{currency(data.cost.breakdown)}</dd>
                </div>
                <div className="flex justify-between border-t border-border pt-2 font-semibold">
                  <dt>{t("total_cost")}</dt>
                  <dd className="tabular">{currency(data.cost.total)}</dd>
                </div>
              </dl>
            </CardBody>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t("downtime_minutes")}</CardTitle>
            </CardHeader>
            <CardBody>
              <dl className="flex flex-col gap-2 text-sm">
                <div className="flex justify-between">
                  <dt>{t("scheduled_minutes")}</dt>
                  <dd className="tabular">{asHours(k.scheduled_operating_minutes)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>{t("operating_minutes")}</dt>
                  <dd className="tabular">{asHours(k.operating_minutes)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>{t("unplanned_downtime")}</dt>
                  <dd className="tabular text-danger">{asHours(k.unplanned_downtime_minutes)}</dd>
                </div>
                <div className="flex justify-between border-t border-border pt-2 font-semibold">
                  <dt>{t("failures")}</dt>
                  <dd className="tabular">{k.failure_count}</dd>
                </div>
              </dl>
            </CardBody>
          </Card>
        </div>
      </div>
    </section>
  );
}

export { ManagementPanel };
