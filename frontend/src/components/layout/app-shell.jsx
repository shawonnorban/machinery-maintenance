"use client";

import { useState } from "react";
import { Menu, X, Sun, Moon, Monitor, ChevronLeft, ChevronRight, Wrench } from "lucide-react";
import { cn } from "@/lib/utils";
import { useTheme } from "@/components/theme/theme-provider";
import { SyncIndicator } from "@/components/offline/sync-indicator";
import { LocaleToggle } from "@/components/layout/locale-toggle";
import { NotificationBell } from "@/components/layout/notification-bell";
import { SidebarProfile } from "@/components/layout/sidebar-profile";
import { CompanySwitcher } from "@/components/layout/company-switcher";
import { SidebarNav } from "@/components/layout/sidebar-nav";

/**
 * docs/UI-DESIGN-SYSTEM.md §2: the tenant application shell. Fixed sidebar
 * (260px expanded / 72px collapsed, an overlay drawer below `lg`), sticky
 * topbar, `w-[95%] p-6 space-y-6` content (widened from the design doc's own
 * `max-w-7xl` per direct request — most screens here are dense tables/forms
 * that benefit from the extra width more than a hard cap helps readability).
 *
 * `navGroups` is plain serializable data, rendered into `SidebarNav` here
 * rather than the caller building `<SidebarNav>` itself and handing this
 * component the element (or a function of `collapsed`) — a `Server
 * Component` caller (the (app) layout) can pass a plain-data prop across
 * the client boundary, but not a function, which RSC serialization
 * rejects outright ("Functions cannot be passed directly to Client
 * Components"). Owning the render here is also what actually gets this
 * shell's own `collapsed` state to `SidebarNav`'s icon-only mode, which a
 * pre-built element from the caller could never receive.
 *
 * @param {{
 *   navGroups: { label: string, items: { label: string, href: string, icon?: React.ReactNode }[] }[],
 *   topbarExtra?: React.ReactNode,
 *   unreadNotifications?: number,
 *   recentNotifications?: object[],
 *   locale?: string,
 *   userName?: string,
 *   userEmail?: string,
 *   companies?: { id: string, name: string, code: string }[],
 *   currentCompanyId?: string,
 *   companyLogoUrl?: string,
 *   switchCompanyAction?: (companyId: string) => Promise<void>,
 *   children: React.ReactNode,
 * }} props
 */
function AppShell({
  navGroups,
  topbarExtra,
  unreadNotifications = 0,
  recentNotifications = [],
  locale = "en",
  userName,
  userEmail,
  companies = [],
  currentCompanyId,
  companyLogoUrl,
  switchCompanyAction,
  children,
}) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-background">
      {/* Mobile overlay drawer */}
      {mobileOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-foreground/40" onClick={() => setMobileOpen(false)} />
          <div className="relative z-10 flex h-full w-[260px] flex-col border-r border-border bg-surface shadow-lg">
            <SidebarHeader
              onClose={() => setMobileOpen(false)}
              companies={companies}
              currentCompanyId={currentCompanyId}
              companyLogoUrl={companyLogoUrl}
              switchCompanyAction={switchCompanyAction}
            />
            <SidebarNav groups={navGroups} />
            <SidebarProfile name={userName} email={userEmail} />
          </div>
        </div>
      ) : null}

      {/* Fixed sidebar, desktop — `sticky top-0 h-screen` pins the whole rail
          to the viewport regardless of how tall the main content grows, so
          the logo/switcher header and the profile footer never scroll out
          of view; only `SidebarNav`'s own `overflow-y-auto` list scrolls. */}
      <aside
        className={cn(
          "sticky top-0 hidden h-screen shrink-0 flex-col border-r border-border bg-surface transition-[width] duration-150 ease-out lg:flex",
          collapsed ? "w-[72px]" : "w-[260px]",
        )}
      >
        <SidebarHeader
          collapsed={collapsed}
          onToggleCollapse={() => setCollapsed((value) => !value)}
          companies={companies}
          currentCompanyId={currentCompanyId}
          companyLogoUrl={companyLogoUrl}
          switchCompanyAction={switchCompanyAction}
        />
        <SidebarNav groups={navGroups} collapsed={collapsed} />
        <SidebarProfile name={userName} email={userEmail} collapsed={collapsed} />
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar
          onOpenMobileNav={() => setMobileOpen(true)}
          extra={topbarExtra}
          unreadNotifications={unreadNotifications}
          recentNotifications={recentNotifications}
          locale={locale}
        />

        <main className="flex-1">
          <div className="mx-auto w-[95%] p-4 sm:p-6">{children}</div>
        </main>

        <Footer />
      </div>
    </div>
  );
}

function Footer() {
  return (
    <footer className="sticky bottom-0 z-20 flex h-10 shrink-0 items-center justify-between border-t border-border bg-surface/95 px-4 text-xs text-foreground-muted backdrop-blur supports-backdrop-filter:bg-surface/75 sm:px-6">
      <span>A Product by Data State Ltd</span>
      <span>App Version : 2.0</span>
    </footer>
  );
}

function SidebarHeader({ collapsed = false, onClose, onToggleCollapse, companies = [], currentCompanyId, companyLogoUrl, switchCompanyAction }) {
  return (
    <div className="flex shrink-0 flex-col gap-2 border-b border-border p-3">
      <div className="flex items-center justify-between gap-2">
        {companyLogoUrl ? (
          // A real logo keeps its own shape (most are landscape wordmarks,
          // not square icons) rather than being force-cropped into the
          // fallback mark's square badge — `object-contain` never crops,
          // just shrinks to fit the height available.
          // eslint-disable-next-line @next/next/no-img-element -- a tenant-hosted, on-disk logo, not an optimizable remote asset worth Next's image pipeline
          <img
            src={companyLogoUrl}
            alt=""
            className={cn("h-8 shrink-0 object-contain object-left", collapsed ? "w-8" : "max-w-[160px]")}
          />
        ) : (
          <span className="flex size-8 shrink-0 items-center justify-center rounded-sm bg-brand text-brand-foreground">
            <Wrench className="size-4" />
          </span>
        )}

        {onToggleCollapse ? (
          <button
            type="button"
            onClick={onToggleCollapse}
            className={cn("rounded-sm p-1.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground", collapsed && "hidden")}
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            <ChevronLeft className="size-4" />
          </button>
        ) : null}

        {onClose ? (
          <button type="button" onClick={onClose} className="rounded-sm p-1 hover:bg-surface-muted lg:hidden">
            <X className="size-5" />
          </button>
        ) : null}
      </div>

      {collapsed ? (
        <button
          type="button"
          onClick={onToggleCollapse}
          className="flex items-center justify-center rounded-sm p-1.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground"
          aria-label="Expand sidebar"
        >
          <ChevronRight className="size-4" />
        </button>
      ) : null}

      <CompanySwitcher companies={companies} currentCompanyId={currentCompanyId} switchCompanyAction={switchCompanyAction} collapsed={collapsed} />
    </div>
  );
}

function Topbar({ onOpenMobileNav, extra, unreadNotifications = 0, recentNotifications = [], locale = "en" }) {
  return (
    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface/95 px-4 backdrop-blur supports-backdrop-filter:bg-surface/75 sm:px-6">
      <button
        type="button"
        onClick={onOpenMobileNav}
        className="rounded-sm p-1.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground lg:hidden"
      >
        <Menu className="size-5" />
      </button>

      <div className="min-w-0 flex-1">{extra}</div>

      <div className="flex items-center gap-1">
        <SyncIndicator />
        <LocaleToggle locale={locale} />
        <ThemeToggle />
        <NotificationBell unreadCount={unreadNotifications} recent={recentNotifications} />
      </div>
    </header>
  );
}

const THEME_ICONS = { light: Sun, dark: Moon, system: Monitor };

function ThemeToggle() {
  const { theme, setTheme } = useTheme();
  const order = ["light", "dark", "system"];
  const Icon = THEME_ICONS[theme];

  return (
    <button
      type="button"
      onClick={() => setTheme(order[(order.indexOf(theme) + 1) % order.length])}
      className="rounded-sm p-2 text-foreground-muted hover:bg-surface-muted hover:text-foreground"
      aria-label={`Theme: ${theme}. Click to change.`}
      title={`Theme: ${theme}`}
    >
      <Icon className="size-[18px]" />
    </button>
  );
}

export { AppShell };
