"use client";

import { useState, useTransition } from "react";
import { Clock, Gauge, Rows3, TriangleAlert } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";
import { card, dmy, num, weekday, keepUrl } from "./plan-common";

/*
 * Capacity Planning → Line Capacity View (AnnotechERP 12/02), as Brickland
 * lays it out: a year and month; Utilisation % or Planned pieces; the lines,
 * hours available against hours planned, utilisation, free line-days and days
 * over capacity; and every line by every day — holiday, free, part loaded,
 * full or over — with its loaded days and month's utilisation. Nothing
 * reloads.
 */

const YEARS = [2025, 2026, 2027, 2028];
const STATES = ["HOLIDAY", "FREE", "PART", "FULL", "OVER"];
const TONE = {
  HOLIDAY: "bg-surface-muted text-foreground-muted",
  FREE: "bg-success-subtle",
  PART: "bg-info-subtle text-info",
  FULL: "bg-warning-subtle text-warning",
  OVER: "bg-danger-subtle text-danger",
};

function usePlanT() {
  const t = useT("planning");
  return (k, x) => t(`plan.${k}`, x);
}

/** @param {{ data: object, act: Function }} props */
function LineCapacity({ data: initial, act }) {
  const p = usePlanT();
  const [data, setData] = useState(initial);
  const [mode, setMode] = useState("util");
  const [pending, startTransition] = useTransition();
  const load = (year, month) =>
    startTransition(async () => {
      keepUrl({ year, month });
      const res = await act("lineCapacity", { year, month });
      if (res?.status === "success") setData(res.data);
    });
  const days = data.rows[0]?.days.map((d) => d.date) ?? [];
  const month = p(`months.${data.month}`);
  const tiles = [
    ["lines", num(data.tiles.lines), Rows3],
    [
      "avail",
      p("lcv.hours", {
        available: num(data.tiles.available_h),
        planned: num(data.tiles.planned_h),
      }),
      Clock,
    ],
    ["util", `${data.tiles.util}%`, Gauge],
    [
      "free",
      `${num(data.tiles.free)} / ${num(data.tiles.over)}`,
      TriangleAlert,
    ],
  ];
  const select =
    "h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm";

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 p-4",
        )}
      >
        <div className="flex flex-wrap items-center gap-3">
          <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
            {p("lcv.year")}
            <select
              value={data.year}
              onChange={(ev) => load(Number(ev.target.value), data.month)}
              className={cn(select, "w-28")}
              aria-label={p("lcv.year")}
            >
              {YEARS.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
            {p("lcv.month")}
            <select
              value={data.month}
              onChange={(ev) => load(data.year, Number(ev.target.value))}
              className={cn(select, "w-36")}
              aria-label={p("lcv.month")}
            >
              {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                <option key={m} value={m}>
                  {p(`months.${m}`)}
                </option>
              ))}
            </select>
          </label>
        </div>
        <div className="flex rounded-lg border border-border p-0.5">
          {["util", "pieces"].map((m) => (
            <Button
              key={m}
              size="sm"
              variant={mode === m ? "primary" : "ghost"}
              onClick={() => setMode(m)}
              aria-pressed={mode === m}
            >
              {p(`lcv.mode.${m}`)}
            </Button>
          ))}
        </div>
      </section>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {tiles.map(([k, value, Icon]) => (
          <div
            key={k}
            className={cn(card, "flex items-center gap-3 px-4 py-3")}
          >
            <span className="flex size-10 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <Icon className="size-5" aria-hidden />
            </span>
            <div>
              <p className="text-xs font-semibold text-foreground-muted">
                {p(`lcv.tiles.${k}`)}
              </p>
              <p className="text-lg font-semibold tabular-nums text-foreground">
                {value}
              </p>
            </div>
          </div>
        ))}
      </div>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="p-5">
          <h2 className="text-base font-semibold text-foreground">
            {p("lcv.title", { month, year: data.year })}
          </h2>
          <p className="text-[13px] text-foreground-muted">{p("lcv.hint")}</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1100px] border-separate border-spacing-0.5 text-xs">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="px-3 py-2 text-left font-semibold whitespace-nowrap uppercase">
                  {p("lcv.line")}
                </th>
                {days.map((d) => (
                  <th
                    key={d}
                    className="px-0.5 py-1.5 text-center leading-tight font-semibold"
                  >
                    {Number(d.slice(8))}
                    <br />
                    <span className="text-[10px] font-normal">
                      {weekday(d)}
                    </span>
                  </th>
                ))}
                <th className="px-2 py-2 text-right font-semibold uppercase">
                  {p("lcv.days")}
                </th>
                <th className="px-2 py-2 text-right font-semibold uppercase">
                  {p("lcv.util")}
                </th>
              </tr>
            </thead>
            <tbody>
              {data.rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={days.length + 3}
                    className="px-3 py-10 text-center text-sm text-foreground-muted"
                  >
                    {p("lcv.empty")}
                  </td>
                </tr>
              ) : null}
              {data.rows.map((r) => (
                <tr key={r.code}>
                  <td className="px-3 py-1.5">
                    <p className="text-sm font-semibold text-foreground">
                      {r.code}
                    </p>
                    <p className="text-[11px] whitespace-nowrap text-foreground-muted">
                      {p("lcv.meta", {
                        path: r.path ?? "—",
                        mp: r.manpower,
                        hours: num(r.hours),
                        eff: num(r.efficiency),
                      })}
                    </p>
                  </td>
                  {r.days.map((d) => (
                    <td
                      key={d.date}
                      className={cn(
                        "h-9 min-w-7 rounded text-center font-medium tabular-nums",
                        TONE[d.state],
                      )}
                      title={
                        d.state === "HOLIDAY"
                          ? p("lcv.cell_holiday", {
                              line: r.code,
                              date: dmy(d.date),
                            })
                          : p("lcv.cell", {
                              line: r.code,
                              date: dmy(d.date),
                              planned: num(d.minutes),
                              available: num(d.available),
                              pct: d.pct,
                              pieces: num(d.pieces),
                            })
                      }
                    >
                      {d.state === "HOLIDAY"
                        ? p("lcv.h")
                        : d.minutes > 0
                          ? mode === "util"
                            ? d.pct
                            : num(d.pieces)
                          : ""}
                    </td>
                  ))}
                  <td className="px-2 text-right tabular-nums text-foreground">
                    {r.loaded}/{r.working}
                  </td>
                  <td className="px-2 text-right font-semibold tabular-nums text-foreground">
                    {r.util}%
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap gap-4 px-5 py-4 text-xs text-foreground">
          {STATES.map((s) => (
            <span key={s} className="flex items-center gap-1.5">
              <span
                className={cn(
                  "size-3 rounded-sm border border-border",
                  TONE[s],
                )}
                aria-hidden
              />
              {p(`lcv.legend.${s}`)}
            </span>
          ))}
        </div>
      </section>
    </div>
  );
}

export { LineCapacity };
