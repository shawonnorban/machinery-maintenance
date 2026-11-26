import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { DEFAULT_TABS, SCREENS, VO_SCREENS } from "./screens";

/**
 * Finance & Ledger (AnnotechERP 23), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the finance & ledger masters.
 */
export default async function FinanceLedgerPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([getT("accounting"), getT("erp"), getLocale(), screenContext(`/finance-ledger/${key}`), apiFetch("/auth/permissions")]);
  const m = (k, p) => t(`master.${k}`, p);
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const words = ["chart-of-account", "cheque-book-registers", "coa-type-configuration", "compliance-ledger-rules", "cost-center-group", "cost-center", "fiscal-year", "manage-bank", "payment-mode"].includes(key) ? screenWords(te, m(`noun.${key}`)) : null;
  const canManage = permissions.permissions.includes("accounting.master.manage");
  // Voucher Entry (23/02) screens keep their words under "vo".
  const pre = VO_SCREENS.includes(key) ? "vo" : "master";
  const tab = query.tab ?? DEFAULT_TABS[key] ?? "records";
  const screen = await render({ m, te, tab, canManage, permissions: permissions.permissions, searchParams: query });
  // A screen without tabs (Brickland lays these out on one page) keeps whatever view the url asks for.
  const active = !screen.tabs?.length || screen.tabs.some((x) => x.key === tab) ? tab : "records";
  const shown = active === tab ? screen : await render({ m, te, tab: active, canManage, permissions: permissions.permissions, searchParams: query });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`${pre}.screens.${key}`)}
        description={t(`${pre}.descriptions.${key}`)}
        icon={context ? undefined : "Landmark"}
        moduleCode="FINANCE"
        locale={locale}
      />
      {shown.tabs?.length ? <ScreenTabs basePath={`/finance-ledger/${key}`} active={active} tabs={shown.tabs} editLabel={words?.edit} /> : null}
      <ScreenWordsProvider words={words}>{await shown.body()}</ScreenWordsProvider>
    </>
  );
}
