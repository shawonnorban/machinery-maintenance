"use client";

import { useState } from "react";
import { ChevronLeft, ChevronRight, History } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ACTIONS, useQt, useFilters, num, day, Panel, Table, Field, Select, Pill, ActionPill, FilterCard } from "./common";
import { clock12 } from "@/lib/clock";

/*
 * Quality Tally → Reports → Tally History (AnnotechERP 36): every tap — when,
 * where, which job, which action and defects, and who — newest first. A tap
 * synced from a tablet's offline queue is marked "synced late".
 */

function TallyHistory({ data, lookups, query, section }) {
  const p = useQt();
  const { apply, pending } = useFilters(query);
  const [v, setV] = useState({
    from: query.from ?? data.from, to: query.to ?? data.to, line_id: query.line_id ?? "", job_id: query.job_id ?? "", action: query.action ?? "", user_id: query.user_id ?? "",
  });
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const lines = lookups.lines[section] ?? [];
  const go = (page) => apply({ ...v, page: page > 1 ? String(page) : "" });

  const cols = [
    { key: "time", label: p("col.time"), render: (r) => (<><b className="tabular-nums">{clock12(r.time)}</b><div className="text-xs text-foreground-muted">{day(r.production_date)}</div></>) },
    { key: "line", label: p("col.line") },
    { key: "job", label: p("col.job"), render: (r) => (<><b>{r.job_no}</b><div className="text-xs text-foreground-muted">{r.style}</div></>) },
    { key: "action", label: p("col.action"), render: (r) => <ActionPill action={r.action} /> },
    { key: "defects", label: p("col.defects"), render: (r) => (r.defects.length ? r.defects.join(", ") : "—") },
    { key: "user", label: p("col.qc") },
    {
      key: "synced_late",
      label: "",
      // A tap made with no network that arrived after its target or input was missing: kept, for the manager to check.
      render: (r) => (r.review ? <Pill tone="bg-danger-subtle text-danger" title={p(`phone.review_${r.review}`)}>{p("history.review")}</Pill> : r.synced_late ? <Pill tone="bg-warning-subtle text-warning">{p("history.late")}</Pill> : null),
    },
  ];

  return (
    <>
      <FilterCard onApply={() => apply({ ...v, page: "" })} pending={pending} applyLabel={p("apply")}>
        <Field label={p("f.from")}>
          <Input type="date" value={v.from} onChange={(e) => set("from")(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("f.to")}>
          <Input type="date" value={v.to} onChange={(e) => set("to")(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("f.line")}>
          <Select value={v.line_id} onChange={set("line_id")} options={lines} placeholder={p("all")} />
        </Field>
        <Field label={p("f.job")}>
          <Select value={v.job_id} onChange={set("job_id")} options={lookups.all_jobs} placeholder={p("all")} />
        </Field>
        <Field label={p("f.action")}>
          <Select value={v.action} onChange={set("action")} options={ACTIONS.map((a) => ({ value: a, label: p(`action.${a}`) }))} placeholder={p("all")} />
        </Field>
        <Field label={p("f.qc")}>
          <Select value={v.user_id} onChange={set("user_id")} options={data.users} placeholder={p("all")} />
        </Field>
      </FilterCard>
      <Panel
        title={p("history.title")}
        subtitle={p("history.count", { n: num(data.total), from: day(data.from), to: day(data.to) })}
        icon={History}
        action={
          data.last_page > 1 ? (
            <div className="flex items-center gap-2 text-sm">
              <Button size="sm" variant="outline" disabled={data.page <= 1 || pending} onClick={() => go(data.page - 1)} aria-label={p("btn.prev")}>
                <ChevronLeft />
              </Button>
              <span className="tabular-nums">{p("history.page", { n: data.page, of: data.last_page })}</span>
              <Button size="sm" variant="outline" disabled={data.page >= data.last_page || pending} onClick={() => go(data.page + 1)} aria-label={p("btn.next")}>
                <ChevronRight />
              </Button>
            </div>
          ) : null
        }
      >
        <Table columns={cols} rows={data.rows} empty={p("empty.no_taps")} dense />
      </Panel>
    </>
  );
}

export { TallyHistory };
