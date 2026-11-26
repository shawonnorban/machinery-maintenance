"use client";

import { useState, useTransition } from "react";
import { Download, FileText } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { day, firstError, money, qty } from "@/components/sales-shipment/shared";
import { useW } from "@/components/tradeexport/operations";
import { ampm } from "@/lib/clock";

/*
 * Trade & Export → Report (AnnotechERP 22/06), as Brickland lays the screen
 * out: Selection of Parameters — the report list on the left; on the right
 * Company, Buyer, Export L/C, Invoice No, From / To Date, X-factory From /
 * To, and the Invoice Negotiated? / Bill Type? / On Board? choices, a box the
 * picked report does not read disabled; Export to Excel? and Preview; the
 * report drawn below with its total row.
 */

const SELECTS = { company: "companies", buyer: "buyers", lc: "lcs" };
const DATES = ["from", "to", "xf_from", "xf_to"];
const RADIOS = { negotiated: ["", "YES", "NO"], bill_type: ["", "FDBP", "FDBC"], on_board: ["", "WITH", "WITHOUT"] };

function csv(name, head, rows) {
  const text = [head, ...rows].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob([`﻿${text}`], { type: "text/csv;charset=utf-8" }));
  link.download = `${name}.csv`;
  link.click();
}

const cellText = (c, v) => {
  if (v === null || v === undefined || v === "") return "—";
  if (c.type === "date") return day(v);
  if (c.type === "money") return money(v);
  if (c.type === "pct") return `${money(v)}%`;
  if (c.type === "qty" || c.type === "num") return qty(v);
  return ampm(String(v));
};

function Line({ label, off, children }) {
  return (
    <div className="grid items-center gap-2 sm:grid-cols-[10rem_1fr]">
      <span className={cn("text-[13px] font-semibold sm:text-right", off && "text-foreground-muted")}>{label}</span>
      <div className="min-w-0">{children}</div>
    </div>
  );
}

function TradeReports({ options, act }) {
  const w = useW();
  const [kind, setKind] = useState(options.reports[0]?.kind ?? "");
  const [q, setQ] = useState({ company: options.companies[0]?.value ?? "", from: options.year_start, to: options.today, negotiated: "", bill_type: "", on_board: "" });
  const [excel, setExcel] = useState(false);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const report = options.reports.find((r) => r.kind === kind);
  const reads = (p) => p === "company" || (report?.params ?? []).includes(p);
  const lcs = options.lcs.filter((l) => !q.buyer || l.buyer_id === q.buyer);
  const opts = (p) => (p === "lc" ? lcs : options[SELECTS[p]]);
  const label = (c) => w(`rp.col.${c.key}`);
  const shown = (p, v) => (p in SELECTS ? (opts(p)?.find((o) => o.value === v)?.label ?? v) : p in RADIOS ? w(`rp.opt.${p}.${v}`) : DATES.includes(p) ? day(v) : v);

  const run = (k = kind, download = excel) =>
    startTransition(async () => {
      const r = options.reports.find((x) => x.kind === k);
      const body = { kind: k, ...Object.fromEntries((r?.params ?? []).map((p) => [p, q[p] || null])) };
      const res = await act.write("POST", "/tradeexport/ops/reports/run", body);
      if (res?.status !== "success") {
        setData(null);
        return setError(firstError(res));
      }
      setError(null);
      setData(res.data);
      if (download) {
        const d = res.data;
        csv(
          `trade-export-${d.kind}`,
          d.columns.map(label),
          [...d.rows.map((row) => d.columns.map((c) => cellText(c, row[c.key]))), ...(Object.keys(d.totals).length ? [d.columns.map((c, i) => (c.key in d.totals ? cellText(c, d.totals[c.key]) : i === 0 ? w("rp.total") : ""))] : [])],
        );
      }
    });

  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface p-5">
        <fieldset className="rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold text-brand">{w("rp.legend")}</legend>
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="max-h-[44rem] overflow-auto rounded-lg border border-border p-2" role="radiogroup" aria-label={w("rp.report")}>
              {options.reports.map((r) => (
                <label key={r.kind} className={cn("flex cursor-pointer items-center gap-3 rounded-md px-3 py-1.5 text-sm hover:bg-surface-muted", r.kind === kind && "bg-brand-subtle font-semibold")}>
                  <input
                    type="radio"
                    name="trade-report"
                    className="size-4 shrink-0 accent-[var(--color-brand)]"
                    checked={r.kind === kind}
                    onChange={() => {
                      setKind(r.kind);
                      setData(null);
                      setError(null);
                    }}
                  />
                  {w(`rp.kind.${r.kind}`)}
                </label>
              ))}
            </div>
            <div className="flex flex-col gap-3">
              {["company", "buyer", "lc"].map((p) => (
                <Line key={p} label={w(`rp.p.${p}`)} off={!reads(p)}>
                  <Select
                    value={q[p] ?? ""}
                    disabled={!reads(p)}
                    onValueChange={(v) => setQ({ ...q, [p]: v ?? "", ...(p === "buyer" ? { lc: "" } : {}) })}
                    options={[...(p === "company" ? [] : [{ value: "", label: w(`rp.select.${p}`) }]), ...(opts(p) ?? [])]}
                    placeholder={w(`rp.select.${p}`)}
                    aria-label={w(`rp.p.${p}`)}
                  />
                </Line>
              ))}
              <Line label={w("rp.p.invoice")} off={!reads("invoice")}>
                <Input value={q.invoice ?? ""} maxLength={80} disabled={!reads("invoice")} onChange={(e) => setQ({ ...q, invoice: e.target.value })} aria-label={w("rp.p.invoice")} />
              </Line>
              {DATES.map((p) => (
                <Line key={p} label={w(`rp.p.${p}`)} off={!reads(p)}>
                  <Input type="date" value={q[p] ?? ""} disabled={!reads(p)} onChange={(e) => setQ({ ...q, [p]: e.target.value })} aria-label={w(`rp.p.${p}`)} />
                </Line>
              ))}
              {Object.entries(RADIOS).map(([p, vals]) => (
                <Line key={p} label={w(`rp.p.${p}`)} off={!reads(p)}>
                  <div className="flex flex-wrap gap-x-5 gap-y-1.5 text-sm" role="radiogroup" aria-label={w(`rp.p.${p}`)}>
                    {vals.map((v) => (
                      <label key={v || "all"} className={cn("flex items-center gap-1.5", !reads(p) && "text-foreground-muted")}>
                        <input type="radio" name={`rp-${p}`} className="size-4 accent-[var(--color-brand)]" disabled={!reads(p)} checked={(q[p] ?? "") === v} onChange={() => setQ({ ...q, [p]: v })} /> {w(`rp.opt.${p}.${v || "ALL"}`)}
                      </label>
                    ))}
                  </div>
                </Line>
              ))}
              <label className="flex items-center gap-2 text-[13px] font-semibold sm:pl-[10.5rem]">
                <Checkbox checked={excel} onCheckedChange={(v) => setExcel(v === true)} aria-label={w("rp.excel")} /> {w("rp.excel")}
              </label>
              <div className="sm:pl-[10.5rem]">
                <Button className="w-full justify-center" onClick={() => run()} loading={pending} disabled={!kind}>
                  <FileText /> {w("rp.preview")}
                </Button>
              </div>
              {error ? (
                <p role="alert" className="text-sm text-danger sm:pl-[10.5rem]">
                  {error}
                </p>
              ) : null}
            </div>
          </div>
        </fieldset>
      </section>

      {data ? (
        <section className="rounded-lg border border-border bg-surface">
          <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-3">
            <div>
              <h2 className="text-[15px] font-semibold">{w(`rp.kind.${data.kind}`)}</h2>
              <p className="text-[13px] text-foreground-muted">
                {(report?.params ?? [])
                  .filter((p) => q[p])
                  .map((p) => `${w(`rp.p.${p}`)}: ${shown(p, q[p])}`)
                  .join(" · ") || w("rp.all")}
                {" · "}
                {w("rp.rows", { n: data.rows.length })}
              </p>
            </div>
            <Button size="sm" variant="outline" disabled={!data.rows.length} onClick={() => run(data.kind, true)}>
              <Download /> {w("rp.export")}
            </Button>
          </header>
          {data.rows.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-foreground-muted">{w("rp.empty")}</p>
          ) : (
            <div className="max-h-[42rem] overflow-auto px-5 py-4">
              <table className="w-full border border-border text-sm">
                <thead className="sticky top-0 bg-brand text-xs text-brand-foreground">
                  <tr>
                    {data.columns.map((c) => (
                      <th key={c.key} scope="col" className={cn("border border-border px-2 py-2 text-left font-semibold whitespace-nowrap", c.numeric && "text-right")}>
                        {label(c)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {data.rows.map((r, i) => (
                    <tr key={i} className="hover:bg-surface-muted">
                      {data.columns.map((c) => (
                        <td key={c.key} className={cn("border border-border px-2 py-1.5 whitespace-nowrap", c.numeric && "text-right tabular-nums", /_no$/.test(c.key) && "font-mono text-xs")}>
                          {cellText(c, r[c.key])}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
                {Object.keys(data.totals).length ? (
                  <tfoot className="bg-surface-muted text-xs font-semibold">
                    <tr>
                      {data.columns.map((c, i) => (
                        <td key={c.key} className={cn("border border-border px-2 py-2 whitespace-nowrap", c.numeric && "text-right tabular-nums")}>
                          {c.key in data.totals ? cellText(c, data.totals[c.key]) : i === 0 ? w("rp.total") : ""}
                        </td>
                      ))}
                    </tr>
                  </tfoot>
                ) : null}
              </table>
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}

export { TradeReports };
