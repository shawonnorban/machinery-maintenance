import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Buyer Gateway (AnnotechERP 25), one Brickland screen per route: the page
 * header, then the screen — its tiles and its one card of records with New,
 * Edit, Delete and the row's switch for whoever may change them.
 */
export default async function BuyerGatewayPage({ params }) {
  const { screen: key } = await params;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("buyergateway"), getLocale(), screenContext(`/buyer-gateway/${key}`), apiFetch("/auth/permissions")]);
  const m = (k, p) => t(k, p);

  return (
    <>
      <ErpPageHeader context={context} title={context ? undefined : m(`screens.${key}`)} description={m(`descriptions.${key}`)} icon={context ? undefined : "Globe"} moduleCode="BUYER_GATEWAY" locale={locale} />
      {await render({ m, permissions: permissions.permissions })}
    </>
  );
}
