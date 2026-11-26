import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Colour Lab (AnnotechERP 16), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may work the lab; approving a fabric test
 * to whoever may approve it.
 */
export default async function ColourLabPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([
    getT("colourlab"),
    getT("erp"),
    getLocale(),
    screenContext(`/colour-lab/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const c = (k, p) => t(k, p);
  // Brickland's words for the register ("New register entry", "Edit register entry", "Remove this register entry?").
  const words =
    key === "certificate-register" ? screenWords(te, c("noun.register")) : null;
  const can = {
    manage: permissions.permissions.includes("colourlab.manage"),
    approve: permissions.permissions.includes("colourlab.approve"),
  };
  const tab = query.tab ?? "records";
  const screen = await render({ c, te, tab, can, searchParams: query });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown =
    active === tab
      ? screen
      : await render({ c, te, tab: active, can, searchParams: query });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : c(`screens.${key}`)}
        description={c(`descriptions.${key}`)}
        icon={context ? undefined : "FlaskConical"}
        moduleCode="COLOUR_LAB"
        locale={locale}
      />
      {shown.tabs?.length ? (
        <ScreenTabs
          basePath={`/colour-lab/${key}`}
          active={active}
          tabs={shown.tabs}
          editLabel={words?.edit}
        />
      ) : null}
      <ScreenWordsProvider words={words}>
        {await shown.body()}
      </ScreenWordsProvider>
    </>
  );
}
