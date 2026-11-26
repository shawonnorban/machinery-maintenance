"use client";

import { useState, useTransition } from "react";
import { Download, FileText, Plus, Save, SlidersHorizontal, Table2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { day, Filters, firstError, money, qty, Register, within } from "@/components/sales-shipment/shared";
import { Box } from "@/components/tradeexport/operations";
import { Field, Tabs, useW } from "@/components/finance/vouchers";
import { ampm } from "@/lib/clock";

/*
 * Finance & Ledger → Reports (AnnotechERP 23/07), as Brickland lays the five
 * screens out:
 *
 * - Run History: the runs made on the screen (report, company, period, the
 *   boxes it was narrowed by, rows, Export to Excel?, who and when), searched;
 *   PDF and Excel; a run can be removed.
 * - Selection of Parameters (Financial Statement Reports only): the reports
 *   as one list; beside it only the boxes the picked report reads; Export to
 *   Excel? and Preview.
 * - New report run: the report and the screen's boxes; Save report run.
 *
 * Every run reads the posted ledger at that moment and is kept in the Run
 * History; the report is drawn below with its total row and summary.
 */

const P = "/accounting/reports";
const SELECTS = { company: "companies", account: "ledgers", cost_center: "cost_centers", main_class: "main_classes", layer: "layers", currency: "currencies" };
const ORDER = ["company", "main_class", "layer", "account", "cost_center", "bill_status", "bill_no", "currency", "posted", "from_month", "to_month", "from", "to"];

function csv(name, head, rows) {
  const text = [head, ...rows].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob([`﻿${text}`], { type: "text/csv;charset=utf-8" }));
  link.download = `${name}.csv`;
  link.click();
}

const cellText = (c, v) => {
  if (v === null || v === undefined || v === "") return c.numeric ? "" : "—";
  if (c.type === "date") return day(v);
  if (c.type === "money") return money(v);
  if (c.type === "num") return qty(v);
  return ampm(String(v));
};

const defaults = (opt) => ({ company: opt.companies[0]?.value ?? "", layer: "LEDGER", posted: "YES", from: opt.fy_start, to: opt.today, from_month: opt.fy_start.slice(0, 7), to_month: opt.month });

function useRun(act, screen) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const run = (report, q, params, excel, after) =>
    startTransition(async () => {
      const body = { screen, report, excel, ...Object.fromEntries(params.map((p) => [p, q[p] || null])) };
      const res = await act.write("POST", `${P}/run`, body);
      if (res?.status !== "success") {
        setData(null);
        return setError(firstError(res));
      }
      setError(null);
      setData({ ...res.data, q: body });
      after?.(res.data, excel);
    });
  return { data, error, pending, run, clear: () => (setData(null), setError(null)) };
}

/** One box of the parameters, as the report reads it. */
function Param({ w, p, opt, q, set, off }) {
  const label = w(`rp.p.${p}`);
  if (p in SELECTS) {
    const all = !["company", "layer"].includes(p);
    return (
      <Field label={label}>
        <Select value={q[p] ?? ""} disabled={off} onValueChange={(v) => set(p, v ?? "")} options={[...(all ? [{ value: "", label: w(`rp.all_${p}`) }] : []), ...opt[SELECTS[p]]]} placeholder={w(`rp.all_${p}`)} aria-label={label} />
      </Field>
    );
  }
  if (p === "bill_status" || p === "posted") {
    const vals = p === "posted" ? ["YES", "NO"] : ["", "UNPAID", "PARTIAL", "PAID"];
    return (
      <div className="flex min-w-0 flex-col gap-1.5">
        <span className="text-[13px] font-semibold">{label}</span>
        <div className="flex flex-wrap gap-x-5 gap-y-1.5 pt-1.5 text-sm" role="radiogroup" aria-label={label}>
          {vals.map((v) => (
            <label key={v || "all"} className={cn("flex items-center gap-1.5", off && "text-foreground-muted")}>
              <input type="radio" name={`rp-${p}`} className="size-4 accent-[var(--color-brand)]" disabled={off} checked={(q[p] ?? "") === v} onChange={() => set(p, v)} />
              {w(`rp.opt.${p}.${v || "ALL"}`)}
            </label>
          ))}
        </div>
      </div>
    );
  }
  const type = p.endsWith("_month") ? "month" : p === "from" || p === "to" ? "date" : "text";
  return (
    <Field label={label}>
      <Input type={type} value={q[p] ?? ""} maxLength={48} disabled={off} onChange={(e) => set(p, e.target.value)} aria-label={label} />
    </Field>
  );
}

/** The report as it was read: its table, total row and summary; Excel. */
function Result({ w, data, opt }) {
  if (!data) return null;
  const label = (c) => w(`rp.col.${c.key}`);
  const exportIt = () =>
    csv(
      `finance-${data.report.toLowerCase()}`,
      data.columns.map(label),
      [...data.rows.map((row) => data.columns.map((c) => cellText(c, row[c.key]))), ...(Object.keys(data.totals).length ? [data.columns.map((c, i) => (c.key in data.totals ? cellText(c, data.totals[c.key]) : i === 0 ? w("rp.total") : ""))] : []), ...data.summary.map((s) => [s.label, money(s.value)])],
    );
  const narrowed = Object.entries(data.q)
    .filter(([k, v]) => v && !["screen", "report", "excel", "company"].includes(k))
    .map(([k, v]) => `${w(`rp.p.${k}`)}: ${k in SELECTS ? (opt[SELECTS[k]].find((o) => o.value === v)?.label ?? v) : k === "from" || k === "to" ? day(v) : k === "bill_status" || k === "posted" ? w(`rp.opt.${k}.${v}`) : v}`)
    .join(" · ");
  return (
    <section className="rounded-lg border border-border bg-surface">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-3">
        <div>
          <h2 className="text-[15px] font-semibold">{data.title}</h2>
          <p className="text-[13px] text-foreground-muted">
            {narrowed || w("rp.all")} · {w("rp.rows", { n: data.rows.length })}
          </p>
        </div>
        <Button size="sm" variant="outline" disabled={!data.rows.length} onClick={exportIt}>
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
                    <td key={c.key} className={cn("border border-border px-2 py-1.5 whitespace-pre", c.numeric && "text-right tabular-nums", /_no$/.test(c.key) && "font-mono text-xs")}>
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
      {data.summary.length ? (
        <dl className="flex flex-wrap gap-x-8 gap-y-2 border-t border-border px-5 py-3 text-sm">
          {data.summary.map((s) => (
            <div key={s.label} className="flex gap-2">
              <dt className="text-foreground-muted">{s.label}</dt>
              <dd className="font-semibold tabular-nums">{money(s.value)}</dd>
            </div>
          ))}
        </dl>
      ) : null}
    </section>
  );
}

/** Selection of Parameters: the reports as one list, beside it only the boxes the picked report reads. */
function Runner({ w, opt, act, screen, onRun }) {
  const [code, setCode] = useState(opt.reports.find((r) => r.value === "ACC-TRIAL-BALANCE")?.value ?? opt.reports[0]?.value);
  const [q, setQ] = useState(defaults(opt));
  const [excel, setExcel] = useState(false);
  const r = useRun(act, screen);
  const report = opt.reports.find((x) => x.value === code);
  const params = ORDER.filter((p) => report?.params.includes(p));
  const set = (k, v) => setQ((s) => ({ ...s, [k]: v }));
  return (
    <div className="flex flex-col gap-4">
      <Box legend={w("rp.sheet")} grid={false}>
        <p className="mb-3 text-[13px] text-foreground-muted">{w("rp.sheet_note")}</p>
        <div className="grid gap-6 lg:grid-cols-2">
          <div className="max-h-[40rem] overflow-auto rounded-lg border border-border p-2" role="radiogroup" aria-label={w("rp.sheet")}>
            {opt.reports.map((x) => (
              <label key={x.value} className={cn("flex cursor-pointer items-center gap-3 rounded-md px-3 py-1.5 text-sm hover:bg-surface-muted", x.value === code && "bg-brand-subtle font-semibold")}>
                <input type="radio" name="finance-report" className="size-4 shrink-0 accent-[var(--color-brand)]" checked={x.value === code} onChange={() => (setCode(x.value), r.clear())} />
                {x.label}
              </label>
            ))}
          </div>
          <div className="flex flex-col gap-3">
            {params.map((p) => (
              <Param key={p} w={w} p={p} opt={opt} q={q} set={set} />
            ))}
            <label className="flex items-center gap-2 text-[13px] font-semibold">
              <Checkbox checked={excel} onCheckedChange={(v) => setExcel(v === true)} aria-label={w("rp.excel")} /> {w("rp.excel")}
            </label>
            <Button className="w-full justify-center" onClick={() => r.run(code, q, params, excel, onRun)} loading={r.pending} disabled={!code}>
              <FileText /> {w("rp.preview")}
            </Button>
            {r.error ? (
              <p role="alert" className="text-sm text-danger">
                {r.error}
              </p>
            ) : null}
            <p className="text-xs text-foreground-muted">{w("rp.posted_only")}</p>
          </div>
        </div>
      </Box>
      <Result w={w} data={r.data} opt={opt} />
    </div>
  );
}

/** New report run: the report and the screen's own boxes; Save report run. */
function NewRun({ w, opt, act, screen, onRun }) {
  const toastManager = useToastManager();
  const [code, setCode] = useState(opt.reports.length === 1 ? opt.reports[0].value : "");
  const [q, setQ] = useState(defaults(opt));
  const [excel, setExcel] = useState(false);
  const r = useRun(act, screen);
  const report = opt.reports.find((x) => x.value === code);
  const reads = (p) => !report || report.params.includes(p);
  const set = (k, v) => setQ((s) => ({ ...s, [k]: v }));
  const save = () =>
    r.run(code, q, opt.form.filter(reads), excel, (d, x) => {
      toastManager.add({ title: w("rp.saved", { n: d.rows.length }), type: "success" });
      onRun?.(d, x);
    });
  return (
    <div className="flex flex-col gap-4">
      <Box legend={w(`rp.new.${screen}`)} grid={false}>
        <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
          <Field label={w("rp.p.report")} req>
            <Select value={code} onValueChange={(v) => (setCode(v ?? ""), r.clear())} options={opt.reports} placeholder={w("rp.pick_report")} aria-label={w("rp.p.report")} />
          </Field>
          {opt.form.map((p) => (
            <Param key={p} w={w} p={p} opt={opt} q={q} set={set} off={!reads(p)} />
          ))}
          <label className="flex items-center gap-2 self-end pb-2 text-sm font-semibold">
            <Checkbox checked={excel} onCheckedChange={(v) => setExcel(v === true)} aria-label={w("rp.excel")} /> {w("rp.excel")}
          </label>
        </div>
        <p className="mt-3 text-xs text-foreground-muted">{w(`rp.form_note.${screen}`)}</p>
        {r.error ? (
          <p role="alert" className="mt-2 text-sm text-danger">
            {r.error}
          </p>
        ) : null}
        <div className="mt-4 flex justify-end">
          <Button onClick={save} loading={r.pending} disabled={!code}>
            <Save /> {w("rp.save")}
          </Button>
        </div>
      </Box>
      <Result w={w} data={r.data} opt={opt} />
    </div>
  );
}

function FinanceReportDesk({ screen, initial, opt, canManage, act }) {
  const w = useW();
  const [tab, setTab] = useState("records");
  const [runs, setRuns] = useState(initial);
  const [filter, setFilter] = useState({});
  const reload = async () => {
    const res = await act.read(`${P}/runs?screen=${screen}`);
    if (res?.status === "success") setRuns(res.data);
  };
  const after = (d, excel) => {
    reload();
    if (!excel) return;
    const label = (c) => w(`rp.col.${c.key}`);
    csv(`finance-${d.report.toLowerCase()}`, d.columns.map(label), [...d.rows.map((row) => d.columns.map((c) => cellText(c, row[c.key]))), ...(Object.keys(d.totals).length ? [d.columns.map((c, i) => (c.key in d.totals ? cellText(c, d.totals[c.key]) : i === 0 ? w("rp.total") : ""))] : [])]);
  };
  const sel = (name, options, field) => ({ name, label: w(`rp.p.${name}`), type: "select", options, test: (r, v) => !v || field(r) === v });
  const FILTERS = {
    company: sel("company", opt.companies, () => opt.companies[0]?.value),
    account: sel("account", opt.ledgers, (r) => r.account_id),
    cost_center: sel("cost_center", opt.cost_centers, (r) => r.cost_center_id),
    from: { name: "from", label: w("rp.p.from"), type: "date", test: (r, v) => !v || !r.to || within(r.to, v, null) },
    to: { name: "to", label: w("rp.p.to"), type: "date", test: (r, v) => !v || !r.from || within(r.from, null, v) },
  };
  const filters = opt.search.map((k) => FILTERS[k]);
  const shown = runs
    .map((r) => ({ ...r, range: r.from || r.to ? `${day(r.from)} – ${day(r.to)}` : "—", excel_label: w(r.excel ? "rp.yes" : "rp.no"), filters_text: r.filters || "—" }))
    .filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const columns = [
    { key: "report", label: w("rp.h.report") },
    { key: "company", label: w("rp.h.company") },
    { key: "range", label: w("rp.h.range") },
    { key: "filters_text", label: w("rp.h.filters") },
    { key: "rows", label: w("rp.h.rows"), type: "number" },
    { key: "excel_label", label: w("rp.h.excel") },
    { key: "by", label: w("rp.h.by") },
    { key: "at", label: w("rp.h.at") },
  ];
  const pdf = () =>
    printDocument({ title: w(`rp.records.${screen}`), orientation: "landscape", sections: [{ columns: columns.map((c) => ({ label: c.label, align: c.type === "number" ? "right" : undefined })), rows: shown.map((r) => columns.map((c) => r[c.key] ?? "—")) }] });
  const tabs = [
    { key: "records", label: w(`rp.records.${screen}`), icon: <Table2 className="size-4" />, count: runs.length },
    ...(screen === "statements" ? [{ key: "sheet", label: w("rp.sheet"), icon: <SlidersHorizontal className="size-4" /> }] : []),
    { key: "new", label: w(`rp.new.${screen}`), icon: <Plus className="size-4" /> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <Tabs tabs={tabs} active={tab} onPick={setTab} />
      <div hidden={tab !== "records"}>
        <Filters
          w={w}
          value={filter}
          onChange={setFilter}
          fields={filters}
          note={w("rp.filter_note")}
          extra={
            <Button type="button" variant="outline" onClick={pdf} disabled={!shown.length}>
              <FileText /> {w("pdf")}
            </Button>
          }
        />
        <Register
          w={w}
          rows={shown}
          refKey="id"
          exportName={`finance-report-runs-${screen}`}
          emptyTitle={w("rp.no_runs")}
          emptyText=""
          onDone={reload}
          columns={columns}
          actions={
            canManage
              ? [{ key: "delete", label: w("rp.delete"), title: w("rp.delete_title"), confirm: w("rp.delete_confirm"), destructive: true, done: w("rp.deleted"), run: (id) => act.write("DELETE", `${P}/runs/${id}`) }]
              : []
          }
        />
        <p className="mt-3 text-xs text-foreground-muted">{w(`rp.form_note.${screen}`)}</p>
      </div>
      {screen === "statements" ? (
        <div hidden={tab !== "sheet"}>
          <Runner w={w} opt={opt} act={act} screen={screen} onRun={after} />
        </div>
      ) : null}
      <div hidden={tab !== "new"}>
        <NewRun w={w} opt={opt} act={act} screen={screen} onRun={after} />
      </div>
    </div>
  );
}

export { FinanceReportDesk };
