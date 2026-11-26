"use client";

import { useState, useTransition } from "react";
import { Download, FileText } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
// Brickland's "16 Oct 2026".
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const numFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
// "IN_BATCH" → "In batch": a status read as words.
const words = (v) => String(v).toLowerCase().replaceAll("_", " ").replace(/^\w/, (c) => c.toUpperCase());

/**
 * A module's report set, as Brickland lays it out (Order Merchandising
 * Reports, AnnotechERP 06/13): the reports as one list of choices, a From
 * Date and Export to Excel?, and Preview — the report read at that moment
 * and shown in a dialog (its table, or its chart, with the row count and
 * Export), or, with Export to Excel? ticked, saved straight to a file.
 * Labels come from the module's lang under `prefix`: report.<key>,
 * col.<column>, head.<value> and pending.<module>.
 *
 * `withTo` adds a To Date beside From Date (Sample Grey Fabric Indent's lists):
 * the preview is then asked for `(report, from, to)` and names the span.
 *
 * @param {{ namespace: string, prefix: string, reports: string[], preview: (report: string, from: string, to?: string) => Promise<object>, withTo?: boolean }} props
 */
function ReportSet({ namespace, prefix, reports, preview, withTo = false }) {
  const t = useT(namespace);
  const m = (k, p) => t(`${prefix}.${k}`, p);
  const toastManager = useToastManager();
  const [report, setReport] = useState(reports[0]);
  const [from, setFrom] = useState(todayIso);
  const [to, setTo] = useState(todayIso);
  const [excel, setExcel] = useState(false);
  const [result, setResult] = useState(null);
  const [pending, startTransition] = useTransition();

  const cell = (col, row) => {
    const v = ampm(row[col.key]);
    if (v === null || v === undefined || v === "") return "—";
    switch (col.type) {
      case "date":
        return dateFmt.format(new Date(`${String(v).slice(0, 10)}T00:00:00Z`));
      case "number":
        return numFmt.format(v);
      case "money":
        return `${row.currency ?? ""} ${numFmt.format(v)}`.trim();
      case "yesno":
        return v ? m("yes") : m("no");
      case "status":
      case "badge":
        return words(v);
      case "head":
        return m(`head.${v}`);
      default:
        return String(v);
    }
  };

  function download(data) {
    const head = data.columns.map((c) => m(`col.${c.key}`));
    const body = data.rows.map((r) => data.columns.map((c) => cell(c, r)));
    const csv = [head, ...body].map((l) => l.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${csv}`], { type: "text/csv;charset=utf-8" }));
    link.download = `${data.report}-${data.from}.csv`;
    link.click();
  }

  function run() {
    startTransition(async () => {
      const res = await (withTo ? preview(report, from, to) : preview(report, from));
      if (res?.status !== "success") {
        toastManager.add({ title: res?.message ?? "", type: "warning" });
        return;
      }
      if (excel) download(res.data);
      else setResult(res.data);
    });
  }

  const chart = result?.chart;
  const peak = chart ? Math.max(1, ...result.rows.flatMap((r) => chart.series.map((s) => Number(r[s]) || 0))) : 1;
  return (
    <section className="rounded-xl border border-border bg-surface p-5">
      <fieldset className="rounded-lg border border-border p-4">
        <legend className="px-1 text-[13px] font-semibold text-brand">{m("params")}</legend>
        <div className="grid gap-6 lg:grid-cols-[1fr_24rem]">
          <div role="radiogroup" aria-label={m("params")} className="flex flex-col gap-0.5 rounded-lg border border-border p-2">
            {reports.map((key) => (
              <label key={key} className={cn("flex cursor-pointer items-center gap-3 rounded-md px-3 py-1.5 text-sm text-foreground hover:bg-surface-muted", report === key && "bg-brand-subtle font-semibold")}>
                <input type="radio" name="report" value={key} checked={report === key} onChange={() => setReport(key)} className="size-4 accent-[var(--color-brand)]" />
                {m(`report.${key}`)}
              </label>
            ))}
          </div>
          <div className="flex flex-col gap-3">
            <label className="grid items-center gap-3 sm:grid-cols-[6rem_1fr]">
              <span className="text-[13px] font-semibold text-foreground sm:text-right">{m("from")}</span>
              <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} aria-label={m("from")} />
            </label>
            {withTo ? (
              <label className="grid items-center gap-3 sm:grid-cols-[6rem_1fr]">
                <span className="text-[13px] font-semibold text-foreground sm:text-right">{m("to")}</span>
                <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} aria-label={m("to")} />
              </label>
            ) : null}
            <label className="flex items-center gap-2 text-sm font-semibold text-foreground sm:pl-[6.75rem]">
              <Checkbox checked={excel} onCheckedChange={(c) => setExcel(c === true)} />
              {m("excel")}
            </label>
            <div className="sm:pl-[6.75rem]">
              <Button className="w-full" onClick={run} loading={pending} disabled={!from}>
                <FileText /> {m("preview")}
              </Button>
            </div>
          </div>
        </div>
      </fieldset>

      <Modal open={result !== null} onOpenChange={(open) => !open && setResult(null)} title={result ? m(`report.${result.report}`) : ""} description={m("hint")} className="max-w-6xl">
        {result ? (
          <div className="flex flex-col gap-3">
            {result.pending_module ? <p className="rounded-lg bg-warning-subtle px-3 py-2 text-sm text-warning">{m(`pending.${result.pending_module}`)}</p> : null}
            <p className="text-sm font-semibold text-foreground">
              {m(`report.${result.report}`)} ·{" "}
              {result.to
                ? m("between", { from: dateFmt.format(new Date(`${result.from}T00:00:00Z`)), to: dateFmt.format(new Date(`${result.to}T00:00:00Z`)) })
                : m("since", { date: dateFmt.format(new Date(`${result.from}T00:00:00Z`)) })}
            </p>
            {chart && result.rows.length ? (
              <div className="flex flex-col gap-2 rounded-lg border border-border p-4">
                <div className="flex gap-4 text-xs text-foreground-muted">
                  {chart.series.map((s, i) => (
                    <span key={s} className="flex items-center gap-1.5">
                      <span className={cn("size-2.5 rounded-sm", i === 0 ? "bg-brand" : "bg-warning")} /> {m(`col.${s}`)}
                    </span>
                  ))}
                </div>
                {result.rows.map((r, ri) => (
                  <div key={ri} className="grid grid-cols-[10rem_1fr] items-center gap-3 text-sm">
                    <span className="truncate text-foreground">{r[chart.label] ?? "—"}</span>
                    <span className="flex flex-col gap-1">
                      {chart.series.map((s, i) => (
                        <span key={s} className="flex items-center gap-2">
                          <span className={cn("h-3 rounded-sm", i === 0 ? "bg-brand" : "bg-warning")} style={{ width: `${(100 * (Number(r[s]) || 0)) / peak}%` }} />
                          <span className="text-xs tabular-nums text-foreground-muted">{numFmt.format(Number(r[s]) || 0)}</span>
                        </span>
                      ))}
                    </span>
                  </div>
                ))}
              </div>
            ) : null}
            <div className="max-h-[55vh] overflow-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-brand text-brand-foreground">
                  <tr>
                    {result.columns.map((c) => (
                      <th key={c.key} className={cn("px-3 py-2 text-left text-[13px] font-semibold whitespace-nowrap", ["number", "money"].includes(c.type) && "text-right")}>
                        {m(`col.${c.key}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {result.rows.length === 0 ? (
                    <tr>
                      <td colSpan={result.columns.length} className="px-3 py-8 text-center text-foreground-muted">
                        {m("empty")}
                      </td>
                    </tr>
                  ) : (
                    result.rows.map((r, ri) => (
                      <tr key={ri} className="border-t border-border">
                        {result.columns.map((c) => (
                          <td key={c.key} className={cn("px-3 py-1.5 whitespace-nowrap", ["number", "money"].includes(c.type) && "text-right tabular-nums")}>
                            {cell(c, r)}
                          </td>
                        ))}
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-foreground-muted">{m("rows", { n: result.rows.length })}</span>
              <Button variant="outline" size="sm" onClick={() => download(result)} disabled={!result.rows.length}>
                <Download /> {m("export")}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>
    </section>
  );
}

export { ReportSet };
