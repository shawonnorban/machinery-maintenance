import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Pre-Budget (AnnotechERP 33/34, from Norban's Knit Woven and Accessories
 * Pre-Budget), one menu screen per route: the page header and the screen.
 */
export default async function PreBudgetPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("prebudget"), getLocale(), screenContext(`/pre-budget/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  const body = await render({ t, can, query, key });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "ClipboardList"}
        moduleCode="PRE_BUDGET"
        locale={locale}
      />
      {body}
    </>
  );
}
