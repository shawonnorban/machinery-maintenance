"use client";

import { useEffect, useMemo, useState } from "react";
import { Plus, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { useQt, useRun, num, day, Select, Notice } from "./common";

/*
 * Quality Tally → Line Target → Plan the day (AnnotechERP 36, phase A):
 * every line of a section on one sheet — job, hours, manpower, target —
 * saved at once. Beside each line, what it fell short by on its last
 * scheduled day: shown, never added by itself; "+" adds it when the planner
 * agrees (annotech-rmg's shortfall rule).
 */

function PlanDay({ date, section, rows, lookups, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [shortfall, setShortfall] = useState(null);
  const lines = useMemo(() => lookups.lines[section] ?? [], [lookups.lines, section]);
  // A job this section may not have yet is not offered (user, 2026-11-28); one already planned stays.
  const planned = new Set(rows.filter((r) => r.section === section).map((r) => r.job_id));
  const jobs = lookups.jobs.filter((j) => !j.blocked?.[section] || planned.has(j.value));

  const initial = useMemo(() => {
    const mine = rows.filter((r) => r.section === section);
    const out = mine.map((r) => ({ key: r.id, line_id: r.line_id, job_id: r.job_id, working_hours: r.working_hours, manpower: r.manpower ?? "", target_qty: r.target_qty }));
    for (const l of lines) if (!mine.some((r) => r.line_id === l.value)) out.push({ key: `new-${l.value}`, line_id: l.value, job_id: "", working_hours: 8, manpower: "", target_qty: "" });
    const order = Object.fromEntries(lines.map((l, i) => [l.value, i]));
    return out.sort((a, b) => (order[a.line_id] ?? 99) - (order[b.line_id] ?? 99));
  }, [rows, section, lines]);
  const [grid, setGrid] = useState(initial);

  useEffect(() => {
    let live = true;
    act("shortfall", date, section).then((res) => live && setShortfall(res?.status === "success" ? res.data : []));
    return () => {
      live = false;
    };
  }, [act, date, section]);

  const set = (key, field) => (val) => setGrid((all) => all.map((r) => (r.key === key ? { ...r, [field]: val } : r)));
  const addRow = (lineId) => setGrid((all) => {
    const at = all.findLastIndex((r) => r.line_id === lineId);
    const next = [...all];
    next.splice(at + 1, 0, { key: `new-${lineId}-${Date.now()}`, line_id: lineId, job_id: "", working_hours: 8, manpower: "", target_qty: "" });
    return next;
  });
  const lineLabel = (id) => lines.find((l) => l.value === id)?.label ?? "—";
  const jobLabel = (id) => jobs.find((j) => j.value === id)?.job_no ?? id;
  const shortOf = (r) => (shortfall ?? []).filter((s) => s.line_id === r.line_id && (r.job_id === "" || s.job_id === r.job_id));
  const addShortfall = (r, s) => setGrid((all) => all.map((x) => (x.key === r.key ? { ...x, job_id: x.job_id || s.job_id, target_qty: (Number(x.target_qty) || 0) + s.shortfall } : x)));
  const filled = grid.filter((r) => r.job_id && Number(r.target_qty) > 0);
  const total = filled.reduce((a, r) => a + Number(r.target_qty), 0);

  const save = () =>
    run(
      () =>
        act("bulkTargets", {
          target_date: date,
          section,
          rows: grid
            .filter((r) => r.job_id)
            .map((r) => ({ line_id: r.line_id, job_id: r.job_id, working_hours: Number(r.working_hours) || 8, manpower: r.manpower === "" ? null : Number(r.manpower), target_qty: r.target_qty === "" ? 0 : Number(r.target_qty) })),
        }),
      (d) => p("toast.plan_saved", { n: d.saved }),
      (res) => res.status === "success" && onClose(),
    );

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p("plan.title", { date: day(date) })}
      className="max-w-6xl"
      footer={
        <>
          <span className="mr-auto text-sm text-foreground-muted">{p("plan.total", { lines: filled.length, n: num(total) })}</span>
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={pending} onClick={save}>
            <Save /> {p("btn.save_all")}
          </Button>
        </>
      }
    >
      <div className="mb-3 flex flex-wrap items-end gap-3">
        <p className="text-xs text-foreground-muted">{p("plan.hint")}</p>
      </div>
      {lines.length === 0 ? (
        <Notice>{p("f.no_lines", { section: p(`section.${section}`) })}</Notice>
      ) : (
        <div className="max-h-[60vh] overflow-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead className="sticky top-0 z-10">
              <tr className="bg-brand text-left text-brand-foreground">
                <th className="px-3 py-2 text-[13px] font-semibold">{p("col.line")}</th>
                <th className="px-3 py-2 text-[13px] font-semibold">{p("col.job")}</th>
                <th className="px-3 py-2 text-right text-[13px] font-semibold">{p("col.hours")}</th>
                <th className="px-3 py-2 text-right text-[13px] font-semibold">{p("col.manpower")}</th>
                <th className="px-3 py-2 text-right text-[13px] font-semibold">{p("col.target")}</th>
                <th className="px-3 py-2 text-right text-[13px] font-semibold">{p("col.hourly_target")}</th>
                <th className="px-3 py-2 text-[13px] font-semibold">{p("plan.shortfall")}</th>
              </tr>
            </thead>
            <tbody>
              {grid.map((r, i) => {
                const firstOfLine = i === 0 || grid[i - 1].line_id !== r.line_id;
                const hourly = Number(r.target_qty) > 0 && Number(r.working_hours) > 0 ? Math.round(Number(r.target_qty) / Number(r.working_hours)) : null;
                return (
                  <tr key={r.key} className="border-b border-border last:border-0">
                    <td className="px-3 py-1.5 align-middle">
                      {firstOfLine ? (
                        <span className="flex items-center gap-2">
                          <b>{lineLabel(r.line_id)}</b>
                          <button type="button" onClick={() => addRow(r.line_id)} className="rounded p-0.5 text-foreground-muted hover:bg-surface-muted hover:text-foreground" title={p("plan.add_job")} aria-label={p("plan.add_job")}>
                            <Plus className="size-3.5" />
                          </button>
                        </span>
                      ) : null}
                    </td>
                    <td className="px-3 py-1.5">
                      <Select value={r.job_id} onChange={set(r.key, "job_id")} options={jobs} placeholder={p("plan.no_job")} className="h-8 w-72" />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0.5" max="24" step="0.5" value={r.working_hours} onChange={(e) => set(r.key, "working_hours")(e.target.value)} className="ml-auto h-8 w-20 text-right" />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="1" value={r.manpower} onChange={(e) => set(r.key, "manpower")(e.target.value)} className="ml-auto h-8 w-20 text-right" />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" value={r.target_qty} onChange={(e) => set(r.key, "target_qty")(e.target.value)} className="ml-auto h-8 w-24 text-right" />
                    </td>
                    <td className="px-3 py-1.5 text-right text-foreground-muted tabular-nums">{num(hourly)}</td>
                    <td className="px-3 py-1.5">
                      {shortfall === null ? (
                        <span className="text-xs text-foreground-subtle">…</span>
                      ) : (
                        shortOf(r).map((s) => (
                          <button
                            key={`${s.job_id}${s.day}`}
                            type="button"
                            onClick={() => addShortfall(r, s)}
                            className="mr-1 inline-flex items-center gap-1 rounded-full bg-warning-subtle px-2 py-0.5 text-xs font-semibold text-warning hover:bg-warning/20"
                            title={p("plan.shortfall_hint", { day: day(s.day), target: num(s.target), pass: num(s.pass) })}
                          >
                            <Plus className="size-3" /> {num(s.shortfall)} · {jobLabel(s.job_id)} · {day(s.day)}
                          </button>
                        ))
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </Modal>
  );
}

export { PlanDay };
