"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";

const STORAGE_KEY = "annotech-nav-expanded";

/**
 * docs/UI-DESIGN-SYSTEM.md §3: driven by the API's `modules` payload,
 * already filtered by entitlement and permission — nothing is hidden here
 * that the server would allow, and nothing shown that it would refuse. This
 * component renders whatever group/item structure it's handed; fetching
 * that structure from `GET /auth/permissions` (or a dedicated modules
 * endpoint) is Phase D wiring, not part of the shell itself.
 *
 * A group with more than one item is a collapsible section (accordion,
 * chevron, smooth height transition) — a single-item group renders as a flat
 * top-level link instead, since a header-plus-chevron over one row is
 * overhead a "Reports" entry doesn't need. The section containing the
 * active route always opens, even across a fresh page load, and every other
 * section's open/closed state persists in localStorage so navigating around
 * doesn't re-collapse the section a person is working in.
 *
 * A section's own icon is its first item's icon — the data model carries one
 * icon per leaf link, not a second one per group, and the header row reuses
 * it rather than asking every `navGroups` entry in every caller to supply a
 * group-level icon that would otherwise just repeat the first child's.
 * Every other child in an open section renders as plain indented text with
 * no icon of its own (a small dot marks whichever one is active) — this is
 * a deliberate two-tier hierarchy, not a shortcut: the icon says what kind
 * of thing the section is, the text says which one inside it you're on.
 *
 * Collapsed to icon rail, a section with children reveals them in a flyout
 * anchored to the right on hover instead of losing them entirely.
 *
 * `icon` is a rendered node (e.g. `<Wrench />`), not a component reference:
 * a Server Component caller can pass JSX like this across the client
 * boundary, but not a raw function, which the RSC wire format can't
 * serialize.
 *
 * `badge` is an optional count rendered at the row's trailing edge (e.g. open
 * tickets, unread notifications) — additive to the original `{label, href,
 * icon}` shape, so every existing caller that never sets it is unaffected.
 *
 * @param {{
 *   groups: { label: string, newSection?: boolean, items: { label: string, href: string, icon?: React.ReactNode, badge?: number }[] }[],
 *   collapsed?: boolean,
 *   tone?: "app" | "platform",
 * }} props
 */
function SidebarNav({ groups, collapsed = false, tone = "app" }) {
  const pathname = usePathname();
  const platform = tone === "platform";

  const activeGroupLabel = groups.find((group) =>
    group.items.some((item) => pathname === item.href || pathname?.startsWith(`${item.href}/`)),
  )?.label;

  const [expanded, setExpanded] = useState(() => new Set(activeGroupLabel ? [activeGroupLabel] : []));

  useEffect(() => {
    let stored = [];
    try {
      stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]");
    } catch {
      stored = [];
    }
    // queueMicrotask rather than a bare setState call, same pattern
    // ThemeProvider already uses for its own post-mount localStorage read —
    // avoids the react-hooks/set-state-in-effect lint rule without an
    // eslint-disable comment.
    queueMicrotask(() => setExpanded((current) => new Set([...stored, ...current])));
    // Only on mount — this restores what localStorage had at load time and
    // then leaves state to toggleGroup()/the active-group effect below.
  }, []);

  useEffect(() => {
    if (!activeGroupLabel) return;
    queueMicrotask(() => {
      setExpanded((current) => (current.has(activeGroupLabel) ? current : new Set(current).add(activeGroupLabel)));
    });
  }, [activeGroupLabel]);

  function toggleGroup(label) {
    setExpanded((current) => {
      const next = new Set(current);
      if (next.has(label)) next.delete(label);
      else next.add(label);
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify([...next]));
      } catch {
        // Best-effort only — a private window or blocked storage just
        // means sections don't remember their state across a reload.
      }
      return next;
    });
  }

  function isActive(item) {
    return pathname === item.href || pathname?.startsWith(`${item.href}/`);
  }

  return (
    <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-4">
      {groups.map((group) => {
        const isSection = group.items.length > 1;
        const isOpen = !isSection || collapsed || expanded.has(group.label);
        const groupIcon = group.items[0]?.icon;
        const groupActive = group.items.some(isActive);

        return (
          <div key={group.label}>
            {group.newSection ? (
              <div className={cn("my-3 border-t border-border", collapsed && "mx-1")} aria-hidden />
            ) : null}

            <div className={cn("group/section relative flex flex-col", collapsed && "items-center")}>
              {isSection ? (
                <button
                  type="button"
                  onClick={() => !collapsed && toggleGroup(group.label)}
                  aria-expanded={isOpen}
                  className={cn(
                    "flex items-center gap-3 rounded-sm px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out",
                    collapsed ? "justify-center px-0" : "w-full",
                    platform
                      ? groupActive && collapsed
                        ? "bg-platform-accent-subtle text-platform-accent"
                        : "text-platform-foreground-muted hover:bg-platform-surface-raised hover:text-platform-foreground"
                      : groupActive && collapsed
                        ? "bg-brand-subtle text-brand-hover"
                        : "text-foreground-muted hover:bg-surface-muted hover:text-foreground",
                  )}
                >
                  {groupIcon ? <span className="size-[18px] shrink-0 [&_svg]:size-[18px]">{groupIcon}</span> : null}
                  {!collapsed ? (
                    <>
                      <span className="min-w-0 flex-1 truncate text-left">{group.label}</span>
                      <ChevronDown className={cn("size-3.5 shrink-0 transition-transform duration-150 ease-out", isOpen && "-rotate-180")} />
                    </>
                  ) : null}
                </button>
              ) : (
                <NavLink item={group.items[0]} active={isActive(group.items[0])} collapsed={collapsed} platform={platform} />
              )}

              {isSection && !collapsed ? (
                <div className={cn("grid transition-[grid-template-rows] duration-200 ease-out", isOpen ? "grid-rows-[1fr]" : "grid-rows-[0fr]")}>
                  <div className="flex flex-col gap-0.5 overflow-hidden py-0.5 pl-[27px]">
                    {group.items.map((item) => (
                      <ChildLink key={item.href} item={item} active={isActive(item)} platform={platform} />
                    ))}
                  </div>
                </div>
              ) : null}

              {/* Collapsed-rail flyout: hover reveals the section's children beside the icon, so collapsing the sidebar doesn't hide them entirely. */}
              {isSection && collapsed ? (
                <div
                  className={cn(
                    "invisible absolute top-0 left-full z-40 ml-2 w-48 rounded-sm border border-border bg-surface p-1.5 opacity-0 shadow-md transition-opacity duration-100 group-hover/section:visible group-hover/section:opacity-100",
                  )}
                >
                  <p className="px-2 py-1 text-[11px] font-semibold tracking-wider text-foreground-muted uppercase">{group.label}</p>
                  <div className="flex flex-col gap-0.5">
                    {group.items.map((item) => (
                      <ChildLink key={item.href} item={item} active={isActive(item)} platform={platform} />
                    ))}
                  </div>
                </div>
              ) : null}
            </div>
          </div>
        );
      })}
    </nav>
  );
}

function NavLink({ item, active, collapsed, platform }) {
  return (
    <Link
      href={item.href}
      title={collapsed ? item.label : undefined}
      className={cn(
        "flex items-center gap-3 rounded-sm px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out",
        collapsed ? "justify-center px-0" : "w-full",
        platform
          ? active
            ? "bg-platform-accent-subtle text-platform-accent"
            : "text-platform-foreground-muted hover:bg-platform-surface-raised hover:text-platform-foreground"
          : active
            ? "bg-foreground text-background"
            : "text-foreground-muted hover:bg-surface-muted hover:text-foreground",
      )}
    >
      {item.icon ? <span className="size-[18px] shrink-0 [&_svg]:size-[18px]">{item.icon}</span> : null}
      {!collapsed ? (
        <>
          <span className="min-w-0 flex-1 truncate">{item.label}</span>
          <NavBadge count={item.badge} platform={platform} active={active} />
        </>
      ) : null}
    </Link>
  );
}

/** A child row inside an open section (or a collapsed-rail flyout): no icon of its own, just a leading dot when active. */
function ChildLink({ item, active, platform }) {
  return (
    <Link
      href={item.href}
      className={cn(
        "flex items-center gap-2 rounded-sm px-3 py-1.5 text-sm font-medium transition-colors duration-150 ease-out",
        platform
          ? active
            ? "bg-platform-accent-subtle text-platform-accent"
            : "text-platform-foreground-muted hover:bg-platform-surface-raised hover:text-platform-foreground"
          : active
            ? "bg-foreground text-background"
            : "text-foreground-muted hover:bg-surface-muted hover:text-foreground",
      )}
    >
      <span className={cn("size-1 shrink-0 rounded-full", active && !platform ? "bg-background" : "bg-transparent")} aria-hidden />
      <span className="min-w-0 flex-1 truncate">{item.label}</span>
      <NavBadge count={item.badge} platform={platform} active={active} />
    </Link>
  );
}

/** A small pill count at a nav row's trailing edge — open tickets, unread notifications, anything worth surfacing before a click. Renders nothing at zero/undefined: an empty badge is noise, not information. */
function NavBadge({ count, platform, active }) {
  if (!count) return null;

  return (
    <span
      className={cn(
        "ml-auto flex h-4.5 min-w-4.5 shrink-0 items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular",
        platform
          ? active
            ? "bg-platform-accent text-platform-accent-foreground"
            : "bg-platform-surface-raised text-platform-foreground-muted"
          : active
            ? "bg-background text-foreground"
            : "bg-surface-muted text-foreground-muted",
      )}
    >
      {count > 99 ? "99+" : count}
    </span>
  );
}

export { SidebarNav };
