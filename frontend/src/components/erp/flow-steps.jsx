import Link from "next/link";
import { ArrowLeft, ArrowRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";

/**
 * Brickland's workflow strip above a screen that is one step of a flow
 * (AnnotechERP UI parity): "Style & Order · Step 4 of 5", Previous and Next
 * step, and every step as a numbered link, the current one marked.
 *
 * @param {{ title: string, stepLabel: string, prevLabel: string, nextLabel: string,
 *   steps: { key: string, label: string, href: string }[], active: string }} props
 */
function FlowSteps({ title, stepLabel, prevLabel, nextLabel, steps, active }) {
  const at = steps.findIndex((s) => s.key === active);
  const prev = at > 0 ? steps[at - 1] : null;
  const next = at >= 0 && at < steps.length - 1 ? steps[at + 1] : null;

  return (
    <nav aria-label={title} className="mb-4 rounded-lg border border-border bg-surface p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-[13px] text-foreground-muted">
          <b className="text-foreground">{title}</b> · {stepLabel}
        </p>
        <div className="flex gap-2">
          {prev?.href ? (
            <Link href={prev.href} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {prevLabel}
            </Link>
          ) : null}
          {next?.href ? (
            <Link href={next.href} className={cn(buttonVariants({ size: "sm" }))}>
              {nextLabel} <ArrowRight />
            </Link>
          ) : null}
        </div>
      </div>
      <ol className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        {steps.map((s, i) => {
          const current = s.key === active;
          return (
            <li key={s.key}>
              {s.href ? (
                <Link
                  href={s.href}
                  aria-current={current ? "step" : undefined}
                  className={cn(
                    "flex items-center gap-3 rounded-md border px-3 py-2.5 text-sm font-semibold transition-colors",
                    current ? "border-brand/40 bg-brand-subtle text-brand" : "border-border text-foreground hover:bg-surface-muted",
                  )}
                >
                  <span className={cn("grid size-6 shrink-0 place-items-center rounded-full text-xs", current ? "bg-brand text-brand-foreground" : "bg-surface-muted text-foreground-muted")}>{i + 1}</span>
                  {s.label}
                </Link>
              ) : (
                <span className="flex items-center gap-3 rounded-md border border-dashed border-border px-3 py-2.5 text-sm font-semibold text-foreground-muted">
                  <span className="grid size-6 shrink-0 place-items-center rounded-full bg-surface-muted text-xs">{i + 1}</span>
                  {s.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

export { FlowSteps };
