import { notFound } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { PrintFrame } from "@/components/prebudget/print/print-frame";
import { BudgetPrint, BudgetSigns } from "@/components/prebudget/print/budget-print";
import { CostPrint } from "@/components/prebudget/print/cost-print";
import { AccessoriesPrint } from "@/components/prebudget/print/accessories-print";
import { StockPrint } from "@/components/prebudget/print/stock-print";
import { RequisitionPrint, requisitionScale } from "@/components/prebudget/print/requisition-print";
import { stamp } from "@/components/prebudget/print/format";

/*
 * Pre-Budget prints, each laid out like Norban's PDF of it:
 *   /print/pre-budget/budget?budget=PB-…[&orientation=portrait][&summary=fabrication]
 *   /print/pre-budget/cost?budget=PB-…[&view=costing]   (Knit/Dying Costing's own: with the "printed" line)
 *   /print/pre-budget/accessories?budget=PB-…[&bom=PB-…-A1]
 *   /print/pre-budget/declaration?budget=PB-…
 *   /print/pre-budget/stock-use?budget=PB-…
 * The API decides who may read them (a signer of the budget's approval too).
 */

const KINDS = {
  budget: { file: "Budget", scale: 96 / 122 },
  cost: { file: "Cost", scale: 96 / 107 },
  accessories: { file: "Accessories-Budget", scale: 96 / 107 },
  declaration: { file: "Stock-Declaration", scale: 0.85 },
  "stock-use": { file: "Use-from-Stock", scale: 0.85 },
  requisitions: { file: "Knit-Woven-Requisition", scale: null },
};

// All Requisition's Print: its filters (f / r / q), the shown columns (cols) and the ticked rows (selected).
const listQuery = (q) => {
  const out = new URLSearchParams();
  for (const [k, v] of Object.entries(q)) {
    if (/^(f|r)\[/.test(k) || k === "q") (Array.isArray(v) ? v : [v]).forEach((x) => out.append(k, x));
  }
  out.set("per_page", "all");
  return out.toString();
};

const enc = encodeURIComponent;

export async function generateMetadata({ params, searchParams }) {
  const { kind } = await params;
  const q = (await searchParams) ?? {};
  const k = KINDS[kind];
  if (kind === "requisitions") return { title: k.file };
  // The browser names a saved PDF after the title: Budget-PB-CNA-1126-0003 (Norban's file names).
  return { title: k ? `${kind === "cost" && q.view === "costing" ? "Costing" : k.file}-${q.bom || q.budget || ""}` : "Print" };
}

export default async function PreBudgetPrint({ params, searchParams }) {
  const { kind } = await params;
  const q = (await searchParams) ?? {};
  const k = KINDS[kind];
  if (!k) notFound();
  const t = await getT("prebudget");

  if (kind === "requisitions") {
    try {
      const [meta, data] = await Promise.all([apiFetch("/pre-budget/meta"), apiFetch(`/pre-budget/requisitions?${listQuery(q)}`)]);
      const want = String(q.cols ?? "").split(",").filter(Boolean);
      const byKey = new Map(meta.columns.filter((c) => c.access !== "none").map((c) => [c.key, c]));
      const columns = (want.length ? want : meta.columns.map((c) => c.key)).map((key) => byKey.get(key)).filter(Boolean);
      const picked = new Set(String(q.selected ?? "").split(",").filter(Boolean));
      const rows = picked.size ? data.rows.filter((r) => picked.has(r.id)) : data.rows;
      return (
        <PrintFrame title={t("print.title.requisitions")} scale={requisitionScale(columns)}>
          <RequisitionPrint rows={rows} columns={columns} />
        </PrintFrame>
      );
    } catch (e) {
      return <p style={{ padding: 24, fontFamily: "Segoe UI, Arial, sans-serif" }}>{e?.status === 403 ? t("print.forbidden") : (e?.message ?? t("print.error"))}</p>;
    }
  }

  const no = typeof q.budget === "string" ? q.budget : "";
  if (!/^PB-[A-Za-z0-9-]+$/.test(no)) notFound();

  let body;
  try {
    if (kind === "budget" || kind === "cost") {
      const b = await apiFetch(`/pre-budget/budgets/${enc(no)}`);
      if (kind === "budget") {
        const portrait = q.orientation === "portrait";
        return (
          <PrintFrame title={t("print.title.budget", { no: b.budget_no })} orientation={portrait ? "portrait" : "landscape"} scale={k.scale} onePage={portrait} portraitToggle bottom={<BudgetSigns b={b} />}>
            <BudgetPrint b={b} byFabrication={q.summary === "fabrication"} />
          </PrintFrame>
        );
      }
      const costing = q.view === "costing";
      body = { title: t("print.title.cost", { no: b.budget_no }), footer: costing ? `Knitting & Dyeing / Embellishment Cost - ${b.budget_no} | printed ${stamp()}` : null, node: <CostPrint b={b} /> };
    } else if (kind === "accessories") {
      const [acc, b] = await Promise.all([apiFetch(`/pre-budget/budgets/${enc(no)}/accessories`), apiFetch(`/pre-budget/budgets/${enc(no)}`)]);
      const bom = typeof q.bom === "string" ? q.bom : null;
      body = { title: t("print.title.accessories", { no: bom ?? acc.budget_no }), node: <AccessoriesPrint acc={acc} factory={(b.factories ?? []).join(", ")} bomNo={bom} /> };
    } else {
      const d = await apiFetch(`/pre-budget/budgets/${enc(no)}/declaration`);
      const store = kind === "stock-use";
      body = {
        title: t(store ? "print.title.stock_use" : "print.title.declaration", { no: d.budget_no }),
        footer: store ? `Store - Use from Stock - ${d.budget_no} | printed ${stamp()}` : null,
        node: <StockPrint d={d} kind={store ? "store" : "declaration"} />,
      };
    }
  } catch (e) {
    if (e?.status === 404) notFound();
    return <p style={{ padding: 24, fontFamily: "Segoe UI, Arial, sans-serif" }}>{e?.status === 403 ? t("print.forbidden") : (e?.message ?? t("print.error"))}</p>;
  }

  return (
    <PrintFrame title={body.title} scale={k.scale} footer={body.footer}>
      {body.node}
    </PrintFrame>
  );
}
