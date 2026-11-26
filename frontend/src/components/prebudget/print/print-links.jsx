"use client";

import { FileText, Printer } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/*
 * The Print buttons of a Pre-Budget screen — each opens its print (laid out
 * like Norban's PDF) in a new tab, as Norban's do:
 *   budget (Landscape) · portrait · cost · accessories · costing (Knit/Dying
 *   Costing's own cost print) · declaration · stock-use · bom (one BOM).
 */

export function printHref(kind, budgetNo, extra = {}) {
  const map = { portrait: ["budget", { orientation: "portrait" }], costing: ["cost", { view: "costing" }], bom: ["accessories", {}] };
  const [path, add] = map[kind] ?? [kind, {}];
  const q = new URLSearchParams({ budget: budgetNo, ...add, ...extra });
  return `/print/pre-budget/${path}?${q.toString()}`;
}

const LABEL = { budget: "budget", portrait: "portrait", cost: "cost", accessories: "accessories", costing: "print", declaration: "print", "stock-use": "print", bom: "bom" };

export function PrintLinks({ budgetNo, kinds, extra = {}, size = "sm", variant = "outline" }) {
  const t = useT("prebudget");
  return kinds.map((k) => (
    <a key={k} href={printHref(k, budgetNo, extra)} target="_blank" rel="noopener" className={buttonVariants({ size, variant })} aria-label={`${t(`print.btn.${LABEL[k]}`)} ${budgetNo}`}>
      {k === "portrait" ? <FileText /> : <Printer />} {t(`print.btn.${LABEL[k]}`)}
    </a>
  ));
}
