import Link from "next/link";
import { ArrowLeft, ArrowRight } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * Brickland's "Production Plan · Step N of 6" bar (Capacity Planning,
 * AnnotechERP 12): the six steps a plan is built in, the current one marked,
 * Previous and Next. A step whose screen is not built yet shows but does not
 * link.
 *
 * @param {{ steps: { key: string, label: string, href?: string }[], current: string, title: string, previous: string, next: string }} props
 */
function PlanSteps({ steps, current, title, previous, next }) {
  const index = steps.findIndex((s) => s.key === current);
  const prev = steps
    .slice(0, index)
    .reverse()
    .find((s) => s.href);
  const nextStep = steps.slice(index + 1).find((s) => s.href);

  return (
    <div className="mb-4 rounded-lg border border-border bg-surface p-4">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p className="text-[13px] font-semibold text-foreground">
          {title
            .replace(":step", String(index + 1))
            .replace(":count", String(steps.length))}
        </p>
        <div className="flex gap-2">
          {prev ? (
            <Link
              href={prev.href}
              className="inline-flex h-9 items-center gap-1.5 rounded-md border border-border-strong px-3 text-sm font-medium hover:bg-surface-muted"
            >
              <ArrowLeft className="size-4" /> {previous}
            </Link>
          ) : null}
          {nextStep ? (
            <Link
              href={nextStep.href}
              className="inline-flex h-9 items-center gap-1.5 rounded-md bg-brand px-3 text-sm font-semibold text-brand-foreground hover:bg-brand/90"
            >
              {next} <ArrowRight className="size-4" />
            </Link>
          ) : null}
        </div>
      </div>
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        {steps.map((step, i) => {
          const body = (
            <>
              <span
                className={cn(
                  "flex size-6 items-center justify-center rounded-full text-xs font-semibold",
                  step.key === current
                    ? "bg-brand text-brand-foreground"
                    : "bg-surface-muted text-foreground-muted",
                )}
              >
                {i + 1}
              </span>
              {step.label}
            </>
          );
          const cls = cn(
            "flex items-center gap-2.5 rounded-md border px-3 py-2 text-sm",
            step.key === current
              ? "border-brand bg-brand-subtle font-semibold text-brand"
              : "border-border text-foreground",
            !step.href && "cursor-not-allowed opacity-50",
          );
          return step.href ? (
            <Link
              key={step.key}
              href={step.href}
              className={cn(
                cls,
                step.key !== current && "hover:bg-surface-muted",
              )}
            >
              {body}
            </Link>
          ) : (
            <span key={step.key} className={cls} aria-disabled="true">
              {body}
            </span>
          );
        })}
      </div>
    </div>
  );
}

export { PlanSteps };
