"use client";

import { useState } from "react";
import { CheckCheck, ListChecks } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useQt, useFilters, useRun, num, day, stamp, Panel, Table, Field, Select, Kpi, Pill, ActionPill, FilterCard } from "./common";

/*
 * Quality Tally → {Section} → Review (AnnotechERP 37, phase 3): what the
 * manager must look at — taps the server REFUSED for good, with the reason,
 * and taps made with no network that were KEPT with a Check mark (no target,
 * nothing ready then). The section's manager marks each seen; the same list
 * is on the manager's phone.
 */

function TallyReview({ data, query, canManage, act }) {
  const p = useQt();
  const { apply, pending } = useFilters(query);
  const { run, pending: saving } = useRun();
  const [v, setV] = useState({ from: query.from ?? data.from, to: query.to ?? data.to, status: query.status ?? data.status });
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const open = data.rows.filter((r) => !r.seen_at);
  const mark = (rows) => run(() => act("reviewSeen", { section: data.section, items: rows.map((r) => ({ kind: r.kind, id: r.id })) }), (d) => p("rv.marked", { n: d.seen }));

  const cols = [
    { key: "arrived", label: p("rv.when"), render: (r) => (<><b className="tabular-nums">{stamp(r.occurred_at)}</b><div className="text-xs text-foreground-muted">{p("rv.arrived", { at: stamp(r.arrived_at) })}</div></>) },
    { key: "kind", label: p("rv.kind"), render: (r) => <Pill tone={r.kind === "REFUSED" ? "bg-danger-subtle text-danger" : "bg-warning-subtle text-warning"}>{p(`rv.kind_${r.kind}`)}</Pill> },
    { key: "line", label: p("col.line") },
    { key: "job", label: p("col.job"), render: (r) => (<><b>{r.job_no ?? "—"}</b><div className="text-xs text-foreground-muted">{r.style}</div></>) },
    { key: "action", label: p("col.action"), render: (r) => (r.action ? <ActionPill action={r.action} /> : "—") },
    { key: "qc", label: p("col.qc"), render: (r) => (<>{r.qc ?? "—"}{r.phone ? <div className="text-xs text-foreground-muted">{r.phone}</div> : null}</>) },
    { key: "reason", label: p("rv.reason"), render: (r) => <span className="text-[13px]">{r.reason}</span> },
    {
      key: "seen",
      label: "",
      render: (r) =>
        r.seen_at ? (
          <span className="block max-w-40 text-xs whitespace-normal text-foreground-muted">{p("rv.seen_by", { name: r.seen_by ?? "—", at: stamp(r.seen_at) })}</span>
        ) : canManage ? (
          <Button size="sm" variant="outline" disabled={saving} onClick={() => mark([r])}>
            <CheckCheck /> {p("rv.seen")}
          </Button>
        ) : null,
    },
  ];

  return (
    <>
      <FilterCard onApply={() => apply(v)} pending={pending} applyLabel={p("apply")}>
        <Field label={p("f.from")}>
          <Input type="date" value={v.from} onChange={(e) => set("from")(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("f.to")}>
          <Input type="date" value={v.to} onChange={(e) => set("to")(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("rv.show")}>
          <Select value={v.status} onChange={set("status")} options={[{ value: "open", label: p("rv.open") }, { value: "all", label: p("rv.all") }]} />
        </Field>
      </FilterCard>
      <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <Kpi label={p("rv.open_refused")} value={num(data.open.refused)} tone="danger" />
        <Kpi label={p("rv.open_kept")} value={num(data.open.kept)} tone="warning" />
      </div>
      <Panel
        title={p("rv.title")}
        subtitle={p("rv.count", { n: num(data.rows.length), from: day(data.from), to: day(data.to) })}
        icon={ListChecks}
        action={
          canManage && open.length > 1 ? (
            <Button size="sm" variant="outline" disabled={saving} onClick={() => mark(open)}>
              <CheckCheck /> {p("rv.seen_all", { n: open.length })}
            </Button>
          ) : null
        }
      >
        <Table columns={cols} rows={data.rows} rowKey={(r) => `${r.kind}:${r.id}`} empty={p("rv.empty")} dense />
      </Panel>
    </>
  );
}

export { TallyReview };
