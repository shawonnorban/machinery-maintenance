"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  CircleAlert,
  CircleCheck,
  Download,
  Lock,
  Search,
  TrendingDown,
  TrendingUp,
  TimerReset,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n";
import { card, dmy, num, Pill, keepUrl } from "./plan-common";

/*
 * Capacity Planning → Plan vs Actual & Forecast (AnnotechERP 12/02), as
 * Brickland lays it out: how many locked plans are at risk, delayed, on track,
 * ahead or completed; each order's original completion, what is made, the
 * current forecast against shipment, the variance and the extra capacity it
 * needs; then line by line, day by day, the plan target against the output
 * between two dates. Filters apply in place.
 */

const TILES = [
  ["locked", Lock, "bg-info-subtle text-info"],
  ["AT_RISK", CircleAlert, "bg-danger-subtle text-danger"],
  ["DELAYED", TrendingDown, "bg-warning-subtle text-warning"],
  ["ON_TRACK", TimerReset, "bg-success-subtle text-success"],
  ["AHEAD", TrendingUp, "bg-info-subtle text-info"],
  ["COMPLETED", CircleCheck, "bg-success-subtle text-success"],
];
const COLS = [
  "order_no",
  "lines",
  "order_qty",
  "produced",
  "remaining",
  "original_completion",
  "current_forecast",
  "ship_date",
  "variance_days",
  "extra_capacity",
  "status",
];
const RIGHT = new Set(["order_qty", "remaining", "extra_capacity"]);

function usePlanT() {
  const t = useT("planning");
  return (k, x) => t(`plan.${k}`, x);
}

/** @param {{ data: object, window: { from: string, to: string }, act: Function }} props */
function PlanVsActual({ data: initial, window: initialWindow, act }) {
  const p = usePlanT();
  const [data, setData] = useState(initial);
  const [q, setQ] = useState("");
  const [f, setF] = useState({ ...initialWindow, line: "" });
  const [pending, startTransition] = useTransition();

  const load = (next) =>
    startTransition(async () => {
      setF(next);
      keepUrl(Object.fromEntries(Object.entries(next).filter(([, v]) => v)));
      const res = await act("pva", next);
      if (res?.status === "success") setData(res.data);
    });
  const rows = data.rows.filter(
    (r) =>
      !q ||
      [r.order_no, r.buyer, r.plan_no, r.lines]
        .join(" ")
        .toLowerCase()
        .includes(q.toLowerCase()),
  );
  const variance = (d) =>
    d > 0
      ? p("fc.late", { n: d })
      : d < 0
        ? p("fc.early", { n: -d })
        : p("fc.on_time");
  const cell = (r, k) => {
    switch (k) {
      case "order_no":
        return (
          <>
            <Link
              href={`/capacity-planning/master-plan?plan=${encodeURIComponent(r.plan_no)}&v=${r.version}`}
              className="font-semibold text-brand hover:underline"
            >
              {r.order_no}
            </Link>
            <p className="text-xs text-foreground-muted">
              {[r.buyer, `${r.plan_no} V${r.version}`]
                .filter(Boolean)
                .join(" · ")}
            </p>
          </>
        );
      case "produced":
        return (
          <div className="min-w-36">
            <div className="h-1.5 rounded-full bg-surface-muted">
              <div
                className="h-1.5 rounded-full bg-success"
                style={{ width: `${Math.min(100, r.percent)}%` }}
              />
            </div>
            <p className="mt-1 text-xs tabular-nums">
              {num(r.produced)} ({r.percent}%)
            </p>
          </div>
        );
      case "remaining":
        return <span className="font-semibold">{num(r.remaining)}</span>;
      case "current_forecast":
        return (
          <span
            className={cn(
              "font-semibold",
              r.status === "AT_RISK" && "text-danger",
            )}
          >
            {dmy(r.current_forecast)}
          </span>
        );
      case "original_completion":
      case "ship_date":
        return dmy(r[k]);
      case "variance_days":
        return (
          <span className={cn(r.variance_days > 0 && "text-warning")}>
            {variance(r.variance_days)}
          </span>
        );
      case "extra_capacity":
        return r.extra_capacity ? (
          <span className="text-danger">{num(r.extra_capacity)}</span>
        ) : (
          "—"
        );
      case "status":
        return (
          <Pill tone={r.status}>{p(`fc.statuses_title.${r.status}`)}</Pill>
        );
      default:
        return RIGHT.has(k) ? num(r[k]) : r[k] || "—";
    }
  };

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        {TILES.map(([k, Icon, tone]) => (
          <div
            key={k}
            className={cn(card, "flex items-center gap-3 px-4 py-3")}
          >
            <span
              className={cn(
                "flex size-10 items-center justify-center rounded-lg",
                tone,
              )}
            >
              <Icon className="size-5" aria-hidden />
            </span>
            <div>
              <p className="text-xs font-semibold text-foreground-muted">
                {p(`pvx.tiles.${k}`)}
              </p>
              <p className="text-lg font-semibold tabular-nums text-foreground">
                {num(data.tiles[k])}
              </p>
            </div>
          </div>
        ))}
      </div>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {p("pvx.title")}
            </h2>
            <p className="text-[13px] text-foreground-muted">
              {p("pvx.hint", { date: dmy(data.as_of) })}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <div className="relative w-56">
              <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
                aria-hidden
              />
              <Input
                value={q}
                onChange={(ev) => setQ(ev.target.value)}
                placeholder={p("pvx.search")}
                className="h-9 pl-9"
                aria-label={p("pvx.search")}
              />
            </div>
            <a
              href={`/api/capacity-planning/excel/plan-vs-actual${q ? `?${new URLSearchParams({ q })}` : ""}`}
              className={buttonVariants({ size: "sm", variant: "outline" })}
            >
              <Download className="size-4" aria-hidden /> {p("pvx.export")}
            </a>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1150px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {COLS.map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      RIGHT.has(k) && "text-right",
                    )}
                  >
                    {p(`pvx.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={COLS.length}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {p("pvx.empty")}
                  </td>
                </tr>
              ) : null}
              {rows.map((r) => (
                <tr
                  key={`${r.plan_no}-${r.version}`}
                  className="border-t border-border"
                >
                  {COLS.map((k) => (
                    <td
                      key={k}
                      className={cn(
                        "px-3 py-2.5",
                        RIGHT.has(k) && "text-right tabular-nums",
                        [
                          "order_no",
                          "original_completion",
                          "current_forecast",
                          "ship_date",
                          "variance_days",
                        ].includes(k) && "whitespace-nowrap",
                      )}
                    >
                      {cell(r, k)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-end justify-between gap-3 p-5">
          <div className="max-w-2xl">
            <h2 className="text-base font-semibold text-foreground">
              {p("pvx.prog_title")}
            </h2>
            <p className="text-[13px] text-foreground-muted">
              {p("pvx.prog_hint")}
            </p>
          </div>
          <div className="flex flex-wrap items-end gap-3">
            {["from", "to"].map((k) => (
              <label
                key={k}
                className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground"
              >
                {p(`pvx.${k}`)}
                <Input
                  type="date"
                  value={f[k]}
                  onChange={(ev) => load({ ...f, [k]: ev.target.value })}
                  className="w-40"
                  aria-label={p(`pvx.${k}`)}
                />
              </label>
            ))}
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              {p("pvx.line")}
              <select
                value={f.line}
                onChange={(ev) => load({ ...f, line: ev.target.value })}
                className="h-10 w-36 rounded-lg border border-border-strong bg-surface px-3 text-sm"
                aria-label={p("pvx.line")}
              >
                <option value="">{p("pvx.all_lines")}</option>
                {data.lines.map((l) => (
                  <option key={l.value} value={l.value}>
                    {l.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  ["date", "pva.date"],
                  ["line", "pva.line"],
                  ["order", "pvx.order"],
                  ["plan_target", "pva.plan_target"],
                  ["actual", "pva.actual"],
                  ["variance", "pva.variance"],
                  ["balance", "pva.balance"],
                  ["remarks", "pva.remarks"],
                ].map(([k, key]) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      ["plan_target", "actual", "variance", "balance"].includes(
                        k,
                      ) && "text-right",
                    )}
                  >
                    {p(key)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.progress.length === 0 ? (
                <tr>
                  <td
                    colSpan={8}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {p("pvx.prog_empty")}
                  </td>
                </tr>
              ) : null}
              {data.progress.map((d) => (
                <tr
                  key={`${d.date}-${d.order_no}-${d.line}`}
                  className="border-t border-border"
                >
                  <td className="px-3 py-2.5 whitespace-nowrap">
                    {dmy(d.date)}
                  </td>
                  <td className="px-3 py-2.5">{d.line}</td>
                  <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                    {d.order_no}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.plan_target)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.actual)}
                  </td>
                  <td
                    className={cn(
                      "px-3 py-2.5 text-right tabular-nums",
                      d.variance < 0 && "text-danger",
                      d.variance > 0 && "text-success",
                    )}
                  >
                    {d.variance === null
                      ? "—"
                      : `${d.variance > 0 ? "+" : ""}${num(d.variance)}`}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.balance)}
                  </td>
                  <td className="px-3 py-2.5">{d.remarks ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

export { PlanVsActual };
