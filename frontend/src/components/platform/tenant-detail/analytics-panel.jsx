import { Factory, Boxes, Users, ClipboardList } from "lucide-react";
import { Card, CardBody } from "@/components/ui/card";
import { TrendBadge } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";

const METRICS = {
  ACTIVE_FACTORIES: { label: "Active factories", icon: Factory, tone: "brand" },
  ACTIVE_ASSETS: { label: "Active assets", icon: Boxes, tone: "success" },
  ACTIVE_USERS: { label: "Active users", icon: Users, tone: "info" },
  WORK_ORDERS_CREATED: { label: "Work orders created", icon: ClipboardList, tone: "warning" },
};

const TONE_BADGE = {
  brand: "bg-brand-subtle text-brand-hover",
  success: "bg-success-subtle text-success",
  warning: "bg-warning-subtle text-warning",
  info: "bg-info-subtle text-info",
};

const TONE_BORDER = {
  brand: "border-l-brand",
  success: "border-l-success",
  warning: "border-l-warning",
  info: "border-l-info",
};

const TONE_BAR = {
  brand: "bg-brand",
  success: "bg-success",
  warning: "bg-warning",
  info: "bg-info",
};

/**
 * Twelve months of real, already-recorded usage — `UsageMeter` measures
 * every company monthly, so this reads that history back rather than
 * computing a fresh trend (mirrors `_usage.blade.php`). Styled after
 * `StatCard` (the same "current value + trend" language the rest of the
 * console already uses) rather than a from-scratch chart: a new company
 * with only one or two months on record is the common case here, and a
 * bar chart with one bar just reads as a solid block, not a chart — this
 * leads with the number that's actually true regardless of how much
 * history exists yet, and treats the bar row as a bonus underneath it.
 */
function AnalyticsPanel({ usage }) {
  return (
    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
      {Object.entries(usage).map(([metric, series]) => (
        <MetricCard key={metric} metric={metric} series={series} />
      ))}
    </div>
  );
}

function MetricCard({ metric, series }) {
  const meta = METRICS[metric] ?? { label: metric, icon: Factory, tone: "brand" };
  const Icon = meta.icon;
  const entries = Object.entries(series);
  const max = Math.max(1, ...entries.map(([, value]) => value));

  const latest = entries.at(-1);
  const previous = entries.at(-2);
  const trend =
    previous && Number(previous[1]) > 0 ? Math.round(((latest[1] - previous[1]) / previous[1]) * 100) : undefined;

  return (
    <Card className={cn("border-l-4 p-5", TONE_BORDER[meta.tone])}>
      <div className="flex items-start justify-between gap-3">
        <span className="text-xs font-medium text-foreground-muted">{meta.label}</span>
        <span className={cn("flex size-10 items-center justify-center rounded-full ring-4", TONE_BADGE[meta.tone])}>
          <Icon className="size-4.5" />
        </span>
      </div>

      {entries.length === 0 ? (
        <p className="mt-3 text-sm text-foreground-muted">No data yet.</p>
      ) : (
        <>
          <div className="mt-3 flex items-baseline gap-2">
            <span className="tabular text-3xl font-bold tracking-tight text-foreground">{latest[1]}</span>
            <TrendBadge value={trend} />
          </div>
          <p className="mt-1.5 text-xs text-foreground-subtle">{latest[0]}</p>

          {entries.length > 1 ? (
            <div className="mt-4 flex h-12 items-end gap-1.5 border-t border-border pt-3">
              {entries.map(([month, value]) => (
                <div
                  key={month}
                  className={cn("w-3 shrink-0 rounded-t-sm opacity-70", TONE_BAR[meta.tone])}
                  style={{ height: `${Math.max(8, (value / max) * 100)}%` }}
                  title={`${month}: ${value}`}
                />
              ))}
            </div>
          ) : null}
        </>
      )}
    </Card>
  );
}

export { AnalyticsPanel };
