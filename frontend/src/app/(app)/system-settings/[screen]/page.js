import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SETTINGS_SCREENS } from "./screens";

/**
 * Settings (AnnotechERP 03), one Brickland screen per route: the page header,
 * the screen's tabs (`?tab=`) and the chosen tab's body (screens.js).
 */
export default async function SettingsScreenPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SETTINGS_SCREENS[key];
  if (!render) notFound();

  const [ts, te, locale, context] = await Promise.all([getT("settings"), getT("erp"), getLocale(), screenContext(`/system-settings/${key}`)]);
  const h = (k, params) => ts(`hub.${k}`, params);

  const probe = await render({ h, te, tab: query.tab ?? null, key, searchParams: query, locale });
  const fallback = probe.defaultTab ?? "records";
  const active = probe.tabs.some((tab) => tab.key === query.tab) ? query.tab : fallback;
  const screen = active === (query.tab ?? null) ? probe : await render({ h, te, tab: active, key, searchParams: query, locale });
  const body = await screen.body();

  return (
    <>
      <ErpPageHeader context={context} title={context ? undefined : h(`screens.${key}`)} description={h(`descriptions.${key}`)} icon={context ? undefined : "SlidersHorizontal"} moduleCode="SETTINGS" locale={locale} />
      {screen.tabs.length ? <ScreenTabs basePath={`/system-settings/${key}`} active={active} tabs={screen.tabs} /> : null}
      {body}
    </>
  );
}
