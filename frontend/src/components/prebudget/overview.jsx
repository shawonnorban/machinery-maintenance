"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FileSpreadsheet, FolderOpen, Pencil, Printer } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";
import { printDocument } from "@/lib/print-document";
import { useT } from "@/lib/i18n";

/*
 * Pre-Budget → Budget Overview (user, 2026-11-22): every budget's orders at a
 * glance — "Order Overview of Buyer - Brand - Season - Factory" at the top
 * centre, then one line per style (its requisitions together: every colour
 * and size it has, the quantities and values added up): SL, Buyer, Brand, Dept, Style, Job
 * No, Item, Artwork, Fabrication, Order Color, Size Range, Order Qty, FOB
 * Price, FOB Value, PO-wise RFI/SD Date, with the totals. Pick a budget (or
 * come from a budget's Overview button, ?budget=PB-…); Print gives the same
 * on A4 landscape; Excel downloads the same as a book, the artwork in it.
 */

const SCREEN = "/pre-budget/budget-overview";
const n = (v, d = 0) => (v === null || v === undefined || v === "" ? "" : Number(v).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d }));
const money = (v) => (v === null || v === undefined || v === "" ? "" : `$${n(v, 2)}`);
const day = (s) => {
  if (!s) return "";
  const d = new Date(`${String(s).slice(0, 10)}T00:00:00`);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }).replace(/ /g, "-");
};

function BudgetOverview({ budgets, data }) {
  const t = useT("prebudget");
  const router = useRouter();
  const [pick, setPick] = useState(data?.budget_no ?? "");
  const cols = ["sl", "buyer", "brand", "dept", "style", "job_no", "item", "artwork", "fabrication", "order_color", "size_range", "order_qty", "fob_price", "fob_value", "rfi_sd_date"];
  const right = new Set(["order_qty", "fob_price", "fob_value"]);
  const label = (k) => t(`overview.c.${k}`);

  const print = () =>
    printDocument({
      title: data.title,
      docNo: data.budget_no,
      orientation: "landscape",
      sections: [
        {
          columns: cols.map((k) => ({ label: label(k), align: right.has(k) ? "right" : k === "sl" || k === "artwork" ? "center" : "left", nowrap: ["rfi_sd_date", "job_no", "size_range"].includes(k) })),
          rows: data.rows.map((r) => [
            r.sl, r.buyer, r.brand, r.dept, r.style, r.job_no, r.item, r.artwork_url ? { img: r.artwork_url } : "", r.fabrication, r.order_color, r.size_range,
            n(r.order_qty), money(r.fob_price), money(r.fob_value), (r.rfi_sd_dates ?? []).map(day).join(", "),
          ]),
          foot: [[{ v: t("overview.total"), bold: true }, "", "", "", "", "", "", "", "", "", "", { v: n(data.totals.order_qty), bold: true }, "", { v: money(data.totals.fob_value), bold: true }, ""]],
          empty: t("overview.empty"),
        },
      ],
    });

  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <section className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-surface p-3">
        <select className="h-8 min-w-72 rounded border border-border-strong bg-surface px-2 text-[12px]" value={pick} onChange={(e) => setPick(e.target.value)} aria-label={t("overview.pick")}>
          <option value="">{t("overview.pick")}</option>
          {budgets.map((b) => (
            <option key={b.budget_no} value={b.budget_no}>
              {b.budget_no} — {b.buyer} — {t(`budget.status.${b.status}`)}
            </option>
          ))}
        </select>
        <Button type="button" size="sm" variant="outline" disabled={!pick} onClick={() => router.push(`${SCREEN}?budget=${encodeURIComponent(pick)}`)}>
          <FolderOpen /> {t("overview.open")}
        </Button>
        {data ? (
          <span className="ml-auto flex gap-2">
            <Link href={`/pre-budget/create-budget?budget=${encodeURIComponent(data.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
              <Pencil /> {t("work.open_budget")}
            </Link>
            <a href={`/api/pre-budget/overview-export?budget=${encodeURIComponent(data.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
              <FileSpreadsheet /> {t("overview.excel")}
            </a>
            <Button type="button" size="sm" onClick={print}>
              <Printer /> {t("overview.print")}
            </Button>
          </span>
        ) : null}
      </section>

      {data ? (
        <section className="rounded-lg border border-border bg-surface p-4">
          <h2 className="text-center text-[17px] font-bold text-foreground">{data.title}</h2>
          <p className="mt-0.5 text-center text-[12px] text-foreground-muted">
            {data.budget_no}
            {data.contract_no ? ` · ${t("budget.contract_no")}: ${data.contract_no}` : ""} · {t(`budget.status.${data.status}`)}
          </p>
          <div className="mt-4 overflow-x-auto">
            <table className="w-full border-collapse text-[11.5px]">
              <thead>
                <tr className="bg-[#1e3a5f] text-white">
                  {cols.map((k) => (
                    <th key={k} className={`border border-[#cbd5e1] px-2 py-1.5 font-semibold whitespace-nowrap ${right.has(k) ? "text-right" : k === "sl" || k === "artwork" ? "text-center" : "text-left"}`}>{label(k)}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.rows.length === 0 ? (
                  <tr>
                    <td colSpan={cols.length} className="px-3 py-6 text-center text-foreground-muted">{t("overview.empty")}</td>
                  </tr>
                ) : (
                  data.rows.map((r) => (
                    <tr key={r.sl} className="odd:bg-white even:bg-[#f8fafc]">
                      <td className="border border-[#e2e8f0] px-2 py-1 text-center tabular-nums">{r.sl}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1">{r.buyer}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1">{r.brand}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1">{r.dept}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 font-semibold">{r.style}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 whitespace-nowrap">{r.job_no}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1">{r.item}</td>
                      <td className="border border-[#e2e8f0] px-1 py-1 text-center">
                        {r.artwork_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <a href={r.artwork_url} target="_blank" rel="noreferrer"><img src={r.artwork_url} alt={r.style ?? ""} className="mx-auto max-h-12 max-w-16 object-contain" /></a>
                        ) : null}
                      </td>
                      <td className="border border-[#e2e8f0] px-2 py-1 min-w-40">{r.fabrication}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 min-w-28">{r.order_color}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 min-w-28">{r.size_range ?? <span className="text-foreground-subtle">—</span>}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 text-right tabular-nums">{n(r.order_qty)}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 text-right tabular-nums">{money(r.fob_price)}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 text-right tabular-nums">{money(r.fob_value)}</td>
                      <td className="border border-[#e2e8f0] px-2 py-1 whitespace-nowrap">{(r.rfi_sd_dates ?? []).map((d) => <div key={d}>{day(d)}</div>)}</td>
                    </tr>
                  ))
                )}
              </tbody>
              {data.rows.length ? (
                <tfoot>
                  <tr className="bg-[#eef2ff] font-bold">
                    <td colSpan={11} className="border border-[#cbd5e1] px-2 py-1.5 text-right">{t("overview.total")}</td>
                    <td className="border border-[#cbd5e1] px-2 py-1.5 text-right tabular-nums">{n(data.totals.order_qty)}</td>
                    <td className="border border-[#cbd5e1] px-2 py-1.5" />
                    <td className="border border-[#cbd5e1] px-2 py-1.5 text-right tabular-nums">{money(data.totals.fob_value)}</td>
                    <td className="border border-[#cbd5e1] px-2 py-1.5" />
                  </tr>
                </tfoot>
              ) : null}
            </table>
          </div>
        </section>
      ) : (
        <section className="rounded-lg border border-dashed border-border bg-surface px-4 py-10 text-center text-foreground-muted">{t("overview.choose")}</section>
      )}
    </div>
  );
}

export { BudgetOverview };
