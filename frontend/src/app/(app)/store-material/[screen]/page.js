import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Inventory & Stores → Store & Material (AnnotechERP 09), one Brickland
 * screen per route, each with its own permission.
 */
export default async function StoreMaterialPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([
    getT("storematerial"),
    getLocale(),
    screenContext(`/store-material/${key}`),
    apiFetch("/auth/permissions"),
  ]);

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "Boxes"}
        moduleCode="STORE_MATERIAL"
        locale={locale}
      />
      {await render({
        searchParams: query,
        permissions: permissions.permissions,
      })}
    </>
  );
}
