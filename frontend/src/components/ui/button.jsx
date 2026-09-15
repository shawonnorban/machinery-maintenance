import { cva } from "class-variance-authority";
import { Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: primary / secondary / outline / ghost /
 * danger / success, each with default, hover, active, disabled and loading
 * states. One height (40px, matching form controls per §6), one radius
 * (rounded-sm, 8px per §1), one focus ring.
 */
const buttonVariants = cva(
  "inline-flex shrink-0 items-center justify-center gap-1.5 rounded-sm text-sm font-medium " +
    "whitespace-nowrap transition-colors duration-150 ease-out outline-none select-none " +
    "focus-visible:ring-2 focus-visible:ring-brand/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background " +
    "disabled:pointer-events-none disabled:opacity-50 " +
    "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        primary: "bg-brand text-brand-foreground hover:bg-brand-hover active:bg-brand-hover",
        secondary:
          "bg-surface-muted text-foreground border border-border hover:bg-border active:bg-border",
        outline:
          "border border-border-strong bg-transparent text-foreground hover:bg-surface-muted active:bg-surface-muted",
        ghost: "bg-transparent text-foreground hover:bg-surface-muted active:bg-surface-muted",
        danger: "bg-danger text-white hover:bg-danger/90 active:bg-danger/90",
        success: "bg-success text-white hover:bg-success/90 active:bg-success/90",
      },
      size: {
        sm: "h-8 px-3 text-xs",
        default: "h-10 px-4",
        lg: "h-11 px-5",
        icon: "size-10",
      },
    },
    defaultVariants: {
      variant: "primary",
      size: "default",
    },
  },
);

function Button({
  className,
  variant,
  size,
  loading = false,
  disabled,
  children,
  ...props
}) {
  return (
    <button
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      {...props}
    >
      {loading ? <Loader2 className="animate-spin" /> : null}
      {children}
    </button>
  );
}

export { Button, buttonVariants };
