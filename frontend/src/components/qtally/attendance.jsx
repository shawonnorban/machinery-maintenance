"use client";

import { useState } from "react";
import { Save, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SECTIONS, useQt, useRun, useFilters, num, day, Panel, Field, Select, FilterCard, Notice } from "./common";

/*
 * Quality Tally → Planning → Line Attendance (AnnotechERP 36, phase A): how
 * many people each line had on a day — per HR shift when the company runs
 * shifts — and its overtime minutes. Efficiency divides by this:
 * Σ attendance × (shift minutes + overtime); without it, the target's
 * manpower × hours. Every line of the section on one sheet, saved at once.
 */

function LineAttendance({ data, query, canManage, act }) {
  const p = useQt();
  const { apply, pending: filtering } = useFilters(query);
  const { run, pending } = useRun();
  const [date, setDate] = useState(query.date ?? data.date);
  const shifts = data.shifts.length ? data.shifts : [{ id: "", label: p("att.whole_day"), time: "", minutes: null }];
  const [values, setValues] = useState(() => {
    const v = {};
    for (const l of data.lines) for (const sh of shifts) v[`${l.line_id}|${sh.id}`] = { attendance: l.entries[sh.id]?.attendance ?? "", overtime_minutes: l.entries[sh.id]?.overtime_minutes ?? "" };
    return v;
  });
  const set = (key, field) => (e) => setValues((all) => ({ ...all, [key]: { ...all[key], [field]: e.target.value } }));
  const total = (field, shiftId) => data.lines.reduce((a, l) => a + (Number(values[`${l.line_id}|${shiftId}`]?.[field]) || 0), 0);
  // A line with a target but nobody recorded reads efficiency from the target's manpower — say so.
  const missing = data.lines.filter((l) => l.has_target && shifts.every((sh) => !Number(values[`${l.line_id}|${sh.id}`]?.attendance))).length;

  const save = () =>
    run(
      () =>
        act("saveAttendance", {
          date: data.date,
          section: data.section,
          rows: data.lines.flatMap((l) =>
            shifts.map((sh) => {
              const v = values[`${l.line_id}|${sh.id}`];
              return { line_id: l.line_id, shift_id: sh.id, attendance: v.attendance === "" ? null : Number(v.attendance), overtime_minutes: v.overtime_minutes === "" ? null : Number(v.overtime_minutes) };
            }),
          ),
        }),
      p("toast.attendance_saved"),
    );

  return (
    <>
      <FilterCard onApply={() => apply({ date })} pending={filtering} applyLabel={p("apply")}>
        <Field label={p("f.date")}>
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
        </Field>
      </FilterCard>
      {missing ? <Notice className="mb-4">{p("att.missing", { n: missing })}</Notice> : null}
      <Panel
        title={p("att.title", { section: p(`section.${data.section}`), date: day(data.date) })}
        subtitle={p("att.hint")}
        icon={Users}
        action={
          canManage ? (
            <Button loading={pending} onClick={save}>
              <Save /> {p("btn.save_all")}
            </Button>
          ) : null
        }
      >
        {data.lines.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-foreground-muted">{p("f.no_lines", { section: p(`section.${data.section}`) })}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th rowSpan={2} className="px-4 py-2 text-[13px] font-semibold">{p("col.line")}</th>
                  <th rowSpan={2} className="px-4 py-2 text-right text-[13px] font-semibold">{p("att.target_manpower")}</th>
                  {shifts.map((sh) => (
                    <th key={sh.id} colSpan={2} className="border-l border-white/20 px-4 py-2 text-center text-[13px] font-semibold">
                      {sh.label}
                      {sh.time ? <span className="block text-[11px] font-normal opacity-80">{sh.time} · {p("att.minutes", { n: sh.minutes })}</span> : null}
                    </th>
                  ))}
                </tr>
                <tr className="bg-brand/90 text-brand-foreground">
                  {shifts.flatMap((sh) => [
                    <th key={`${sh.id}a`} className="border-l border-white/20 px-3 py-1.5 text-right text-xs font-semibold">{p("att.people")}</th>,
                    <th key={`${sh.id}o`} className="px-3 py-1.5 text-right text-xs font-semibold">{p("att.ot_minutes")}</th>,
                  ])}
                </tr>
              </thead>
              <tbody>
                {data.lines.map((l) => (
                  <tr key={l.line_id} className="border-b border-border last:border-0 hover:bg-surface-muted/50">
                    <td className="px-4 py-2">
                      <b>{l.line}</b>
                      {l.has_target ? <span className="ml-2 rounded-full bg-info-subtle px-2 py-0.5 text-[11px] font-semibold text-info">{p("att.has_target")}</span> : null}
                    </td>
                    <td className="px-4 py-2 text-right text-foreground-muted tabular-nums">{num(l.target_manpower)}</td>
                    {shifts.flatMap((sh) => {
                      const key = `${l.line_id}|${sh.id}`;
                      return [
                        <td key={`${key}a`} className="border-l border-border px-2 py-1.5">
                          <Input type="number" min="0" value={values[key].attendance} onChange={set(key, "attendance")} disabled={!canManage} className="ml-auto h-8 w-20 text-right" aria-label={`${l.line} ${sh.label} ${p("att.people")}`} />
                        </td>,
                        <td key={`${key}o`} className="px-2 py-1.5">
                          <Input type="number" min="0" max="720" value={values[key].overtime_minutes} onChange={set(key, "overtime_minutes")} disabled={!canManage} className="ml-auto h-8 w-20 text-right" aria-label={`${l.line} ${sh.label} ${p("att.ot_minutes")}`} />
                        </td>,
                      ];
                    })}
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t-2 border-border-strong bg-surface-muted font-semibold">
                  <td className="px-4 py-2">{p("total")}</td>
                  <td />
                  {shifts.flatMap((sh) => [
                    <td key={`${sh.id}ta`} className="border-l border-border px-4 py-2 text-right tabular-nums">{num(total("attendance", sh.id))}</td>,
                    <td key={`${sh.id}to`} className="px-4 py-2 text-right tabular-nums">{num(total("overtime_minutes", sh.id))}</td>,
                  ])}
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </Panel>
    </>
  );
}

export { LineAttendance };
