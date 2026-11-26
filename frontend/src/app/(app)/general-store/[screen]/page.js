import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/** Dashboard and Inventory (10/02–10/03) read `inv.*`; Requisition `req.*`, Administration `admin.*`, the master lists `master.*`. */
const INVENTORY = [
  "gs-dashboard",
  "gs-opening",
  "gs-receive",
  "gs-issue",
  "gs-return",
  "gs-adjustment",
  "gs-transfer",
  "gs-current-stock",
  "gs-ledger",
];

/**
 * General Store (AnnotechERP 10), one Brickland screen per route. The master
 * lists are one card each — "Item Category (10)", Excel and New — with Edit on
 * every row; the New button and Edit show only to whoever may change them.
 * Dashboard and Inventory screens get the caller's permissions.
 */
export default async function GeneralStorePage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([
    getT("store"),
    getLocale(),
    screenContext(`/general-store/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes("store.master.manage");
  // The master lists read `master.*`; Dashboard and Inventory read `inv.*`.
  const group = INVENTORY.includes(key)
    ? "inv"
    : key.startsWith("gs-req-")
      ? "req"
      : key.startsWith("gs-rpt-")
        ? "rpt"
        : ["gs-audit-log", "gs-settings"].includes(key)
          ? "admin"
          : "master";
  const g = (k, p) => t(`${group}.${k}`, p);

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : g(`screens.${key}`)}
        description={g(`descriptions.${key}`)}
        icon={context ? undefined : "Warehouse"}
        moduleCode="GENERAL_STORE"
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
