import { cva } from "class-variance-authority";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: a neutral pill. Rounded-full only.
 *
 * Each tone's own `*-subtle` background token is a very pale tint by
 * design (it also backs alert/empty-state surfaces, where that's right) —
 * on a plain white table row it read as barely-there colored text with no
 * visible pill at all (found live, the Assets list: "Critical"/"Running"
 * looked like unstyled text). A matching low-opacity border gives the pill
 * a visible boundary regardless of how pale the fill is, without touching
 * the shared tint tokens other surfaces already rely on.
 */
const badgeVariants = cva(
  "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap",
  {
    variants: {
      variant: {
        neutral: "border-border bg-surface-muted text-foreground-muted",
        brand: "border-brand/25 bg-brand-subtle text-brand-hover",
        success: "border-success/25 bg-success-subtle text-success",
        warning: "border-warning/25 bg-warning-subtle text-warning",
        danger: "border-danger/25 bg-danger-subtle text-danger",
        info: "border-info/25 bg-info-subtle text-info",
      },
    },
    defaultVariants: {
      variant: "neutral",
    },
  },
);

function Badge({ className, variant, ...props }) {
  return <span data-slot="badge" className={cn(badgeVariants({ variant, className }))} {...props} />;
}

export { Badge, badgeVariants };
