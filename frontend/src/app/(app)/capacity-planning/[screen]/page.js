import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { ScreenWordsProvider } from "@/components/erp/screen-words";
import { screenWords } from "@/lib/brickland-labels";
import { PlanSteps } from "@/components/planning/plan-steps";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

const PLAN_SCREENS = ["projections", "master-plan", "master-plan-list", "plan-vs-actual", "revision-request", "daily-plan", "daily-plan-list", "line-capacity-view", "plan-timeline"];

/** Brickland's Production Plan steps; a step links once its screen is built. */
const STEPS = [
  { key: "factory-calendar", href: "/hr-payroll/festival-calendar?tab=holidays" },
  { key: "planning-line-setup", href: "/capacity-planning/planning-line-setup" },
  { key: "projections", href: "/capacity-planning/projections" },
  { key: "master-plan", href: "/capacity-planning/master-plan" },
  { key: "daily-plan", href: "/capacity-planning/daily-plan" },
  { key: "plan-vs-actual", href: "/capacity-planning/plan-vs-actual" },
];

/**
 * Capacity Planning (AnnotechERP 12), one Brickland screen per route: the
 * page header, the Production Plan steps on the screens that are steps, the
 * tab strip where the screen has tabs, and the screen. New, Edit and Delete
 * show only to whoever may change the planning masters.
 */
export default async function CapacityPlanningPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context, permissions] = await Promise.all([getT("planning"), getT("erp"), getLocale(), screenContext(`/capacity-planning/${key}`), apiFetch("/auth/permissions")]);
  const m = (k, p) => t(`master.${k}`, p);
  // Brickland's words for this screen ("Save line", "Edit line", "Remove this line?").
  const words = ["planning-line-setup", "smv-sam-master", "efficiency-master", "planning-setup", "machine-master", "manpower-master", "shift-master"].includes(key) ? screenWords(te, m(`noun.${key}`)) : null;
  const canManage = permissions.permissions.includes("planning.master.manage");
  const tab = query.tab ?? "records";
  const p = (k, x) => t(`plan.${k}`, x);
  const args = { m, p, te, canManage, searchParams: query, permissions: permissions.permissions };
  const screen = await render({ ...args, tab });
  const active = screen.tabs?.some((x) => x.key === tab) ? tab : "records";
  const shown = active === tab ? screen : await render({ ...args, tab: active });
  // The Production Plan screens (12/02) read `plan.*`; the masters `master.*`.
  const g = (k, p) => t(`${PLAN_SCREENS.includes(key) ? "plan" : "master"}.${k}`, p);

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : g(`screens.${key}`)}
        description={g(`descriptions.${key}`)}
        icon={context ? undefined : "CalendarRange"}
        moduleCode="CAPACITY"
        locale={locale}
      />
      {STEPS.some((s) => s.key === key) ? (
        <PlanSteps steps={STEPS.map((s) => ({ ...s, label: m(`steps.${s.key}`) }))} current={key} title={m("steps.title")} previous={m("steps.previous")} next={m("steps.next")} />
      ) : null}
      {shown.tabs?.length ? <ScreenTabs basePath={`/capacity-planning/${key}`} active={active} tabs={shown.tabs} editLabel={words?.edit} /> : null}
      <ScreenWordsProvider words={words}>{await shown.body()}</ScreenWordsProvider>
    </>
  );
}
