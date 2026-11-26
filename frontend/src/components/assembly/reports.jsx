"use client";

import { useState, useTransition } from "react";
import { FileText } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { downloadCsv, firstError, num, useWords } from "@/components/assembly/finishing";
import { ampm } from "@/lib/clock";

/*
 * Assembly Floor → Reports (AnnotechERP 20/09), Brickland's Apparel Assembly
 * Reports as it lays the screen out: Selection of Parameters — the report
 * list on the left, on the right only the boxes the picked report reads
 * (production date, from / to, buyer, style / order, line, store, item,
 * unit), Export to Excel? and Preview — and the report drawn below, with its
 * total row.
 */

const PARAM_OPTIONS = { buyer: "buyers", order: "orders", line: "lines", qline: "qlines", unit: "units", store: "stores", item: "items" };

function AssemblyReports({ options, act }) {
  const w = useWords();
  const [kind, setKind] = useState(options.reports[0]?.kind ?? "");
  const [q, setQ] = useState({ date: options.today, from: options.today.slice(0, 8) + "01", to: options.today });
  const [excel, setExcel] = useState(false);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const params = options.reports.find((r) => r.kind === kind)?.params ?? [];
  const label = (c) => c.label ?? w(`ar.col.${c.key}`);
  const cell = (c, r) => (c.numeric && typeof r[c.key] === "number" ? num(r[c.key]) : (ampm(r[c.key]) ?? "—"));
  const orders = options.orders.filter((o) => !q.buyer || o.buyer_id === q.buyer);

  const preview = () =>
    startTransition(async () => {
      const res = await act.run({ kind, ...Object.fromEntries(params.map((p) => [p, q[p] ?? ""])) });
      if (res?.status !== "success") {
        setData(null);
        return setError(firstError(res));
      }
      setError(null);
      setData(res.data);
      if (excel) {
        const d = res.data;
        downloadCsv(
          `apparel-${d.kind}`,
          d.columns.map(label),
          [...d.rows.map((r) => d.columns.map((c) => r[c.key] ?? "")), ...(Object.keys(d.totals).length ? [d.columns.map((c, i) => (c.key in d.totals ? d.totals[c.key] : i === 0 ? w("ar.total") : ""))] : [])],
        );
      }
    });

  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface p-5">
        <fieldset className="rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold text-brand">{w("ar.selection")}</legend>
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <div className="max-h-[34rem] overflow-auto rounded-lg border border-border p-2" role="radiogroup" aria-label={w("ar.selection")}>
              {options.reports.map((r) => (
                <label key={r.kind} className={cn("flex cursor-pointer items-center gap-3 rounded-md px-3 py-1.5 text-sm hover:bg-surface-muted", r.kind === kind && "bg-brand-subtle font-semibold")}>
                  <input
                    type="radio"
                    name="assembly-report"
                    checked={r.kind === kind}
                    onChange={() => {
                      setKind(r.kind);
                      setData(null);
                      setError(null);
                    }}
                  />
                  {w(`ar.kind.${r.kind}`)}
                </label>
              ))}
            </div>
            <div className="flex flex-col gap-3">
              {params.map((p) => (
                <label key={p} className="grid items-center gap-2 sm:grid-cols-[9rem_1fr]">
                  <span className="text-[13px] font-semibold sm:text-right">{w(`ar.p.${p}`)}</span>
                  {p === "date" || p === "from" || p === "to" ? (
                    <Input type="date" value={q[p] ?? ""} onChange={(e) => setQ({ ...q, [p]: e.target.value })} />
                  ) : (
                    <Select
                      value={q[p] ?? ""}
                      onValueChange={(v) => setQ({ ...q, [p]: v ?? "", ...(p === "buyer" ? { order: "" } : {}) })}
                      options={[{ value: "", label: w("ar.select") }, ...(p === "order" ? orders : options[PARAM_OPTIONS[p]])]}
                      placeholder={w("ar.select")}
                      aria-label={w(`ar.p.${p}`)}
                    />
                  )}
                </label>
              ))}
              <label className="flex items-center gap-2 text-[13px] font-semibold sm:pl-[9.5rem]">
                <input type="checkbox" checked={excel} onChange={(e) => setExcel(e.target.checked)} /> {w("ar.excel")}
              </label>
              <div className="sm:pl-[9.5rem]">
                <Button className="w-full justify-center" onClick={preview} disabled={pending || !kind}>
                  <FileText /> {w("ar.preview")}
                </Button>
              </div>
              {error ? (
                <p role="alert" className="text-sm text-danger sm:pl-[9.5rem]">
                  {error}
                </p>
              ) : null}
            </div>
          </div>
        </fieldset>
      </section>

      {data ? (
        <section className="rounded-lg border border-border bg-surface">
          <header className="border-b border-border px-5 py-3">
            <h2 className="text-[15px] font-semibold">{w(`ar.kind.${data.kind}`)}</h2>
            <p className="text-[13px] text-foreground-muted">
              {Object.entries(data.params)
                .filter(([, v]) => v)
                .map(([k, v]) => `${w(`ar.p.${k}`)}: ${PARAM_OPTIONS[k] ? (options[PARAM_OPTIONS[k]].find((o) => o.value === v)?.label ?? v) : v}`)
                .join(" · ") || w("ar.all")}
              {" · "}
              {w("ar.rows", { n: data.rows.length })}
            </p>
          </header>
          {data.rows.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-foreground-muted">{w("ar.empty")}</p>
          ) : (
            <div className="max-h-[42rem] overflow-auto px-5 py-4">
              <table className="w-full border border-border text-sm">
                <thead className="sticky top-0 bg-surface-muted text-xs text-foreground-muted">
                  <tr>
                    {data.columns.map((c) => (
                      <th key={c.key} scope="col" className={cn("border border-border px-2 py-2 text-left font-medium whitespace-nowrap", c.numeric && "text-right")}>
                        {label(c)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {data.rows.map((r, i) => (
                    <tr key={i} className="hover:bg-surface-muted">
                      {data.columns.map((c) => (
                        <td key={c.key} className={cn("border border-border px-2 py-1.5", c.numeric && "text-right tabular-nums", c.key === "bundle_no" && "font-mono text-xs")}>
                          {cell(c, r)}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
                {Object.keys(data.totals).length ? (
                  <tfoot className="bg-surface-muted text-xs font-semibold">
                    <tr>
                      {data.columns.map((c, i) => (
                        <td key={c.key} className={cn("border border-border px-2 py-1.5", c.numeric && "text-right tabular-nums")}>
                          {c.key in data.totals ? num(data.totals[c.key]) : i === 0 ? w("ar.total") : ""}
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

export { AssemblyReports };
