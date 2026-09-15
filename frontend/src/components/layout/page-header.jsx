import Link from "next/link";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: breadcrumb, title, description, actions.
 * One page title per page (text-2xl font-semibold tracking-tight) — this
 * component is where that rule is enforced by construction, since nothing
 * else renders an <h1> at that size.
 *
 * @param {{
 *   breadcrumb?: { label: string, href?: string }[],
 *   title: string,
 *   description?: string,
 *   actions?: React.ReactNode,
 *   className?: string,
 * }} props
 */
function PageHeader({ breadcrumb, title, description, actions, className }) {
  return (
    <div className={cn("flex flex-col gap-1 pb-6", className)}>
      {breadcrumb && breadcrumb.length > 0 ? (
        <nav aria-label="Breadcrumb" className="flex items-center gap-1 text-xs text-foreground-muted">
          {breadcrumb.map((crumb, index) => (
            <span key={crumb.label} className="flex items-center gap-1">
              {index > 0 ? <ChevronRight className="size-3.5 text-foreground-subtle" /> : null}
              {crumb.href ? (
                <Link href={crumb.href} className="hover:text-foreground">
                  {crumb.label}
                </Link>
              ) : (
                <span>{crumb.label}</span>
              )}
            </span>
          ))}
        </nav>
      ) : null}

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
          {description ? <p className="text-sm text-foreground-muted">{description}</p> : null}
        </div>

        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </div>
    </div>
  );
}

export { PageHeader };
