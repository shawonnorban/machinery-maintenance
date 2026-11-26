import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Sales & Shipment (AnnotechERP 21), one Brickland screen per route: the
 * page header and the screen. Changing the settings shows only to whoever
 * may manage them.
 */
export default async function SalesShipmentPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("salesshipment"), getLocale(), screenContext(`/sales-shipment/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  const screen = await render({ can, query });

  return (
    <>
      <ErpPageHeader context={context} title={context ? undefined : t(`screens.${key}`)} description={t(`descriptions.${key}`)} icon={context ? undefined : "Truck"} moduleCode="SALES_SHIPMENT" locale={locale} />
      {await screen.body()}
    </>
  );
}
