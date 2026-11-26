import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";
import { OPS_SCREENS } from "./ops-screens";

/**
 * Fabric Requirement (AnnotechERP 13), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the masters (13/01) or the plans
 * (13/02).
 */
export default async function FabricRequirementPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const ops = Boolean(OPS_SCREENS[key]);
  const render = SCREENS[key] ?? OPS_SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([getT("fabricplanning"), getT("erp"), getLocale(), screenContext(`/fabric-requirement/${key}`), apiFetch("/auth/permissions")]);
  const m = (k, p) => t(`master.${k}`, p);
  const o = (k, p) => t(`ops.${k}`, p);
  // The Operations screens (13/02) read `ops.*`; the masters `master.*`.
  const g = ops ? o : m;
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const words = ["batch-plan-date-before-shipment-pcd", "standard-batch-hour-setup", "dyeing-machine-routing-setup", "planning-constraints-rules", "factory-holiday-model", "dyeing-machine-capacity-dashboard", "batch-scheduling", "fabric-planning-reports"].includes(key)
    ? screenWords(te, g(`noun.${key}`))
    : null;
  const canManage = permissions.permissions.includes(key === "fabric-planning-reports" ? "fabricplanning.report.view" : ops ? "fabricplanning.plan.manage" : "fabricplanning.master.manage");
  const tab = query.tab ?? "records";
  const args = { m, o, te, canManage, searchParams: query };
  const screen = await render({ ...args, tab });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown = active === tab ? screen : await render({ ...args, tab: active });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : g(`screens.${key}`)}
        description={g(`descriptions.${key}`)}
        icon={context ? undefined : "Layers"}
        moduleCode="FABRIC_REQ"
        locale={locale}
      />
      {shown.tabs?.length ? <ScreenTabs basePath={`/fabric-requirement/${key}`} active={active} tabs={shown.tabs} editLabel={words?.edit} /> : null}
      <ScreenWordsProvider words={words}>{await shown.body()}</ScreenWordsProvider>
    </>
  );
}
