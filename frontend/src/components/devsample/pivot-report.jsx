"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { ArrowLeft, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { useT } from "@/lib/i18n";

/*
 * Dev Sample Status → Pivot Report (AnnotechERP 31/01), Norban's "Running
 * Development Sample Summary": Season and Buyer filters; buyers by order
 * confirmation; then buyers by sample status, opened up by the levels ticked
 * under "Show in report" (Sample Type, Item, Styling, Job No., Color,
 * Fabrication). A sample with several items or colours shows under each, but
 * counts once in every total above.
 */

const LEVEL_ORDER = ["sample_type", "item", "styling", "job_no", "color", "fabrication"];
const BLANK = "(Blank)";

function PivotReport({ data, lookups, query }) {
  const t = useT("devsample");
  const router = useRouter();
  const pathname = usePathname();
  const [season, setSeason] = useState(query.season ?? "");
  const [buyer, setBuyer] = useState(query.buyer ?? "");

  const go = (next) => {
    const params = new URLSearchParams();
    if (next.season) params.set("season", next.season);
    if (next.buyer) params.set("buyer", next.buyer);
    for (const l of next.levels ?? []) params.append("levels", l);
    router.push(`${pathname}${params.size ? `?${params}` : ""}`, { scroll: false });
  };
  const label = (list) => (code) => (code === BLANK ? t("report.blank") : (lookups.lists[list] ?? []).find((o) => o.value === code)?.label ?? code);
  const confirmation = label("order_confirmation");
  const status = label("sample_status");
  const nodeLabel = (n) => (n.label === BLANK ? t("report.blank") : n.level === "sample_type" ? label("sample_type")(n.label) : n.label);
  const { summary, detail } = data;

  return (
    <div className="flex flex-col gap-5">
      <section className="rounded-lg border border-border bg-surface p-5">
        <form
          className="flex flex-wrap items-end gap-4"
          onSubmit={(e) => {
            e.preventDefault();
            go({ season, buyer, levels: query.levels });
          }}
        >
          <label className="flex w-56 flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{t("f.season")}</span>
            <Select value={season} onValueChange={(v) => setSeason(v ?? "")} options={[{ value: "", label: t("report.all_seasons") }, ...lookups.seasons]} aria-label={t("f.season")} />
          </label>
          <label className="flex w-64 flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{t("f.buyer")}</span>
            <Select value={buyer} onValueChange={(v) => setBuyer(v ?? "")} options={[{ value: "", label: t("report.all_buyers") }, ...lookups.buyers]} aria-label={t("f.buyer")} />
          </label>
          <Button type="submit">
            <Search /> {t("btn.apply")}
          </Button>
          <Button type="button" variant="outline" onClick={() => go({ levels: query.levels })}>
            <RotateCcw /> {t("btn.reset_default")}
          </Button>
          <Link href="/dev-sample-status/list" className={cn(buttonVariants({ variant: "outline" }), "ml-auto")}>
            <ArrowLeft /> {t("btn.view_all")}
          </Link>
        </form>
      </section>

      <section className="flex flex-col gap-2">
        <h3 className="text-[15px] font-bold text-brand">{t("report.summary")}</h3>
        <div className="overflow-x-auto rounded-lg border border-border bg-surface">
          <table className="w-full min-w-max text-[13px]">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                <th className="px-3 py-2 text-left font-semibold">{t("report.buyer")}</th>
                {summary.columns.map((c) => (
                  <th key={c} className="max-w-32 px-3 py-2 text-center text-[11px] font-semibold uppercase">
                    {confirmation(c)}
                  </th>
                ))}
                <th className="px-3 py-2 text-center font-semibold">{t("report.grand_total")}</th>
              </tr>
            </thead>
            <tbody>
              {summary.rows.length === 0 ? (
                <tr>
                  <td colSpan={summary.columns.length + 2} className="px-4 py-8 text-center text-foreground-muted">
                    {t("report.empty")}
                  </td>
                </tr>
              ) : (
                summary.rows.map((r) => (
                  <tr key={r.buyer} className="border-b border-border last:border-0 hover:bg-surface-muted/50">
                    <td className="px-3 py-1.5 font-medium">{r.buyer === BLANK ? t("report.blank") : r.buyer}</td>
                    {summary.columns.map((c) => (
                      <td key={c} className={cn("px-3 py-1.5 text-center tabular-nums", !r.cells[c] && "text-foreground-subtle")}>
                        {r.cells[c]}
                      </td>
                    ))}
                    <td className="bg-brand-subtle px-3 py-1.5 text-center font-semibold tabular-nums">{r.total}</td>
                  </tr>
                ))
              )}
            </tbody>
            <tfoot>
              <tr className="bg-brand font-bold text-brand-foreground">
                <td className="px-3 py-2">{t("report.grand_total")}</td>
                {summary.columns.map((c) => (
                  <td key={c} className="px-3 py-2 text-center tabular-nums">
                    {summary.totals[c]}
                  </td>
                ))}
                <td className="px-3 py-2 text-center tabular-nums">{summary.grand}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <section className="flex flex-col gap-2">
        <h3 className="text-[15px] font-bold text-brand">{t("report.detail")}</h3>
        <div className="flex flex-wrap items-center gap-4 rounded-lg border border-border bg-surface px-4 py-3">
          <span className="text-[13px] font-semibold">{t("report.show_in_report")}</span>
          {LEVEL_ORDER.map((l) => {
            const on = query.levels.includes(l);
            return (
              <label key={l} className="flex cursor-pointer items-center gap-2 text-[13px]">
                <Checkbox
                  checked={on}
                  onCheckedChange={() => go({ season: query.season, buyer: query.buyer, levels: on ? query.levels.filter((x) => x !== l) : [...query.levels, l] })}
                  aria-label={t(`report.levels.${l}`)}
                />
                {t(`report.levels.${l}`)}
              </label>
            );
          })}
        </div>
        <div className="overflow-x-auto rounded-lg border border-border bg-surface">
          <table className="w-full min-w-max text-[13px]">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                <th className="px-3 py-2 text-left font-semibold">{t("report.row_labels")}</th>
                {detail.columns.map((c) => (
                  <th key={c} className="px-3 py-2 text-center text-[11px] font-semibold uppercase">
                    {status(c)}
                  </th>
                ))}
                <th className="px-3 py-2 text-center font-semibold">{t("report.grand_total")}</th>
              </tr>
            </thead>
            <tbody>
              {detail.tree.length === 0 ? (
                <tr>
                  <td colSpan={detail.columns.length + 2} className="px-4 py-8 text-center text-foreground-muted">
                    {t("report.empty")}
                  </td>
                </tr>
              ) : (
                detail.tree.flatMap((node) => rows(node, 0, detail.columns, nodeLabel, ""))
              )}
            </tbody>
            <tfoot>
              <tr className="bg-brand font-bold text-brand-foreground">
                <td className="px-3 py-2">{t("report.grand_total")}</td>
                {detail.columns.map((c) => (
                  <td key={c} className="px-3 py-2 text-center tabular-nums">
                    {detail.totals[c]}
                  </td>
                ))}
                <td className="px-3 py-2 text-center tabular-nums">{detail.totals.total}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </div>
  );
}

/** A node and everything under it, indented by depth. */
function rows(node, depth, columns, nodeLabel, path) {
  const key = `${path}/${node.label}`;
  const own = (
    <tr key={key} className={cn("border-b border-border last:border-0", depth === 0 ? "bg-surface-muted/60 font-semibold" : "hover:bg-surface-muted/40")}>
      <td className="py-1.5 pr-3" style={{ paddingLeft: `${0.75 + depth * 1.25}rem` }}>
        {nodeLabel(node)}
      </td>
      {columns.map((c) => (
        <td key={c} className={cn("px-3 py-1.5 text-center tabular-nums", !node.counts[c] && "text-foreground-subtle")}>
          {node.counts[c]}
        </td>
      ))}
      <td className="bg-brand-subtle px-3 py-1.5 text-center font-semibold tabular-nums">{node.counts.total}</td>
    </tr>
  );
  return [own, ...node.children.flatMap((child) => rows(child, depth + 1, columns, nodeLabel, key))];
}

export { PivotReport };
