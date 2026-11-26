import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Cutting Floor (AnnotechERP 19), one Brickland screen per route: the page
 * header and the screen. Cutting, printing and cancelling show only to
 * whoever may cut bundle cards.
 */
export default async function CuttingFloorPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("cutting"), getLocale(), screenContext(`/cutting-floor/${key}`), apiFetch("/auth/permissions")]);
  const w = (k, p) => t(k, p);
  // New Cutting is the cutting master's (cutting.input.manage); the output and the cards are the output clerk's (cutting.bundle.manage).
  const canManage = permissions.permissions.includes(key === "new-cutting" ? "cutting.input.manage" : "cutting.bundle.manage");
  const screen = await render({ w, canManage, searchParams: query });

  return (
    <>
      <ErpPageHeader context={context} title={context ? undefined : w(`screens.${key}`)} description={w(`descriptions.${key}`)} icon={context ? undefined : "Scissors"} moduleCode="CUTTING" locale={locale} />
      {await screen.body()}
    </>
  );
}
