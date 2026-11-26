"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ProtoList, ReadOnly, Section, blank, card, messagesOf, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Room Daily Target (AnnotechERP 07/03): the day
 * sheet — one per date, addressed by it — with the room's capacity and a
 * target per sample line, and the daily, weekly or monthly plan read against
 * the day sheets inside its period.
 */

const HEAD = ["target_date", "sample_room", "shift", "unit_company_id", "supervisor_id", "prepared_by", "state", "total_manpower", "available_manpower", "shift_hours", "remarks"];
const LINE = ["planning_line_id", "sample_id", "sample_type_id", "order_id", "target", "wh", "manpower", "target_hrs", "actual", "actual_hrs", "start_time", "end_time", "team", "priority", "remarks"];
const NUMBERS = ["target", "wh", "manpower", "target_hrs", "actual", "actual_hrs"];
const blankLine = () => Object.fromEntries(LINE.map((k) => [k, ""]));
const num = (v) => Number(v) || 0;
const pct = (v) => (v === null || v === undefined ? null : `${v}%`);

/** The grid's running totals, as Brickland shows them under the lines. */
function totalsOf(lines) {
  const wh = lines.filter((l) => l.wh !== "");
  return {
    target: lines.reduce((a, l) => a + num(l.target), 0),
    wh: wh.length ? wh.reduce((a, l) => a + num(l.wh), 0) / wh.length : 0,
    manpower: lines.reduce((a, l) => a + num(l.manpower), 0),
    target_hrs: lines.reduce((a, l) => a + num(l.target_hrs), 0),
    actual: lines.reduce((a, l) => a + num(l.actual), 0),
  };
}

function DaySheetForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(row?.[k])])),
    target_date: row?.target_date ?? todayIso(),
    state: row?.state ?? "DRAFT",
    shift_hours: row ? str(row.shift_hours) : "8",
    lines: row?.lines?.length ? row.lines.map((l) => Object.fromEntries(LINE.map((k) => [k, str(l[k])]))) : [blankLine()],
  });
  const payloadOf = (form) => ({ ...Object.fromEntries(HEAD.map((k) => [k, blank(form[k])])), lines: form.lines.map((l) => Object.fromEntries(LINE.map((k) => [k, blank(l[k])]))) });
  const { row, form, set, setForm, errors, pending, load, save } = useDocForm({ kind: "day-sheets", tab: "target", param: "date", noKey: "target_date", initial, formOf, payloadOf, actions, savedText: (date) => t("ui.dt.saved", { date }) });
  const locked = !canManage || Boolean(row?.locked);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const dash = (list) => [{ value: "", label: "—" }, ...list];
  const labelled = (list, ns) => list.map((v) => ({ value: v, label: t(`${ns}.${v}`) }));
  const setLine = (i, key) => (v) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, [key]: v ?? "" } : l)) }));
  const addLine = () => setForm((f) => ({ ...f, lines: [...f.lines, blankLine()] }));
  const dropLine = (i) => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }));
  const totals = totalsOf(form.lines);
  const capacity = num(form.available_manpower) * num(form.shift_hours);
  // One sheet per day: choosing a date that already has one loads it.
  const pickDate = (v) => (!row && existing.some((e) => e.value === v) ? load(v) : set("target_date")(v));
  const cell = "px-1.5 py-1.5";
  const numberCell = (i, key, step = "1") => (
    <td className={cn(cell, "w-24")}>
      <Input type="number" min={0} step={step} value={form.lines[i][key]} onChange={(e) => setLine(i, key)(e.target.value)} disabled={locked} aria-label={`${t(`ui.dt.lc.${key}`)} ${i + 1}`} />
    </td>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.dt.form")}
          hint={t("ui.dt.form_hint")}
          badge={row ? t(`day_state.${row.state}`) : t("ui.new")}
          existingLabel={t("ui.dt.existing")}
          newOption={t("ui.dt.new_option")}
          existing={existing}
          value={row?.target_date ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.dt.s.day")}>
          <Field label={t("ui.dt.f.target_date")} required hint={t("ui.dt.h.target_date")}>
            <Input type="date" value={form.target_date} onChange={(e) => pickDate(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.dt.f.sample_room")}>
            <Input value={form.sample_room} onChange={(e) => set("sample_room")(e.target.value)} placeholder={t("ui.dt.h.sample_room")} disabled={locked} maxLength={120} />
          </Field>
          <Field label={t("ui.dt.f.shift")}>
            <Select value={form.shift} onValueChange={set("shift")} options={opt(labelled(options.shifts, "shift"))} disabled={locked} aria-label={t("ui.dt.f.shift")} />
          </Field>
          <Field label={t("ui.dt.f.company")}>
            <Select value={form.unit_company_id} onValueChange={set("unit_company_id")} options={opt(options.companies)} disabled={locked} aria-label={t("ui.dt.f.company")} />
          </Field>
          <Field label={t("ui.dt.f.supervisor")}>
            <Select value={form.supervisor_id} onValueChange={set("supervisor_id")} options={opt(options.users)} disabled={locked} aria-label={t("ui.dt.f.supervisor")} />
          </Field>
          <Field label={t("ui.dt.f.prepared_by")}>
            <Select value={form.prepared_by} onValueChange={set("prepared_by")} options={opt(options.users)} disabled={locked} aria-label={t("ui.dt.f.prepared_by")} />
          </Field>
          <Field label={t("ui.dt.f.state")}>
            <Select value={form.state} onValueChange={set("state")} options={opt(labelled(options.states, "day_state"))} disabled={locked} aria-label={t("ui.dt.f.state")} />
          </Field>
        </Section>
        <Section title={t("ui.dt.s.capacity")}>
          <Field label={t("ui.dt.f.total_manpower")}>
            <Input type="number" min={0} value={form.total_manpower} onChange={(e) => set("total_manpower")(e.target.value)} placeholder="0" disabled={locked} />
          </Field>
          <Field label={t("ui.dt.f.available_manpower")} hint={t("ui.dt.h.available_manpower")}>
            <Input type="number" min={0} value={form.available_manpower} onChange={(e) => set("available_manpower")(e.target.value)} placeholder="0" disabled={locked} />
          </Field>
          <Field label={t("ui.dt.f.shift_hours")} hint={t("ui.dt.h.shift_hours")}>
            <Input type="number" min={0} step="0.5" value={form.shift_hours} onChange={(e) => set("shift_hours")(e.target.value)} placeholder="8" disabled={locked} />
          </Field>
        </Section>
        <Field label={t("ui.dt.f.remarks")} wide>
          <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
        </Field>
      </section>
      <section className={cn(card, "p-4")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-foreground">{t("ui.dt.lines")}</h3>
            <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.dt.lines_hint")}</p>
          </div>
          {!locked ? (
            <Button type="button" variant="outline" size="sm" onClick={addLine}>
              <Plus /> {t("ui.dt.add_line")}
            </Button>
          ) : null}
        </div>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[1900px] text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {LINE.map((k) => (
                  <th key={k} className={cn("px-1.5 py-2 font-medium", NUMBERS.includes(k) && "text-right")}>
                    {t(`ui.dt.lc.${k === "planning_line_id" ? "line" : k === "sample_id" ? "sample_no" : k === "sample_type_id" ? "sample_type" : k === "order_id" ? "order" : k}`)}
                  </th>
                ))}
                <th className="px-1.5 py-2" />
              </tr>
            </thead>
            <tbody>
              {form.lines.length === 0 ? (
                <tr>
                  <td colSpan={LINE.length + 1} className="px-2 py-6 text-center text-foreground-muted">
                    <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                    <p className="text-xs">{t("ui.dt.no_lines_hint")}</p>
                  </td>
                </tr>
              ) : (
                form.lines.map((l, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    <td className={cn(cell, "w-44")}>
                      <Select value={l.planning_line_id} onValueChange={setLine(i, "planning_line_id")} options={dash(options.lines)} placeholder="—" disabled={locked} aria-label={`${t("ui.dt.lc.line")} ${i + 1}`} />
                    </td>
                    <td className={cn(cell, "w-48")}>
                      <Select value={l.sample_id} onValueChange={setLine(i, "sample_id")} options={dash(options.samples)} placeholder="—" disabled={locked} aria-label={`${t("ui.dt.lc.sample_no")} ${i + 1}`} />
                    </td>
                    <td className={cn(cell, "w-44")}>
                      <Select value={l.sample_type_id} onValueChange={setLine(i, "sample_type_id")} options={dash(options.sample_types)} placeholder="—" disabled={locked} aria-label={`${t("ui.dt.lc.sample_type")} ${i + 1}`} />
                    </td>
                    <td className={cn(cell, "w-48")}>
                      <Select value={l.order_id} onValueChange={setLine(i, "order_id")} options={dash(options.orders)} placeholder="—" disabled={locked} aria-label={`${t("ui.dt.lc.order")} ${i + 1}`} />
                    </td>
                    {numberCell(i, "target")}
                    {numberCell(i, "wh", "0.5")}
                    {numberCell(i, "manpower")}
                    {numberCell(i, "target_hrs", "0.5")}
                    {numberCell(i, "actual")}
                    {numberCell(i, "actual_hrs", "0.5")}
                    {["start_time", "end_time"].map((k) => (
                      <td key={k} className={cn(cell, "w-28")}>
                        <Input type="time" value={l[k]} onChange={(e) => setLine(i, k)(e.target.value)} disabled={locked} aria-label={`${t(`ui.dt.lc.${k}`)} ${i + 1}`} />
                      </td>
                    ))}
                    <td className={cn(cell, "w-32")}>
                      <Input value={l.team} onChange={(e) => setLine(i, "team")(e.target.value)} disabled={locked} maxLength={120} aria-label={`${t("ui.dt.lc.team")} ${i + 1}`} />
                    </td>
                    <td className={cn(cell, "w-32")}>
                      <Select value={l.priority} onValueChange={setLine(i, "priority")} options={dash(labelled(options.priorities, "priority"))} placeholder="—" disabled={locked} aria-label={`${t("ui.dt.lc.priority")} ${i + 1}`} />
                    </td>
                    <td className={cn(cell, "w-40")}>
                      <Input value={l.remarks} onChange={(e) => setLine(i, "remarks")(e.target.value)} disabled={locked} maxLength={500} aria-label={`${t("ui.dt.lc.remarks")} ${i + 1}`} />
                    </td>
                    <td className={cell}>
                      {!locked ? (
                        <Button type="button" variant="ghost" size="sm" onClick={() => dropLine(i)} aria-label={`${t("ui.dt.remove_line")} ${i + 1}`}>
                          <X />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[13px] text-foreground-muted">
          {[
            ["target", totals.target],
            ["wh", totals.wh.toFixed(2)],
            ["manpower", totals.manpower],
            ["target_hrs", totals.target_hrs.toFixed(2)],
            ["actual", totals.actual],
            ["capacity", capacity.toFixed(2)],
          ].map(([k, v]) => (
            <span key={k} className="inline-flex items-center gap-1.5">
              {t(`ui.dt.tot.${k}`)}
              <span className={cn("rounded bg-surface-muted px-1.5 font-medium tabular-nums text-foreground", k === "target_hrs" && totals.target_hrs > capacity && "text-warning")}>{v}</span>
            </span>
          ))}
        </div>
      </section>
      <DocFoot note={t("ui.dt.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.dt.save")} pending={pending} />
    </div>
  );
}

const PLAN = ["period", "period_start", "period_end", "sample_room", "team", "supervisor_id", "sample_type_id", "target_qty", "available_manpower", "available_hours", "planned_hours", "remarks"];

function TargetPlanForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({ ...Object.fromEntries(PLAN.map((k) => [k, str(row?.[k])])), period: row?.period ?? "WEEKLY" });
  const payloadOf = (form) => Object.fromEntries(PLAN.map((k) => [k, blank(form[k])]));
  const { row, form, set, errors, pending, load, save } = useDocForm({ kind: "target-plans", tab: "plan", param: "plan", noKey: "plan_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.tp.saved", { no }) });
  const locked = !canManage;
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const numberField = (k, step = "1") => (
    <Field label={t(`ui.tp.f.${k}`)}>
      <Input type="number" min={0} step={step} value={form[k]} onChange={(e) => set(k)(e.target.value)} placeholder="0" disabled={locked} />
    </Field>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.tp.form")}
          hint={t("ui.tp.form_hint")}
          badge={row ? row.plan_no : t("ui.new")}
          existingLabel={t("ui.tp.existing")}
          newOption={t("ui.tp.new_option")}
          existing={existing}
          value={row?.plan_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.tp.s.period")}>
          <Field label={t("ui.tp.f.period")} required>
            <Select value={form.period} onValueChange={set("period")} options={opt(options.periods.map((v) => ({ value: v, label: t(`period.${v}`) })))} disabled={locked} aria-label={t("ui.tp.f.period")} />
          </Field>
          <Field label={t("ui.tp.f.period_start")} required>
            <Input type="date" value={form.period_start} onChange={(e) => set("period_start")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.tp.f.period_end")} required>
            <Input type="date" value={form.period_end} onChange={(e) => set("period_end")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.tp.f.sample_room")}>
            <Input value={form.sample_room} onChange={(e) => set("sample_room")(e.target.value)} disabled={locked} maxLength={120} />
          </Field>
          <Field label={t("ui.tp.f.team")}>
            <Input value={form.team} onChange={(e) => set("team")(e.target.value)} disabled={locked} maxLength={120} />
          </Field>
          <Field label={t("ui.tp.f.supervisor")}>
            <Select value={form.supervisor_id} onValueChange={set("supervisor_id")} options={opt(options.users)} disabled={locked} aria-label={t("ui.tp.f.supervisor")} />
          </Field>
          <Field label={t("ui.tp.f.sample_type")}>
            <Select value={form.sample_type_id} onValueChange={set("sample_type_id")} options={opt(options.sample_types)} disabled={locked} aria-label={t("ui.tp.f.sample_type")} />
          </Field>
        </Section>
        <Section title={t("ui.tp.s.target")}>
          {numberField("target_qty")}
          {numberField("available_manpower")}
          {numberField("available_hours", "0.5")}
          {numberField("planned_hours", "0.5")}
          <Field label={t("ui.tp.f.actual")}>
            <ReadOnly value={row?.actual} />
          </Field>
          <Field label={t("ui.tp.f.achievement")}>
            <ReadOnly value={pct(row?.achievement)} />
          </Field>
        </Section>
        <Field label={t("ui.tp.f.remarks")} wide>
          <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
        </Field>
      </section>
      <section className={cn(card, "p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.tp.sheets")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.tp.sheets_hint")}</p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["target_date", "sample_room", "shift", "lines", "total_target", "actual", "achievement", "status"].map((k) => (
                  <th key={k} className="px-2 py-2 font-medium">
                    {t(`ui.dt.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(row?.day_sheets ?? []).length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-2 py-6 text-center text-foreground-muted">
                    <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                    <p className="text-xs">{t("ui.no_lines_hint")}</p>
                  </td>
                </tr>
              ) : (
                row.day_sheets.map((d) => (
                  <tr key={d.target_date} className="border-b border-border last:border-0">
                    <td className="px-2 py-2">
                      <FormattedDateTime value={d.target_date} mode="date" />
                    </td>
                    <td className="px-2 py-2">{d.sample_room ?? "—"}</td>
                    <td className="px-2 py-2">{d.shift ? t(`shift.${d.shift}`) : "—"}</td>
                    <td className="px-2 py-2 tabular-nums">{d.lines_count}</td>
                    <td className="px-2 py-2 tabular-nums">{d.total_target}</td>
                    <td className="px-2 py-2 tabular-nums">{d.total_actual}</td>
                    <td className="px-2 py-2 tabular-nums">{pct(d.achievement) ?? "—"}</td>
                    <td className="px-2 py-2">{t(`day_state.${d.state}`)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
      <DocFoot note={t("ui.tp.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.tp.save")} pending={pending} />
    </div>
  );
}

/** Records: the day sheets, Brickland's pills (Avg. Achievement a figure), filters, Open and Remove. */
function DaySheetList({ rows, options, canManage, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const data = rows.map((r) => ({
    ...r,
    all: true,
    overdue_filter: r.overdue ? "OVERDUE" : "NOT_OVERDUE",
    shift_label: r.shift ? t(`shift.${r.shift}`) : null,
    achievement_label: pct(r.achievement),
    efficiency_label: pct(r.efficiency),
  }));
  const average = (list) => {
    const known = list.filter((r) => r.achievement !== null);
    return `${known.length ? Math.round((known.reduce((a, r) => a + r.achievement, 0) / known.length) * 10) / 10 : 0}%`;
  };
  function remove() {
    startTransition(async () => {
      const result = await actions.remove("day-sheets", confirm.target_date);
      if (result?.status !== "success") return toastManager.add({ title: messagesOf(result).join(" "), type: "danger" });
      toastManager.add({ title: t("ui.dt.removed", { date: confirm.target_date }), type: "success" });
      setConfirm(null);
      router.refresh();
    });
  }
  return (
    <>
      <ProtoList
        rows={data}
        rowKey="target_date"
        exportName="sample-room-day-sheets"
        title={t("ui.dt.list")}
        subtitle={t("ui.dt.list_hint")}
        emptyText={t("ui.dt.empty")}
        addHref={canManage ? "?tab=target" : undefined}
        addLabel={t("ui.dt.new")}
        openHref="?tab=target&date="
        filters={[
          { key: "state", label: t("ui.dt.f.state"), field: "state", options: options.states.map((v) => ({ value: v, label: t(`day_state.${v}`) })) },
          { key: "overdue", label: t("ui.dt.f.overdue"), field: "overdue_filter", options: [{ value: "OVERDUE", label: t("ui.dt.overdue_yes") }, { value: "NOT_OVERDUE", label: t("ui.dt.overdue_no") }] },
          { key: "room", label: t("ui.dt.f.sample_room"), field: "sample_room", options: options.rooms.map((v) => ({ value: v, label: v })) },
          { key: "date", label: t("ui.dt.f.target_date"), field: "target_date", range: true },
        ]}
        pills={[
          { key: "sheets", label: t("ui.dt.pill.sheets"), field: "all", value: true },
          { key: "overdue", label: t("ui.dt.pill.overdue"), field: "overdue", value: true },
          { key: "over", label: t("ui.dt.pill.over_capacity"), field: "over_capacity", value: true },
          { key: "avg", label: t("ui.dt.pill.avg_achievement"), stat: average },
        ]}
        columns={[
          { key: "target_date", label: t("ui.dt.col.target_date"), type: "date" },
          { key: "sample_room", label: t("ui.dt.col.sample_room") },
          { key: "shift_label", label: t("ui.dt.col.shift") },
          { key: "company", label: t("ui.dt.col.company") },
          { key: "lines_count", label: t("ui.dt.col.lines"), type: "number" },
          { key: "total_target", label: t("ui.dt.col.total_target"), type: "number" },
          { key: "total_actual", label: t("ui.dt.col.actual"), type: "number" },
          { key: "achievement_label", label: t("ui.dt.col.achievement"), align: "right" },
          { key: "average_wh", label: t("ui.dt.col.average_wh"), type: "number" },
          { key: "planned_hrs", label: t("ui.dt.col.planned_hrs"), type: "number" },
          { key: "capacity_hrs", label: t("ui.dt.col.capacity_hrs"), type: "number" },
          { key: "efficiency_label", label: t("ui.dt.col.efficiency"), align: "right" },
          { key: "prepared_by_name", label: t("ui.dt.col.prepared_by") },
          { key: "remarks", label: t("ui.dt.col.remarks") },
          { key: "state", label: t("ui.dt.col.status"), type: "badge", labels: Object.fromEntries(options.states.map((v) => [v, t(`day_state.${v}`)])) },
        ]}
        rowActions={canManage ? [{ key: "remove", label: t("ui.dt.remove"), icon: <Trash2 />, onClick: (row) => setConfirm(row), disabled: (row) => row.locked }] : []}
      />
      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={t("ui.dt.remove_title")}
        description={confirm ? t("ui.dt.remove_hint", { date: confirm.target_date }) : ""}
        confirmLabel={t("ui.dt.remove")}
        cancelLabel={t("ui.dt.keep")}
        destructive
        loading={pending}
        onConfirm={remove}
      />
    </>
  );
}

export { DaySheetForm, DaySheetList, TargetPlanForm };
