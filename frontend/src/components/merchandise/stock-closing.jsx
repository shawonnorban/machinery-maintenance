"use client";

import { useMemo, useState, useTransition } from "react";
import { ListChecks, RotateCcw, Save, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const card = "rounded-xl border border-border bg-surface";

/**
 * A closing's Show Exception tab (AnnotechERP 06/06), as Brickland lays it
 * out: the Periods every Month Close has checked (searchable, with status and
 * exception count) on the left; picking one loads, beside it, the documents
 * the last run found standing in its way. Nothing is read until a period is
 * picked, and nothing reloads.
 *
 * @param {{ periods: object[], load: (period: string) => Promise<object> }} props
 */
function StockClosingExceptions({ periods, load }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.clo.${k}`, p);
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const [rows, setRows] = useState([]);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return periods.filter((p) => !needle || `${p.label} ${p.period}`.toLowerCase().includes(needle));
  }, [periods, query]);

  function pick(p) {
    setPicked(p);
    startTransition(async () => {
      const result = await load(p.period);
      if (result?.status !== "success") return setError(messagesOf(result).join(" · "));
      setRows(result.data);
      setError(null);
    });
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{m("periods")}</h2>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_label")} />
        </div>
        <ul className="mt-3 flex flex-col gap-1">
          {shown.map((p) => (
            <li key={p.period}>
              <button
                type="button"
                onClick={() => pick(p)}
                className={cn("flex w-full items-center justify-between gap-2 rounded-lg border border-transparent px-3 py-2 text-left text-sm hover:bg-surface-muted", picked?.period === p.period && "border-brand bg-brand-subtle")}
              >
                <span className="font-medium text-foreground">{p.label}</span>
                <span className="flex items-center gap-2 text-xs">
                  <span className={cn("rounded-md px-1.5 py-0.5 font-medium", p.status === "CLOSED" ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning")}>{m(`status.${p.status}`)}</span>
                  <span className="tabular-nums text-foreground-muted">{p.exceptions}</span>
                </span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-3 py-6 text-center text-[13px] text-foreground-muted">{periods.length ? m("no_match") : m("no_periods")}</li> : null}
        </ul>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="px-5 py-4">
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <ListChecks className="size-4" /> {picked ? m("exceptions_of", { period: picked.label }) : m("exceptions")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{m("pick_hint")}</p>
        </div>
        {error ? (
          <div className="px-5 pb-4">
            <Alert variant="danger">{error}</Alert>
          </div>
        ) : null}
        {!picked ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("select_period")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("select_hint")}</p>
          </div>
        ) : pending ? (
          <div className="border-t border-border px-5 py-12 text-center text-[13px] text-foreground-muted">…</div>
        ) : rows.length === 0 ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("none", { period: picked.label })}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("none_hint")}</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["document", "doc_no", "doc_date", "doc_status"].map((k) => (
                    <th key={k} className="px-4 py-2.5 text-left text-[13px] font-semibold">
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={`${r.document}-${r.doc_no}`} className="border-t border-border">
                    <td className="px-4 py-2">{m(`doc.${r.document}`)}</td>
                    <td className="px-4 py-2 font-mono text-[13px]">{r.doc_no}</td>
                    <td className="px-4 py-2 whitespace-nowrap">{dateFmt.format(new Date(`${r.doc_date}T00:00:00Z`))}</td>
                    <td className="px-4 py-2">{m(`doc_status.${r.status}`)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {picked && !pending ? <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{m("count", { n: rows.length })}</p> : null}
      </section>
    </div>
  );
}

/**
 * A closing's Month Close tab (AnnotechERP 06/06), as Brickland lays it out:
 * the period (Jul/2026 or 2026-07) and remarks; Month Close checks the month,
 * closes it when no document stands in its way and otherwise says how many
 * do (listed on Show Exception). Reset clears the form. Nothing reloads.
 *
 * @param {{ noun: string, canManage: boolean, close: (payload: object) => Promise<object> }} props
 */
function StockMonthClose({ noun, canManage, close }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.clo.${k}`, p);
  const toastManager = useToastManager();
  const [period, setPeriod] = useState("");
  const [remarks, setRemarks] = useState("");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();

  function run() {
    startTransition(async () => {
      const result = await close({ period: period.trim() || null, remarks: remarks.trim() || null });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setErrors(null);
      setPeriod("");
      setRemarks("");
      toastManager.add({ title: m("closed_toast", { period: result.data.label }), type: "success" });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">{m("new", { noun })}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{m("rules")}</p>
        <label className="mt-4 flex max-w-sm flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">
            {m("f.period")}
            <span className="text-danger">*</span>
          </span>
          <Input value={period} onChange={(e) => setPeriod(e.target.value)} placeholder={m("period_ph")} maxLength={10} disabled={!canManage} aria-label={m("f.period")} />
          <span className="text-xs text-foreground-muted">{m("period_hint")}</span>
        </label>
        <label className="mt-4 flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">{m("f.remarks")}</span>
          <Textarea rows={3} value={remarks} onChange={(e) => setRemarks(e.target.value)} maxLength={2000} disabled={!canManage} aria-label={m("f.remarks")} />
        </label>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{m("footer")}</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => (setPeriod(""), setRemarks(""), setErrors(null))} disabled={pending}>
            <RotateCcw /> {m("reset")}
          </Button>
          {canManage ? (
            <Button onClick={run} loading={pending}>
              <Save /> {m("close")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { StockClosingExceptions, StockMonthClose };
