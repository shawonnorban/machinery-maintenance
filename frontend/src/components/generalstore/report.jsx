"use client";

import { useState, useTransition } from "react";
import { Download, Printer, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useT } from "@/lib/i18n";
import { card, num, dash, Field, Pill } from "./common";
import { ampm } from "@/lib/clock";

/*
 * General Store → Reports (AnnotechERP 10/06): the thirteen reports as
 * Brickland lays them out — the report's own filters, then its card with the
 * count, the date note, Excel and Print / PDF (a PDF opened in a new tab to
 * print or save), the rows and a Total row. Filters apply in place.
 */

/** Each report's filters, in Brickland's order. */
const FILTERS = {
  stock: ["q", "store", "category", "stock_status"],
  receive: [
    "q",
    "from",
    "to",
    "store",
    "category",
    "item",
    "supplier",
    "status",
  ],
  issue: [
    "q",
    "from",
    "to",
    "store",
    "category",
    "item",
    "department",
    "status",
  ],
  return: [
    "q",
    "from",
    "to",
    "store",
    "category",
    "item",
    "department",
    "status",
  ],
  adjustment: ["q", "from", "to", "store", "category", "item", "status"],
  transfer: ["q", "from", "to", "store", "category", "item", "status"],
  department: ["from", "to", "store", "category", "item", "department"],
  consumption: ["from", "to", "store", "category", "item", "department"],
  category: ["store"],
  valuation: ["q", "store", "category"],
  "low-stock": ["q", "store", "category"],
  monthly: ["from", "to", "store", "category", "item"],
  history: ["from", "to", "store", "category", "item", "type"],
};

const TYPES = ["OPENING", "RECEIVE", "ISSUE", "RETURN", "ADJUST", "TRANSFER"];
const filled = (f) =>
  Object.fromEntries(Object.entries(f).filter(([, x]) => x));
const query = (f) =>
  Object.keys(f).length ? `?${new URLSearchParams(f)}` : "";

/**
 * One report.
 *
 * @param {{ report: string, data: object, options: object, act: Function }} props
 */
function GsReport({ report, data, options, act }) {
  const t = useT("store");
  const r = (k, p) => t(`rpt.${k}`, p);
  const inv = (k, p) => t(`inv.${k}`, p);
  const fields = FILTERS[report];
  const blank = Object.fromEntries(
    fields.map((k) => [k === "stock_status" ? "status" : k, ""]),
  );
  const [f, setF] = useState(blank);
  const [shown, setShown] = useState({});
  const [result, setResult] = useState(data);
  const [pending, startTransition] = useTransition();
  const set = (k) => (x) =>
    setF((all) => ({
      ...all,
      [k]: x ?? "",
      ...(k === "category" ? { item: "" } : {}),
    }));
  const load = (nf) =>
    startTransition(async () => {
      const res = await act("report", report, filled(nf));
      if (res?.status === "success") {
        setResult(res.data);
        setShown(filled(nf));
      }
    });

  const select = (k, key, all, list) => (
    <Field key={k} label={r(`f.${key}`)}>
      <Select
        value={f[k]}
        onValueChange={set(k)}
        options={[{ value: "", label: all }, ...list]}
        placeholder={all}
        aria-label={r(`f.${key}`)}
      />
    </Field>
  );
  const control = (k) => {
    switch (k) {
      case "q":
        return (
          <Field key={k} label={r("f.search")}>
            <Input
              value={f.q}
              onChange={(ev) => set("q")(ev.target.value)}
              placeholder={r("f.search_ph")}
              aria-label={r("f.search")}
            />
          </Field>
        );
      case "from":
      case "to":
        return (
          <Field key={k} label={r(`f.${k}`)}>
            <Input
              type="date"
              value={f[k]}
              onChange={(ev) => set(k)(ev.target.value)}
              aria-label={r(`f.${k}`)}
            />
          </Field>
        );
      case "store":
        return select("store", "store", r("f.all_stores"), options.stores);
      case "category":
        return select(
          "category",
          "category",
          r("f.all_categories"),
          options.categories,
        );
      case "item":
        return select(
          "item",
          "item",
          r("f.all_items"),
          options.items
            .filter((i) => !f.category || i.category_id === f.category)
            .map((i) => ({ value: i.value, label: i.label })),
        );
      case "supplier":
        return select(
          "supplier",
          "supplier",
          r("f.all_suppliers"),
          options.suppliers,
        );
      case "department":
        return select(
          "department",
          "department",
          r("f.all_departments"),
          options.departments,
        );
      case "status":
        return select("status", "status", r("f.any_status"), [
          { value: "POSTED", label: inv("status.POSTED") },
          { value: "CANCELLED", label: inv("status.CANCELLED") },
        ]);
      case "stock_status":
        return select(
          "status",
          "stock_status",
          r("f.any_status"),
          ["IN_STOCK", "LOW", "OUT"].map((s) => ({
            value: s,
            label: inv(`stock_status.${s}`),
          })),
        );
      case "type":
        return select(
          "type",
          "type",
          r("f.all_types"),
          TYPES.map((x) => ({ value: x, label: inv(`badge.${x}`) })),
        );
      default:
        return null;
    }
  };

  const cell = (row, c) => {
    const v = ampm(row[c.key]);
    if (c.key === "status")
      return v ? <Pill tone={v}>{inv(`stock_status.${v}`)}</Pill> : "—";
    if (c.key === "doc_status")
      return v ? <Pill tone={v}>{inv(`status.${v}`)}</Pill> : "—";
    if (c.key === "adjustment_type") return v ? inv(`adjust.${v}`) : "—";
    if (c.key === "type")
      return (
        <span
          className={cn(row.reversal && "text-foreground-muted line-through")}
        >
          {row.reversal
            ? inv("ledger.reversal", { type: inv(`badge.${v}`) })
            : inv(`badge.${v}`)}
        </span>
      );
    return c.numeric ? num(v) : dash(v);
  };
  const files = (as) =>
    `/api/general-store/excel/rpt-${report}${query({ ...shown, ...(as ? { as } : {}) })}`;

  return (
    <div className="flex flex-col gap-4">
      <form
        className={cn(
          card,
          "grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[repeat(6,minmax(0,1fr))]",
        )}
        onSubmit={(ev) => {
          ev.preventDefault();
          load(f);
        }}
      >
        {fields.map(control)}
        <div className="flex items-end gap-2">
          <Button type="submit" loading={pending}>
            <Search className="size-4" aria-hidden />
            {r("btn.search")}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setF(blank);
              load({});
            }}
          >
            <RotateCcw className="size-4" aria-hidden />
            {r("btn.reset")}
          </Button>
        </div>
      </form>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {r(`titles.${report}`)} ({result.rows.length})
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
                        [
                          "item_name",
                          "doc_no",
                          "department",
                          "category",
                        ].includes(c.key) && "font-medium",
                        ["date", "doc_no", "item_code", "month"].includes(
                          c.key,
                        ) && "whitespace-nowrap",
                      )}
                    >
                      {cell(row, c)}
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
                          ? num(result.total[c.key])
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

export { GsReport };
