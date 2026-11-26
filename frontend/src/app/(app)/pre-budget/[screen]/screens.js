import { notFound, forbidden } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { RequisitionSheet } from "@/components/prebudget/requisition-sheet";
import { DraftList } from "@/components/prebudget/draft-list";
import { CreateBudget } from "@/components/prebudget/create-budget";
import { WorkList, CostingForm, StockForm, Declaration } from "@/components/prebudget/budget-work";
import { PendingList, BudgetBoms, BomList } from "@/components/prebudget/accessories";
import { BomWorksheet } from "@/components/prebudget/bom-worksheet";
import { FinalizedList, FinalizedSnapshot } from "@/components/prebudget/finalized";
import { PbDashboard } from "@/components/prebudget/dashboard";
import { BudgetOverview } from "@/components/prebudget/overview";
import { pbAction } from "./actions";

/*
 * Pre-Budget's screens (AnnotechERP 33/34), built step by step (plan
 * 2026-11-19): All and Add Requisition, Draft Requisition (Norban's
 * auto-saved drafts), Create Budget, the costing and stock screens,
 * Accessories Budget Pending and BOM List. The two Column Setups live under
 * Master Data (/pre-budget-master). The sheet's filters and page
 * live in the address (f[key][]=…), so a filtered view can be bookmarked.
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

/** ?budget=PB-CNA-1126-0001 (a budget's number, never an id). */
const budgetNo = (query) => (typeof query.budget === "string" && /^PB-[A-Z0-9-]+$/.test(query.budget) ? query.budget : null);

const SCREENS = {
  dashboard: async function render() {
    return <PbDashboard data={await apiFetch("/pre-budget/dashboard")} />;
  },
  "all-requisition": async function render({ query }) {
    const q = qs(query, SHEET_KEYS);
    const [meta, data] = await Promise.all([apiFetch("/pre-budget/meta"), apiFetch(`/pre-budget/requisitions?${q}`)]);
    return <RequisitionSheet mode="list" meta={meta} data={data} queryString={q} act={pbAction} />;
  },
  "add-requisition": async function render({ can, query }) {
    if (!can("prebudget.create")) forbidden();
    // ?draft=12 — a Draft Requisition opened again (by its number), its rows filled back in.
    const no = /^\d+$/.test(String(query.draft ?? "")) ? Number(query.draft) : null;
    // The order picker's list: the user's open Order Summary orders with no requisition yet.
    const [meta, draft, picker] = await Promise.all([
      apiFetch("/pre-budget/meta"),
      no ? apiFetch(`/pre-budget/drafts/${no}`).catch(() => null) : null,
      apiFetch("/pre-budget/orders").catch(() => ({ orders: [] })),
    ]);
    return <RequisitionSheet key={draft ? `draft-${draft.draft_no}` : "new"} mode="new" meta={meta} data={null} queryString="" act={pbAction} draft={draft} orders={picker.orders} />;
  },
  "draft-requisition": async function render({ can }) {
    if (!can("prebudget.create")) forbidden();
    return <DraftList data={await apiFetch("/pre-budget/drafts")} act={pbAction} />;
  },
  "create-budget": async function render({ query }) {
    // ?budget=PB-CNA-1126-0001 opens a budget (its number, never an id); without one, the requisitions to make one from.
    const no = typeof query.budget === "string" && /^PB-[A-Z0-9-]+$/.test(query.budget) ? query.budget : null;
    const [budgets, budget, candidates] = await Promise.all([
      apiFetch("/pre-budget/budgets"),
      no ? apiFetch(`/pre-budget/budgets/${encodeURIComponent(no)}`).catch(() => null) : null,
      no ? null : apiFetch(`/pre-budget/budgets/candidates?${qs(query, ["buyer", "brand", "style", "item", "job_no"])}`),
    ]);
    if (no && !budget) notFound();
    return <CreateBudget budgets={budgets} budget={budget} candidates={candidates} query={query} act={pbAction} />;
  },
  // Knit/Dying Costing, Stock Yarn Use, Stock Yarn Declaration: the budgets to work on; ?budget=PB-… opens one.
  "knit-dying-costing": async function render({ query }) {
    const no = budgetNo(query);
    if (!no) return <WorkList kind="costing" rows={await apiFetch("/pre-budget/budgets/work?kind=costing")} />;
    const data = await apiFetch(`/pre-budget/budgets/${encodeURIComponent(no)}/costing`).catch(() => null);
    if (!data) notFound();
    return <CostingForm key={`${no}|${data.version}`} data={data} act={pbAction} />;
  },
  "stock-yarn-use": async function render({ query }) {
    const no = budgetNo(query);
    if (!no) return <WorkList kind="stock" rows={await apiFetch("/pre-budget/budgets/work?kind=stock")} />;
    const data = await apiFetch(`/pre-budget/budgets/${encodeURIComponent(no)}/stock`).catch(() => null);
    if (!data) notFound();
    return <StockForm key={`${no}|${data.version}`} data={data} act={pbAction} />;
  },
  "stock-yarn-declaration": async function render({ query }) {
    const no = budgetNo(query);
    if (!no) return <WorkList kind="declaration" rows={await apiFetch("/pre-budget/budgets/work?kind=declaration")} />;
    const data = await apiFetch(`/pre-budget/budgets/${encodeURIComponent(no)}/declaration`).catch(() => null);
    if (!data) notFound();
    return <Declaration data={data} />;
  },
  // Budget Overview (user, 2026-11-22): a budget's orders at a glance; ?budget=PB-… opens one.
  "budget-overview": async function render({ query }) {
    const no = budgetNo(query);
    const [budgets, data] = await Promise.all([apiFetch("/pre-budget/budgets"), no ? apiFetch(`/pre-budget/budgets/${encodeURIComponent(no)}/overview`).catch(() => null) : null]);
    if (no && !data) notFound();
    return <BudgetOverview key={no ?? "none"} budgets={budgets} data={data} />;
  },
  // Budget Finalized: the budgets sent for approval; ?budget=PB-… one whole (its approval, sheet, costing, stock, BOMs).
  "budget-finalized": async function render({ query }) {
    const no = budgetNo(query);
    if (!no) return <FinalizedList rows={await apiFetch("/pre-budget/budgets/finalized")} />;
    const enc = encodeURIComponent(no);
    const [budget, costing, declaration, acc] = await Promise.all([
      apiFetch(`/pre-budget/budgets/${enc}`).catch(() => null),
      apiFetch(`/pre-budget/budgets/${enc}/costing`).catch(() => null),
      apiFetch(`/pre-budget/budgets/${enc}/declaration`).catch(() => null),
      apiFetch(`/pre-budget/budgets/${enc}/accessories`).catch(() => null),
    ]);
    if (!budget) notFound();
    const boms = await Promise.all((acc?.summary?.boms ?? []).map((b) => apiFetch(`/pre-budget/boms/${encodeURIComponent(b.bom_no)}`).catch(() => null)));
    return <FinalizedSnapshot key={`${no}|${budget.version}|${budget.status}`} budget={budget} costing={costing} declaration={declaration} boms={boms.filter(Boolean)} act={pbAction} />;
  },
  // Accessories Budget Pending: the budgets; ?budget=PB-… its BOMs (&bom=new a new worksheet); ?bom=PB-…-A1 a BOM's worksheet.
  "accessories-budget-pending": async function render({ query }) {
    const bom = typeof query.bom === "string" && /^PB-[A-Z0-9-]+-A\d+$/.test(query.bom) ? query.bom : null;
    if (bom) {
      const data = await apiFetch(`/pre-budget/boms/${encodeURIComponent(bom)}`).catch(() => null);
      if (!data) notFound();
      return <BomWorksheet key={`${bom}|${data.bom.version}`} data={data} act={pbAction} />;
    }
    const no = budgetNo(query);
    if (!no) return <PendingList data={await apiFetch("/pre-budget/boms/pending")} />;
    const data = await apiFetch(`/pre-budget/budgets/${encodeURIComponent(no)}/accessories`).catch(() => null);
    if (!data) notFound();
    return query.bom === "new" && data.editable ? <BomWorksheet key={`${no}|new`} data={data} act={pbAction} /> : <BudgetBoms data={data} act={pbAction} />;
  },
  "bom-list": async function render() {
    return <BomList rows={await apiFetch("/pre-budget/boms")} />;
  },
};

export { SCREENS };
