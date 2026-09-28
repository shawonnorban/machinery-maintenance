import { cn } from "@/lib/utils";

const TONE_CLASS = {
  brand: "bg-brand",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-danger",
  info: "bg-info",
};

/**
 * A thin, rounded-full fill bar — the "pill" shape the design doc reserves
 * `rounded-full` for. Used wherever a proportion (technician capacity, a
 * status split) is more scannable as a bar than as another number.
 *
 * @param {{ value: number, max?: number, tone?: "brand"|"success"|"warning"|"danger"|"info", className?: string }} props
 */
function ProgressBar({ value, max = 100, tone = "brand", className }) {
  const pct = max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;

  return (
    <div className={cn("h-1.5 w-full overflow-hidden rounded-full bg-surface-muted", className)}>
      <div
        className={cn("h-full rounded-full transition-[width] duration-300 ease-out", TONE_CLASS[tone] ?? TONE_CLASS.brand)}
        style={{ width: `${pct}%` }}
      />
    </div>
  );
}

/**
 * A single bar split into proportional, tone-coloured segments — e.g. a
 * fleet's running/idle/breakdown counts as one glanceable strip instead of
 * a column of numbers with nothing showing their relative weight.
 *
 * @param {{ segments: { value: number, tone: "brand"|"success"|"warning"|"danger"|"info" }[], className?: string }} props
 */
function SegmentedBar({ segments, className }) {
  const total = segments.reduce((sum, s) => sum + s.value, 0);

  return (
    <div className={cn("flex h-2.5 w-full overflow-hidden rounded-full bg-surface-muted", className)}>
      {total > 0
        ? segments
            .filter((s) => s.value > 0)
            .map((s, index) => (
              <div
                key={index}
                className={TONE_CLASS[s.tone] ?? TONE_CLASS.brand}
                style={{ width: `${(s.value / total) * 100}%` }}
              />
            ))
        : null}
    </div>
  );
}

export { ProgressBar, SegmentedBar };
