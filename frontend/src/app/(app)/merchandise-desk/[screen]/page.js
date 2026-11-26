import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";
import { INQUIRY_MANAGE, INQUIRY_SCREENS } from "./inquiry";
import { ORDER_MANAGE, ORDER_SCREENS, STYLE_ORDER_FLOW } from "./order";
import { COSTING_MANAGE, COSTING_SCREENS } from "./costing";
import { SAMPLE_MANAGE, SAMPLE_SCREENS } from "./sample";
import { BOOKING_MANAGE, BOOKING_SCREENS } from "./booking";
import { TNA_MANAGE, TNA_SCREENS } from "./tna";
import { REPORT_MANAGE, REPORT_SCREENS } from "./reports";
import { ACCESSORIES_MANAGE, ACCESSORIES_SCREENS } from "./accessories";
import { ACC_TRIMS_MANAGE, ACC_TRIMS_SCREENS } from "./acc-trims";
import { UPLOAD_MANAGE, UPLOAD_SCREENS } from "./upload";
import { AI_MANAGE, AI_SCREENS } from "./ai-merch";
import { AI_MONITOR_MANAGE, AI_MONITOR_SCREENS } from "./ai-monitor";
import { FlowSteps } from "@/components/erp/flow-steps";

const ALL = { ...SCREENS, ...INQUIRY_SCREENS, ...ORDER_SCREENS, ...COSTING_SCREENS, ...SAMPLE_SCREENS, ...BOOKING_SCREENS, ...TNA_SCREENS, ...REPORT_SCREENS, ...ACCESSORIES_SCREENS, ...ACC_TRIMS_SCREENS, ...UPLOAD_SCREENS, ...AI_SCREENS, ...AI_MONITOR_SCREENS };
const MANAGE = { ...INQUIRY_MANAGE, ...ORDER_MANAGE, ...COSTING_MANAGE, ...SAMPLE_MANAGE, ...BOOKING_MANAGE, ...TNA_MANAGE, ...REPORT_MANAGE, ...ACCESSORIES_MANAGE, ...ACC_TRIMS_MANAGE, ...UPLOAD_MANAGE, ...AI_MANAGE, ...AI_MONITOR_MANAGE };

/**
 * Merchandise Desk (AnnotechERP 06), one Brickland screen per route: the page
 * header, the tab strip (`?tab=`) and the chosen tab. The New tab and the
 * grid's Edit/Delete show only to whoever may change the screen's data (master
 * data, or an Inquiry screen's own permission).
 */
export default async function MerchandiseScreenPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = ALL[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([
    getT("merchandise"),
    getT("erp"),
    getLocale(),
    screenContext(`/merchandise-desk/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes(MANAGE[key] ?? "merchandise.master.manage");

  // A screen may show a panel of another permission (the style sheet's Order Confirmation tab).
  const can = (permission) => permissions.permissions.includes(permission);
  const probe = await render({ m, te, tab: query.tab ?? "records", canManage, can, searchParams: query, locale });
  const active = probe.tabs.some((tab) => tab.key === query.tab) ? query.tab : "records";
  const screen = active === (query.tab ?? "records") ? probe : await render({ m, te, tab: active, canManage, can, searchParams: query, locale });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : m(`screens.${key}`)}
        description={m(`descriptions.${key}`)}
        icon={context ? undefined : "Shirt"}
        moduleCode="MERCHANDISE"
        locale={locale}
      />
      {STYLE_ORDER_FLOW.screens[key] ? (
        <FlowSteps
          title={m("flow.title")}
          stepLabel={m("flow.step", { n: STYLE_ORDER_FLOW.steps.findIndex((s) => s.key === STYLE_ORDER_FLOW.screens[key]) + 1, total: STYLE_ORDER_FLOW.steps.length })}
          prevLabel={m("flow.prev")}
          nextLabel={m("flow.next")}
          steps={STYLE_ORDER_FLOW.steps.map((s) => ({ ...s, label: m(`flow.steps.${s.key}`) }))}
          active={STYLE_ORDER_FLOW.screens[key]}
        />
      ) : null}
      {screen.tabs.length ? <ScreenTabs basePath={`/merchandise-desk/${key}`} active={active} tabs={screen.tabs} /> : null}
      {await screen.body()}
    </>
  );
}
