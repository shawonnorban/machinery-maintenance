"use client";

import { useState } from "react";
import { Menu, X, PanelLeft } from "lucide-react";
import { cn } from "@/lib/utils";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { SupportSessionBanner } from "@/components/layout/support-session-banner";
import { SyncIndicator } from "@/components/offline/sync-indicator";
import { LocaleToggle } from "@/components/layout/locale-toggle";
import { NotificationBell } from "@/components/layout/notification-bell";
import { ChatButton } from "@/components/chat/chat-button";
import { ChatDock } from "@/components/chat/chat-dock";
import { CompanySwitcher } from "@/components/layout/company-switcher";
import { ErpSidebar } from "@/components/layout/erp-sidebar";
import { ModuleChip, ScreenSearch, UserMenu } from "@/components/layout/erp-topbar";

/**
 * The tenant application shell, laid out as Brickland's (AnnotechERP UI
 * parity): a sidebar of sections → modules → folders → screens with the
 * company mark on top and the system-status card at the foot; a topbar with
 * the current module chip, screen search, the company, theme, language,
 * notifications and the signed-in user; the page; a one-line footer.
 *
 * `modules` is plain data with rendered icon nodes — a server component can
 * pass JSX across the client boundary, but not a function.
 *
 * @param {{
 *   modules: object[], moduleCount?: number, unreadNotifications?: number, recentNotifications?: object[],
 *   locale?: string, userName?: string, userEmail?: string, companies?: object[], currentCompanyId?: string,
 *   companyName?: string, companyLogoUrl?: string, switchCompanyAction?: (companyId: string) => Promise<void>,
 *   impersonatedBy?: object, children: React.ReactNode,
 * }} props
 */
function AppShell({
  modules = [],
  moduleCount = 0,
  unreadNotifications = 0,
  recentNotifications = [],
  locale = "en",
  userName,
  userEmail,
  companies = [],
  currentCompanyId,
  companyName,
  companyLogoUrl,
  switchCompanyAction,
  impersonatedBy,
  children,
}) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-background">
      {mobileOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-foreground/40" onClick={() => setMobileOpen(false)} />
          <div className="relative z-10 flex h-full w-[272px] flex-col border-r border-border bg-surface shadow-lg">
            <SidebarBrand name={companyName} logoUrl={companyLogoUrl} onClose={() => setMobileOpen(false)} />
            <ErpSidebar modules={modules} moduleCount={moduleCount} />
          </div>
        </div>
      ) : null}

      {/* Pinned to the viewport: only the menu list scrolls. */}
      <aside className={cn("sticky top-0 hidden h-screen shrink-0 flex-col border-r border-border bg-surface transition-[width] duration-150 ease-out lg:flex", collapsed ? "w-[72px]" : "w-[272px]")}>
        <SidebarBrand name={companyName} logoUrl={companyLogoUrl} collapsed={collapsed} />
        <ErpSidebar modules={modules} collapsed={collapsed} moduleCount={moduleCount} />
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        {impersonatedBy ? <SupportSessionBanner actingAsName={userName} actingAsEmail={userEmail} staffName={impersonatedBy.name} /> : null}

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

          <ModuleChip modules={modules} />
          <ScreenSearch modules={modules} />
          <div className="hidden rounded-full border border-border px-1.5 py-0.5 xl:block">
            <CompanySwitcher companies={companies} currentCompanyId={currentCompanyId} switchCompanyAction={switchCompanyAction} />
          </div>

          <div className="ml-auto flex items-center gap-1">
            <SyncIndicator />
            <ThemeToggle />
            <LocaleToggle locale={locale} />
            <ChatButton />
            <NotificationBell unreadCount={unreadNotifications} recent={recentNotifications} />
            <span className="mx-2 hidden h-8 w-px bg-border sm:block" aria-hidden />
            <UserMenu name={userName} email={userEmail} />
          </div>
        </header>

        <ChatDock />
        <main className="flex-1">
          <div className="mx-auto w-full p-4 sm:px-6 sm:py-5">{children}</div>
        </main>

        <footer className="sticky bottom-0 z-20 flex h-10 shrink-0 items-center justify-between gap-4 border-t border-border bg-surface/95 px-4 text-xs text-foreground-muted backdrop-blur supports-backdrop-filter:bg-surface/80 sm:px-6">
          <span className="truncate">
            Annotech ERP · <span className="font-medium text-brand">{companyName}</span> · A Product by Data State Ltd
          </span>
          <span className="shrink-0">App Version : 2.0</span>
        </footer>
      </div>
    </div>
  );
}

/** The company mark and name at the head of the sidebar, as Brickland shows its own. */
function SidebarBrand({ name, logoUrl, collapsed = false, onClose }) {
  const initial = name?.charAt(0)?.toUpperCase() || "A";

  return (
    <div className={cn("flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-4", collapsed && "justify-center px-0")}>
      {logoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element -- a tenant-hosted, on-disk logo, not an optimizable remote asset
        <img src={logoUrl} alt="" className="size-9 shrink-0 rounded-full border border-border object-contain" />
      ) : (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-brand-foreground">{initial}</span>
      )}
      {!collapsed ? (
        <span className="min-w-0 flex-1 leading-tight">
          <span className="block truncate text-[15px] font-bold text-brand">{name}</span>
          <span className="block text-[10px] font-semibold tracking-[0.16em] text-foreground-muted uppercase">Annotech ERP</span>
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

export { AppShell };
