import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { DEFAULT_TABS, SCREENS } from "./screens";

/**
 * Trade & Export (AnnotechERP 22), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the trade & export masters.
 */
export default async function TradeExportPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, op, te, locale, context, permissions] = await Promise.all([getT("tradeexport"), getT("tradeop"), getT("erp"), getLocale(), screenContext(`/trade-export/${key}`), apiFetch("/auth/permissions")]);
  const m = (k, p) => t(`master.${k}`, p);
  // Brickland's Trade & Export screens are single pages (form above, grid below) — no Edit tab to mirror.
  const words = null;
  const canManage = permissions.permissions.includes("tradeexport.master.manage");
  const tab = query.tab ?? DEFAULT_TABS[key] ?? "records";
  const screen = await render({ m, op, key, te, tab, canManage, permissions: permissions.permissions, searchParams: query });
  // A screen without tabs (Brickland lays these out on one page) keeps whatever view the url asks for.
  const active = !screen.tabs?.length || screen.tabs.some((x) => x.key === tab) ? tab : "records";
  const shown = active === tab ? screen : await render({ m, op, key, te, tab: active, canManage, permissions: permissions.permissions, searchParams: query });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : m(`screens.${key}`)}
        description={shown.description ?? m(`descriptions.${key}`)}
        icon={context ? undefined : "Globe"}
        moduleCode="TRADE_EXPORT"
        locale={locale}
      />
      {shown.tabs?.length ? <ScreenTabs basePath={`/trade-export/${key}`} active={active} tabs={shown.tabs} editLabel={words?.edit} /> : null}
      <ScreenWordsProvider words={words}>{await shown.body()}</ScreenWordsProvider>
    </>
  );
}
