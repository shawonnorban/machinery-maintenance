import { redirect } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { getNavigation } from "@/lib/navigation";
import { getT } from "@/lib/i18n-server";
import { ApiError } from "@/lib/api-error";
import { MenuIcon } from "@/components/layout/menu-icon";
import { AppShell } from "@/components/layout/app-shell";
import { RegisterServiceWorker } from "@/components/offline/register-service-worker";
import { UomProvider } from "@/components/erp/uom-select";
import { ForcePasswordChange } from "@/components/account/force-password-change";
import { PortalGuard } from "@/components/layout/portal-guard";
import { SetupGuard } from "@/components/layout/setup-guard";
import { switchCompany } from "./switch-company-action";

export default async function AppLayout({ children }) {
  let notifications, me, companies, navigation, uoms, restricted, locked;

  try {
    [notifications, me, companies, navigation, uoms, restricted, locked] = await Promise.all([
      // One call for both: `filter=ALL&per_page=20` gives the bell dropdown's
      // 20 most recent (it scrolls; it reads them again each time it opens) (mirrors `AppShellComposer::recentNotifications()`)
      // and `meta.unread_count` for its badge, computed regardless of filter —
      // the topbar needs both everywhere, not a per-page fetch.
      apiFetch("/notifications?filter=ALL&per_page=20", { includeMeta: true }),
      apiFetch("/auth/me"),
      // Every company this login may switch to — the sidebar's own
      // workspace switcher hides itself entirely when this is just one.
      apiFetch("/auth/companies"),
      // The sidebar (Access Control's menu tree, AnnotechERP 02/01): only the
      // screens this caller may open — each screen's View permission is the
      // exact string its own API checks, so no link is offered that the
      // backend would refuse. An admin renames, reorders and hides entries;
      // only code adds a screen (MenuRegistry).
      getNavigation(),
      // The unit pick lists of every screen that keeps a unit as its code;
      // empty for someone who may open none of them.
      apiFetch("/lookups/uoms").catch(() => []),
      // Setup screens (master data, settings) this caller may not keep: a
      // typed-in address shows "for administrators" instead of the page.
      apiFetch("/access/restricted-screens").catch(() => []),
      // Modules the platform has not switched on for this company (Quality Tally …).
      apiFetch("/access/locked-modules").catch(() => []),
    ]);
  } catch (error) {
    // An expired/revoked session token surfaces here as a 401 from the
    // first authenticated call this layout makes — every page under (app)
    // shares this layout, so this is the one place that needs to catch it
    // rather than each page crashing with a raw, uncaught ApiError.
    if (error instanceof ApiError && error.status === 401) {
      redirect("/login");
    }

    throw error;
  }

  // Menu names follow the language the interface is shown in.
  const [uiLocale, tn] = await Promise.all([getLocale(), getT("nav")]);
  const bangla = uiLocale === "bn";
  const label = (node) => (bangla && node.name_bn) || node.name;
  const screen = (item) => ({ code: item.code, label: label(item), href: item.url, newWindow: item.open_new_window, icon: <MenuIcon name={item.icon} /> });
  // A folder may hold folders (Brickland's OPERATION → Style & Order …).
  const entryOf = (entry) =>
    entry.type === "folder"
      ? { type: "folder", code: entry.code, label: label(entry), icon: <MenuIcon name={entry.icon} />, items: entry.items.map(screen), entries: (entry.entries ?? entry.items.map((item) => ({ type: "screen", ...item }))).map(entryOf) }
      : { type: "screen", ...screen(entry) };
  const modules = navigation.map((module) => ({
    code: module.code,
    label: label(module),
    section: module.section,
    sectionLabel: tn(`sections.${module.section}`),
    icon: <MenuIcon name={module.icon} />,
    count: module.count,
    entries: module.entries.map(entryOf),
  }));

  return (
    <>
      <RegisterServiceWorker />
      <ForcePasswordChange required={me.user?.must_change_password === true} />
      {/* What a printed document heads and signs itself with (lib/print-document). */}
      <template id="erp-print-context" data-company={me.company?.name ?? ""} data-logo={me.company?.logo_url ?? ""} data-user={me.user?.name ?? me.name ?? ""} />
      <AppShell
        modules={modules}
        moduleCount={modules.length}
        unreadNotifications={notifications.meta.unread_count}
        recentNotifications={notifications.data}
        locale={me.user?.locale ?? "en"}
        userName={me.user?.name ?? me.name}
        userEmail={me.user?.email}
        companies={companies}
        currentCompanyId={me.company_id}
        companyName={me.company?.name}
        companyLogoUrl={me.company?.logo_url}
        switchCompanyAction={switchCompany}
        impersonatedBy={me.impersonated_by}
      >
        {/* A supplier's portal login sees only the Supplier Portal (AnnotechERP 08/07). */}
        <UomProvider uoms={uoms}>{me.user?.portal_only ? <PortalGuard>{children}</PortalGuard> : <SetupGuard restricted={restricted} locked={locked}>{children}</SetupGuard>}</UomProvider>
      </AppShell>
    </>
  );
}
