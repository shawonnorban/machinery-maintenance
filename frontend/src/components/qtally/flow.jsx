"use client";

import { useState } from "react";
import { ArrowRight, Workflow } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { useQt, useFilters, num, pct, day, Field, Select, FilterCard, Notice, Pill } from "./common";

/*
 * Quality Tally → Tally → Job Flow (user, 2026-11-25): a job starts at
 * cutting and ends at quality. Cutting's panels become complete pieces when
 * every panel of the garment passed; sewing works on those pieces, quality on
 * sewing's pass — by their line targets. What waits between sections is shown, and how much
 * of the order has passed quality.
 */

function JobFlow({ rows, query }) {
  const p = useQt();
  const { apply, pending } = useFilters(query);
  const [status, setStatus] = useState(query.status ?? "OPEN");
  const [search, setSearch] = useState(query.search ?? "");

  return (
    <>
      <FilterCard onApply={() => apply({ status, search })} pending={pending} applyLabel={p("apply")}>
        <Field label={p("f.status")}>
          <Select value={status} onChange={setStatus} options={["OPEN", "CLOSED"].map((s) => ({ value: s, label: p(`job_status.${s}`) }))} />
        </Field>
        <Field label={p("f.search")} className="min-w-56 flex-1">
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={p("f.search_job")} />
        </Field>
      </FilterCard>
      {rows.length === 0 ? <Notice tone="info">{p("empty.jobs")}</Notice> : null}
      <div className="flex flex-col gap-4">
        {rows.map((j) => (
          <section key={j.job_id} className="rounded-lg border border-border bg-surface">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3">
              <div className="flex items-center gap-2.5">
                <Workflow className="size-4 text-foreground-muted" aria-hidden />
                <span className="text-[15px] font-semibold text-foreground">{j.job_no}</span>
                <span className="text-sm text-foreground-muted">{[j.buyer, j.style, j.color, j.size].filter(Boolean).join(" · ")}</span>
                {j.due_date ? <Pill tone="bg-surface-muted text-foreground-muted">{p("col.due_date")}: {day(j.due_date)}</Pill> : null}
              </div>
              <div className="flex items-center gap-3">
                <span className="h-2 w-40 overflow-hidden rounded-full bg-surface-muted">
                  <span className="block h-full rounded-full bg-success" style={{ width: `${Math.min(100, j.done_pct ?? 0)}%` }} />
                </span>
                <span className="text-sm font-semibold tabular-nums">{pct(j.done_pct)}</span>
              </div>
            </div>
            <div className="grid grid-cols-1 items-stretch gap-2 p-4 lg:grid-cols-[1fr_auto_1.4fr_auto_1.4fr_auto_1.4fr]">
              <Stage title={p("flow.order")} items={[[p("col.order_qty"), num(j.order_qty)]]} />
              <Arrow />
              <Stage
                title={p("section.CUTTING")}
                tone="border-info"
                items={[
                  [p("col.bundles"), num(j.cutting.bundles)], [p("flow.panels_checked"), num(j.cutting.panels_checked)], [p("flow.panels_reject"), num(j.cutting.panels_reject)], [p("flow.pieces"), <b key="p" className="text-success">{num(j.cutting.pieces)}</b>],
                ]}
                note={[...Object.entries(j.cutting.pieces_by_size).filter(([s]) => s).map(([s, n]) => `${s}: ${num(n)}`), ...(j.opening.CUTTING ? [`${p("flow.opening")}: ${num(j.opening.CUTTING)}`] : [])].join(" · ")}
              />
              <Arrow wait={j.sewing.waiting} p={p} />
              <Stage title={p("section.SEWING")} tone="border-brand" items={[[p("flow.ready"), num(j.sewing.waiting)], [p("col.checked"), num(j.sewing.checked)], [p("action.REJECT"), num(j.sewing.reject)], [p("col.pass"), <b key="p" className="text-success">{num(j.sewing.pass)}</b>]]} note={j.opening.SEWING ? `${p("flow.opening")}: ${num(j.opening.SEWING)}` : null} />
              <Arrow wait={j.quality.waiting} p={p} />
              <Stage title={p("section.QUALITY")} tone="border-success" items={[[p("flow.ready"), num(j.quality.waiting)], [p("col.checked"), num(j.quality.checked)], [p("action.REJECT"), num(j.quality.reject)], [p("col.pass"), <b key="p" className="text-success">{num(j.quality.pass)}</b>]]} note={j.opening.QUALITY ? `${p("flow.opening")}: ${num(j.opening.QUALITY)}` : null} />
            </div>
          </section>
        ))}
      </div>
    </>
  );
}

function Stage({ title, items, note, tone = "border-border-strong" }) {
  return (
    <div className={cn("rounded-md border border-t-4 border-border bg-surface px-4 py-3", tone)}>
      <p className="mb-2 text-[13px] font-semibold text-foreground">{title}</p>
      <dl className="flex flex-col gap-1 text-sm">
        {items.map(([k, v]) => (
          <div key={k} className="flex justify-between gap-3">
            <dt className="text-foreground-muted">{k}</dt>
            <dd className="font-medium tabular-nums">{v}</dd>
          </div>
        ))}
      </dl>
      {note ? <p className="mt-2 text-xs text-foreground-muted">{note}</p> : null}
    </div>
  );
}

function Arrow({ wait, p }) {
  return (
    <div className="flex flex-col items-center justify-center px-1 text-foreground-muted">
      <ArrowRight className="size-5 rotate-90 lg:rotate-0" aria-hidden />
      {wait ? <span className="mt-1 rounded-full bg-warning-subtle px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap text-warning">{p("flow.waiting", { n: num(wait) })}</span> : null}
    </div>
  );
}

export { JobFlow };
