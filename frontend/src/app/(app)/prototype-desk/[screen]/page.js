import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS as ALL } from "../../merchandise-desk/[screen]/screens";
import { SAMPLE_PLANNING_SCREENS } from "./sample-planning";
import { SAMPLE_MATERIAL_SCREENS } from "./sample-material";
import { SAMPLE_QUALITY_SCREENS } from "./sample-quality";
import { SAMPLE_DASHBOARD_SCREENS } from "./sample-dashboard";

/** Prototype Desk → Dashboard (07/02), Master Data (07/01) on the Merchandise Desk screen kit, and the sample documents (07/03). */
const SCREENS = { "sample-type-configuration": ALL["sample-type-configuration"], ...SAMPLE_PLANNING_SCREENS, ...SAMPLE_MATERIAL_SCREENS, ...SAMPLE_QUALITY_SCREENS, ...SAMPLE_DASHBOARD_SCREENS };
/** The screens whose title and words live with the Prototype Desk rather than the Merchandise Desk. */
const OWN = new Set([...Object.keys(SAMPLE_PLANNING_SCREENS), ...Object.keys(SAMPLE_MATERIAL_SCREENS), ...Object.keys(SAMPLE_QUALITY_SCREENS), ...Object.keys(SAMPLE_DASHBOARD_SCREENS)]);

/**
 * Prototype Desk (AnnotechERP 07), one Brickland screen per route: the page
 * header, the tab strip (`?tab=`) and the chosen tab. The New tab and the
 * grid's Edit/Delete show only to whoever may change master data.
 */
export default async function PrototypeScreenPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, tp, te, locale, context, permissions] = await Promise.all([
    getT("merchandise"),
    getT("prototype"),
    getT("erp"),
    getLocale(),
    screenContext(`/prototype-desk/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes("merchandise.master.manage");

  const granted = permissions.permissions;
  const probe = await render({ m, te, tab: query.tab ?? "records", canManage, searchParams: query, locale, permissions: granted });
  const active = probe.tabs.some((tab) => tab.key === query.tab) ? query.tab : "records";
  const screen = active === (query.tab ?? "records") ? probe : await render({ m, te, tab: active, canManage, searchParams: query, locale, permissions: granted });
  const own = OWN.has(key);

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : own ? tp(`screens.${key}`) : m(`screens.${key}`)}
        description={own ? tp(`descriptions.${key}`) : m(`descriptions.${key}`)}
        icon={context ? undefined : "Scissors"}
        moduleCode="PROTOTYPE"
        locale={locale}
      />
      {screen.tabs.length ? <ScreenTabs basePath={`/prototype-desk/${key}`} active={active} tabs={screen.tabs} /> : null}
      {await screen.body()}
    </>
  );
}
