"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  BarChart3,
  CalendarCheck,
  CheckCheck,
  ClipboardList,
  Info,
  LayoutDashboard,
  RotateCcw,
  Save,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import {
  card,
  dmy,
  num,
  weekday,
  Pill,
  Section,
  ForecastTiles,
  firstError,
  capacity,
  keepUrl,
} from "./plan-common";

/*
 * Capacity Planning → Daily Plan (AnnotechERP 12/02), as Brickland lays it
 * out: a date and an order with a locked master plan; the day's lines with
 * the line master's resources (an HR shift sets the hours), capacity worked
 * out as they change, the master target beside the day's target, and the
 * output once the day has come; Add line, reset and remove; Save Daily Plan
 * and Confirm Day; the order's current forecast; and every order planned on
 * the day. The date and order stay in the address; nothing reloads.
 */

const WEEKDAY = {
  Su: "Sun",
  Mo: "Mon",
  Tu: "Tue",
  We: "Wed",
  Th: "Thu",
  Fr: "Fri",
  Sa: "Sat",
};

function usePlanT() {
  const t = useT("planning");
  return (k, x) => t(`plan.${k}`, x);
}

/** A row worked out again after its resources change; until it is typed, the target is the master target the capacity can make. */
function rework(row, smv, patch) {
  const next = { ...row, ...patch };
  const [hourly, daily] = capacity(
    next.manpower,
    next.hours,
    next.efficiency,
    smv,
  );
  return {
    ...next,
    hourly_capacity: hourly,
    daily_capacity: daily,
    daily_target: next.touched
      ? next.daily_target
      : next.master_target
        ? Math.min(next.master_target, daily)
        : daily,
  };
}

const total = (rows, k) => rows.reduce((s, r) => s + (Number(r[k]) || 0), 0);

function NumberCell({ value, onChange, label, step = 1, disabled, wide }) {
  return (
    <Input
      type="number"
      inputMode="decimal"
      min="0"
      step={step}
      value={value ?? ""}
      onChange={(ev) => onChange(ev.target.value)}
      disabled={disabled}
      aria-label={label}
      className={cn("h-8 px-2 text-right tabular-nums", wide ? "w-24" : "w-18")}
    />
  );
}

/** @param {{ initial: { date: string, entry: object|null, dashboard: object[] }, orders: object[], perms: object, act: Function }} props */
function DailyPlan({ initial, orders, perms, act }) {
  const p = usePlanT();
  const toastManager = useToastManager();
  const [date, setDate] = useState(initial.date);
  const [orderNo, setOrderNo] = useState(initial.entry?.order_no ?? "");
  const [entry, setEntry] = useState(initial.entry);
  const [rows, setRows] = useState(initial.entry?.rows ?? []);
  const [dashboard, setDashboard] = useState(initial.dashboard);
  const [dirty, setDirty] = useState(false);
  const [pending, startTransition] = useTransition();

  const take = (data) => {
    setEntry(data.entry);
    setRows(data.entry?.rows ?? []);
    setDashboard(data.dashboard);
    setDirty(false);
  };
  const load = (d, no) =>
    startTransition(async () => {
      setDate(d);
      setOrderNo(no);
      keepUrl(no ? { date: d, order: no } : { date: d });
      const res = await act("day", { date: d, order: no });
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      take(res.data);
    });
  const edit = (i, patch, resources = true) => {
    setRows((rs) =>
      rs.map((r, j) =>
        j !== i
          ? r
          : resources
            ? rework(r, entry.smv, patch)
            : { ...r, ...patch },
      ),
    );
    setDirty(true);
  };
  const shiftOf = (id) => entry.shifts.find((s) => s.value === id);
  const reset = (i) =>
    edit(i, {
      ...rows[i].master,
      shift_id: null,
      touched: false,
    });
  const remove = (i) => {
    setRows((rs) => rs.filter((_, j) => j !== i));
    setDirty(true);
  };
  const addLine = (id) => {
    const line = entry.add_lines.find((l) => l.value === id);
    if (!line) return;
    setRows((rs) => [...rs, line.row]);
    setDirty(true);
  };
  const save = () =>
    startTransition(async () => {
      const res = await act("saveDay", {
        date,
        order_no: orderNo,
        rows: rows.map((r) => ({
          line_id: r.line_id,
          shift_id: r.shift_id || null,
          manpower: r.manpower,
          machines: r.machines,
          hours: r.hours,
          efficiency: r.efficiency,
          daily_target: r.daily_target,
          typed_output:
            r.typed_output === "" || r.typed_output === null
              ? null
              : r.typed_output,
          remarks: r.remarks || null,
        })),
      });
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      take(res.data);
      toastManager.add({
        title: p("daily.toast.saved", {
          date,
          lines: res.data.entry.rows.length,
          qty: num(total(res.data.entry.rows, "daily_target")),
        }),
        type: "success",
      });
    });
  const confirmDay = () =>
    startTransition(async () => {
      const res = await act("confirmDay", { date, order_no: orderNo });
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      take(res.data);
      toastManager.add({
        title: p("daily.toast.confirmed", { date: dmy(date) }),
        type: "success",
      });
    });

  const added = new Set(rows.map((r) => r.line_id));
  const addable = (entry?.add_lines ?? []).filter((l) => !added.has(l.value));
  const g = (k) => p(`daily.g.${k}`);
  const head = [
    "line",
    "shift",
    "manpower",
    "machine",
    "hours",
    "efficiency",
    "smv",
    "hourly",
    "daily_cap",
    "master_target",
    "daily_target",
    "actual",
    "status",
  ];
  const right = new Set([
    "manpower",
    "machine",
    "hours",
    "efficiency",
    "smv",
    "hourly",
    "daily_cap",
    "master_target",
    "daily_target",
  ]);
  const canEdit = perms.manage;

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section
        className={cn(
          card,
          "grid gap-4 p-5 md:grid-cols-[13rem_1fr] md:items-start",
        )}
      >
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          <span>
            {p("daily.date")}
            <span className="text-danger"> *</span>
          </span>
          <Input
            type="date"
            value={date}
            onChange={(ev) => ev.target.value && load(ev.target.value, orderNo)}
            aria-label={p("daily.date")}
          />
        </label>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("daily.order")}
          <Select
            value={orderNo}
            onValueChange={(no) => no && load(date, no)}
            options={orders}
            placeholder={p("daily.order_ph")}
            aria-label={p("daily.order")}
          />
          <span className="text-xs font-normal text-foreground-muted">
            {p("daily.order_hint")}
          </span>
        </label>
      </section>

      {!entry ? (
        <section
          className={cn(
            card,
            "px-5 py-10 text-center text-sm text-foreground-muted",
          )}
        >
          {p("daily.pick")}
        </section>
      ) : (
        <>
          <section className={cn(card, "min-w-0 overflow-hidden")}>
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
              <div>
                <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
                  <CalendarCheck className="size-4" aria-hidden />
                  {p("daily.create_title", { date: dmy(date) })}
                  <span className="text-[13px] font-normal text-foreground-muted">
                    {WEEKDAY[weekday(date)]}
                  </span>
                </h2>
                <p className="mt-0.5 text-[13px] text-foreground-muted">
                  {p("daily.header", {
                    order: entry.order_no,
                    buyer: entry.buyer ?? "—",
                    qty: num(entry.qty),
                    smv: Number(entry.smv).toFixed(2),
                    plan: entry.plan_no,
                    v: entry.version,
                    from: dmy(entry.sewing_start),
                    to: dmy(entry.sewing_end),
                  })}
                </p>
              </div>
              <Link
                href={`/capacity-planning/master-plan?plan=${encodeURIComponent(entry.plan_no)}&v=${entry.version}`}
                className={buttonVariants({ size: "sm", variant: "outline" })}
              >
                <ClipboardList className="size-4" aria-hidden />{" "}
                {p("daily.master_plan")}
              </Link>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1250px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {head.map((k) => (
                      <th
                        key={k}
                        className={cn(
                          "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                          right.has(k) && "text-right",
                        )}
                      >
                        {g(k)}
                      </th>
                    ))}
                    <th className="px-3 py-2.5" aria-hidden />
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, i) => {
                    const locked = r.status === "CONFIRMED" || !canEdit;
                    const diff = Number(r.daily_target) - r.master_target;
                    return (
                      <tr key={r.line_id} className="border-t border-border">
                        <td className="px-3 py-2">
                          <p className="font-semibold text-foreground">
                            {r.code}
                          </p>
                          <p className="text-xs text-foreground-muted">
                            {r.floor ?? "—"}
                          </p>
                        </td>
                        <td className="px-3 py-2">
                          <select
                            value={r.shift_id ?? ""}
                            disabled={locked}
                            onChange={(ev) => {
                              const s = shiftOf(ev.target.value);
                              edit(
                                i,
                                s
                                  ? { shift_id: s.value, hours: s.hours }
                                  : { shift_id: null },
                              );
                            }}
                            aria-label={`${r.code} ${g("shift").toLowerCase()}`}
                            className="h-8 w-24 rounded-lg border border-border-strong bg-surface px-2 text-xs disabled:opacity-60"
                          >
                            <option value="">—</option>
                            {entry.shifts.map((s) => (
                              <option key={s.value} value={s.value}>
                                {s.label}
                              </option>
                            ))}
                          </select>
                        </td>
                        {[
                          ["manpower", "manpower", 1],
                          ["machines", "machine", 1],
                          ["hours", "hours", 0.5],
                          ["efficiency", "efficiency", 1],
                        ].map(([k, label, step]) => (
                          <td key={k} className="px-3 py-2 text-right">
                            <NumberCell
                              value={r[k]}
                              step={step}
                              disabled={locked}
                              label={`${r.code} ${g(label).toLowerCase()}`}
                              onChange={(v) =>
                                edit(i, { [k]: v }, k !== "machines")
                              }
                            />
                          </td>
                        ))}
                        <td className="px-3 py-2 text-right tabular-nums">
                          {Number(r.smv).toFixed(2)}
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums">
                          {num(r.hourly_capacity)}
                        </td>
                        <td className="px-3 py-2 text-right font-semibold tabular-nums">
                          {num(r.daily_capacity)}
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums">
                          {num(r.master_target)}
                        </td>
                        <td className="px-3 py-2 text-right">
                          <NumberCell
                            wide
                            value={r.daily_target}
                            disabled={locked}
                            label={`${r.code} ${g("daily_target").toLowerCase()}`}
                            onChange={(v) =>
                              edit(i, { daily_target: v, touched: true }, false)
                            }
                          />
                          {r.master_target && diff ? (
                            <p
                              className={cn(
                                "mt-0.5 text-[11px]",
                                diff < 0 ? "text-danger" : "text-success",
                              )}
                            >
                              {p("daily.vs_master", {
                                n: `${diff > 0 ? "+" : ""}${num(diff)}`,
                              })}
                            </p>
                          ) : null}
                        </td>
                        <td className="px-3 py-2 text-right">
                          {entry.can_type && canEdit ? (
                            <NumberCell
                              wide
                              value={r.typed_output}
                              label={`${r.code} ${g("actual").toLowerCase()}`}
                              onChange={(v) =>
                                edit(i, { typed_output: v }, false)
                              }
                            />
                          ) : (
                            <span className="tabular-nums">
                              {r.typed_output ?? "—"}
                            </span>
                          )}
                        </td>
                        <td className="px-3 py-2">
                          <Pill tone={r.status}>
                            {p(`daily.row.${r.status}`)}
                          </Pill>
                        </td>
                        <td className="px-2 py-2">
                          {locked ? null : (
                            <div className="flex gap-1">
                              <Button
                                size="icon"
                                className="size-7"
                                variant="ghost"
                                onClick={() => reset(i)}
                                aria-label={p("daily.reset", { line: r.code })}
                                title={p("daily.reset_title")}
                              >
                                <RotateCcw className="size-3.5" aria-hidden />
                              </Button>
                              <Button
                                size="icon"
                                className="size-7"
                                variant="ghost"
                                onClick={() => remove(i)}
                                aria-label={p("daily.remove", { line: r.code })}
                              >
                                <X className="size-3.5" aria-hidden />
                              </Button>
                            </div>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                  <tr className="border-t border-border bg-surface-muted font-semibold">
                    <td className="px-3 py-2.5">{p("daily.total")}</td>
                    <td />
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(total(rows, "manpower"))}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(total(rows, "machines"))}
                    </td>
                    <td />
                    <td />
                    <td />
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(total(rows, "hourly_capacity"))}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(total(rows, "daily_capacity"))}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(total(rows, "master_target"))}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(total(rows, "daily_target"))}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {rows.some(
                        (r) => r.typed_output !== null && r.typed_output !== "",
                      )
                        ? num(total(rows, "typed_output"))
                        : "—"}
                    </td>
                    <td />
                    <td />
                  </tr>
                </tbody>
              </table>
            </div>
            <div className="flex flex-wrap items-end justify-between gap-4 p-5">
              <div className="flex max-w-xl flex-1 flex-col gap-3">
                {canEdit && !entry.confirmed ? (
                  <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                    {p("daily.add_line")}
                    <Select
                      key={rows.length}
                      value=""
                      onValueChange={(id) => id && addLine(id)}
                      options={addable.map(({ value, label }) => ({
                        value,
                        label,
                      }))}
                      placeholder={p("daily.add_ph")}
                      aria-label={p("daily.add_line")}
                      className="max-w-72"
                    />
                  </label>
                ) : null}
                <p className="flex gap-2 rounded-lg border border-info/30 bg-info-subtle px-4 py-2.5 text-[13px] text-info">
                  <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
                  {p("daily.hint")}
                </p>
              </div>
              {canEdit ? (
                <div className="flex flex-col items-end gap-1.5">
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      disabled={
                        !entry.saved || dirty || entry.confirmed || pending
                      }
                      onClick={confirmDay}
                    >
                      <CheckCheck className="size-4" aria-hidden />{" "}
                      {p("daily.confirm")}
                    </Button>
                    <Button
                      variant="outline"
                      loading={pending}
                      disabled={rows.length === 0}
                      onClick={save}
                    >
                      <Save className="size-4" aria-hidden /> {p("daily.save")}
                    </Button>
                  </div>
                  <p className="text-xs text-foreground-muted">
                    {entry.confirmed
                      ? p("daily.confirmed_note")
                      : dirty || !entry.saved
                        ? p("daily.unsaved")
                        : p("daily.saved")}
                  </p>
                </div>
              ) : null}
            </div>
          </section>

          <Section
            title={
              <>
                <BarChart3 className="size-4" aria-hidden />{" "}
                {p("daily.fc_title")}
              </>
            }
            hint={p("daily.fc_hint")}
          >
            <ForecastTiles f={entry.forecast} />
          </Section>
        </>
      )}

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="p-5">
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <LayoutDashboard className="size-4" aria-hidden />
            {p("daily.dash_title", { date: dmy(date) })}
          </h2>
          <p className="text-[13px] text-foreground-muted">
            {p("daily.dash_hint")}
          </p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "order",
                  "line",
                  "manpower",
                  "machine",
                  "hours",
                  "efficiency",
                  "capacity",
                  "target",
                  "actual",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      !["order", "line", "status"].includes(k) && "text-right",
                    )}
                  >
                    {p(`daily.d.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {dashboard.length === 0 ? (
                <tr>
                  <td
                    colSpan={10}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {p("daily.dash_empty")}
                  </td>
                </tr>
              ) : null}
              {dashboard.map((d) => (
                <tr
                  key={`${d.order_no}-${d.line}`}
                  className="border-t border-border"
                >
                  <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                    {d.order_no}
                  </td>
                  <td className="px-3 py-2.5">{d.line}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.manpower)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.machines)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.hours)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.efficiency)}%
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.capacity)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(d.target)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {d.actual ?? "—"}
                  </td>
                  <td className="px-3 py-2.5">
                    <Pill tone={d.status}>{p(`daily.row.${d.status}`)}</Pill>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

export { DailyPlan };
