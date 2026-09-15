import { cva } from "class-variance-authority";
import { Info, CheckCircle2, AlertTriangle, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";

/** docs/UI-DESIGN-SYSTEM.md §4: an inline notice — info / success / warning / danger. */
const alertVariants = cva("flex items-start gap-3 rounded-sm border px-4 py-3 text-sm", {
  variants: {
    variant: {
      info: "border-info/30 bg-info-subtle text-info",
      success: "border-success/30 bg-success-subtle text-success",
      warning: "border-warning/30 bg-warning-subtle text-warning",
      danger: "border-danger/30 bg-danger-subtle text-danger",
    },
  },
  defaultVariants: { variant: "info" },
});

const ICONS = { info: Info, success: CheckCircle2, warning: AlertTriangle, danger: XCircle };

function Alert({ variant = "info", title, children, className }) {
  const Icon = ICONS[variant];

  return (
    <div role="alert" className={cn(alertVariants({ variant }), className)}>
      <Icon className="mt-0.5 size-4 shrink-0" />
      <div className="flex flex-col gap-0.5 text-foreground">
        {title ? <p className="font-medium">{title}</p> : null}
        {children ? <div className="text-foreground-muted">{children}</div> : null}
      </div>
    </div>
  );
}

export { Alert };
