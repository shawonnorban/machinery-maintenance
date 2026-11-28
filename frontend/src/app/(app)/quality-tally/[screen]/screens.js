import { apiFetch } from "@/lib/api-server";
import { QtDashboard } from "@/components/qtally/dashboard";
import { TallyEntry } from "@/components/qtally/tally";
import { TallySheet } from "@/components/qtally/sheet";
import { TallyHistory } from "@/components/qtally/history";
import { TallyReview } from "@/components/qtally/review";
import { QtJobs } from "@/components/qtally/jobs";
import { LineTargets } from "@/components/qtally/targets";
import { QcAssignment } from "@/components/qtally/inspectors";
import { QtPhones } from "@/components/qtally/phones";
import { DefectTypes } from "@/components/qtally/defects";
import { LineAttendance } from "@/components/qtally/attendance";
import { QtAnalytics } from "@/components/qtally/analytics";
import { QtReports } from "@/components/qtally/reports";
import { CutPanelInspection } from "@/components/qtally/cut-panel";
import { Panels } from "@/components/qtally/panels";
import { CutInput } from "@/components/qtally/cut-input";
import { JobFlow } from "@/components/qtally/flow";
import { qtAction } from "./actions";

/*
 * Quality Tally's screens (AnnotechERP 36). Cutting, sewing and quality each
 * have their own (user, 2026-11-25): the same screens, held to one section.
 * Filters live in the address; the tally reads and writes from the browser.
 */

const keep = (query, keys) => Object.fromEntries(keys.filter((k) => query[k]).map((k) => [k, query[k]]));
const qs = (q) => new URLSearchParams(Object.entries(q).filter(([, v]) => v)).toString();

const SCREENS = {
  dashboard: async function render({ query }) {
    const q = keep(query, ["date", "section"]);
    const [data, lookups] = await Promise.all([apiFetch(`/quality-tally/dashboard?${qs(q)}`), apiFetch("/quality-tally/lookups")]);
    return <QtDashboard data={data} lookups={lookups} query={q} />;
  },
  "cutting-input": async function render({ query, can }) {
    const q = keep(query, ["date", "search"]);
    const [data, form] = await Promise.all([apiFetch(`/quality-tally/cut-bundles?${qs(q)}`), apiFetch("/quality-tally/panel-inspections/form")]);
    return <CutInput data={data} form={form} query={q} canManage={can("qtally.target.manage") || can("qtally.cutting.manage")} act={qtAction} />;
  },
  "job-flow": async function render({ query }) {
    const q = keep(query, ["status", "search"]);
    const rows = await apiFetch(`/quality-tally/flow?${qs(q)}`);
    return <JobFlow rows={rows} query={q} />;
  },
  "cut-panel-inspection": async function render({ query }) {
    const q = keep(query, ["from", "to", "search"]);
    const [data, form] = await Promise.all([apiFetch(`/quality-tally/panel-inspections?${qs(q)}`), apiFetch("/quality-tally/panel-inspections/form")]);
    return <CutPanelInspection data={data} form={form} query={q} act={qtAction} />;
  },
  panels: async function render({ can }) {
    const rows = await apiFetch("/quality-tally/panels");
    return <Panels rows={rows} canManage={can("qtally.defect.manage")} act={qtAction} />;
  },
  analytics: async function render({ query }) {
    const q = keep(query, ["from", "to", "section"]);
    const data = await apiFetch(`/quality-tally/analytics?${qs(q)}`);
    return <QtAnalytics key={`${data.from}-${data.to}-${data.section}`} data={data} query={q} />;
  },
  reports: async function render() {
    const lookups = await apiFetch("/quality-tally/lookups");
    return <QtReports today={lookups.today} act={qtAction} />;
  },
  jobs: async function render({ query, can }) {
    const q = keep(query, ["status", "search"]);
    const rows = await apiFetch(`/quality-tally/jobs?${qs(q)}`);
    return <QtJobs rows={rows} query={q} canManage={can("qtally.job.manage")} act={qtAction} />;
  },
  "qc-assignment": async function render({ can }) {
    const [rows, lookups] = await Promise.all([apiFetch("/quality-tally/inspectors"), apiFetch("/quality-tally/lookups")]);
    return <QcAssignment rows={rows} lookups={lookups} canManage={can("qtally.inspector.manage")} act={qtAction} />;
  },
  phones: async function render() {
    const [rows, lookups] = await Promise.all([apiFetch("/quality-tally/phones"), apiFetch("/quality-tally/lookups")]);
    return <QtPhones rows={rows} lookups={lookups} act={qtAction} />;
  },
  "defect-types": async function render({ can }) {
    const rows = await apiFetch("/quality-tally/defect-types");
    return <DefectTypes rows={rows} canManage={can("qtally.defect.manage")} act={qtAction} />;
  },
};

/** The screens each section has of its own: /quality-tally/{section}-{name}. */
const SECTION_SCREENS = {
  dashboard: async ({ query, section }) => {
    const q = keep(query, ["date"]);
    const [data, lookups] = await Promise.all([apiFetch(`/quality-tally/dashboard?${qs({ ...q, section })}`), apiFetch("/quality-tally/lookups")]);
    return <QtDashboard data={data} lookups={lookups} query={q} fixed={section} />;
  },
  tally: async ({ locale, section }) => <TallyEntry key={section} locale={locale} section={section} />,
  target: async ({ query, section }) => {
    const q = keep(query, ["date"]);
    const scope = qs({ ...q, section });
    const [data, pending, lookups] = await Promise.all([apiFetch(`/quality-tally/targets?${scope}`), apiFetch(`/quality-tally/targets/pending?${scope}`), apiFetch("/quality-tally/lookups")]);
    return <LineTargets key={section} data={data} pendingOrders={pending.rows} lookups={lookups} query={q} section={section} canManage={lookups.managed.includes(section)} act={qtAction} />;
  },
  attendance: async ({ query, section }) => {
    const q = keep(query, ["date"]);
    const data = await apiFetch(`/quality-tally/attendance?${qs({ ...q, section })}`);
    return <LineAttendance key={`${data.date}-${section}`} data={data} query={q} canManage={data.can_manage} act={qtAction} />;
  },
  sheet: async ({ query, section }) => {
    const q = keep(query, ["date", "line_id", "job_id"]);
    const lookups = await apiFetch("/quality-tally/lookups");
    const sheet = q.line_id ? await apiFetch(`/quality-tally/sheet?${qs({ ...q, section })}`) : null;
    return <TallySheet key={section} sheet={sheet} lookups={lookups} query={q} section={section} />;
  },
  review: async ({ query, section, can }) => {
    const q = keep(query, ["from", "to", "status"]);
    const data = await apiFetch(`/quality-tally/review?${qs({ ...q, section })}`);
    return <TallyReview key={section} data={data} query={q} canManage={can("qtally.target.manage") || can(`qtally.${section.toLowerCase()}.manage`)} act={qtAction} />;
  },
  history: async ({ query, section }) => {
    const q = keep(query, ["from", "to", "line_id", "job_id", "action", "user_id", "page"]);
    const [data, lookups] = await Promise.all([apiFetch(`/quality-tally/history?${qs({ ...q, section })}`), apiFetch("/quality-tally/lookups")]);
    return <TallyHistory key={section} data={data} lookups={lookups} query={q} section={section} />;
  },
};

for (const section of ["CUTTING", "SEWING", "QUALITY"]) {
  for (const [name, render] of Object.entries(SECTION_SCREENS)) SCREENS[`${section.toLowerCase()}-${name}`] = (args) => render({ ...args, section });
}

export { SCREENS };
