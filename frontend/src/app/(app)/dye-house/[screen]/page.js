import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS as MASTER_SCREENS } from "./screens";
import { BATCH_SCREENS } from "./batch-screens";
import { SAMPLE_SCREENS } from "./sample-screens";
import { REQ_SCREENS } from "./req-screens";
import { CHEM_SCREENS, CHEM_EDITORS } from "./chem-screens";
import { PROD_SCREENS, PROD_EDITORS } from "./prod-screens";
import { QC_SCREENS } from "./qc-screens";
import { SC_SCREENS, SC_EDITORS } from "./sc-screens";
import { SCI_SCREENS } from "./sci-screens";

const SCREENS = { ...MASTER_SCREENS, ...BATCH_SCREENS, ...SAMPLE_SCREENS, ...REQ_SCREENS, ...CHEM_SCREENS, ...PROD_SCREENS, ...QC_SCREENS, ...SC_SCREENS, ...SCI_SCREENS };
const LAB_DIP = ["dosing-template", "lab-dip-recipe", "lab-dip-recipe-view"];
// The screens whose rows Brickland edits in place ("Edit X" in the tab strip, "Update X").
const WORDED = [
  "dyeing-method-setup",
  "dyeing-machine-profile",
  "dyeing-fin-and-g-quality-resp-emploee",
  "dyeing-sc-party-rate",
  "lab-dip-recipe",
  "dyeing-batch-plan",
  "sc-pre-treatment-program-heat-set-etc",
  "sc-pre-treatment-challan",
  "sc-pre-treatment-program-receive",
  "sc-pre-treatment-bill",
  "dyeing-batch-src-f-fab",
  "order-wise-grey-fabric-trans-from-batch-store",
  "dyeing-color-transfer-replace",
  "batch-transfer-to-left-over",
  "manual-batch-for-dyeing",
  "dyeing-finishing-qc-sample",
  "sample-batch-from-bulk",
  "dyeing-reprocess",
  "dyeing-mc-maintenance",
  "stock-opening-balance",
  "loan-opening-balance",
  "check-roll-parameter",
  "rib-collar-shade-approval",
  "sc-out-house-bill",
  "sc-order-list",
  "sc-in-house-fabric-receive",
];
// Where a screen opens when no tab is asked for.
const FIRST_TAB = { "dyeing-sc-party-rate": "entry", "dyeing-batch-plan": "board" };
// A requisition named in the address opens on its editor tab.
const EDITOR_TABS = ["requisition-for-dc-for-batch", "requisition-for-dc-machine-wash", "requisition-for-dc-finishing-process", "update-reprocess", "print-chemical-requisition"];

/**
 * Dye House (AnnotechERP 17), one Brickland screen per route: the page
 * header, the tab strip where the screen has tabs, and the screen. Changing
 * anything shows only to whoever may change the dye house masters — or, on
 * Lab-Dip's and Batch (Bulk)'s screens, their own work.
 */
export default async function DyeHousePage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([getT("dyehouse"), getT("erp"), getLocale(), screenContext(`/dye-house/${key}`), apiFetch("/auth/permissions")]);
  // Lab-Dip's screens (17/02), Batch (Bulk)'s and (Sample)'s (17/03–17/04), Requisition's (17/05), Chemical Store's (17/06), Production Board's (17/07), Dyeing Q.C's (17/08), S/C Dyeing's (17/09) and S/C In-House's (17/10) read their words from their own group and change under their own permission.
  const group = LAB_DIP.includes(key) ? "labdip" : BATCH_SCREENS[key] || SAMPLE_SCREENS[key] ? "batch" : REQ_SCREENS[key] ? "req" : CHEM_SCREENS[key] ? "chem" : PROD_SCREENS[key] ? "prod" : QC_SCREENS[key] ? "qc" : SC_SCREENS[key] ? "sc" : SCI_SCREENS[key] ? "sci" : "master";
  const m = (k, p) => t(`${group}.${k}`, p);
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const words = WORDED.includes(key) ? screenWords(te, m(`noun.${key}`)) : null;
  // Brickland's Dye Method Master and Lab Dip Recipe Entry save with their tab's own words, "Add New".
  if (words && key === "dyeing-method-setup") words.save = m("method.new");
  if (words && key === "lab-dip-recipe") words.save = m("btn.add_new");
  const manage = { labdip: "dyehouse.labdip.manage", batch: "dyehouse.batch.manage", req: "dyehouse.requisition.manage", chem: "dyehouse.chemstore.manage", prod: "dyehouse.production.manage", qc: "dyehouse.qc.manage", sc: "dyehouse.sc.manage", sci: "dyehouse.sc.manage", master: "dyehouse.master.manage" }[group];
  const canManage = permissions.permissions.includes(manage);
  const tab = query.tab ?? ((query.req && EDITOR_TABS.includes(key)) || (query.doc && (CHEM_EDITORS.includes(key) || PROD_EDITORS.includes(key) || SC_EDITORS.includes(key))) ? "entry" : null) ?? FIRST_TAB[key] ?? "records";
  const ctx = { m, te, canManage, searchParams: query, permissions: permissions.permissions };
  const screen = await render({ ...ctx, tab });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown = active === tab ? screen : await render({ ...ctx, tab: active });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : m(`screens.${key}`)}
        description={m(`descriptions.${key}`)}
        icon={context ? undefined : "Palette"}
        moduleCode="DYE_HOUSE"
        locale={locale}
      />
      {shown.tabs?.length ? <ScreenTabs basePath={`/dye-house/${key}`} active={active} tabs={shown.tabs} editLabel={words?.edit} /> : null}
      <ScreenWordsProvider words={words}>{await shown.body()}</ScreenWordsProvider>
    </>
  );
}
