import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { DEFAULT_TABS, SCREENS as MASTER_SCREENS } from "./screens";
import { EMPLOYEE_SCREENS } from "./employee";

const SCREENS = { ...MASTER_SCREENS, ...EMPLOYEE_SCREENS };

/**
 * HR & Payroll (AnnotechERP 24), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the HR & payroll masters.
 */
export default async function HrPayrollPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([
    getT("hrpayroll"),
    getT("erp"),
    getLocale(),
    screenContext(`/hr-payroll/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const canManage = permissions.permissions.includes("hrpayroll.master.manage");
  const tab = query.tab ?? DEFAULT_TABS[key] ?? "records";
  const screen = await render({
    m,
    te,
    tab,
    canManage,
    searchParams: query,
    locale,
    permissions: permissions.permissions,
  });
  // A screen without tabs (Brickland lays these out on one page) keeps whatever view the url asks for.
  const active =
    !screen.tabs?.length || screen.tabs.some((x) => x.key === tab)
      ? tab
      : "records";
  const shown =
    active === tab
      ? screen
      : await render({
          m,
          te,
          tab: active,
          canManage,
          searchParams: query,
          locale,
          permissions: permissions.permissions,
        });
  // The employee screens carry their own words.
  const words = key in EMPLOYEE_SCREENS ? (k) => t(`emp.${k}`) : m;

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : words(`screens.${key}`)}
        description={words(`descriptions.${key}`)}
        icon={context ? undefined : "Users"}
        moduleCode="HR_PAYROLL"
        locale={locale}
        actions={shown.actions}
      />
      {shown.tabs?.length ? (
        <ScreenTabs
          basePath={`/hr-payroll/${key}`}
          active={active}
          tabs={shown.tabs}
        />
      ) : null}
      {await shown.body()}
    </>
  );
}
