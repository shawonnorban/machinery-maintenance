import { apiFetch } from "@/lib/api-server";
import { LineBoard, CuttingBoard, AllBoard, ProgressBoard, ReportBoard } from "@/components/productiontracking/boards";
import { DailyPlans } from "@/components/productiontracking/plans";
import { LineCapacities } from "@/components/productiontracking/capacity";
import { ProductionEntries } from "@/components/productiontracking/entries";
import { TvScreens } from "@/components/productiontracking/tv";
import { PtSettings } from "@/components/productiontracking/settings";
import { ptAction } from "./actions";

/*
 * Production Tracking's thirteen screens (AnnotechERP 30). The dashboards,
 * order progress and report read one board endpoint by view with the
 * period (Today / Last 3 Days / Last 7 Days / Custom) and filters from the
 * address; they re-read on the settings' refresh interval.
 */

const keep = (query, keys) => Object.fromEntries(keys.filter((k) => query[k]).map((k) => [k, query[k]]));
const qs = (q) => new URLSearchParams(Object.entries(q).filter(([, v]) => v)).toString();

function board(view, Component, filters) {
  return async function render({ query }) {
    const q = keep(query, ["range", "from", "to", ...filters]);
    const [data, lookups] = await Promise.all([apiFetch(`/production-tracking/board/${view}?${qs(q)}`), apiFetch("/production-tracking/lookups")]);
    return <Component view={view} data={data} lookups={lookups} query={q} />;
  };
}

const SCREENS = {
  "all-dashboard": board("all", AllBoard, ["search"]),
  "cutting-dashboard": board("cutting", CuttingBoard, ["barcode"]),
  "sewing-dashboard": board("sewing", LineBoard, ["line", "buyer", "search"]),
  "finishing-dashboard": board("finishing", LineBoard, ["line", "buyer", "search"]),
  "buyer-wise": board("buyer", ProgressBoard, ["buyer", "search"]),
  "style-wise": board("style", ProgressBoard, ["buyer", "search"]),
  "po-wise": board("po", ProgressBoard, ["buyer", "search"]),
  reports: board("report", ReportBoard, ["line", "buyer"]),

  "production-data": async function render({ query, can }) {
    const date = query.date ?? "";
    const [data, lookups] = await Promise.all([apiFetch(`/production-tracking/entries${date ? `?date=${date}` : ""}`), apiFetch("/production-tracking/lookups")]);
    return <ProductionEntries data={data} lookups={lookups} tab={query.tab ?? "all"} canManage={can("productiontracking.entry.manage")} act={ptAction} />;
  },
  "line-capacity": async function render({ can }) {
    const [data, lookups] = await Promise.all([apiFetch("/production-tracking/capacities"), apiFetch("/production-tracking/lookups")]);
    return <LineCapacities data={data} lookups={lookups} canManage={can("productiontracking.capacity.manage")} act={ptAction} />;
  },
  "daily-plan": async function render({ query, can }) {
    const q = keep(query, ["date", "section", "status"]);
    const [data, lookups] = await Promise.all([apiFetch(`/production-tracking/plans?${qs(q)}`), apiFetch("/production-tracking/lookups")]);
    return <DailyPlans data={data} lookups={lookups} query={q} canPrepare={can("productiontracking.plan.prepare")} canApprove={can("productiontracking.plan.approve")} act={ptAction} />;
  },
  "tv-dashboard": async function render({ can }) {
    const [screens, lookups] = await Promise.all([apiFetch("/production-tracking/tv-screens"), apiFetch("/production-tracking/lookups")]);
    return <TvScreens screens={screens} lookups={lookups} canManage={can("productiontracking.tv.manage")} act={ptAction} />;
  },
  settings: async function render({ can }) {
    const settings = await apiFetch("/production-tracking/settings");
    return <PtSettings settings={settings} canManage={can("productiontracking.settings.manage")} act={ptAction} />;
  },
};

export { SCREENS };
