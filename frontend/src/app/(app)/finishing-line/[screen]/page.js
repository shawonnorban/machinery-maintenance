import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { DEFAULT_TABS, SCREENS as MASTER_SCREENS } from "./screens";
import { PROD_DEFAULT_TABS, PROD_SCREENS } from "./prod-screens";
import { SC_SCREENS } from "./sc-screens";
import { QC_SCREENS } from "./qc-screens";
import { STORE_SCREENS } from "./store-screens";
import { RPT_SCREENS } from "./rpt-screens";

const SCREENS = { ...MASTER_SCREENS, ...PROD_SCREENS, ...SC_SCREENS, ...QC_SCREENS, ...STORE_SCREENS, ...RPT_SCREENS };
const DEFAULTS = { ...DEFAULT_TABS, ...PROD_DEFAULT_TABS };

/**
 * Finishing Line (AnnotechERP 18), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Each
 * screen reads its own words (Master Data, DF Production, S/C Process, QC Process, Fabric Store, Report) and changing
 * anything shows only to whoever may change that part.
 */
export default async function FinishingLinePage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([getT("finishing"), getT("erp"), getLocale(), screenContext(`/finishing-line/${key}`), apiFetch("/auth/permissions")]);
  const prod = key in PROD_SCREENS;
  const sc = key in SC_SCREENS;
  const qc = key in QC_SCREENS;
  const store = key in STORE_SCREENS;
  const group = key in RPT_SCREENS ? "rpt" : store ? "store" : qc ? "qc" : sc ? "sc" : prod ? "prod" : "master";
  const m = (k, p) => t(`${group}.${k}`, p);
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const worded = ["process-types", "machine-profile", "machine-process-mapping", "machine-target-setup", "batch-receive-from-dyeing", "batch-return-to-re-process", "df-production-amendment", "finishing-qc-entry", "shade-gsm-width-check", "qc-rejection-re-process-advice", "roll-wise-racking-barcode", "fabric-store-return-transfer"];
  const words = worded.includes(key) ? screenWords(te, m(`noun.${key}`), ["machine-profile", "machine-process-mapping"].includes(key) ? "clear" : "reset") : null;
  const canManage = permissions.permissions.includes(store ? "finishing.store.manage" : qc ? "finishing.qc.manage" : sc ? "finishing.sc.manage" : prod ? "finishing.production.manage" : "finishing.master.manage");
  const tab = query.tab ?? DEFAULTS[key] ?? "records";
  const screen = await render({ m, te, tab, canManage, searchParams: query });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown = active === tab ? screen : await render({ m, te, tab: active, canManage, searchParams: query });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : m(`screens.${key}`)}
        description={m(`descriptions.${key}`)}
        icon={context ? undefined : "Sparkles"}
        moduleCode="FINISHING"
        locale={locale}
      />
      {shown.tabs?.length ? <ScreenTabs basePath={`/finishing-line/${key}`} active={active} tabs={shown.tabs} editLabel={words?.edit} /> : null}
      <ScreenWordsProvider words={words}>{await shown.body()}</ScreenWordsProvider>
    </>
  );
}
