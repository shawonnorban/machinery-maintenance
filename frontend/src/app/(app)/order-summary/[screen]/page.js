import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Order Summary (AnnotechERP 32, from Norban), one menu screen per route: the
 * page header and the screen. What each user may write shows only to them
 * (create / update / delete on the sheet, Column Setup, teams, mail).
 */
export default async function OrderSummaryPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("ordersummary"), getLocale(), screenContext(`/order-summary/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  const body = await render({ t, can, query, key });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "Sheet"}
        moduleCode="ORDER_SUMMARY"
        locale={locale}
      />
      {body}
    </>
  );
}
