"use client";

import { useState, useTransition } from "react";
import { Download, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

const qty = new Intl.NumberFormat("en-US", { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const rateFmt = new Intl.NumberFormat("en-US", { minimumFractionDigits: 4, maximumFractionDigits: 4 });

/**
 * Dummy Order Accessories Indent → Order BOM (AnnotechERP 06/06), as
 * Brickland has it: the orders on the left (each with its job and approved
 * BOM, searchable); picking one — or arriving from a Records row's View BOM —
 * loads beside it the approved BOM's accessories: required, in stock and
 * short, at the BOM rate, each marked Short or In stock; Export takes the
 * table as CSV. Nothing reloads.
 *
 * @param {{ orders: object[], initialOrder: ?object, initialSheet: ?object, load: Function }} props
 */
function OrderBomViewer({ orders, initialOrder, initialSheet, load }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.dum.${k}`, p);
  const [query, setQuery] = useState("");
  // A Records row's View BOM arrives with its order, and its BOM read on the server.
  const [picked, setPicked] = useState(initialOrder);
  const [sheet, setSheet] = useState(initialSheet);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = orders.filter((o) => `${o.order_no} ${o.job} ${o.style}`.toLowerCase().includes(query.trim().toLowerCase()));

  function choose(o) {
    setPicked(o);
    startTransition(async () => {
      const result = await load(o.value);
      if (result?.status !== "success") return setError(result?.message ?? "");
      setError(null);
      setSheet(result.data);
    });
  }

  function exportCsv() {
    const cell = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const head = ["sl", "code", "item", "uom", "required", "stock", "short", "rate", "remarks", "status"].map((k) => m(`col.${k}`));
    const rows = sheet.lines.map((l, i) => [i + 1, l.item_code ?? "", l.description, l.uom, l.required_qty, l.stock_qty, l.short_qty, l.rate, l.bom_remarks ?? "", m(`line.${l.short_qty > 0 ? "SHORT" : "OK"}`)]);
    const blob = new Blob([[head, ...rows].map((r) => r.map(cell).join(",")).join("\n")], { type: "text/csv;charset=utf-8" });
    const a = Object.assign(document.createElement("a"), { href: URL.createObjectURL(blob), download: `order-bom-${picked.order_no}.csv` });
    a.click();
    URL.revokeObjectURL(a.href);
  }

  const card = "rounded-xl border border-border bg-surface";
  return (
    <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{m("orders")}</h2>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search_orders")} className="pl-8" aria-label={m("search_orders")} />
        </div>
        <ul className="mt-3 flex max-h-[32rem] flex-col gap-1 overflow-y-auto">
          {shown.map((o) => (
            <li key={o.value}>
              <button type="button" onClick={() => choose(o)} className={cn("flex w-full flex-col rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted", picked?.value === o.value && "bg-brand-subtle text-brand")}>
                <span className="font-medium">
                  {o.order_no} · {o.job}
                </span>
                <span className="text-xs text-foreground-muted">
                  {o.job} · {o.bom ?? m("no_approved_bom")}
                </span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-3 py-6 text-center text-[13px] text-foreground-muted">{m("no_order")}</li> : null}
        </ul>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-center justify-between gap-3 px-5 py-4">
          <h2 className="text-base font-semibold text-foreground">
            {m("approved_bom")}
            {picked ? ` — ${picked.order_no} · ${picked.job}` : ""}
          </h2>
          {sheet?.lines?.length ? (
            <Button variant="outline" size="sm" onClick={exportCsv}>
              <Download /> {m("export")}
            </Button>
          ) : null}
        </div>
        {error ? <Alert variant="danger">{error}</Alert> : null}
        {!picked ? (
          <p className="px-5 pb-10 text-center text-sm text-foreground-muted">{m("pick_order")}</p>
        ) : !sheet?.bom && !pending ? (
          <p className="px-5 pb-10 text-center text-sm text-foreground-muted">{m("bom_none")}</p>
        ) : (
          <div className={cn("overflow-x-auto", pending && "opacity-60")}>
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["sl", "code", "item", "uom", "required", "stock", "short", "rate", "remarks", "status"].map((k) => (
                    <th key={k} className={cn("px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["required", "stock", "short", "rate"].includes(k) && "text-right")}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {(sheet?.lines ?? []).map((l, i) => (
                  <tr key={l.bom_line_id} className="border-t border-border">
                    <td className="px-4 py-2.5">{i + 1}</td>
                    <td className="px-4 py-2.5 font-mono text-xs">{l.item_code ?? "—"}</td>
                    <td className="px-4 py-2.5">{l.description}</td>
                    <td className="px-4 py-2.5">{l.uom}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{qty.format(l.required_qty)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{qty.format(l.stock_qty)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{qty.format(l.short_qty)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{rateFmt.format(l.rate ?? 0)}</td>
                    <td className="px-4 py-2.5">{l.bom_remarks ?? "—"}</td>
                    <td className="px-4 py-2.5">
                      <span className={cn("rounded-full px-2 py-0.5 text-xs font-medium", l.short_qty > 0 ? "bg-warning-subtle text-warning" : "bg-success-subtle text-success")}>{m(`line.${l.short_qty > 0 ? "SHORT" : "OK"}`)}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

export { OrderBomViewer };
