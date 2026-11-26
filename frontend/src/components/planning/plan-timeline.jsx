"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n";
import {
  card,
  dmy,
  num,
  today,
  Pill,
  GanttHead,
  GanttBars,
  ganttDays,
  keepUrl,
} from "./plan-common";

/*
 * Capacity Planning → Gantt / Timeline (AnnotechERP 12/02), as Brickland lays
 * it out: a year, month, which plans (locked and drafts, locked only, drafts
 * only) and a search; then every live master plan with an activity in the
 * month — its order, buyer, style, quantity, lines, plan, shipment and status
 * — over its activities from booking to shipment, on one day heading. Nothing
 * reloads.
 */

const YEARS = [2025, 2026, 2027, 2028];
const PLANS = ["ALL", "LOCKED", "DRAFT"];

function usePlanT() {
  const t = useT("planning");
  return (k, x) => t(`plan.${k}`, x);
}

/** @param {{ data: object, act: Function }} props */
function PlanTimeline({ data: initial, act }) {
  const p = usePlanT();
  const [data, setData] = useState(initial);
  const [f, setF] = useState({ plans: "ALL", q: "" });
  const [pending, startTransition] = useTransition();
  const load = (year, month, next = f) =>
    startTransition(async () => {
      setF(next);
      const query = {
        year,
        month,
        ...(next.plans !== "ALL" ? { plans: next.plans } : {}),
        ...(next.q ? { q: next.q } : {}),
      };
      keepUrl(query);
      const res = await act("timeline", query);
      if (res?.status === "success") setData(res.data);
    });
  const { start, end, span, days } = ganttDays([], data.from, data.to);
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
            {p("tl.year")}
            <select
              value={data.year}
              onChange={(ev) => load(Number(ev.target.value), data.month)}
              className={cn(select, "w-28")}
              aria-label={p("tl.year")}
            >
              {YEARS.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
            {p("tl.month")}
            <select
              value={data.month}
              onChange={(ev) => load(data.year, Number(ev.target.value))}
              className={cn(select, "w-36")}
              aria-label={p("tl.month")}
            >
              {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                <option key={m} value={m}>
                  {p(`months.${m}`)}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
            {p("tl.plans")}
            <select
              value={f.plans}
              onChange={(ev) =>
                load(data.year, data.month, { ...f, plans: ev.target.value })
              }
              className={cn(select, "w-44")}
              aria-label={p("tl.plans")}
            >
              {PLANS.map((k) => (
                <option key={k} value={k}>
                  {p(`tl.plan_opts.${k}`)}
                </option>
              ))}
            </select>
          </label>
        </div>
        <div className="relative w-64">
          <Search
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
            aria-hidden
          />
          <Input
            value={f.q}
            onChange={(ev) => setF({ ...f, q: ev.target.value })}
            onKeyDown={(ev) =>
              ev.key === "Enter" && load(data.year, data.month)
            }
            onBlur={() => load(data.year, data.month)}
            placeholder={p("tl.search")}
            className="pl-9"
            aria-label={p("tl.search")}
          />
        </div>
      </section>

      <section className={cn(card, "min-w-0 overflow-hidden p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {p("tl.title", {
            month: p(`months.${data.month}`),
            year: data.year,
          })}
        </h2>
        <p className="text-[13px] text-foreground-muted">
          {p("tl.hint", { n: data.rows.length })}
        </p>
        <div className="mt-4 overflow-x-auto">
          <div className="min-w-[1100px]">
            <GanttHead days={days} label={p("mp.activity")} />
            {data.rows.length === 0 ? (
              <p className="py-10 text-center text-sm text-foreground-muted">
                {p("tl.empty")}
              </p>
            ) : null}
            {data.rows.map((r) => (
              <div key={`${r.plan_no}-${r.version}`} className="mt-3">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-border pb-1.5 text-[13px]">
                  <Link
                    href={`/capacity-planning/master-plan?plan=${encodeURIComponent(r.plan_no)}&v=${r.version}`}
                    className="text-sm font-semibold text-brand hover:underline"
                  >
                    {r.order_no}
                  </Link>
                  <span className="text-foreground">
                    {[r.buyer, r.style, `${num(r.qty)} PCS`, r.lines]
                      .filter(Boolean)
                      .join(" · ")}
                  </span>
                  <span className="text-foreground-muted">
                    {r.plan_no} V{r.version} ·{" "}
                    {p("tl.ship", { date: dmy(r.ship_date) })}
                  </span>
                  <Pill tone={r.status}>{p(`status.${r.status}`)}</Pill>
                </div>
                <GanttBars
                  activities={r.activities}
                  start={start}
                  end={end}
                  span={span}
                />
              </div>
            ))}
          </div>
        </div>
        <p className="mt-4 flex flex-wrap gap-6 text-xs text-foreground-muted">
          <span className="flex items-center gap-1.5">
            <span
              className="h-3 border-l border-dashed border-foreground-muted"
              aria-hidden
            />
            {p("tl.today", { date: dmy(today()) })}
          </span>
          <span>◂ ▸ {p("tl.past")}</span>
        </p>
      </section>
    </div>
  );
}

export { PlanTimeline };
