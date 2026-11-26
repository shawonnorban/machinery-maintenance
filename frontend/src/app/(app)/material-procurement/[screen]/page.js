import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS as MASTER_SCREENS } from "./screens";
import { REQUISITION_SCREENS } from "./requisition";
import { PORTAL_SCREENS } from "./portal";
import { REVIEW_SCREENS } from "./review";
import { FOLLOWUP_SCREENS } from "./followup";
import { CONTROL_SCREENS } from "./control";
import { DASHBOARD_SCREENS } from "./dashboard";

const SCREENS = {
  ...MASTER_SCREENS,
  ...REQUISITION_SCREENS,
  ...PORTAL_SCREENS,
  ...REVIEW_SCREENS,
  ...FOLLOWUP_SCREENS,
  ...CONTROL_SCREENS,
  ...DASHBOARD_SCREENS,
};
/** The screens whose title lives with the purchase requisition or the portal rather than the master data. */
const OWN = new Set(Object.keys(REQUISITION_SCREENS));

/**
 * Material Procurement (AnnotechERP 08), one Brickland screen per route. The
 * master lists have no tab strip in Brickland: one card, its All / Active /
 * Inactive pills (`?status=`) and an Add dialog, which only whoever may change
 * the master data sees. The purchase requisition screens (08/03) carry their
 * own words and their own permission.
 */
export default async function MaterialProcurementPage({
  params,
  searchParams,
}) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([
    getT("materialprocurement"),
    getLocale(),
    screenContext(`/material-procurement/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes("material.master.manage");
  // Which word group a screen's title lives in: the requisition's, the portal's, or the master data's.
  const own =
    key === "supplier-portal-access"
      ? "portal"
      : key in PORTAL_SCREENS
        ? "sp"
        : key in REVIEW_SCREENS
          ? "review"
          : key in FOLLOWUP_SCREENS
            ? "fu"
            : key in CONTROL_SCREENS
              ? "ctl"
              : key in DASHBOARD_SCREENS
                ? "dash"
                : OWN.has(key)
                  ? "pr"
                  : null;

  return (
    <>
      <ErpPageHeader
        context={context}
        title={
          context
            ? undefined
            : own
              ? t(`${own}.screens.${key}`)
              : m(`screens.${key}`)
        }
        description={
          own ? t(`${own}.descriptions.${key}`) : m(`descriptions.${key}`)
        }
        icon={context ? undefined : "ShoppingCart"}
        moduleCode="MATERIAL"
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
