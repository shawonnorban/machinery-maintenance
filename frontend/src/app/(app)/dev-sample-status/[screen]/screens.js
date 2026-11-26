import { forbidden } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { SampleSheet } from "@/components/devsample/sample-sheet";
import { PivotReport } from "@/components/devsample/pivot-report";
import { DsDashboard } from "@/components/devsample/dashboard";
import { ColumnSetup } from "@/components/ordersummary/column-setup";
import { dsAction } from "./actions";

/*
 * Dev Sample Status's screens (AnnotechERP 31, Norban's menu; Column Setup
 * 2026-11-21): View All (the sheet — its filters and page in the address,
 * f[key][]=…, so a filtered view can be bookmarked or sent), Add Dev Sample
 * (the sheet, Create New), the Pivot Report and Column Setup.
 */

/** The address's query as a query string, arrays kept (f[buyer][]=H&M&f[buyer][]=Next). */
function qs(query, only = null) {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(query)) {
    if (only && !only.some((o) => k === o || k.startsWith(`${o}[`))) continue;
    for (const one of [v].flat()) if (one !== undefined && one !== null) p.append(k, String(one));
  }
  return p.toString();
}

const SHEET_KEYS = ["f", "r", "q", "page", "per_page"];
const keep = (query, keys) => Object.fromEntries(keys.filter((k) => query[k]).map((k) => [k, Array.isArray(query[k]) ? query[k][0] : query[k]]));

const SCREENS = {
  dashboard: async function render() {
    return <DsDashboard data={await apiFetch("/dev-sample/dashboard")} />;
  },
  list: async function render({ query }) {
    const q = qs(query, SHEET_KEYS);
    const [meta, data] = await Promise.all([apiFetch("/dev-sample/meta"), apiFetch(`/dev-sample/samples?${q}`)]);
    return <SampleSheet mode="list" meta={meta} data={data} queryString={q} act={dsAction} />;
  },
  new: async function render({ can }) {
    if (!can("devsample.create")) forbidden();
    const meta = await apiFetch("/dev-sample/meta");
    return <SampleSheet mode="new" meta={meta} data={null} queryString="" act={dsAction} />;
  },
  report: async function render({ query }) {
    const levels = [query.levels ?? []].flat().filter(Boolean);
    const params = new URLSearchParams(keep(query, ["season", "buyer"]));
    levels.forEach((l) => params.append("levels[]", l));
    const [data, lookups] = await Promise.all([apiFetch(`/dev-sample/report?${params}`), apiFetch("/dev-sample/lookups")]);
    return <PivotReport data={data} lookups={lookups} query={{ ...keep(query, ["season", "buyer"]), levels }} />;
  },
  columns: async function render({ can }) {
    if (!can("devsample.columns.manage")) forbidden();
    return <ColumnSetup data={await apiFetch("/dev-sample/columns")} act={dsAction} />;
  },
};

export { SCREENS };
