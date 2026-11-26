"use client";

import { useState, useTransition } from "react";
import { Download, FileText, Printer } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { MultiPick } from "@/components/erp/multi-pick";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

/*
 * Work Study → Reports (AnnotechERP 14/02) → IE Reports, as Brickland lays it
 * out: the report set as one list of choices, beside it the parameters the
 * chosen report asks for, Export to Excel? and Preview — the report read at
 * that moment and shown in a dialog (its table, totals, row count, PDF and
 * Excel), or, with Export to Excel? ticked, saved straight to a workbook. A
 * report that waits for another module says which.
 */

const BASE = "/api/work-study/reports";
const todayIso = () =>
  new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date(),
  );
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  timeZone: "UTC",
});
const numFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 2 });
const moneyFmt = new Intl.NumberFormat("en-US", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;

function Param({ label, children }) {
  return (
    <label className="grid items-center gap-3 sm:grid-cols-[8rem_1fr]">
      <span className="text-[13px] font-semibold text-foreground sm:text-right">
        {label}
      </span>
      {children}
    </label>
  );
}

function IeReports({ options, act }) {
  const t = useT("workstudy");
  const r = (k, p) => t(`rp.${k}`, p);
  const toastManager = useToastManager();
  const [report, setReport] = useState(options.reports[0].key);
  const [values, setValues] = useState(() => ({
    office: "",
    buyer: "",
    order: "",
    style: "",
    floors: [],
    lines: [],
    date: todayIso(),
    from: todayIso(),
    to: todayIso(),
    pluck: "",
    car: false,
    day: "1",
  }));
  const [excel, setExcel] = useState(false);
  const [result, setResult] = useState(null);
  const [pending, startTransition] = useTransition();
  const meta = options.reports.find((x) => x.key === report);
  const has = (p) => meta.params.includes(p);
  const set = (k, v) => setValues((all) => ({ ...all, [k]: v }));

  // Only what the chosen report asks for travels; floors and lines by code.
  function query() {
    const q = {};
    for (const p of ["office", "buyer", "order", "style", "pluck"])
      if (has(p) && values[p]) q[p] = values[p];
    if (has("floors") && values.floors.length)
      q.floors = values.floors.join(",");
    if (has("lines") && values.lines.length) q.lines = values.lines.join(",");
    if (has("range")) {
      q.from = values.from;
      q.to = values.to;
    } else if (has("date")) q.date = values.date;
    if (has("car") && values.car) q.car = "1";
    return q;
  }
  const href = (format) =>
    `${BASE}/${report.replaceAll("_", "-")}/${format}?${new URLSearchParams(query())}`;

  function run() {
    startTransition(async () => {
      const res = await act("preview", { report, query: query() });
      if (res?.status !== "success") {
        toastManager.add({ title: firstError(res) ?? "", type: "warning" });
        return;
      }
      if (excel && !res.data.pending) {
        const link = document.createElement("a");
        link.href = href("xlsx");
        link.click();
        return;
      }
      setResult({
        ...res.data,
        href: { pdf: href("pdf"), xlsx: href("xlsx") },
      });
    });
  }

  const cell = (col, row) => {
    const v = ampm(row[col.key]);
    if (v === null || v === undefined || v === "") return "—";
    switch (col.type) {
      case "date":
        return dateFmt.format(new Date(`${String(v).slice(0, 10)}T00:00:00Z`));
      case "number":
        return numFmt.format(v);
      case "money":
        return moneyFmt.format(v);
      case "pct":
        return `${numFmt.format(v)}%`;
      case "status":
        return r(`status.${v}`);
      default:
        return String(v);
    }
  };
  const numeric = (c) => ["number", "money", "pct"].includes(c.type);
  const floorCodes = values.floors;
  const lineOptions = options.lines.filter(
    (l) => !floorCodes.length || floorCodes.includes(l.floor),
  );

  return (
    <section className="rounded-xl border border-border bg-surface p-5">
      <fieldset className="rounded-lg border border-border p-4">
        <legend className="px-1 text-[13px] font-semibold text-brand">
          {r("params")}
        </legend>
        <div className="grid gap-6 lg:grid-cols-[1fr_30rem]">
          <div
            role="radiogroup"
            aria-label={r("params")}
            className="flex flex-col gap-0.5 rounded-lg border border-border p-2"
          >
            {options.reports.map((x) => (
              <label
                key={x.key}
                className={cn(
                  "flex cursor-pointer items-center gap-3 rounded-md px-3 py-1.5 text-sm text-foreground hover:bg-surface-muted",
                  report === x.key && "bg-brand-subtle font-semibold",
                )}
              >
                <input
                  type="radio"
                  name="report"
                  value={x.key}
                  checked={report === x.key}
                  onChange={() => setReport(x.key)}
                  className="size-4 accent-[var(--color-brand)]"
                />
                {r(`report.${x.key}`)}
              </label>
            ))}
          </div>
          <div className="flex flex-col gap-3 self-start lg:sticky lg:top-4">
            {has("office") ? (
              <Param label={r("office")}>
                <Select
                  value={values.office}
                  onValueChange={(v) => set("office", v ?? "")}
                  options={[
                    { value: "", label: r("office_ph") },
                    ...options.offices,
                  ]}
                  placeholder={r("office_ph")}
                  aria-label={r("office")}
                />
              </Param>
            ) : null}
            {has("buyer") ? (
              <Param label={r("buyer")}>
                <Select
                  value={values.buyer}
                  onValueChange={(v) => set("buyer", v ?? "")}
                  options={[
                    { value: "", label: r("select_ph") },
                    ...options.buyers,
                  ]}
                  placeholder={r("select_ph")}
                  aria-label={r("buyer")}
                />
              </Param>
            ) : null}
            {has("order") ? (
              <Param label={r("order")}>
                <Select
                  value={values.order}
                  onValueChange={(v) => set("order", v ?? "")}
                  options={[
                    { value: "", label: r("select_ph") },
                    ...options.orders,
                  ]}
                  placeholder={r("select_ph")}
                  aria-label={r("order")}
                />
              </Param>
            ) : null}
            {has("style") ? (
              <Param label={r("style")}>
                <Input
                  value={values.style}
                  onChange={(e) => set("style", e.target.value)}
                  aria-label={r("style")}
                />
              </Param>
            ) : null}
            {has("floors") ? (
              <Param label={r("floors")}>
                <MultiPick
                  options={options.floors}
                  value={values.floors}
                  onChange={(v) => {
                    set("floors", v);
                    set(
                      "lines",
                      values.lines.filter(
                        (l) => !v.length || v.includes(l.split("/")[0]),
                      ),
                    );
                  }}
                  placeholder={r("search_ph")}
                  ariaLabel={r("floors")}
                />
              </Param>
            ) : null}
            {has("lines") ? (
              <Param label={r("lines")}>
                <MultiPick
                  options={lineOptions}
                  value={values.lines}
                  onChange={(v) => set("lines", v)}
                  placeholder={r("search_ph")}
                  ariaLabel={r("lines")}
                />
              </Param>
            ) : null}
            {has("range") ? (
              <>
                <Param label={r("from")}>
                  <Input
                    type="date"
                    value={values.from}
                    onChange={(e) => set("from", e.target.value)}
                    aria-label={r("from")}
                  />
                </Param>
                <Param label={r("to")}>
                  <Input
                    type="date"
                    value={values.to}
                    onChange={(e) => set("to", e.target.value)}
                    aria-label={r("to")}
                  />
                </Param>
              </>
            ) : null}
            {has("date") ? (
              <Param label={r("date")}>
                <Input
                  type="date"
                  value={values.date}
                  onChange={(e) => set("date", e.target.value)}
                  aria-label={r("date")}
                />
              </Param>
            ) : null}
            {has("pluck") ? (
              <Param label={r("pluck")}>
                <Select
                  value={values.pluck}
                  onValueChange={(v) => set("pluck", v ?? "")}
                  options={[
                    { value: "", label: r("select_ph") },
                    ...options.pluck.map((p) => ({
                      value: p,
                      label: r(`pluck_opt.${p}`),
                    })),
                  ]}
                  aria-label={r("pluck")}
                />
              </Param>
            ) : null}
            {has("day") ? (
              <Param label={r("day")}>
                <Input
                  type="number"
                  min="1"
                  max="60"
                  value={values.day}
                  onChange={(e) => set("day", e.target.value)}
                  aria-label={r("day")}
                />
              </Param>
            ) : null}
            {has("car") ? (
              <label className="flex items-center gap-2 text-sm font-semibold text-foreground sm:pl-[8.75rem]">
                <Checkbox
                  checked={values.car}
                  onCheckedChange={(c) => set("car", c === true)}
                />
                {r("car")}
              </label>
            ) : null}
            <label className="flex items-center gap-2 text-sm font-semibold text-foreground sm:pl-[8.75rem]">
              <Checkbox
                checked={excel}
                onCheckedChange={(c) => setExcel(c === true)}
              />
              {r("excel")}
            </label>
            <div className="sm:pl-[8.75rem]">
              <Button className="w-full" onClick={run} loading={pending}>
                <FileText /> {r("preview")}
              </Button>
            </div>
          </div>
        </div>
      </fieldset>

      <Modal
        open={result !== null}
        onOpenChange={(open) => !open && setResult(null)}
        title={result ? r(`report.${result.report}`) : ""}
        description={r("result_hint")}
        className="max-w-6xl"
      >
        {result ? (
          <div className="flex flex-col gap-3">
            {result.pending ? (
              <p className="rounded-lg bg-warning-subtle px-3 py-2 text-sm text-warning">
                {r(`pending.${result.pending}`)}
              </p>
            ) : (
              <>
                <p className="text-sm font-semibold text-foreground">
                  {r(`category.${result.category}`)} ·{" "}
                  {result.from === result.to
                    ? dateFmt.format(new Date(`${result.from}T00:00:00Z`))
                    : r("range_of", {
                        from: dateFmt.format(
                          new Date(`${result.from}T00:00:00Z`),
                        ),
                        to: dateFmt.format(new Date(`${result.to}T00:00:00Z`)),
                      })}
                </p>
                <div className="max-h-[55vh] overflow-auto rounded-lg border border-border">
                  <table className="w-full text-sm">
                    <thead className="sticky top-0 bg-brand text-brand-foreground">
                      <tr>
                        {result.columns.map((c) => (
                          <th
                            key={c.key}
                            className={cn(
                              "px-3 py-2 text-left text-[13px] font-semibold whitespace-nowrap",
                              numeric(c) && "text-right",
                            )}
                          >
                            {r(`col.${c.key}`)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {result.rows.length === 0 ? (
                        <tr>
                          <td
                            colSpan={result.columns.length}
                            className="px-3 py-8 text-center text-foreground-muted"
                          >
                            {r("empty")}
                          </td>
                        </tr>
                      ) : (
                        result.rows.map((row, ri) => (
                          <tr key={ri} className="border-t border-border">
                            {result.columns.map((c) => (
                              <td
                                key={c.key}
                                className={cn(
                                  "px-3 py-1.5 whitespace-nowrap",
                                  numeric(c) && "text-right tabular-nums",
                                  ["profit", "variance"].includes(c.key) ||
                                    c.key.startsWith("pl_")
                                    ? Number(row[c.key]) < 0 && "text-danger"
                                    : null,
                                )}
                              >
                                {cell(c, row)}
                              </td>
                            ))}
                          </tr>
                        ))
                      )}
                    </tbody>
                    {result.totals ? (
                      <tfoot className="sticky bottom-0 bg-surface-muted font-semibold">
                        <tr className="border-t-2 border-border">
                          {result.columns.map((c, i) => (
                            <td
                              key={c.key}
                              className={cn(
                                "px-3 py-1.5 whitespace-nowrap",
                                numeric(c) && "text-right tabular-nums",
                              )}
                            >
                              {i === 0
                                ? t("cost.total")
                                : result.totals[c.key] === null
                                  ? ""
                                  : cell(c, result.totals)}
                            </td>
                          ))}
                        </tr>
                      </tfoot>
                    ) : null}
                  </table>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-foreground-muted">
                    {r("rows", { n: result.rows.length })}
                  </span>
                  {result.rows.length ? (
                    <div className="flex gap-2">
                      <a
                        href={result.href.pdf}
                        target="_blank"
                        rel="noreferrer"
                        className={buttonVariants({
                          variant: "outline",
                          size: "sm",
                        })}
                      >
                        <Printer className="size-4" aria-hidden /> {r("pdf")}
                      </a>
                      <a
                        href={result.href.xlsx}
                        className={buttonVariants({
                          variant: "outline",
                          size: "sm",
                        })}
                      >
                        <Download className="size-4" aria-hidden /> {r("xlsx")}
                      </a>
                    </div>
                  ) : null}
                </div>
              </>
            )}
          </div>
        ) : null}
      </Modal>
    </section>
  );
}

export { IeReports };
