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
import { YARN_SCREENS } from "./yarn-screens";
import { YSTORE_SCREENS } from "./ystore-screens";
import { PROD_SCREENS } from "./prod-screens";
import { FSTORE_SCREENS } from "./fstore-screens";
import { CC_SCREENS } from "./cc-screens";
import { NEEDLE_SCREENS } from "./needle-screens";
import { REPORT_SCREENS } from "./rpt-screens";

/**
 * Knit Production (AnnotechERP 15), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the knit production masters.
 */
export default async function KnitProductionPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const yarn = Boolean(YARN_SCREENS[key]);
  const ystore = Boolean(YSTORE_SCREENS[key]);
  const needle = Boolean(NEEDLE_SCREENS[key]);
  const report = Boolean(REPORT_SCREENS[key]);
  const prod = Boolean(
    PROD_SCREENS[key] || CC_SCREENS[key] || needle || report,
  );
  const fstore = Boolean(FSTORE_SCREENS[key]);
  const render =
    SCREENS[key] ??
    YARN_SCREENS[key] ??
    YSTORE_SCREENS[key] ??
    PROD_SCREENS[key] ??
    FSTORE_SCREENS[key] ??
    CC_SCREENS[key] ??
    NEEDLE_SCREENS[key] ??
    REPORT_SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([
    getT("knitting"),
    getT("erp"),
    getLocale(),
    screenContext(`/knit-production/${key}`),
    apiFetch("/auth/permissions"),
  ]);
  const m = (k, p) => t(`master.${k}`, p);
  // Yarn Procurement (15/02) reads `yarn.*`; the masters `master.*`.
  const y = (k, p) => t(`yarn.${k}`, p);
  // Yarn Store (15/03) reads `ystore.*`.
  const s = (k, p) => t(`ystore.${k}`, p);
  // Production (15/04) reads `prod.*`.
  const pr = (k, p) => t(`prod.${k}`, p);
  // The sub-contract programme screens (15/04) read `sc.*` as well.
  const sc = (k, p) => t(`sc.${k}`, p);
  // … and the yarn test chain `yt.*`.
  const yt = (k, p) => t(`yt.${k}`, p);
  // … the floor's yarn indents `gy.*`, the replacement claims `rr.*`.
  const gy = (k, p) => t(`gy.${k}`, p);
  const rr = (k, p) => t(`rr.${k}`, p);
  // … the sample knit plans `sk.*`, direct bookings `db.*`.
  const sk = (k, p) => t(`sk.${k}`, p);
  const db = (k, p) => t(`db.${k}`, p);
  // … Yarn Twisting Entry `tw.*`.
  const tw = (k, p) => t(`tw.${k}`, p);
  // … the machine loading plan `ml.*`, the operator's station `op.*`.
  const ml = (k, p) => t(`ml.${k}`, p);
  const op = (k, p) => t(`op.${k}`, p);
  // … and yarn dyeing (15/03 and 15/04) `yd.*`.
  const yd = (k, p) => t(`yd.${k}`, p);
  // Fabric Store (15/05) reads `fs.*`.
  const fs = (k, p) => t(`fs.${k}`, p);
  // … and the grey movements on 15/04 `gt.*`.
  const gt = (k, p) => t(`gt.${k}`, p);
  // … and the sub-contract grey challans `scg.*`.
  const scg = (k, p) => t(`scg.${k}`, p);
  // … and the yarn transfer challans `ytr.*`.
  const ytr = (k, p) => t(`ytr.${k}`, p);
  // … and the sub-contract bills and debit memo `scb.*`.
  const scb = (k, p) => t(`scb.${k}`, p);
  // Collar/Cuff (15/06) reads `cc.*`.
  const cc = (k, p) => t(`cc.${k}`, p);
  // Needle Store (15/07) reads `nd.*`.
  const nd = (k, p) => t(`nd.${k}`, p);
  // Reports (15/11) read `rp.*`.
  const rp = (k, p) => t(`rp.${k}`, p);
  const g = fstore ? fs : prod ? pr : ystore ? s : yarn ? y : m;
  const can = {
    manage: permissions.permissions.includes("knitting.yarn.manage"),
    approve: permissions.permissions.includes("knitting.yarn.approve"),
    store: permissions.permissions.includes("knitting.ystore.manage"),
    prod: permissions.permissions.includes("knitting.production.manage"),
    fstore: permissions.permissions.includes("knitting.fstore.manage"),
    needle: permissions.permissions.includes("knitting.needle.manage"),
  };
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const words = [
    "assign-machine-operator",
    "label-printer-setup",
    "machine-capacity-hour-plan",
    "machine-profile",
    "sc-party-rate",
    "yarn-count-setup",
  ].includes(key)
    ? screenWords(te, m(`noun.${key}`), "cancel")
    : null;
  const canManage = permissions.permissions.includes(
    needle
      ? "knitting.needle.manage"
      : fstore
        ? "knitting.fstore.manage"
        : prod
          ? "knitting.production.manage"
          : ystore
            ? "knitting.ystore.manage"
            : yarn
              ? "knitting.yarn.manage"
              : "knitting.master.manage",
  );
  const tab = query.tab ?? DEFAULT_TABS[key] ?? "records";
  const screen = await render({
    m,
    y,
    s,
    pr,
    sc,
    yt,
    gy,
    rr,
    sk,
    db,
    tw,
    ml,
    op,
    yd,
    fs,
    gt,
    scg,
    ytr,
    scb,
    cc,
    nd,
    rp,
    te,
    tab,
    canManage,
    can,
    searchParams: query,
  });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown =
    active === tab
      ? screen
      : await render({
          m,
          y,
          s,
          pr,
          sc,
          yt,
          gy,
          rr,
          sk,
          db,
          tw,
          ml,
          op,
          yd,
          fs,
          gt,
          scg,
          ytr,
          scb,
          cc,
          nd,
          rp,
          te,
          tab: active,
          canManage,
          can,
          searchParams: query,
        });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : g(`screens.${key}`)}
        description={g(`descriptions.${key}`)}
        icon={context ? undefined : "Layers3"}
        moduleCode="KNITTING"
        locale={locale}
      />
      {shown.tabs?.length ? (
        <ScreenTabs
          basePath={`/knit-production/${key}`}
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
