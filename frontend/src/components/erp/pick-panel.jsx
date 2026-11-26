"use client";

import { useState } from "react";
import Link from "next/link";
import { ListChecks, Search } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * Brickland's pick-then-load tab (Price Quotation → Quotation and Buyer Rates,
 * Style Development → Size Range): a searchable list on the left with its
 * count, and on the right the chosen entry's panel — or, until one is picked,
 * "Select a buyer" and the reminder that nothing is read before then. Each
 * entry is a link (`hrefBase` + its key), so a choice can be bookmarked.
 *
 * @param {{
 *   title: string, items: {key: string, label: string, sub?: string, count?: number}[], active?: string|null,
 *   hrefBase: string, searchPlaceholder: string, noMatch: string,
 *   panelTitle: string, panelHint: string, emptyTitle: string, emptyText: string,
 *   actions?: React.ReactNode, children?: React.ReactNode,
 * }} props
 */
function PickPanel({ title, items, active, hrefBase, searchPlaceholder, noMatch, panelTitle, panelHint, emptyTitle, emptyText, actions = null, children }) {
  const [query, setQuery] = useState("");
  const needle = query.trim().toLowerCase();
  const shown = needle ? items.filter((i) => `${i.label} ${i.sub ?? ""}`.toLowerCase().includes(needle)) : items;

  return (
    <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[20rem_1fr]">
      <div className="rounded-lg border border-border bg-surface">
        <div className="flex items-center justify-between px-5 pt-4 pb-3">
          <span className="text-base font-semibold text-foreground">{title}</span>
          <span className="rounded-full bg-surface-muted px-2 text-xs font-semibold text-foreground-muted tabular-nums">{items.length}</span>
        </div>
        <div className="relative mx-5 mb-4">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={searchPlaceholder}
            aria-label={searchPlaceholder}
            className="h-10 w-full rounded-md border border-border-strong bg-surface pr-3 pl-9 text-sm outline-none placeholder:text-foreground-muted focus:border-brand"
          />
        </div>
        <ul className="max-h-[65vh] overflow-y-auto border-t border-border">
          {shown.length === 0 ? (
            <li className="px-5 py-6 text-center text-[13px] text-foreground-muted">{noMatch}</li>
          ) : (
            shown.map((item) => (
              <li key={item.key} className="border-b border-border last:border-0">
                <Link
                  href={`${hrefBase}${encodeURIComponent(item.key)}`}
                  scroll={false}
                  className={cn("flex items-center justify-between gap-3 px-5 py-2.5 text-sm", item.key === active ? "bg-brand-subtle text-brand" : "text-foreground hover:bg-surface-muted")}
                >
                  <span className="min-w-0">
                    <span className="block truncate font-semibold">{item.label}</span>
                    {item.sub ? <span className="block truncate text-xs text-foreground-muted">{item.sub}</span> : null}
                  </span>
                  {item.count !== undefined ? <span className="text-xs font-semibold text-foreground-muted tabular-nums">{item.count}</span> : null}
                </Link>
              </li>
            ))
          )}
        </ul>
      </div>

      <div className="min-w-0 rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-4 pb-3">
          <div className="flex min-w-0 gap-2.5">
            <ListChecks className="mt-0.5 size-4 shrink-0 text-foreground-muted" />
            <div className="min-w-0">
              <h2 className="text-base font-semibold text-foreground">{panelTitle}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{panelHint}</p>
            </div>
          </div>
          {active ? actions : null}
        </div>
        {active ? (
          children
        ) : (
          <div className="flex flex-col items-center gap-2 px-6 pt-8 pb-14 text-center">
            <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <ListChecks className="size-5" />
            </span>
            <p className="text-sm font-semibold text-foreground">{emptyTitle}</p>
            <p className="max-w-sm text-[13px] text-foreground-muted">{emptyText}</p>
          </div>
        )}
      </div>
    </div>
  );
}

export { PickPanel };
