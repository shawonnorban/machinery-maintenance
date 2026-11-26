"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { PackageCheck, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

const qty = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");

/**
 * Accessories Receipt Report → Receive List (AnnotechERP 06/06), as Brickland
 * has it: the suppliers on the left, searchable; picking one loads beside it
 * the purchase orders of theirs still waiting to be received — ordered,
 * received and left — each with "Receive", the entry filled with it. Nothing
 * is read until a supplier is picked, and nothing reloads.
 *
 * @param {{ suppliers: {value: string, label: string, count: number}[], load: Function, entryHref: string, canManage: boolean }} props
 */
function AccReceivables({ suppliers, load, entryHref, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.mrr.${k}`, p);
  const [query, setQuery] = useState("");
  const [supplier, setSupplier] = useState(null);
  const [rows, setRows] = useState([]);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = suppliers.filter((s) => s.label.toLowerCase().includes(query.trim().toLowerCase()));

  function choose(s) {
    setSupplier(s);
    startTransition(async () => {
      const result = await load(s.value);
      if (result?.status !== "success") return setError(result?.message ?? "");
      setError(null);
      setRows(result.data.orders);
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{m("customer")}</h2>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search_suppliers")} className="pl-8" aria-label={m("search_suppliers")} />
        </div>
        <ul className="mt-3 flex flex-col gap-1">
          {shown.map((s) => (
            <li key={s.value}>
              <button type="button" onClick={() => choose(s)} className={cn("flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted", supplier?.value === s.value && "bg-brand-subtle font-semibold text-brand")}>
                <span>{s.label}</span>
                <span className="rounded-full bg-surface-muted px-2 text-xs text-foreground-muted">{s.count}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-3 py-6 text-center text-[13px] text-foreground-muted">{m("no_supplier")}</li> : null}
        </ul>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="px-5 py-4">
          <h2 className="text-base font-semibold text-foreground">
            {m("orders")}
            {supplier ? ` — ${supplier.label}` : ""}
          </h2>
        </div>
        {error ? <Alert variant="danger">{error}</Alert> : null}
        {!supplier ? (
          <div className="px-5 pb-10 text-center">
            <p className="text-sm font-semibold text-foreground">{m("pick_supplier")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("pick_supplier_hint")}</p>
          </div>
        ) : (
          <div className={cn("overflow-x-auto", pending && "opacity-60")}>
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["po_no", "po_date", "delivery_date", "store", "receive_status", "ordered", "received", "left"].map((k) => (
                    <th key={k} className={cn("px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["ordered", "received", "left"].includes(k) && "text-right")}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                  {canManage ? <th className="px-4 py-2.5" /> : null}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.po_no} className="border-t border-border">
                    <td className="px-4 py-2.5 font-mono text-xs">{r.po_no}</td>
                    <td className="px-4 py-2.5 whitespace-nowrap">{day(r.po_date)}</td>
                    <td className="px-4 py-2.5 whitespace-nowrap">{day(r.delivery_date)}</td>
                    <td className="px-4 py-2.5">{r.store ?? "—"}</td>
                    <td className="px-4 py-2.5">{t(`master.apo.receive.${r.receive_status}`)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{qty.format(r.ordered)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{qty.format(r.received)}</td>
                    <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{qty.format(r.left)}</td>
                    {canManage ? (
                      <td className="px-4 py-2.5 text-right">
                        <Link href={`${entryHref}${encodeURIComponent(r.po_no)}`} className={buttonVariants({ variant: "outline", size: "sm" })}>
                          <PackageCheck /> {m("receive")}
                        </Link>
                      </td>
                    ) : null}
                  </tr>
                ))}
                {!pending && rows.length === 0 ? (
                  <tr>
                    <td colSpan={9} className="px-5 py-8 text-center text-[13px] text-foreground-muted">
                      {m("no_waiting")}
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

export { AccReceivables };
