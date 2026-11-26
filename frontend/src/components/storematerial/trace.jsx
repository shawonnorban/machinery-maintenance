"use client";

import { useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import Link from "next/link";
import {
  ArrowRight,
  Eye,
  FileDown,
  FileSpreadsheet,
  Printer,
  Search,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";
import { card, dash, day, messagesOf, qty, StoreFilters } from "./common";
import { ampm } from "@/lib/clock";

/*
 * Store & Material → Traceability and Reports (AnnotechERP 09/01), as Brickland
 * draws them. Traceability: trace by PR, GRN, Material Out or roll; the PR's
 * facts and, for each material, the chain PR → GRN → warehouse → Material
 * Out → floor / line → receiver → return → current stock. Reports: eight of
 * them on one screen, each with the buyer / style / material / date filters,
 * View, Export Excel and Export PDF (files the server writes — the PDF with
 * Bangla shaped) and Print (the browser's own, from the same table).
 */

const BY = ["PR", "GRN", "MO", "ROLL"];
const REPORTS = [
  "stock-summary",
  "grn",
  "material-out",
  "material-return",
  "stock-ledger",
  "traceability",
  "fabric-roll",
  "low-stock",
];

/** Traceability. */
function Traceability({ act }) {
  const t = useT("storematerial");
  const r = (k, p) => t(`tr.${k}`, p);
  const [by, setBy] = useState("PR");
  const [q, setQ] = useState("");
  const [result, setResult] = useState(null);
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();

  function search(ev) {
    ev.preventDefault();
    setErrors({});
    startTransition(async () => {
      const res = await act("trace", { by, q: q.trim() });
      if (res?.status !== "success") {
        setResult(null);
        return setErrors(messagesOf(res));
      }
      setResult(res.data);
    });
  }

  return (
    <section className={cn(card, "flex flex-col gap-4 p-5")}>
      <h2 className="text-base font-semibold text-foreground">{r("title")}</h2>
      <div
        role="tablist"
        className="flex w-fit flex-wrap gap-1 rounded-lg border border-border p-1"
      >
        {BY.map((k) => (
          <button
            key={k}
            type="button"
            role="tab"
            aria-selected={by === k}
            onClick={() => {
              setBy(k);
              setResult(null);
              setErrors({});
            }}
            className={cn(
              "rounded-md px-3 py-1 text-[13px] font-medium",
              by === k
                ? "bg-brand text-brand-foreground"
                : "text-foreground hover:bg-surface-muted",
            )}
          >
            {r(`by.${k}`)}
          </button>
        ))}
      </div>
      <form onSubmit={search} className="flex flex-wrap items-end gap-3">
        <label className="flex min-w-64 flex-1 flex-col gap-1.5">
          <span className="text-[13px] font-medium">{r(`label.${by}`)}</span>
          <Input
            value={q}
            onChange={(ev) => setQ(ev.target.value)}
            placeholder={r(`ph.${by}`)}
            aria-label={r(`label.${by}`)}
          />
        </label>
        <Button type="submit" loading={pending} disabled={!q.trim()}>
          <Search className="size-4" aria-hidden />
          {r("search")}
        </Button>
      </form>
      {Object.values(errors)[0] ? (
        <Alert variant="danger">{Object.values(errors)[0][0]}</Alert>
      ) : null}
      {result ? (
        <div className="flex flex-col gap-4">
          {result.pr ? (
            <div className="flex flex-wrap gap-x-6 gap-y-1 rounded-lg bg-surface-muted px-4 py-3 text-[13px]">
              <span className="font-semibold text-foreground">
                {result.pr.pr_no}
              </span>
              <span>
                {r("merchandiser")}: <b>{dash(result.pr.merchandiser)}</b>
              </span>
              <span>
                {r("date")}: <b>{day(result.pr.date)}</b>
              </span>
              <span>
                {r("supplier")}: <b>{dash(result.pr.supplier)}</b>
              </span>
            </div>
          ) : null}
          {result.roll ? (
            <div className="flex flex-wrap gap-x-6 gap-y-1 rounded-lg bg-surface-muted px-4 py-3 text-[13px]">
              <span className="font-semibold text-foreground">
                {r("roll")} {result.roll.roll_no}
              </span>
              {[
                ["dye_lot", result.roll.dye_lot],
                ["gsm", result.roll.gsm],
                ["width", result.roll.width],
              ].map(([k, v]) => (
                <span key={k}>
                  {t(`roll.col.${k}`)}: <b>{dash(v)}</b>
                </span>
              ))}
            </div>
          ) : null}
          {result.chains.map((c, i) => (
            <div
              key={i}
              className="flex flex-col gap-3 rounded-lg border border-border p-4"
            >
              <div className="flex flex-wrap gap-x-6 gap-y-1 text-[13px]">
                {[
                  ["buyer", c.buyer],
                  ["po", c.po],
                  ["style", c.style],
                  ["order", c.order_no],
                ].map(([k, v]) => (
                  <span key={k}>
                    {r(k)}: <b className="text-foreground">{dash(v)}</b>
                  </span>
                ))}
              </div>
              <ol className="grid gap-2 lg:grid-cols-[repeat(5,minmax(0,1fr))]">
                {[
                  [
                    r("step.grn"),
                    c.grns.length
                      ? c.grns.map((g) => (
                          <Link
                            key={g}
                            href={`/store-material/in-house-grn?grn=${encodeURIComponent(g)}`}
                            className="block font-semibold text-brand hover:underline"
                          >
                            {g}
                          </Link>
                        ))
                      : "—",
                    `${r("qty")}: ${qty(c.in_house)} ${c.unit}`,
                  ],
                  [
                    r("step.warehouse"),
                    c.warehouse,
                    `${r("material")}: ${c.material}`,
                  ],
                  [
                    r("step.out"),
                    c.outs.map((o) => o.mo_no).join(", ") || "—",
                    `${r("qty")}: ${qty(c.out)} ${c.unit}`,
                  ],
                  [
                    r("step.floor"),
                    c.outs
                      .map((o) =>
                        [o.floor, o.section, o.line]
                          .filter(Boolean)
                          .join(" · "),
                      )
                      .filter((v, j, all) => all.indexOf(v) === j)
                      .join(", ") || "—",
                    `${r("step.receiver")}: ${
                      c.outs
                        .map((o) => o.receiver)
                        .filter(Boolean)
                        .filter((v, j, all) => all.indexOf(v) === j)
                        .join(", ") || "—"
                    }`,
                  ],
                  [
                    r("step.return"),
                    c.returns.map((x) => x.mr_no).join(", ") || "—",
                    `${r("qty")}: ${qty(c.returned)} ${c.unit}${c.returns[0] ? ` · ${t(`return_reason.${c.returns[0].reason}`)}` : ""}`,
                  ],
                ].map(([title, main, sub], j) => (
                  <li
                    key={j}
                    className="relative flex flex-col gap-1 rounded-lg bg-surface-muted px-3 py-2.5"
                  >
                    <span className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
                      {title}
                    </span>
                    <span className="text-sm font-medium text-foreground">
                      {main}
                    </span>
                    <span className="text-xs text-foreground-muted">{sub}</span>
                    {j < 4 ? (
                      <ArrowRight
                        className="absolute top-1/2 -right-2.5 z-10 hidden size-4 -translate-y-1/2 text-foreground-muted lg:block"
                        aria-hidden
                      />
                    ) : null}
                  </li>
                ))}
              </ol>
              <p className="text-sm text-foreground">
                {r("available")}:{" "}
                <span className="font-semibold tabular-nums">
                  {qty(c.available)} {c.unit}
                </span>
              </p>
            </div>
          ))}
        </div>
      ) : null}
    </section>
  );
}

/** Reports. */
function StoreReports({ initial, filters, act }) {
  const t = useT("storematerial");
  const p = (k, v) => t(`rp.${k}`, v);
  const [state, setState] = useState(initial);
  const [f, setF] = useState({});
  const [pending, startTransition] = useTransition();

  function load(report, nf = f) {
    startTransition(async () => {
      const result = await act("report", report, nf);
      if (result?.status === "success") setState(result.data);
    });
  }

  const query = Object.keys(f).length ? `?${new URLSearchParams(f)}` : "";
  const excel = `/api/store-material/report-excel/${state.report}${query}`;
  const pdf = `/api/store-material/report-pdf/${state.report}${query}`;
  const totalOf = (key) =>
    state.rows.reduce((sum, r) => sum + (Number(r[key]) || 0), 0);
  const showTotal = state.report === "material-return";
  const cell = (r, c) => {
    const v = ampm(r[c.key]);
    if (c.numeric) return v === null || v === undefined ? "—" : qty(v);
    if (c.key === "date") return day(v);
    if (c.key === "status") return v ? p(`status.${v}`) : "—";
    return dash(v);
  };
  // The report on paper: the filters it was read with, then its rows (and the total where it has one).
  const lists = {
    buyer: filters.buyers,
    style: filters.styles,
    material: filters.materials,
  };
  const labelOf = (k, v) => lists[k]?.find((o) => o.value === v)?.label ?? v;
  const print = () =>
    printDocument({
      title: p(`heading.${state.report}`),
      orientation: state.columns.length > 7 ? "landscape" : "portrait",
      meta: ["buyer", "style", "material", "from", "to"].map((k) => [
        t(`f.${k}`),
        f[k]
          ? k === "from" || k === "to"
            ? day(f[k])
            : labelOf(k, f[k])
          : t("rp.all"),
      ]),
      sections: [
        {
          columns: state.columns.map((c) => ({
            label: p(`col.${c.key}`),
            align: c.numeric ? "right" : undefined,
            nowrap: !c.numeric,
          })),
          rows: state.rows.map((r) => state.columns.map((c) => cell(r, c))),
          foot:
            showTotal && state.rows.length
              ? [
                  state.columns.map((c, i) =>
                    i === 0 ? p("total") : c.numeric ? qty(totalOf(c.key)) : "",
                  ),
                ]
              : [],
          empty: p("empty"),
        },
      ],
    });

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-3 p-5 print:hidden")}>
        <h2 className="text-base font-semibold text-foreground">
          {p("title")}
        </h2>
        <nav className="flex flex-wrap gap-1" aria-label={p("title")}>
          {REPORTS.map((k) => (
            <button
              key={k}
              type="button"
              onClick={() => load(k)}
              aria-current={state.report === k ? "page" : undefined}
              className={cn(
                "rounded-md px-3 py-1.5 text-[13px] font-medium",
                state.report === k
                  ? "bg-brand text-brand-foreground"
                  : "text-foreground hover:bg-surface-muted",
              )}
            >
              {p(`report.${k}`)}
            </button>
          ))}
        </nav>
      </section>
      <div className="print:hidden">
        <StoreFilters
          fields={["buyer", "style", "material", "from", "to"]}
          options={filters}
          pending={pending}
          onSearch={(nf) => {
            setF(nf);
            load(state.report, nf);
          }}
        />
      </div>
      <section className={cn(card, "min-w-0 overflow-hidden print:border-0")}>
        <div className="flex flex-wrap items-center justify-between gap-3 p-5">
          <h2 className="text-base font-semibold text-foreground">
            {p(`heading.${state.report}`)}
          </h2>
          <div className="flex flex-wrap gap-2 print:hidden">
            <Button
              size="sm"
              variant="outline"
              onClick={() => load(state.report)}
              loading={pending}
            >
              <Eye className="size-4" aria-hidden />
              {p("view")}
            </Button>
            <a
              href={excel}
              className="inline-flex h-8 items-center gap-1.5 rounded-sm border border-border-strong px-3 text-xs font-medium text-foreground hover:bg-surface-muted"
            >
              <FileSpreadsheet className="size-4" aria-hidden />
              {p("excel")}
            </a>
            <a
              href={pdf}
              className="inline-flex h-8 items-center gap-1.5 rounded-sm border border-border-strong px-3 text-xs font-medium text-foreground hover:bg-surface-muted"
            >
              <FileDown className="size-4" aria-hidden />
              {p("pdf")}
            </a>
            <Button size="sm" variant="outline" onClick={print}>
              <Printer className="size-4" aria-hidden />
              {p("print")}
            </Button>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {state.columns.map((c) => (
                  <th
                    key={c.key}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold whitespace-nowrap uppercase",
                      c.numeric && "text-right",
                    )}
                  >
                    {p(`col.${c.key}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {state.rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={state.columns.length}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {p("empty")}
                  </td>
                </tr>
              ) : null}
              {state.rows.map((r, i) => (
                <tr key={i} className="border-t border-border">
                  {state.columns.map((c) => (
                    <td
                      key={c.key}
                      className={cn(
                        "px-3 py-2.5",
                        c.numeric
                          ? "text-right tabular-nums"
                          : "whitespace-nowrap",
                        [
                          "grn_no",
                          "mo_no",
                          "mr_no",
                          "reference",
                          "material",
                        ].includes(c.key) && "font-medium",
                      )}
                    >
                      {cell(r, c)}
                    </td>
                  ))}
                </tr>
              ))}
              {showTotal && state.rows.length ? (
                <tr className="border-t-2 border-border font-semibold">
                  {state.columns.map((c, i) => (
                    <td
                      key={c.key}
                      className={cn(
                        "px-3 py-2.5",
                        c.numeric && "text-right tabular-nums",
                      )}
                    >
                      {i === 0
                        ? p("total")
                        : c.numeric
                          ? qty(totalOf(c.key))
                          : ""}
                    </td>
                  ))}
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

export { Traceability, StoreReports };
