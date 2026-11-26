"use client";

import { useMemo, useState } from "react";
import { Database, Search, Shirt } from "lucide-react";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n";

/**
 * Style & Order → Style Catalogue (AnnotechERP 06/07): a visual index of the
 * running styles — a card each with its picture, buyer, season, garment type
 * and how many colours and orders it has — searched by style or buyer and
 * narrowed to one buyer.
 *
 * @param {{ rows: object[] }} props
 */
function StyleCatalogueGrid({ rows }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.sgl.${k}`, p);
  const [query, setQuery] = useState("");
  const [buyer, setBuyer] = useState("");
  const buyers = useMemo(() => [...new Set(rows.map((r) => r.buyer).filter(Boolean))].sort(), [rows]);
  const needle = query.trim().toLowerCase();
  const shown = rows.filter((r) => (!needle || `${r.style_no} ${r.buyer ?? ""} ${r.description ?? ""}`.toLowerCase().includes(needle)) && (!buyer || r.buyer === buyer));

  if (rows.length === 0) {
    return (
      <div className="flex flex-col items-center rounded-lg border border-border bg-surface px-6 py-16 text-center">
        <span className="grid size-12 place-items-center rounded-md bg-surface-muted text-foreground-muted">
          <Database className="size-5" />
        </span>
        <h2 className="mt-3 text-base font-semibold text-foreground">{m("empty_title")}</h2>
        <p className="mt-1 text-[13px] text-foreground-muted">{m("empty_text")}</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-4">
        <div className="relative w-full max-w-sm">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-9" />
        </div>
        <select className="h-9 rounded-md border border-border-strong bg-surface px-2 text-sm text-foreground" value={buyer} onChange={(e) => setBuyer(e.target.value)} aria-label={m("all_buyers")}>
          <option value="">{m("all_buyers")}</option>
          {buyers.map((b) => (
            <option key={b}>{b}</option>
          ))}
        </select>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {shown.map((s) => (
          <article key={s.id} className="overflow-hidden rounded-lg border border-border bg-surface">
            <div className="grid h-44 place-items-center bg-surface-muted text-foreground-muted">
              {s.image ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={s.image} alt={s.style_no} className="h-full w-full object-contain" />
              ) : (
                <span className="flex flex-col items-center gap-1 text-xs">
                  <Shirt className="size-6" /> {m("no_image")}
                </span>
              )}
            </div>
            <div className="p-4">
              <h3 className="font-semibold text-foreground">{s.style_no}</h3>
              <p className="text-[13px] text-foreground-muted">{s.description}</p>
              <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-[13px]">
                <dt className="text-foreground-muted">{m("buyer")}</dt>
                <dd className="text-right font-medium">{s.buyer ?? "—"}</dd>
                <dt className="text-foreground-muted">{m("season")}</dt>
                <dd className="text-right font-medium">{s.season ?? "—"}</dd>
                <dt className="text-foreground-muted">{m("garment")}</dt>
                <dd className="text-right font-medium">{s.garment_type ?? "—"}</dd>
                <dt className="text-foreground-muted">{m("colours")}</dt>
                <dd className="text-right font-medium tabular-nums">{s.colours}</dd>
                <dt className="text-foreground-muted">{m("orders")}</dt>
                <dd className="text-right font-medium tabular-nums">{s.orders}</dd>
              </dl>
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}

export { StyleCatalogueGrid };
