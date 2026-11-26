import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Pre-Budget → Master Data (AnnotechERP 33/34): the two Column Setups and
 * Pre-Budget Master Data (its lists in tabs). Only whoever may change the
 * master data sees Add, Edit and the Status switch.
 */
export default async function PreBudgetMasterPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([
    getT("prebudget"),
    getLocale(),
    screenContext(`/pre-budget-master/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes("prebudget.master.manage");

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : m(`screens.${key}`)}
        description={m(`descriptions.${key}`)}
        icon={context ? undefined : "Database"}
        moduleCode="PRE_BUDGET"
        locale={locale}
      />
      {await render({ m, canManage, searchParams: query })}
    </>
  );
}
