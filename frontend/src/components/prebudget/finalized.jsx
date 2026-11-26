"use client";

import { useState } from "react";
import Link from "next/link";
import { ArrowLeft, FolderOpen } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { Tabs, TabsIndicator, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { useT } from "@/lib/i18n";
import { ApprovalPanel, StatusPill } from "./approval-panel";
import { BudgetPage } from "./create-budget";
import { CostingForm, Declaration } from "./budget-work";
import { BomWorksheet } from "./bom-worksheet";
import { PrintLinks } from "./print/print-links";

/*
 * Pre-Budget → Budget Finalized (Pre-Budget step 6; Norban's Budget
 * Finalized and its pivot snapshot):
 *
 *  - FinalizedList: the budgets sent for approval — waiting (at which step),
 *    approved, sent back, revised — each opened by its number.
 *  - FinalizedSnapshot: one budget whole, as the approvers see it — its
 *    approval (levels, history, Approve / Reject for whoever signs now,
 *    Withdraw, Revise) and, in tabs that switch in the page, the budget
 *    sheet, the Knit/Dying Costing, the Stock Yarn Declaration and the
 *    Accessories BOMs, read only once sent.
 */

const SCREEN = "/pre-budget/budget-finalized";
const n = (v, d = 0) => (v === null || v === undefined ? "" : Number(v).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d }));
const money = (v) => (v === null || v === undefined ? "" : `${Number(v) < 0 ? "-" : ""}$${n(Math.abs(Number(v)), 2)}`);
const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "2-digit", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "");
const FILTERS = ["PENDING", "APPROVED", "RETURNED", "REVISED", "CANCELLED", "ALL"];

function FinalizedList({ rows }) {
  const t = useT("prebudget");
  const [filter, setFilter] = useState(rows.some((r) => r.can_act) ? "MINE" : "PENDING");
  const counts = Object.fromEntries(FILTERS.map((f) => [f, f === "ALL" ? rows.length : rows.filter((r) => r.status === f).length]));
  const mine = rows.filter((r) => r.can_act).length;
  const shown = rows.filter((r) => filter === "ALL" || (filter === "MINE" ? r.can_act : r.status === filter));
  const cols = ["sl", "budget_no", "buyer", "contract_no", "styles", "net_fob", "total_cost", "accessories", "status", "step", "submitted", "approved", "action"];
  const right = ["styles", "net_fob", "total_cost", "accessories", "action"];
  return (
    <section className="rounded-lg border border-border bg-surface text-[13px]">
      <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
        <h2 className="mr-auto font-semibold">{t("screens.budget-finalized")}</h2>
        {mine ? (
          <button type="button" onClick={() => setFilter("MINE")} className={cn("rounded-full border px-3 py-0.5 text-[12px]", filter === "MINE" ? "border-brand bg-brand text-brand-foreground" : "border-brand text-brand")}>
            {t("final.filter.MINE")} ({mine})
          </button>
        ) : null}
        {FILTERS.map((f) => (
          <button key={f} type="button" onClick={() => setFilter(f)} className={cn("rounded-full border px-3 py-0.5 text-[12px]", filter === f ? "border-brand bg-brand text-brand-foreground" : "border-border")}>
            {t(`final.filter.${f}`)} ({counts[f]})
          </button>
        ))}
      </div>
      {shown.length === 0 ? (
        <p className="px-4 py-10 text-center text-foreground-muted">{t("final.empty")}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
              <tr>{cols.map((h) => <th key={h} className={cn("px-3 py-2", right.includes(h) && "text-right")}>{t(`final.f.${h}`)}</th>)}</tr>
            </thead>
            <tbody>
              {shown.map((r, i) => (
                <tr key={r.budget_no} className={cn("border-t border-border", r.can_act && "bg-brand/5")}>
                  <td className="px-3 py-2 tabular-nums">{i + 1}</td>
                  <td className="px-3 py-2 font-mono font-semibold whitespace-nowrap">{r.budget_no}</td>
                  <td className="px-3 py-2">{r.buyer}</td>
                  <td className="px-3 py-2">{r.contract_no || "—"}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{r.styles}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{money(r.net_fob)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{money(r.total_cost)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{money(r.accessories)}</td>
                  <td className="px-3 py-2"><StatusPill status={r.status} /></td>
                  <td className="px-3 py-2 whitespace-nowrap">{r.status === "PENDING" && r.current ? `${r.step}/${r.steps} · ${r.current}` : "—"}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{r.submitted_by ?? "—"}<div className="text-[11px] text-foreground-muted">{when(r.submitted_at)}</div></td>
                  <td className="px-3 py-2 whitespace-nowrap">{r.approved_by ?? "—"}<div className="text-[11px] text-foreground-muted">{when(r.approved_at)}</div></td>
                  <td className="px-3 py-2 text-right whitespace-nowrap">
                    <Link href={`/pre-budget/budget-overview?budget=${encodeURIComponent(r.budget_no)}`} className={`${buttonVariants({ size: "sm", variant: "outline" })} mr-1.5`}>{t("overview.button")}</Link>
                    <Link href={`${SCREEN}?budget=${encodeURIComponent(r.budget_no)}`} className={buttonVariants({ size: "sm", variant: r.can_act ? "default" : "outline" })}>
                      <FolderOpen /> {r.can_act ? t("final.review") : t("final.open")}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

function FinalizedSnapshot({ budget, costing, declaration, boms, act }) {
  const t = useT("prebudget");
  const tabs = [
    ["budget", t("final.tab.budget")],
    ["costing", t("final.tab.costing")],
    ["stock", t("final.tab.stock")],
    ["accessories", t("final.tab.accessories", { n: boms.length })],
  ];
  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <section className="flex flex-wrap items-start gap-x-8 gap-y-3 rounded-lg border border-border bg-surface p-4 text-[13px]">
        {[["budget_no", budget.budget_no, "font-mono"], ["buyer", budget.buyer], ["contract_no", budget.contract_no || "—"]].map(([k, v, extra]) => (
          <div key={k}>
            <div className="text-[11px] text-foreground-muted">{t(`final.f.${k}`)}</div>
            <div className={cn("font-semibold", extra)}>{v}</div>
          </div>
        ))}
        <div>
          <div className="text-[11px] text-foreground-muted">{t("final.f.status")}</div>
          <StatusPill status={budget.status} />
        </div>
        <div className="ml-auto flex flex-wrap gap-2">
          <PrintLinks budgetNo={budget.budget_no} kinds={["budget", "portrait", "cost", "accessories"]} />
          <Link href={`/pre-budget/budget-overview?budget=${encodeURIComponent(budget.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>{t("overview.button")}</Link>
          <Link href={`/pre-budget/create-budget?budget=${encodeURIComponent(budget.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>{t("work.open_budget")}</Link>
          <Link href={SCREEN} className={buttonVariants({ size: "sm", variant: "outline" })}><ArrowLeft /> {t("work.back")}</Link>
        </div>
      </section>
      <ApprovalPanel budget={budget} act={act} ready={budget.check.costing && budget.check.stock && budget.check.bom} />
      <Tabs defaultValue="budget">
        <TabsList>
          {tabs.map(([k, label]) => <TabsTab key={k} value={k}>{label}</TabsTab>)}
          <TabsIndicator />
        </TabsList>
        <TabsPanel value="budget"><BudgetPage budgets={[]} budget={budget} act={act} embedded /></TabsPanel>
        <TabsPanel value="costing">{costing ? <CostingForm data={{ ...costing, editable: false }} act={act} embedded /> : null}</TabsPanel>
        <TabsPanel value="stock">{declaration ? <Declaration data={declaration} embedded /> : null}</TabsPanel>
        <TabsPanel value="accessories">
          <div className="flex flex-col gap-4">
            {boms.length ? boms.map((b) => (
              <div key={b.bom.bom_no}>
                <h3 className="mb-1 font-mono text-[13px] font-semibold">{b.bom.bom_no} · {t(`bom.status.${b.bom.status}`)}</h3>
                <BomWorksheet data={{ ...b, editable: false }} act={act} embedded />
              </div>
            )) : <p className="text-foreground-muted">{t("bom.empty")}</p>}
          </div>
        </TabsPanel>
      </Tabs>
    </div>
  );
}

export { FinalizedList, FinalizedSnapshot };
