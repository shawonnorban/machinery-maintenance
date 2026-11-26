import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

const MASTERS = [
  "gp-categories",
  "gp-location-types",
  "gp-locations",
  "gp-items",
  "gp-purposes",
];

/**
 * Gate Pass (AnnotechERP 11), one Brickland screen per route. The master
 * lists are one card each with a New dialog and Edit on every row, shown only
 * to whoever may change them; Dashboard, Reports and the gate pass screens
 * (11/02–11/04) get the caller's permissions.
 */
export default async function GatePassPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([
    getT("gatepass"),
    getLocale(),
    screenContext(`/gate-pass/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes("gatepass.master.manage");
  // The master lists read `master.*`; Dashboard, Reports and the gate pass screens `gp.*`.
  const group = MASTERS.includes(key) ? "master" : "gp";
  const g = (k, p) => t(`${group}.${k}`, p);

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : g(`screens.${key}`)}
        description={g(`descriptions.${key}`)}
        icon={context ? undefined : "DoorOpen"}
        moduleCode="GATE_PASS"
        locale={locale}
      />
      {await render({
        m,
        canManage,
        searchParams: query,
        permissions: permissions.permissions,
      })}
    </>
  );
}
