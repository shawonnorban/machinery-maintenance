import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Production Tracking (AnnotechERP 30), one Brickland screen per route: the
 * page header and the screen. Writing shows only to whoever may: Production
 * Master Data (.entry.manage), Line Capacity (.capacity.manage), Daily Plan
 * (.plan.prepare / .plan.approve), TV screens (.tv.manage), Settings
 * (.settings.manage).
 */
export default async function ProductionTrackingPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("productiontracking"), getLocale(), screenContext(`/production-tracking/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  const body = await render({ t, can, query, key });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "Activity"}
        moduleCode="PRODUCTION_TRACKING"
        locale={locale}
      />
      {body}
    </>
  );
}
