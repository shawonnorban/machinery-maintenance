import { forbidden } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { StatementSheet } from "@/components/exportstatement/statement-sheet";
import { EsDashboard } from "@/components/exportstatement/dashboard";
import { EsApprovals } from "@/components/exportstatement/approvals";
import { ColumnSetup } from "@/components/ordersummary/column-setup";
import { esAction } from "./actions";

/*
 * Export Statement's screens (AnnotechERP 35, laid out like Order Summary):
 * Dashboard (Norban's KPI cards), View All (the sheet — its filters and page
 * in the address, f[key][]=…), Add Export Statement (the sheet, Create New),
 * Pending Approvals and Column Setup.
 */

/** The address's query as a query string, arrays kept. */
function qs(query, only) {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(query)) {
    if (!only.some((o) => k === o || k.startsWith(`${o}[`))) continue;
    for (const one of [v].flat()) if (one !== undefined && one !== null) p.append(k, String(one));
  }
  return p.toString();
}

const SHEET_KEYS = ["f", "r", "q", "page", "per_page"];

const SCREENS = {
  dashboard: async function render() {
    return <EsDashboard data={await apiFetch("/export-statement/dashboard")} />;
  },
  list: async function render({ query }) {
    const q = qs(query, SHEET_KEYS);
    const [meta, data] = await Promise.all([apiFetch("/export-statement/meta"), apiFetch(`/export-statement/statements?${q}`)]);
    return <StatementSheet mode="list" meta={meta} data={data} queryString={q} act={esAction} />;
  },
  new: async function render({ can }) {
    if (!can("exportstatement.create")) forbidden();
    const meta = await apiFetch("/export-statement/meta");
    return <StatementSheet mode="new" meta={meta} data={null} queryString="" act={esAction} />;
  },
  approvals: async function render() {
    return <EsApprovals data={await apiFetch("/export-statement/approvals")} act={esAction} />;
  },
  columns: async function render({ can }) {
    if (!can("exportstatement.columns.manage")) forbidden();
    return <ColumnSetup data={await apiFetch("/export-statement/columns")} act={esAction} />;
  },
};

export { SCREENS };
