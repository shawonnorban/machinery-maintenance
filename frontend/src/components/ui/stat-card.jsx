import { TrendingUp, TrendingDown } from "lucide-react";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/utils";

const TONE_BADGE = {
  brand: "bg-brand-subtle text-brand-hover ring-brand/10",
  success: "bg-success-subtle text-success ring-success/10",
  warning: "bg-warning-subtle text-warning ring-warning/10",
  danger: "bg-danger-subtle text-danger ring-danger/10",
  info: "bg-info-subtle text-info ring-info/10",
};

const TONE_BORDER = {
  brand: "border-l-brand",
  success: "border-l-success",
  warning: "border-l-warning",
  danger: "border-l-danger",
  info: "border-l-info",
};

/**
 * Shared with any card that needs the same "+X% from last period" pill
 * outside `StatCard`'s own layout (e.g. a custom tile with a chart in its
 * corner). A flat 0% renders nothing — a badge that says "no change" reads
 * as noise sitting right next to the number it's supposedly qualifying.
 */
function TrendBadge({ value, className }) {
  if (value === undefined || value === null || value === 0) {
    return null;
  }

  const positive = value > 0;
  const Icon = positive ? TrendingUp : TrendingDown;

  return (
    <span
      className={cn(
        "inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold",
        positive ? "bg-success-subtle text-success" : "bg-danger-subtle text-danger",
        className,
      )}
    >
      <Icon className="size-3" />
      <span className="tabular">{Math.abs(value)}%</span>
    </span>
  );
}

/**
 * docs/UI-DESIGN-SYSTEM.md §4: value, label, trend, icon, supporting text.
 * Numbers use `.tabular` — a KPI read at a glance, often from a distance.
 *
 * `tone` colours the icon badge and the left accent border — a small,
 * deliberate signal of what kind of number this is (a count that's fine vs.
 * one that needs attention) rather than "over-colourful surfaces," which the
 * design doc explicitly warns against. Every tile defaulting to the same
 * brand blue regardless of meaning was the actual "plain" complaint this was
 * added for.
 *
 * `icon` is a rendered node (e.g. `<Wrench />`), not a component
 * reference — a Server Component caller can pass JSX like that across the
 * client boundary, but not a raw function.
 */
function StatCard({ label, value, icon, tone = "brand", trend, supportingText, className }) {
  return (
    <Card className={cn("border-l-4 p-5 transition-shadow duration-150 hover:shadow-sm", TONE_BORDER[tone] ?? TONE_BORDER.brand, className)}>
      <div className="flex items-start justify-between gap-3">
        <span className="text-xs font-medium text-foreground-muted">{label}</span>
        {icon ? (
          <span className={cn("flex size-10 items-center justify-center rounded-full ring-4 [&_svg]:size-4.5", TONE_BADGE[tone] ?? TONE_BADGE.brand)}>
            {icon}
          </span>
        ) : null}
      </div>

      <div className="mt-3 flex items-baseline gap-2">
        <span className="tabular text-3xl font-bold tracking-tight text-foreground">{value}</span>
        <TrendBadge value={trend} />
      </div>

      {supportingText ? (
        <p className="mt-1.5 text-xs text-foreground-subtle">{supportingText}</p>
      ) : null}
    </Card>
  );
}

export { StatCard, TrendBadge };
