import Link from "next/link";
import { cn } from "@/lib/utils";

/**
 * Brickland's catalogue screens (Fabric Master, Supplier Master …): one card
 * with a title and an "N active of M" line, the add button on the right, the
 * All / Active / Inactive pills (`?status=`, so a choice can be bookmarked),
 * and the list underneath.
 *
 * General Store's lists have no pills; they pass no `filters`, and may pass a
 * `toolbar` (a category select) instead.
 *
 * @param {{ title: string, subtitle?: string, action?: React.ReactNode, toolbar?: React.ReactNode,
 *   filters?: { key: string, label: string, count: number }[], active?: string, hrefFor?: (key: string) => string, children: React.ReactNode }} props
 */
function CatalogCard({ title, subtitle, action = null, toolbar = null, filters = [], active, hrefFor, children }) {
  return (
    <div className="flex flex-col gap-3">
      <div className="rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-4 pb-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            {subtitle ? <p className="mt-0.5 text-[13px] text-foreground-muted">{subtitle}</p> : null}
          </div>
          {action}
        </div>
        {toolbar ? <div className="px-5 pb-4">{toolbar}</div> : null}
        <div className={cn("px-5 pb-4", filters.length === 0 && "hidden")}>
          <StatusPills filters={filters} active={active} hrefFor={hrefFor} />
        </div>
      </div>
      {children}
    </div>
  );
}

/**
 * The All / Active / Inactive pills on their own, for a list that puts them in
 * its own card (Material Procurement's one-card lists).
 *
 * @param {{ filters: { key: string, label: string, count: number }[], active?: string, hrefFor: (key: string) => string }} props
 */
function StatusPills({ filters, active, hrefFor }) {
  return (
    <div className="flex flex-wrap gap-2">
      {filters.map((filter) => (
        <Link
          key={filter.key}
          href={hrefFor(filter.key)}
          scroll={false}
          className={cn(
            "inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-[13px] font-semibold",
            filter.key === active ? "border-brand bg-brand text-brand-foreground" : "border-border-strong text-foreground hover:bg-surface-muted",
          )}
        >
          {filter.label}
          <span className={cn("rounded-full px-1.5 text-xs tabular-nums", filter.key === active ? "bg-white/20" : "bg-surface-muted text-foreground-muted")}>{filter.count}</span>
        </Link>
      ))}
    </div>
  );
}

export { CatalogCard, StatusPills };
