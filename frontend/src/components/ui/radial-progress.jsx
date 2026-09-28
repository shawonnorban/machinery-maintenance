import { cn } from "@/lib/utils";

const TONE_COLOR = {
  brand: "var(--brand)",
  success: "var(--success)",
  warning: "var(--warning)",
  danger: "var(--danger)",
  info: "var(--info)",
};

/**
 * A circular percentage ring for the one or two headline figures a
 * dashboard is actually read for at a glance (availability, PM compliance)
 * — plain text made every KPI tile look identical regardless of how
 * important the number was. Pure SVG, no hooks, so it renders fine from an
 * async Server Component the same as the rest of the dashboard panels.
 *
 * `showValue` hides the centred "N%" text — for a ring small enough to sit
 * beside a value already shown as its own large number elsewhere on the
 * card, repeating it inside the ring itself is redundant and, below about
 * 56px, doesn't actually fit.
 *
 * @param {{ value: number, size?: number, strokeWidth?: number, tone?: "brand"|"success"|"warning"|"danger"|"info", label?: string, showValue?: boolean, className?: string }} props
 */
function RadialProgress({ value, size = 88, strokeWidth = 8, tone = "brand", label, showValue = true, className }) {
  const clamped = Math.max(0, Math.min(100, value ?? 0));
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (clamped / 100) * circumference;
  const color = TONE_COLOR[tone] ?? TONE_COLOR.brand;

  return (
    <div className={cn("relative inline-flex shrink-0 items-center justify-center", className)} style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90" role="img" aria-label={label ? `${label}: ${Math.round(clamped)}%` : `${Math.round(clamped)}%`}>
        <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke="var(--border)" strokeWidth={strokeWidth} />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke={color}
          strokeWidth={strokeWidth}
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          strokeLinecap="round"
        />
      </svg>
      {showValue ? (
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="tabular text-lg font-semibold text-foreground">{Math.round(clamped)}%</span>
        </div>
      ) : null}
    </div>
  );
}

export { RadialProgress };
