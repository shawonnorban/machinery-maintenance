import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { DEFAULT_TABS, SCREENS } from "./screens";
import { SPEC_DEFAULT_TABS, SPEC_SCREENS } from "./spec-screens";
import { COST_SCREENS } from "./cost-screens";
import { OT_SCREENS } from "./ot-screens";
import { TF_SCREENS } from "./tf-screens";
import { FO_SCREENS } from "./fo-screens";
import { SP_SCREENS } from "./sp-screens";
import { RP_SCREENS } from "./rp-screens";

/**
 * Work Study (AnnotechERP 14), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the work study masters.
 */
export default async function WorkStudyPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const spec = Boolean(SPEC_SCREENS[key]);
  const cost = Boolean(COST_SCREENS[key]);
  const ot = Boolean(OT_SCREENS[key]);
  const tf = Boolean(TF_SCREENS[key]);
  const fo = Boolean(FO_SCREENS[key]);
  const sp = Boolean(SP_SCREENS[key]);
  const rp = Boolean(RP_SCREENS[key]);
  const render =
    SCREENS[key] ??
    SPEC_SCREENS[key] ??
    COST_SCREENS[key] ??
    OT_SCREENS[key] ??
    TF_SCREENS[key] ??
    FO_SCREENS[key] ??
    SP_SCREENS[key] ??
    RP_SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([
    getT("workstudy"),
    getT("erp"),
    getLocale(),
    screenContext(`/work-study/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  const s = (k, p) => t(`spec.${k}`, p);
  const c = (k, p) => t(`cost.${k}`, p);
  const o = (k, p) => t(`ot.${k}`, p);
  const f = (k, p) => t(`tf.${k}`, p);
  const fl = (k, p) => t(`fo.${k}`, p);
  const sl = (k, p) => t(`sp.${k}`, p);
  const rl = (k, p) => t(`rp.${k}`, p);
  // The Item Specification screens (14/04) read `spec.*`, Costing (14/05) `cost.*`, OT & Salary (14/06) `ot.*`, Target & Feeding (14/03) `tf.*`, Floor Operations (14/07) `fo.*`, Section Profitability (14/08) `sp.*`, Reports (14/02) `rp.*`; the masters `master.*`.
  const g = spec
    ? s
    : cost
      ? c
      : ot
        ? o
        : tf
          ? f
          : fo
            ? fl
            : sp
              ? sl
              : rp
                ? rl
                : m;
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const words = [
    "item-and-process-group",
    "learning-curve-model",
    "resource-capability-profile",
    "gmt-item-specification",
    ...Object.keys(COST_SCREENS),
    ...Object.keys(OT_SCREENS),
    ...Object.keys(TF_SCREENS),
    ...Object.keys(FO_SCREENS),
    ...Object.keys(SP_SCREENS),
    ...Object.keys(RP_SCREENS),
  ].includes(key)
    ? screenWords(te, g(`noun.${key}`))
    : null;
  const canManage = permissions.permissions.includes(
    spec
      ? "workstudy.spec.manage"
      : cost
        ? "workstudy.costing.manage"
        : ot
          ? "workstudy.ot.manage"
          : tf
            ? "workstudy.target.manage"
            : fo
              ? "workstudy.floorops.manage"
              : sp
                ? "workstudy.profit.manage"
                : rp
                  ? "workstudy.report.view"
                  : "workstudy.master.manage",
  );
  const tab =
    query.tab ?? DEFAULT_TABS[key] ?? SPEC_DEFAULT_TABS[key] ?? "records";
  const args = {
    m,
    s,
    c,
    o,
    f: fo ? fl : sp ? sl : rp ? rl : f,
    te,
    canManage,
    searchParams: query,
  };
  const screen = await render({ ...args, tab });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown =
    active === tab ? screen : await render({ ...args, tab: active });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : g(`screens.${key}`)}
        description={g(`descriptions.${key}`)}
        icon={context ? undefined : "Gauge"}
        moduleCode="WORK_STUDY"
        locale={locale}
      />
      {shown.tabs?.length ? (
        <ScreenTabs
          basePath={`/work-study/${key}`}
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
