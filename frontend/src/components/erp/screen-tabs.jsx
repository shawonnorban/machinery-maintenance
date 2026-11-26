"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { cn } from "@/lib/utils";
import { MenuIcon } from "@/components/layout/menu-icon";

/**
 * Brickland's tab strip under the page header: "Records N", the screen's own
 * tabs, then "+ New X" (AnnotechERP UI parity). Each tab is a `?tab=` link,
 * so a tab can be bookmarked and the browser's back button walks them.
 *
 * @param {{
 *   basePath: string, active: string,
 *   tabs: { key: string, label: string, icon?: string, count?: number, href?: string }[],
 *   editLabel?: string,
 * }} props
 *
 * `editLabel` given, a row being edited in place (`?edit=<id>`) shows as
 * Brickland's "Edit X" tab, standing where "New X" was.
 */
function ScreenTabs({ basePath, active, tabs, editLabel }) {
  const editing = useSearchParams().get("edit");
  if (editing && editLabel) {
    const at = tabs.findIndex((t) => t.key === "new");
    const edit = { key: "__edit", label: editLabel, icon: "Pencil", href: `${basePath}?${new URLSearchParams({ ...(active ? { tab: active } : {}), edit: editing })}` };
    tabs = at >= 0 ? tabs.map((t, i) => (i === at ? edit : t)) : [...tabs, edit];
    active = "__edit";
  }
  return (
    <div role="tablist" className="mb-4 flex gap-1 overflow-x-auto border-b border-border">
      {tabs.map((tab) => {
        const current = tab.key === active;
        return (
          <Link
            key={tab.key}
            href={tab.href ?? `${basePath}?tab=${tab.key}`}
            scroll={false}
            role="tab"
            aria-selected={current}
            aria-current={current ? "page" : undefined}
            className={cn(
              "-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors [&_svg]:size-4",
              current ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:border-border-strong hover:text-foreground",
            )}
          >
            <MenuIcon name={tab.icon ?? "Rows3"} />
            {tab.label}
            {tab.count !== undefined ? (
              <span className={cn("rounded-full px-1.5 py-px text-[11px] font-semibold tabular-nums", current ? "bg-brand-subtle text-brand" : "bg-surface-muted text-foreground-muted")}>{tab.count}</span>
            ) : null}
          </Link>
        );
      })}
    </div>
  );
}

export { ScreenTabs };
