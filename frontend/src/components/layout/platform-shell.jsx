"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, X, PanelLeft, ShieldCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { PlatformNotificationBell } from "@/components/layout/platform-notification-bell";
import { PlatformUserMenu } from "@/components/layout/platform-user-menu";

/**
 * The superadmin console shell, in the AnnotechERP look (user, 2026-11-24 —
 * the console was the old design): the same skeleton and tokens as AppShell,
 * section headings in brand capitals as the ERP sidebar shows them, each
 * entry with its count, the topbar's chip naming where you are, and the ERP
 * footer. Only the shield mark and "Platform Console" tell it apart.
 *
 * `navGroups` is plain serializable data (see AppShell for why a server
 * component cannot hand over elements built from functions): sections of
 * { label, href, icon, badge?, tone?, match? } — `match` lists the extra
 * address prefixes that keep an entry lit (a customer's own page lights
 * Customers).
 *
 * @param {{
 *   navGroups: { label: string, items: { label: string, href: string, icon?: React.ReactNode, badge?: number, tone?: string, match?: string[] }[] }[],
 *   userName?: string, userEmail?: string, unreadNotifications?: number, recentNotifications?: object[], children: React.ReactNode,
 * }} props
 */
function PlatformShell({ navGroups, userName, userEmail, unreadNotifications = 0, recentNotifications = [], children }) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const pathname = usePathname();
  const current = activeItem(navGroups, pathname);

  return (
    <div className="flex min-h-screen bg-background">
      {mobileOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-foreground/40" onClick={() => setMobileOpen(false)} />
          <div className="relative z-10 flex h-full w-[272px] flex-col border-r border-border bg-surface shadow-lg">
            <Brand onClose={() => setMobileOpen(false)} />
            <Nav groups={navGroups} current={current} onNavigate={() => setMobileOpen(false)} />
          </div>
        </div>
      ) : null}

      <aside className={cn("sticky top-0 hidden h-screen shrink-0 flex-col border-r border-border bg-surface transition-[width] duration-150 ease-out lg:flex", collapsed ? "w-[72px]" : "w-[272px]")}>
        <Brand collapsed={collapsed} />
        <Nav groups={navGroups} current={current} collapsed={collapsed} />
        {!collapsed ? (
          <div className="m-3 rounded-md border border-border bg-surface p-3">
            <p className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
              <ShieldCheck className="size-3.5 text-brand" /> Superadmin
            </p>
            <p className="mt-0.5 truncate text-[11px] text-foreground-muted">{userEmail}</p>
          </div>
        ) : null}
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface/95 px-4 backdrop-blur supports-backdrop-filter:bg-surface/80 sm:px-6">
          <button type="button" onClick={() => setMobileOpen(true)} className="rounded-md p-1.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground lg:hidden" aria-label="Open menu">
            <Menu className="size-5" />
          </button>
          <button
            type="button"
            onClick={() => setCollapsed((value) => !value)}
            className="hidden rounded-md p-1.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground lg:block"
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            <PanelLeft className="size-5" />
          </button>

          {/* Where you are, as the ERP topbar's module chip shows it. */}
          <span className="hidden items-center gap-2.5 rounded-lg border border-border px-2.5 py-1.5 sm:flex">
            <span className="flex size-7 items-center justify-center rounded-md bg-brand-subtle text-brand [&_svg]:size-4">{current?.icon ?? <ShieldCheck />}</span>
            <span className="leading-tight">
              <span className="block text-sm font-semibold text-foreground">{current?.label ?? "Platform"}</span>
              <span className="block text-[10px] font-semibold tracking-[0.12em] text-foreground-muted uppercase">Platform console</span>
            </span>
          </span>

          <div className="ml-auto flex items-center gap-1">
            <ThemeToggle />
            <PlatformNotificationBell unreadCount={unreadNotifications} recent={recentNotifications} />
            <span className="mx-2 hidden h-8 w-px bg-border sm:block" aria-hidden />
            <PlatformUserMenu name={userName} email={userEmail} />
          </div>
        </header>

        <main className="flex-1">
          <div className="mx-auto w-full p-4 sm:px-6 sm:py-5">{children}</div>
        </main>

        <footer className="sticky bottom-0 z-20 flex h-10 shrink-0 items-center justify-between gap-4 border-t border-border bg-surface/95 px-4 text-xs text-foreground-muted backdrop-blur supports-backdrop-filter:bg-surface/80 sm:px-6">
          <span className="truncate">
            Annotech ERP · <span className="font-medium text-brand">Platform Console</span> · A Product by Data State Ltd
          </span>
          <span className="shrink-0">App Version : 2.0</span>
        </footer>
      </div>
    </div>
  );
}

/** The entry the address belongs to (longest matching prefix wins). */
function activeItem(groups, pathname) {
  let best = null;
  let bestLength = -1;
  for (const group of groups) {
    for (const item of group.items) {
      for (const prefix of [item.href, ...(item.match ?? [])]) {
        const hit = pathname === prefix || (prefix !== "/platform" && pathname?.startsWith(`${prefix}/`));
        if (hit && prefix.length > bestLength) {
          best = item;
          bestLength = prefix.length;
        }
      }
    }
  }
  return best;
}

const ICON_TONE = { brand: "text-brand", success: "text-success", warning: "text-warning", danger: "text-danger", info: "text-info" };

function Nav({ groups, current, collapsed = false, onNavigate }) {
  return (
    <nav className="min-h-0 flex-1 overflow-y-auto px-3 pt-2 pb-4">
      {groups.map((group, gi) => (
        <div key={group.label}>
          {!collapsed ? <p className="mt-4 mb-1.5 px-2 text-[11px] font-semibold tracking-[0.08em] text-brand uppercase">{group.label}</p> : gi > 0 ? <div className="my-3 border-t border-border" aria-hidden /> : null}
          {group.items.map((item) => {
            const active = current === item;
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={onNavigate}
                title={collapsed ? item.label : undefined}
                className={cn(
                  "flex items-center gap-3 rounded-md px-2 py-2 text-sm font-medium transition-colors",
                  collapsed && "justify-center px-0",
                  active ? "bg-brand-subtle text-brand" : "text-foreground hover:bg-surface-muted",
                )}
              >
                <span className={cn("relative size-[18px] shrink-0 [&_svg]:size-[18px]", active ? "text-brand" : (ICON_TONE[item.tone] ?? "text-foreground-muted"))}>
                  {item.icon}
                  {collapsed && item.badge ? <span className="absolute -top-1 -right-1.5 size-2 rounded-full bg-danger" /> : null}
                </span>
                {!collapsed ? (
                  <>
                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                    {item.badge ? (
                      <span className={cn("rounded-full px-1.5 text-[11px] font-semibold tabular-nums", item.badgeTone === "danger" ? "bg-danger-subtle text-danger" : "bg-surface-muted text-foreground-muted")}>{item.badge}</span>
                    ) : null}
                  </>
                ) : null}
              </Link>
            );
          })}
        </div>
      ))}
    </nav>
  );
}

function Brand({ collapsed = false, onClose }) {
  return (
    <div className={cn("flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-4", collapsed && "justify-center px-0")}>
      <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-brand-foreground">
        <ShieldCheck className="size-[18px]" />
      </span>
      {!collapsed ? (
        <span className="min-w-0 flex-1 leading-tight">
          <span className="block truncate text-[15px] font-bold text-brand">Annotech ERP</span>
          <span className="block text-[10px] font-semibold tracking-[0.16em] text-foreground-muted uppercase">Platform Console</span>
        </span>
      ) : null}
      {onClose ? (
        <button type="button" onClick={onClose} className="rounded-md p-1 hover:bg-surface-muted lg:hidden" aria-label="Close menu">
          <X className="size-5" />
        </button>
      ) : null}
    </div>
  );
}

export { PlatformShell };
