import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Assembly Floor (AnnotechERP 20), one Brickland screen per route: the page
 * header and the screen. Scanning and withdrawing show only to whoever may
 * scan bundles on and off the sewing (or finishing) lines; moving finishing
 * rework on, only to a supervisor. Quality (20/03) gates the same way on
 * assembly.quality.manage and .supervise; Packing (20/04–05) on
 * assembly.packing.manage and .supervise; Shipment (20/07) on
 * assembly.shipment.manage and .supervise. Shipment Entry reads the PO and
 * colour picked on Ready for Shipment from its query (?po=…&colour=…).
 */
export default async function AssemblyFloorPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("assembly"), getLocale(), screenContext(`/assembly-floor/${key}`), apiFetch("/auth/permissions")]);
  const w = (k, p) => t(k, p);
  const can = (perm) => permissions.permissions.includes(perm);
  const canManage = can("assembly.sewing.manage");
  const screen = await render({ w, canManage, can, query });

  return (
    <>
      <ErpPageHeader context={context} title={context ? undefined : w(`screens.${key}`)} description={w(`descriptions.${key}`)} icon={context ? undefined : "Shirt"} moduleCode="ASSEMBLY" locale={locale} />
      {await screen.body()}
    </>
  );
}
