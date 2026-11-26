"use client";

import { useMemo, useState, useTransition } from "react";
import { FileText, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useT } from "@/lib/i18n";
import { PSelect, Field, card, messagesOf } from "./yarn-common";

/*
 * Knit Production → Reports (AnnotechERP 15/11), as Brickland lays its
 * report screens out: "Selection of Parameters" — the set's reports as one
 * list of choices, the boxes the set reads, Export to Excel? — Preview and
 * Reset; below, the stat tiles and the report's grid. The stock positions
 * and the day's output sheet are the same card with Search (or Show) in
 * place of the list. Every label comes from `knitting.rp.*`.
 */

/** The report words: `knitting.rp.*` by default; another module passes its own namespace and prefix (Dye House: `dyehouse`, `rpt`). */
function useRp(ns = "knitting", prefix = "rp") {
  const t = useT(ns);
  return (k, p) => t(`${prefix}.${k}`, p);
}

const numFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 3 });
const fixedFmt = new Intl.NumberFormat("en-US", {
  minimumFractionDigits: 3,
  maximumFractionDigits: 3,
});
const moneyFmt = new Intl.NumberFormat("en-US", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
const intFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 });
// "PARTLY_ISSUED" → "Partly Issued": a status read as Brickland writes it in its report grids.
const words = (v) =>
  String(v)
    .toLowerCase()
    .split("_")
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(" ");

/** A figure as its type shows it — `fixed` keeps three places, as the stock positions do. */
function figure(value, type, fixed = false) {
  if (value === null || value === undefined) return "—";
  const n = Number(value);
  if (type === "money") return moneyFmt.format(n);
  if (type === "int") return intFmt.format(n);
  if (type === "pct") return `${moneyFmt.format(n)}%`;
  return (fixed ? fixedFmt : numFmt).format(n);
}

const TONES = [
  "bg-brand-subtle text-brand",
  "bg-brand-subtle text-brand",
  "bg-success-subtle text-success",
  "bg-warning-subtle text-warning",
  "bg-surface-muted text-foreground-muted",
];

/** The stat tiles over a report's grid: Rows (or Lines) first, then what the report sums. */
function StatPills({ stats, r, fixed }) {
  return (
    <div className="flex flex-wrap gap-2">
      {stats.map((s, i) => (
        <span
          key={s.key}
          className="inline-flex items-center gap-2 rounded-lg border border-border-strong px-3 py-1.5 text-[13px] font-medium text-foreground"
        >
          {r(`stat.${s.key}`)}
          <span
            className={cn(
              "rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums",
              TONES[Math.min(i, TONES.length - 1)],
            )}
          >
            {figure(s.value, s.type, fixed && s.type === "number")}
          </span>
        </span>
      ))}
    </div>
  );
}

/**
 * A report's answer as Brickland's grid: its columns (grouped under two-row
 * headings where the report groups them), statuses in words, figures by
 * type, Search and Export above; ticked for Excel, every row on one page.
 */
function ReportGrid({ data, r, plain, exportName, fixed = false }) {
  const columns = useMemo(
    () =>
      data.columns.map((c) => ({
        key: c.key,
        label: r(`col.${c.key}`),
        group: c.group,
        groupLabel: c.group ? r(`grp.${c.group}`) : undefined,
        type:
          c.type === "date"
            ? "date"
            : c.type === "yesno"
              ? "yesno"
              : ["number", "int", "money", "pct"].includes(c.type)
                ? "number"
                : "text",
        format: ["number", "int", "money", "pct"].includes(c.type)
          ? (v) => figure(v, c.type, fixed && c.type === "number")
          : undefined,
      })),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [data],
  );
  const rows = useMemo(() => {
    const statuses = data.columns
      .filter((c) => c.type === "status")
      .map((c) => c.key);
    return data.rows.map((row, i) => {
      const out = { ...row, _k: i };
      for (const k of statuses)
        if (out[k] !== null && out[k] !== undefined && out[k] !== "")
          out[k] = words(out[k]);
      return out;
    });
  }, [data]);
  return (
    <RecordsGrid
      columns={columns}
      rows={rows}
      rowKey="_k"
      exportName={exportName}
      plain={plain}
      emptyState={{ title: r("empty"), text: r("empty_hint") }}
    />
  );
}

/**
 * One parameter box: a select (blank first: "-- Select Store --"), a date, a
 * text box or a tick. A screen that words a box its own way passes
 * `labelKey` and `phKey`.
 */
function ParamBox({ field, value, onChange, r }) {
  const label = r(field.labelKey ?? `f.${field.key}`);
  const ph = field.phKey ?? `ph.${field.key}`;
  if (field.type === "check") {
    return (
      <label className="flex items-center gap-2 pt-7 text-[13px] font-semibold text-foreground">
        <Checkbox
          checked={Boolean(value)}
          onCheckedChange={(c) => onChange(c === true)}
          aria-label={label}
        />
        {label}
      </label>
    );
  }
  if (field.type === "date" || field.type === "text") {
    return (
      <Field label={label}>
        <Input
          type={field.type}
          value={value ?? ""}
          onChange={(e) => onChange(e.target.value)}
          placeholder={field.type === "text" ? r(ph) : undefined}
          aria-label={label}
        />
      </Field>
    );
  }
  const options = field.options ?? [];
  // A box with nothing to choose from stands disabled, as Brickland's do, and says so.
  const empty = options.length === 0 && !field.noBlank;
  return (
    <Field label={label} hint={empty ? r("no_choices") : undefined}>
      <PSelect
        value={value ?? ""}
        onValueChange={(v) => onChange(v ?? "")}
        options={[
          ...(field.noBlank ? [] : [{ value: "", label: r(ph) }]),
          ...options,
        ]}
        disabled={empty}
        aria-label={label}
      />
    </Field>
  );
}

const blankOf = (fields) =>
  Object.fromEntries(
    fields.map((f) => [f.key, f.initial ?? (f.type === "check" ? false : "")]),
  );
const queryOf = (values, fields) =>
  new URLSearchParams(
    fields
      .filter((f) => !f.display)
      .map((f) => [f.key, values[f.key]])
      .filter(
        ([, v]) => v !== "" && v !== false && v !== null && v !== undefined,
      )
      .map(([k, v]) => [k, v === true ? "1" : String(v)]),
  ).toString();

/**
 * A report set: Brickland's report screens (Knit Production Reports, Yarn
 * Store Register, Grey Fabric Store, Knit Quality, Flat Knit Production).
 * The first report is read on opening with its boxes blank — everything on
 * file — and Preview reads the chosen one with the boxes as set.
 *
 * `ns`/`prefix` name where its words live, so another module's report screen
 * (Dye House, 17/11) is the same card with its own words.
 *
 * @param {{ set: string, reports: string[], fields: object[], initial: object|null, load: (path: string) => Promise<object>, ns?: string, prefix?: string }} props
 */
function KnitReportSet({ set, reports, fields, initial, load, ns = "knitting", prefix = "rp" }) {
  const r = useRp(ns, prefix);
  const toastManager = useToastManager();
  const [report, setReport] = useState(reports[0]);
  const [values, setValues] = useState(() => blankOf(fields));
  const [excel, setExcel] = useState(false);
  const [result, setResult] = useState(
    initial ? { ...initial, plain: false } : null,
  );
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const set_ = (k, v) => setValues((x) => ({ ...x, [k]: v }));
  // A short set lays its choices side by side; a long one is a scrolling list.
  const inline = reports.length <= 12 && set === "qc";

  function preview(next = values, which = report, plain = excel) {
    startTransition(async () => {
      const q = queryOf(next, fields);
      const res = await load(`${set}/${which}${q ? `?${q}` : ""}`);
      if (res?.status !== "success") {
        setError(messagesOf(res));
        toastManager.add({
          title: messagesOf(res).join(" · "),
          type: "warning",
        });
        return;
      }
      setError(null);
      setResult({ ...res.data, plain });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Search className="size-4" /> {r("params")}
        </h2>
        <p className="mt-0.5 pl-6 text-[13px] text-foreground-muted">
          {r(`hint.${set}`)}
        </p>
        <p className="mt-4 text-[13px] font-semibold text-foreground">
          {r("params")}
        </p>
        <div
          role="radiogroup"
          aria-label={r("params")}
          className={cn(
            "mt-1.5 rounded-lg border border-border bg-surface-muted/40 px-3 py-2",
            inline
              ? "flex flex-wrap gap-x-5 gap-y-1"
              : "flex max-h-[26rem] flex-col gap-y-0.5 overflow-y-auto",
          )}
        >
          {reports.map((key) => (
            <label
              key={key}
              className={cn(
                "flex cursor-pointer items-center gap-2.5 rounded-md px-1.5 py-1 text-sm text-foreground hover:bg-surface-muted",
                report === key && "font-semibold",
              )}
            >
              <input
                type="radio"
                name="report"
                value={key}
                checked={report === key}
                onChange={() => setReport(key)}
                className="size-3.5 accent-[var(--color-brand)]"
              />
              {r(`report.${key}`)}
            </label>
          ))}
        </div>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {fields.map((f) => (
            <ParamBox
              key={f.key}
              field={f}
              value={values[f.key]}
              onChange={(v) => set_(f.key, v)}
              r={r}
            />
          ))}
          <label className="flex items-start gap-2 pt-6 text-[13px] font-semibold text-foreground">
            <Checkbox
              checked={excel}
              onCheckedChange={(c) => setExcel(c === true)}
              aria-label={r("excel")}
              className="mt-0.5"
            />
            <span>
              {r("excel")}
              <span className="block text-xs font-normal text-foreground-muted">
                {r("excel_hint")}
              </span>
            </span>
          </label>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <Button onClick={() => preview()} loading={pending}>
            <FileText /> {r("preview")}
          </Button>
          <Button
            variant="outline"
            disabled={pending}
            onClick={() => {
              const blank = blankOf(fields);
              setValues(blank);
              setExcel(false);
              setReport(reports[0]);
              preview(blank, reports[0], false);
            }}
          >
            <RotateCcw /> {r("reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      {result ? (
        <section
          className={cn(card, "flex flex-col gap-4 p-5")}
          aria-label={r(`report.${result.report}`)}
        >
          <StatPills stats={result.stats} r={r} />
          {result.pending_module ? (
            <Alert variant="warning">
              {r(`pending.${result.pending_module}`)}
            </Alert>
          ) : null}
          <p className="text-[13px] text-foreground-muted">
            <span className="font-semibold text-foreground">
              {r(`report.${result.report}`)}
            </span>{" "}
            · {r(`desc.${set}`)}
          </p>
          <ReportGrid
            data={result}
            r={r}
            plain={result.plain}
            exportName={result.report}
          />
        </section>
      ) : null}
    </div>
  );
}

/**
 * A searched report screen — Yarn Stock Position, Grey Fabric Stock
 * Position, Daily Knit Output Report: its boxes, Search (or Show) and Reset,
 * then the stat tiles and the grid. It opens on everything on file.
 *
 * @param {{ kind: string, path: string, fields: object[], initial: object, load: (path: string) => Promise<object>, button?: "search"|"show" }} props
 */
function KnitPosition({
  kind,
  path,
  fields,
  initial,
  load,
  button = "search",
}) {
  const r = useRp();
  const [values, setValues] = useState(() => blankOf(fields));
  const [result, setResult] = useState(initial);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function search(next = values) {
    startTransition(async () => {
      const q = queryOf(next, fields);
      const res = await load(`${path}${q ? `?${q}` : ""}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setError(null);
      setResult(res.data);
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Search className="size-4" /> {r(`title.${kind}`)}
        </h2>
        <p className="mt-0.5 pl-6 text-[13px] text-foreground-muted">
          {r(`hint.${kind}`)}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {fields.map((f) => (
            <ParamBox
              key={f.key}
              field={f}
              value={values[f.key]}
              onChange={(v) => setValues((x) => ({ ...x, [f.key]: v }))}
              r={r}
            />
          ))}
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {r(button)}
          </Button>
          <Button
            variant="outline"
            disabled={pending}
            onClick={() => {
              const blank = blankOf(fields);
              setValues(blank);
              search(blank);
            }}
          >
            <RotateCcw /> {r("reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section
        className={cn(card, "flex flex-col gap-4 p-5")}
        aria-label={r(`title.${kind}`)}
      >
        <StatPills
          stats={result.stats.map((s) =>
            s.key === "rows" ? { ...s, key: "lines" } : s,
          )}
          r={r}
          fixed
        />
        <ReportGrid data={result} r={r} exportName={kind} fixed />
      </section>
    </div>
  );
}

export { KnitReportSet, KnitPosition };
