import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { PrototypeDashboard } from "@/components/prototype/desk-dashboard";
import { SampleDocumentForm, SampleRegister } from "@/components/prototype/sample-360";
import { SampleAlertList } from "@/components/prototype/alerts";
import * as actions from "./actions";

/*
 * Prototype Desk → DASHBOARD (AnnotechERP 07/02). Prototype Dashboard: one
 * page, its period in the address bar (`?range=7d`). Sample 360° View:
 * Sample Register and Sample 360° (`?tab=view&sample=SMP-2026-00001` opens
 * that sample in place) and Sample Documents (`?tab=docs&doc=SDC-…`).
 * Notifications & Alerts: one list.
 */

const RANGES = ["7d", "30d", "90d", "year", "all"];
const safe = async (path) => {
  try {
    return await apiFetch(path);
  } catch {
    return null;
  }
};

// Each screen's calls, the server action bound to what it does.
const loadDashboard = actions.deskProto.bind(null, "dashboard");
const loadSample = actions.deskProto.bind(null, "sample");
const loadDocument = actions.deskProto.bind(null, "document");
const retitleDocument = actions.deskProto.bind(null, "retitle");
const actAlert = actions.deskProto.bind(null, "alert");

/** Prototype Dashboard. */
async function dashboard({ searchParams }) {
  const range = RANGES.includes(searchParams.range) ? searchParams.range : "30d";
  const data = await apiFetch(`/prototype/dashboard?range=${range}`);
  return { tabs: [], body: async () => <PrototypeDashboard initial={data} load={loadDashboard} /> };
}

/** Sample 360° View: Sample Register · Sample 360° · Sample Documents. */
async function sample360({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const [rows, requisitionOptions, me] = await Promise.all([apiFetch("/prototype/samples"), apiFetch("/prototype/requisitions/options"), safe("/auth/me")]);
  const tabs = [
    { key: "records", label: t("ui.s360.register_tab"), count: rows.length },
    { key: "view", label: t("ui.s360.view_tab") },
    { key: "docs", label: t("ui.s360.docs_tab") },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "docs") {
        const [documents, options] = await Promise.all([apiFetch("/prototype/documents"), apiFetch("/prototype/documents/options")]);
        const initial = searchParams.doc ? await safe(`/prototype/documents/${encodeURIComponent(searchParams.doc)}`) : null;
        const existing = documents.map((d) => ({ value: d.doc_no, label: `${d.doc_no} — ${d.sample_no} · ${t(`doc_type.${d.doc_type}`)} V${d.version} · ${d.title}` }));
        return <SampleDocumentForm key={initial?.doc_no ?? "new"} options={options} initial={initial} existing={existing} canManage={permissions.includes("prototype.sample360.manage")} load={loadDocument} retitle={retitleDocument} />;
      }
      const open = tab === "view" && searchParams.sample ? searchParams.sample : undefined;
      return <SampleRegister key={open ?? "all"} rows={rows} types={requisitionOptions.sample_types} company={me?.company?.name ?? ""} initialOpen={open} load={loadSample} />;
    },
  };
}

/** Notifications & Alerts. */
async function alerts({ permissions }) {
  const data = await apiFetch("/prototype/alerts");
  return {
    tabs: [],
    body: async () => <SampleAlertList initial={data.alerts} kinds={data.kinds} severities={data.severities} canManage={permissions.includes("prototype.alerts.manage")} act={actAlert} />,
  };
}

const SAMPLE_DASHBOARD_SCREENS = { "prototype-dashboard": dashboard, "sample-360-view": sample360, "sample-alerts": alerts };

export { SAMPLE_DASHBOARD_SCREENS };
