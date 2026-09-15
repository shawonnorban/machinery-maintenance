"use client";

import { useState } from "react";
import { Menu, X, ShieldCheck, ChevronsLeft, ChevronsRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { useTheme } from "@/components/theme/theme-provider";
import { SidebarNav } from "@/components/layout/sidebar-nav";
import { PlatformNotificationBell } from "@/components/layout/platform-notification-bell";
import { PlatformUserMenu } from "@/components/layout/platform-user-menu";

/**
 * The superadmin console shell — same skeleton as `AppShell` (fixed
 * sidebar, sticky topbar, sticky footer) and, on direct request, the same
 * colour tokens too: `bg-surface`/`bg-background`/`text-foreground`/brand
 * blue, following the visitor's own light/dark/system theme rather than a
 * separate fixed dark chrome. Only the "Platform" wordmark badge and a
 * `ShieldCheck` mark distinguish this from the tenant app at a glance —
 * `tone="app"` on `SidebarNav` reuses the exact same active/hover states
 * the tenant sidebar already has, rather than a parallel colour scheme to
 * keep in sync by hand.
 *
 * `navGroups` is plain serializable data, rendered into `SidebarNav` here
 * (see `AppShell`'s own docblock for why passing a pre-built element or a
 * function from a Server Component caller doesn't work) — `items[].badge`
 * is an optional count (open tickets, unread notifications, active support
 * grants) surfaced before a click.
 *
 * @param {{
 *   navGroups: { label: string, items: { label: string, href: string, icon?: React.ReactNode, badge?: number }[] }[],
 *   userName?: string,
 *   userEmail?: string,
 *   unreadNotifications?: number,
 *   recentNotifications?: object[],
 *   children: React.ReactNode,
 * }} props
 */
function PlatformShell({ navGroups, userName, userEmail, unreadNotifications = 0, recentNotifications = [], children }) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-background">
      {/* Mobile overlay drawer */}
      {mobileOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-foreground/40" onClick={() => setMobileOpen(false)} />
          <div className="relative z-10 flex h-full w-[260px] flex-col border-r border-border bg-surface shadow-lg">
            <PlatformSidebarHeader onClose={() => setMobileOpen(false)} />
            <SidebarNav groups={navGroups} tone="app" />
          </div>
        </div>
      ) : null}

      {/* Fixed sidebar, desktop */}
      <aside
        className={cn(
          "sticky top-0 hidden h-screen shrink-0 flex-col border-r border-border bg-surface transition-[width] duration-150 ease-out lg:flex",
          collapsed ? "w-[72px]" : "w-[260px]",
        )}
      >
        <PlatformSidebarHeader collapsed={collapsed} />
        <SidebarNav groups={navGroups} collapsed={collapsed} tone="app" />
        <button
          type="button"
          onClick={() => setCollapsed((value) => !value)}
          className="flex items-center justify-center gap-2 border-t border-border py-3 text-xs font-medium text-foreground-muted hover:bg-surface-muted hover:text-foreground"
        >
          {collapsed ? <ChevronsRight className="size-4" /> : <ChevronsLeft className="size-4" />}
          {!collapsed ? "Collapse" : null}
        </button>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <PlatformTopbar
          onOpenMobileNav={() => setMobileOpen(true)}
          userName={userName}
          userEmail={userEmail}
          unreadNotifications={unreadNotifications}
          recentNotifications={recentNotifications}
        />

        <main className="flex-1">
          <div className="mx-auto max-w-7xl p-4 sm:p-6">{children}</div>
        </main>

        <PlatformFooter />
      </div>
    </div>
  );
}

function PlatformSidebarHeader({ collapsed = false, onClose }) {
  return (
    <div className="flex h-16 shrink-0 items-center justify-between border-b border-border px-4">
      <div className={cn("flex items-center gap-2", collapsed && "justify-center")}>
        <span className="flex size-8 shrink-0 items-center justify-center rounded-sm bg-brand text-brand-foreground">
          <ShieldCheck className="size-4.5" />
        </span>
        {!collapsed ? (
          <div className="flex flex-col leading-none">
            <span className="text-sm font-semibold text-foreground">Annotech RMG</span>
            <span className="mt-0.5 text-[10px] font-semibold tracking-wider text-brand uppercase">Platform</span>
          </div>
        ) : null}
      </div>
      {onClose ? (
        <button type="button" onClick={onClose} className="rounded-sm p-1 text-foreground-muted hover:bg-surface-muted lg:hidden">
          <X className="size-5" />
        </button>
      ) : null}
    </div>
  );
}

function PlatformTopbar({ onOpenMobileNav, userName, userEmail, unreadNotifications, recentNotifications }) {
  const { theme, setTheme } = useTheme();

  return (
    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface/95 px-4 backdrop-blur supports-backdrop-filter:bg-surface/75 sm:px-6">
      <button
        type="button"
        onClick={onOpenMobileNav}
        className="rounded-sm p-1.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground lg:hidden"
      >
        <Menu className="size-5" />
      </button>

      <span className="hidden items-center gap-1.5 rounded-full bg-brand-subtle px-2.5 py-1 text-[11px] font-semibold tracking-wide text-brand-hover uppercase sm:inline-flex">
        <ShieldCheck className="size-3.5" /> Superadmin console
      </span>

      <div className="min-w-0 flex-1" />

      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={() => setTheme(theme === "dark" ? "light" : theme === "light" ? "system" : "dark")}
          className="rounded-sm px-2 py-1.5 text-xs font-medium text-foreground-muted hover:bg-surface-muted hover:text-foreground"
          title={`Content theme: ${theme}`}
        >
          {theme}
        </button>
        <PlatformNotificationBell unreadCount={unreadNotifications} recent={recentNotifications} />
        <div className="ml-1">
          <PlatformUserMenu name={userName} email={userEmail} />
        </div>
      </div>
    </header>
  );
}

function PlatformFooter() {
  return (
    <footer className="sticky bottom-0 z-20 flex h-10 shrink-0 items-center justify-between border-t border-border bg-surface/95 px-4 text-xs text-foreground-muted backdrop-blur supports-backdrop-filter:bg-surface/75 sm:px-6">
      <span className="flex items-center gap-1.5">
        <ShieldCheck className="size-3.5 text-brand" />A Product by Data State Ltd
      </span>
      <span>Superadmin Console · v1.0</span>
    </footer>
  );
}

export { PlatformShell };
