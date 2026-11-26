"use client";

import { useState, useTransition } from "react";
import { Download, Eye, Printer } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useT } from "@/lib/i18n";
import { card, dash, qty, Field } from "./common";
import { ampm } from "@/lib/clock";

/*
 * Gate Pass → Reports (AnnotechERP 11/02), as Brickland lays them out: the
 * report picked from the list, the one filter bar — dates, category, type,
 * purpose, location and a search — and View; then the report's card with
 * the company and its row count, Excel and Print / PDF (a PDF opened in a
 * new tab to print or save), the rows and a Total row. Nothing reloads.
 */

const filled = (f) =>
  Object.fromEntries(Object.entries(f).filter(([, x]) => x));
const query = (f) =>
  Object.keys(f).length ? `?${new URLSearchParams(f)}` : "";

/**
 * Reports.
 *
 * @param {{ data: object, options: object, act: Function }} props
 */
function GpReport({ data, options, act }) {
  const t = useT("gatepass");
  const r = (k, p) => t(`rpt.${k}`, p);
  const blank = {
    report: "register",
    from: "",
    to: "",
    category: "",
    type: "",
    purpose: "",
    location: "",
    q: "",
  };
  const [f, setF] = useState(blank);
  const [shown, setShown] = useState({ report: "register", filters: {} });
  const [result, setResult] = useState(data);
  const [pending, startTransition] = useTransition();
  const set = (k) => (x) => setF((all) => ({ ...all, [k]: x ?? "" }));
  const view = () =>
    startTransition(async () => {
      const { report, ...rest } = f;
      const filters = filled(rest);
      const res = await act("report", report, filters);
      if (res?.status === "success") {
        setResult(res.data);
        setShown({ report, filters });
      }
    });
  const all = r("f.all");
  const pick = (k, list) => (
    <Field key={k} label={r(`f.${k}`)}>
      <Select
        value={f[k]}
        onValueChange={set(k)}
        options={[{ value: "", label: all }, ...list]}
        placeholder={all}
        aria-label={r(`f.${k}`)}
      />
    </Field>
  );
  const files = (as) =>
    `/api/gate-pass/excel/rpt-${shown.report}${query({ ...shown.filters, ...(as ? { as } : {}) })}`;

  return (
    <div className="flex flex-col gap-4">
      <form
        className={cn(
          card,
          "grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))]",
        )}
        onSubmit={(ev) => {
          ev.preventDefault();
          view();
        }}
      >
        <Field label={r("f.report")}>
          <Select
            value={f.report}
            onValueChange={(x) => set("report")(x || "register")}
            options={options.reports.map((k) => ({
              value: k,
              label: r(`titles.${k}`),
            }))}
            placeholder={r("f.select")}
            aria-label={r("f.report")}
          />
        </Field>
        {["from", "to"].map((k) => (
          <Field key={k} label={r(`f.${k}`)}>
            <Input
              type="date"
              value={f[k]}
              onChange={(ev) => set(k)(ev.target.value)}
              aria-label={r(`f.${k}`)}
            />
          </Field>
        ))}
        {pick("category", [
          { value: "MAIN", label: r("f.main") },
          { value: "ITEM", label: r("f.item") },
        ])}
        {pick(
          "type",
          ["RETURNABLE", "NON_RETURNABLE"].map((k) => ({
            value: k,
            label: t(`gp.type.${k}`),
          })),
        )}
        {pick("purpose", options.purposes)}
        {pick("location", options.locations)}
        <Field label={r("f.search")}>
          <Input
            value={f.q}
            onChange={(ev) => set("q")(ev.target.value)}
            placeholder={r("f.search_ph")}
            aria-label={r("f.search")}
          />
        </Field>
        <div className="flex items-end sm:col-span-2 lg:col-span-4">
          <Button type="submit" loading={pending}>
            <Eye className="size-4" aria-hidden />
            {r("btn.view")}
          </Button>
        </div>
      </form>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {r(`titles.${shown.report}`)}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {result.subtitle}
            </p>
          </div>
          <div className="flex gap-2">
            <a
              href={files()}
              className={buttonVariants({ variant: "outline", size: "sm" })}
            >
              <Download className="size-4" aria-hidden />
              {r("btn.excel")}
            </a>
            <a
              href={files("pdf")}
              target="_blank"
              rel="noreferrer"
              className={buttonVariants({ variant: "outline", size: "sm" })}
            >
              <Printer className="size-4" aria-hidden />
              {r("btn.print")}
            </a>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[960px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {result.columns.map((c) => (
                  <th
                    key={c.key}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      c.numeric && "text-right",
                    )}
                  >
                    {c.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {result.rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={result.columns.length}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {r("empty")}
                  </td>
                </tr>
              ) : null}
              {result.rows.map((row, i) => (
                <tr key={i} className="border-t border-border">
                  {result.columns.map((c) => (
                    <td
                      key={c.key}
                      className={cn(
                        "px-3 py-2.5",
                        c.numeric && "text-right tabular-nums",
                        ["gp_no", "gpi_no", "item"].includes(c.key) &&
                          "font-medium",
                        [
                          "gp_no",
                          "gpi_no",
                          "date",
                          "out_time",
                          "in_time",
                          "out_date",
                          "expected_return",
                        ].includes(c.key) && "whitespace-nowrap",
                      )}
                    >
                      {c.numeric ? qty(row[c.key]) : dash(ampm(row[c.key]))}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
            {result.total ? (
              <tfoot>
                <tr className="border-t-2 border-foreground/40 font-semibold">
                  {result.columns.map((c, i) => (
                    <td
                      key={c.key}
                      className={cn(
                        "px-3 py-2.5",
                        c.numeric && "text-right tabular-nums",
                      )}
                    >
                      {i === 0
                        ? r("total")
                        : c.key in result.total
                          ? qty(result.total[c.key])
                          : ""}
                    </td>
                  ))}
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>
      </section>
    </div>
  );
}

export { GpReport };
