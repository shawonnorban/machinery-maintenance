"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import {
  AlertTriangle,
  BarChart3,
  Calculator,
  CalendarCheck,
  CheckCircle2,
  Copy,
  Lock,
  Printer,
  RotateCcw,
  Save,
  Trash2,
  Undo2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import {
  card,
  dmy,
  dm,
  num,
  stamp,
  Pill,
  Tile,
  Section,
  Gantt,
  ForecastTiles,
  firstError,
  capacity,
  keepUrl,
} from "./plan-common";
import { MasterPlanTable } from "./plan-lists";

/*
 * Capacity Planning → Master Plan (AnnotechERP 12/02), as Brickland lays it
 * out. Pick the shipment month and a confirmed order; its information comes
 * from the order book. A new plan (or a draft): tick the lines, adjust their
 * resources (capacity recalculates at once), lay sewing out backward from
 * shipment or forward from a date, press Calculate Plan, read the schedule,
 * the Gantt and the daily targets, save the draft, then Confirm & Lock. A
 * locked plan shows its commitment, the current forecast and its tabs, and is
 * changed only through a revision request. Nothing reloads; the order or the
 * plan in view is kept in the URL by its number.
 */

const YEARS = [2025, 2026, 2027, 2028];

function usePlanT() {
  const t = useT("planning");
  return (k, x) => t(`plan.${k}`, x);
}

/** The order's facts, as read from the order book. */
function OrderInfo({ order, note }) {
  const p = usePlanT();
  const rows = [
    ["order_no", order.order_no],
    ["buyer", order.buyer],
    ["po", order.po],
    ["style", order.style],
    ["order_qty", `${num(order.qty)} ${order.unit ?? ""}`],
    ["ship_date", dmy(order.ship_date)],
    [
      "smv",
      order.smv ? (
        <>
          {num(order.smv)}{" "}
          <span className="text-xs font-normal text-foreground-muted">
            {p(`mp.src.${order.smv_source}`)}
          </span>
        </>
      ) : (
        "—"
      ),
    ],
    [
      "sam",
      order.sam ? (
        <>
          {num(order.sam)}{" "}
          <span className="text-xs font-normal text-foreground-muted">
            {p(`mp.src.${order.sam_source}`)}
          </span>
        </>
      ) : (
        "—"
      ),
    ],
    ["color", order.colors?.join(", ") || "—"],
    ["size", order.sizes?.join(", ") || "—"],
    [
      "tna",
      order.tna ? p("mp.tna_some", { count: order.tna }) : p("mp.tna_none"),
    ],
    [
      "remarks",
      note ?? p(order.tna ? "mp.tna_remark_some" : "mp.tna_remark_none"),
    ],
  ];
  return (
    <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
      {rows.map(([k, v]) => (
        <div key={k}>
          <dt className="text-xs text-foreground-muted">{p(`mp.${k}`)}</dt>
          <dd className="font-semibold text-foreground">{v}</dd>
        </div>
      ))}
    </dl>
  );
}

function ActivityTable({ activities }) {
  const p = usePlanT();
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[640px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            {["activity", "duration", "start", "end", "based_on"].map((k) => (
              <th
                key={k}
                className={cn(
                  "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                  k === "duration" && "text-right",
                )}
              >
                {p(`mp.${k}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {activities.map((a) => (
            <tr key={a.activity} className="border-t border-border">
              <td className="px-3 py-2.5 font-semibold">
                {p(`activities.${a.activity}`)}
              </td>
              <td className="px-3 py-2.5 text-right tabular-nums">
                {a.duration}
              </td>
              <td className="px-3 py-2.5 whitespace-nowrap">
                {dmy(a.start_date)}
              </td>
              <td className="px-3 py-2.5 whitespace-nowrap">
                {dmy(a.end_date)}
              </td>
              <td className="px-3 py-2.5">{p(`based.${a.based_on}`)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** Daily Target Plan: a row per day, a column per line. */
function TargetGrid({ days, lines }) {
  const p = usePlanT();
  const dates = [...new Set(days.map((d) => d.day))].sort();
  const codes = lines.map((l) => ({ id: l.line_id ?? l.id, code: l.code }));
  const at = (date, id) =>
    days
      .filter((d) => d.day === date && d.line_id === id)
      .reduce((s, d) => s + d.target, 0);
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[480px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            <th className="px-3 py-2.5 text-left text-xs font-semibold uppercase">
              {p("mp.date")}
            </th>
            {codes.map((c) => (
              <th
                key={c.id}
                className="px-3 py-2.5 text-right text-xs font-semibold uppercase"
              >
                {c.code}
              </th>
            ))}
            <th className="px-3 py-2.5 text-right text-xs font-semibold uppercase">
              {p("mp.total")}
            </th>
          </tr>
        </thead>
        <tbody>
          {dates.map((d) => (
            <tr key={d} className="border-t border-border">
              <td className="px-3 py-2.5 whitespace-nowrap">{dmy(d)}</td>
              {codes.map((c) => (
                <td key={c.id} className="px-3 py-2.5 text-right tabular-nums">
                  {num(at(d, c.id))}
                </td>
              ))}
              <td className="px-3 py-2.5 text-right font-semibold tabular-nums">
                {num(
                  days
                    .filter((x) => x.day === d)
                    .reduce((s, x) => s + x.target, 0),
                )}
              </td>
            </tr>
          ))}
          <tr className="border-t-2 border-foreground/40 font-semibold">
            <td className="px-3 py-2.5">{p("mp.total")}</td>
            {codes.map((c) => (
              <td key={c.id} className="px-3 py-2.5 text-right tabular-nums">
                {num(
                  days
                    .filter((x) => x.line_id === c.id)
                    .reduce((s, x) => s + x.target, 0),
                )}
              </td>
            ))}
            <td className="px-3 py-2.5 text-right tabular-nums">
              {num(days.reduce((s, x) => s + x.target, 0))}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}

function Tabs({ tabs, active, onChange }) {
  return (
    <div className="flex flex-wrap gap-1 border-b border-border" role="tablist">
      {tabs.map((t) => (
        <button
          key={t.key}
          type="button"
          role="tab"
          aria-selected={active === t.key}
          onClick={() => onChange(t.key)}
          className={cn(
            "-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm",
            active === t.key
              ? "border-brand font-semibold text-brand"
              : "border-transparent text-foreground-muted hover:text-foreground",
          )}
        >
          {t.label}
          {t.count !== undefined ? (
            <span className="rounded-full bg-surface-muted px-1.5 text-xs">
              {t.count}
            </span>
          ) : null}
        </button>
      ))}
    </div>
  );
}

/** The plan on paper: its order, capacity and window, the lines it runs on and the activity plan. */
function printPlan(plan, p, sign) {
  const right = { align: "right" };
  printDocument({
    title: p("screens.master-plan"),
    docNo: `${plan.plan_no} · V${plan.version}`,
    subtitle: p(`status.${plan.status}`),
    meta: [
      [p("mp.order_no"), plan.order.order_no],
      [p("mp.buyer"), plan.order.buyer],
      [p("mp.style"), plan.order.style],
      [p("mp.order_qty"), `${num(plan.order_qty)} ${plan.order.unit ?? ""}`],
      [p("mp.ship_date"), dmy(plan.ship_date)],
      [p("mp.smv"), num(plan.order.smv)],
      [p("mp.tile_cap"), p("mp.pcs_day", { qty: num(plan.daily_capacity) })],
      [p("mp.tile_start"), dmy(plan.sewing_start)],
      [p("mp.tile_original"), dmy(plan.sewing_end)],
    ],
    sections: [
      {
        title: p("ptabs.allocation"),
        columns: [
          { label: p("mp.line") },
          { label: p("mp.manpower"), ...right },
          { label: p("mp.machine"), ...right },
          { label: p("mp.hours"), ...right },
          { label: p("mp.efficiency"), ...right },
          { label: p("mp.daily"), ...right },
          { label: p("mp.allocated"), ...right },
          { label: p("mp.sewing"), nowrap: true },
        ],
        rows: plan.lines.map((l) => [
          l.code,
          num(l.manpower),
          num(l.machines),
          num(l.hours),
          `${num(l.efficiency)}%`,
          num(l.daily_capacity),
          num(l.allocated_qty),
          `${dmy(l.sewing_start)} → ${dmy(l.sewing_end)}`,
        ]),
        foot: [
          [
            p("mp.total"),
            num(plan.lines.reduce((t, l) => t + Number(l.manpower), 0)),
            num(plan.lines.reduce((t, l) => t + Number(l.machines), 0)),
            "",
            "",
            num(plan.daily_capacity),
            num(plan.allocated_qty),
            "",
          ],
        ],
      },
      {
        title: p("ptabs.activity"),
        columns: [
          { label: p("mp.activity") },
          { label: p("mp.duration"), ...right },
          { label: p("mp.start"), nowrap: true },
          { label: p("mp.end"), nowrap: true },
        ],
        rows: plan.activities.map((a) => [
          p(`activities.${a.activity}`),
          a.duration,
          dmy(a.start_date),
          dmy(a.end_date),
        ]),
      },
    ],
    notes: [plan.remarks],
    signatures: [sign("prepared"), sign("checked"), sign("approved")],
  });
}

/**
 * A new plan or a draft: lines, resources, calculation, result, save and confirm.
 */
function PlanEditor({
  order,
  lines,
  leadTimes,
  draft,
  perms,
  act,
  onSaved,
  onLocked,
  onDeleted,
}) {
  const p = usePlanT();
  const toastManager = useToastManager();
  const fromDraft = () => ({
    selected: Object.fromEntries(
      (draft?.lines ?? []).map((l) => [
        l.line_id,
        {
          manpower: l.manpower,
          machines: l.machines,
          hours: l.hours,
          efficiency: l.efficiency,
          allocated_qty: l.allocated_qty,
        },
      ]),
    ),
    direction: draft?.direction ?? "BACKWARD",
    forwardFrom: draft?.forward_from ?? "",
    auto: true,
    remarks: draft?.remarks ?? "",
  });
  const [scenarios, setScenarios] = useState(() => [
    { letter: draft?.scenario?.slice(-1) || "A", state: fromDraft() },
  ]);
  const [active, setActive] = useState(0);
  const [result, setResult] = useState(
    draft
      ? {
          lines: draft.lines.map((l) => ({
            ...l,
            planned_qty: l.allocated_qty,
          })),
          days: draft.days,
          activities: draft.activities,
          daily_capacity: draft.daily_capacity,
          required_days: draft.required_days,
          scheduled_qty: draft.allocated_qty,
          order_qty: draft.order_qty,
          sewing_start: draft.sewing_start,
          sewing_end: draft.sewing_end,
          fully_allocated: draft.allocated_qty >= draft.order_qty,
          lead_passed: [],
          balance: Math.max(0, draft.order_qty - draft.allocated_qty),
          late: false,
        }
      : null,
  );
  const [stale, setStale] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [tab, setTab] = useState("schedule");
  const [dialog, setDialog] = useState(null);
  const [pending, startTransition] = useTransition();
  const st = scenarios[active].state;
  // A change to the plan's inputs makes the result stale; a remark only makes the draft unsaved.
  const setSt = (patch, { keepResult = false } = {}) => {
    setScenarios((all) =>
      all.map((s, i) =>
        i === active
          ? {
              ...s,
              state: {
                ...s.state,
                ...(typeof patch === "function" ? patch(s.state) : patch),
              },
            }
          : s,
      ),
    );
    if (!keepResult) setStale(true);
    setDirty(true);
  };
  const smv = Number(order.smv);
  const chosen = lines.filter((l) => st.selected[l.id]);

  // Capacity at once, as the inputs change; the quantity shared by capacity unless set by hand.
  const preview = useMemo(() => {
    const rows = chosen.map((l) => {
      const v = st.selected[l.id];
      const [hourly, daily] = capacity(v.manpower, v.hours, v.efficiency, smv);
      return { ...l, ...v, hourly, daily };
    });
    const total = rows.reduce((s, r) => s + r.daily, 0);
    let left = order.qty;
    rows.forEach((r, i) => {
      if (st.auto) {
        r.alloc =
          i === rows.length - 1
            ? left
            : total > 0
              ? Math.floor((order.qty * r.daily) / total)
              : 0;
        left -= r.alloc;
      } else r.alloc = Number(r.allocated_qty ?? 0);
    });
    return { rows, total, allocated: rows.reduce((s, r) => s + r.alloc, 0) };
  }, [chosen, st, smv, order.qty]);

  const toggle = (l, on) =>
    setSt((s) => {
      const selected = { ...s.selected };
      if (on)
        selected[l.id] = {
          manpower: l.manpower,
          machines: l.machines,
          hours: l.hours,
          efficiency: l.efficiency,
          allocated_qty: null,
        };
      else delete selected[l.id];
      return { selected };
    });
  const setInput = (id, key, value) =>
    setSt((s) => ({
      selected: { ...s.selected, [id]: { ...s.selected[id], [key]: value } },
      ...(key === "allocated_qty" ? { auto: false } : {}),
    }));
  const resetLine = (l) =>
    setSt((s) => ({
      selected: {
        ...s.selected,
        [l.id]: {
          manpower: l.manpower,
          machines: l.machines,
          hours: l.hours,
          efficiency: l.efficiency,
          allocated_qty: null,
        },
      },
    }));
  const payload = () => ({
    order_no: order.order_no,
    direction: st.direction,
    forward_from: st.direction === "FORWARD" ? st.forwardFrom || null : null,
    auto: st.auto,
    scenario: p("mp.scenario", { letter: scenarios[active].letter }),
    remarks: st.remarks || null,
    lines: preview.rows.map((r) => ({
      line_id: r.id,
      manpower: Number(r.manpower),
      machines: Number(r.machines),
      hours: Number(r.hours),
      efficiency: Number(r.efficiency),
      allocated_qty: st.auto ? null : r.alloc,
    })),
  });
  const calculate = () =>
    startTransition(async () => {
      const res = await act("calculate", payload());
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setResult(res.data);
      setStale(false);
      toastManager.add({
        title: p("toast.calculated", {
          from: dmy(res.data.sewing_start),
          to: dmy(res.data.sewing_end),
          qty: num(res.data.scheduled_qty),
          total: num(res.data.order_qty),
        }),
        type: "success",
      });
    });
  const save = () =>
    startTransition(async () => {
      const res = await act("save", payload());
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setDirty(false);
      setStale(false);
      toastManager.add({
        title: p("toast.saved", { no: res.data.plan_no, v: res.data.version }),
        type: "success",
      });
      onSaved(res.data);
    });
  const confirm = () =>
    startTransition(async () => {
      const res = await act("confirm", draft.plan_no);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setDialog(null);
      toastManager.add({
        title: p("toast.locked", { no: res.data.plan_no, v: res.data.version }),
        type: "success",
      });
      onLocked(res.data);
    });
  const remove = () =>
    startTransition(async () => {
      const res = await act("deleteDraft", draft.plan_no);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setDialog(null);
      toastManager.add({ title: p("toast.deleted"), type: "success" });
      onDeleted();
    });
  const copyScenario = () => {
    const letter = String.fromCharCode(65 + scenarios.length);
    setScenarios((all) => [...all, { letter, state: structuredClone(st) }]);
    setActive(scenarios.length);
    setStale(true);
  };
  const lineOf = (id) =>
    result && !stale ? result.lines.find((x) => x.line_id === id) : null;
  const input =
    "h-9 w-20 rounded-md border border-border-strong bg-surface px-2 text-right text-sm tabular-nums";

  return (
    <div className="flex flex-col gap-4">
      <Section
        step={3}
        title={p("mp.info_title")}
        tag={p("mp.auto")}
        hint={p("mp.info_hint")}
        action={
          <Pill tone={draft ? "DRAFT" : "PENDING"}>
            {draft
              ? `${draft.plan_no} V${draft.version} · ${p("status.DRAFT")}`
              : p("mp.new_plan")}
          </Pill>
        }
      >
        <OrderInfo order={order} />
        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4">
          <span className="text-xs font-semibold tracking-wide text-foreground-muted">
            {p("mp.simulation")}
          </span>
          {scenarios.map((s, i) => (
            <Button
              key={s.letter}
              size="sm"
              variant={i === active ? "primary" : "outline"}
              onClick={() => setActive(i)}
            >
              {p("mp.scenario", { letter: s.letter })}
            </Button>
          ))}
          <Button size="sm" variant="outline" onClick={copyScenario}>
            <Copy className="size-4" aria-hidden /> {p("mp.copy_scenario")}
          </Button>
          <span className="text-xs text-foreground-muted">
            {p("mp.scenario_hint")}
          </span>
        </div>
      </Section>

      <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
        <Section
          step={4}
          title={p("mp.line_sel")}
          hint={p("mp.line_sel_hint", { date: dmy(order.last_sewing) })}
        >
          <div className="-m-5 overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["line", "line_status", "free_days", "mp_mc", "select"].map(
                    (k) => (
                      <th
                        key={k}
                        className={cn(
                          "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                          ["free_days", "select"].includes(k) && "text-center",
                        )}
                      >
                        {p(`mp.${k}`)}
                      </th>
                    ),
                  )}
                </tr>
              </thead>
              <tbody>
                {lines.map((l) => {
                  const off = l.status !== "AVAILABLE" || l.free_days === 0;
                  return (
                    <tr key={l.id} className="border-t border-border">
                      <td className="px-3 py-2.5">
                        <span className="block font-semibold">{l.code}</span>
                        <span className="text-xs text-foreground-muted">
                          {l.path}
                        </span>
                      </td>
                      <td className="px-3 py-2.5">
                        <Pill
                          tone={l.status === "AVAILABLE" ? "LOCKED" : "AT_RISK"}
                        >
                          {p(
                            l.status === "AVAILABLE"
                              ? "mp.available"
                              : "mp.maintenance",
                          )}
                        </Pill>
                      </td>
                      <td className="px-3 py-2.5 text-center tabular-nums">
                        {l.free_days}
                      </td>
                      <td className="px-3 py-2.5 whitespace-nowrap tabular-nums">
                        {l.manpower} / {l.machines}
                      </td>
                      <td className="px-3 py-2.5 text-center">
                        <input
                          type="checkbox"
                          className="size-4 accent-[var(--brand)]"
                          disabled={off && !st.selected[l.id]}
                          checked={Boolean(st.selected[l.id])}
                          onChange={(ev) => toggle(l, ev.target.checked)}
                          aria-label={p("mp.select_line", { line: l.code })}
                          title={off ? p("mp.no_free") : undefined}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Section>

        <Section
          step={5}
          title={p("mp.input_title")}
          hint={p("mp.input_hint")}
          action={
            <div className="flex flex-wrap items-center gap-2">
              {["BACKWARD", "FORWARD"].map((d) => (
                <Button
                  key={d}
                  size="sm"
                  variant={st.direction === d ? "primary" : "outline"}
                  aria-pressed={st.direction === d}
                  onClick={() => setSt({ direction: d })}
                >
                  {p(d === "BACKWARD" ? "mp.backward" : "mp.forward")}
                </Button>
              ))}
              {st.direction === "FORWARD" ? (
                <Input
                  type="date"
                  value={st.forwardFrom}
                  onChange={(ev) => setSt({ forwardFrom: ev.target.value })}
                  className="h-8 w-40"
                  aria-label={p("mp.forward_from")}
                />
              ) : null}
              <Button
                size="sm"
                variant="outline"
                onClick={() => setDialog("lead")}
              >
                {p("mp.lead")}
              </Button>
            </div>
          }
        >
          <div className="-mx-5 overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "line",
                    "manpower",
                    "machine",
                    "hours",
                    "efficiency",
                    "smv",
                    "hourly",
                    "daily",
                    "allocated",
                    "sewing",
                    "",
                  ].map((k, i) => (
                    <th
                      key={i}
                      className={cn(
                        "px-2 py-2.5 text-xs font-semibold uppercase",
                        i === 0 || k === "sewing" ? "text-left" : "text-right",
                      )}
                    >
                      {k ? (k === "smv" ? p("mp.smv") : p(`mp.${k}`)) : ""}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {preview.rows.length === 0 ? (
                  <tr>
                    <td
                      colSpan={11}
                      className="px-3 py-8 text-center text-foreground-muted"
                    >
                      {p("mp.tick_lines")}
                    </td>
                  </tr>
                ) : null}
                {preview.rows.map((r) => {
                  const done = lineOf(r.id);
                  return (
                    <tr key={r.id} className="border-t border-border">
                      <td className="px-2 py-2 font-semibold">{r.code}</td>
                      {[
                        ["manpower", "1"],
                        ["machines", "1"],
                        ["hours", "0.5"],
                        ["efficiency", "1"],
                      ].map(([k, step]) => (
                        <td key={k} className="px-2 py-2 text-right">
                          <input
                            type="number"
                            min="0"
                            step={step}
                            value={r[k]}
                            onChange={(ev) =>
                              setInput(r.id, k, ev.target.value)
                            }
                            className={input}
                            aria-label={`${r.code} ${k}`}
                          />
                        </td>
                      ))}
                      <td className="px-2 py-2 text-right tabular-nums">
                        {num(smv)}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {num(r.hourly)}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {num(r.daily)}
                      </td>
                      <td className="px-2 py-2 text-right">
                        <input
                          type="number"
                          min="0"
                          step="1"
                          value={r.alloc}
                          onChange={(ev) =>
                            setInput(r.id, "allocated_qty", ev.target.value)
                          }
                          className={cn(input, "w-24")}
                          aria-label={`${r.code} allocated quantity`}
                        />
                      </td>
                      <td className="px-2 py-2 whitespace-nowrap">
                        {done?.sewing_start ? (
                          <>
                            {dm(done.sewing_start)} → {dm(done.sewing_end)}
                            <span className="block text-xs text-foreground-muted">
                              {p("mp.days", { count: done.days })}
                            </span>
                          </>
                        ) : (
                          "—"
                        )}
                      </td>
                      <td className="px-2 py-2 text-right">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => resetLine(r)}
                          aria-label={`${r.code} reset`}
                        >
                          <RotateCcw className="size-4" aria-hidden />
                        </Button>
                      </td>
                    </tr>
                  );
                })}
                {preview.rows.length ? (
                  <tr className="border-t-2 border-foreground/40 font-semibold">
                    <td className="px-2 py-2">{p("mp.total")}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {num(
                        preview.rows.reduce(
                          (s, r) => s + Number(r.manpower),
                          0,
                        ),
                      )}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {num(
                        preview.rows.reduce(
                          (s, r) => s + Number(r.machines),
                          0,
                        ),
                      )}
                    </td>
                    <td colSpan={3} />
                    <td className="px-2 py-2 text-right tabular-nums">
                      {num(preview.rows.reduce((s, r) => s + r.hourly, 0))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {num(preview.total)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {num(preview.allocated)}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {result && !stale && result.sewing_start
                        ? `${dm(result.sewing_start)} → ${dm(result.sewing_end)}`
                        : ""}
                    </td>
                    <td />
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
            <Tile
              label={p("mp.total_cap")}
              value={`${num(preview.total)} ${p("mp.pcs")}`}
            />
            <Tile
              label={p("mp.order_qty")}
              value={`${num(order.qty)} ${p("mp.pcs")}`}
            />
            <Tile
              label={p("mp.req_days")}
              value={`${preview.total ? (order.qty / preview.total).toFixed(2) : "0.00"} ${p("mp.day_unit")}`}
            />
            <Tile
              label={p("mp.alloc_bal")}
              value={`${num(preview.allocated)} / ${num(Math.max(0, order.qty - preview.allocated))}`}
              sub={
                preview.allocated >= order.qty && order.qty > 0
                  ? p("mp.fully")
                  : p("mp.shortfall")
              }
              tone={preview.allocated >= order.qty ? "success" : "warning"}
            />
            <div className="rounded-lg border border-border bg-surface-muted px-4 py-3">
              <p className="text-xs font-semibold text-foreground-muted">
                {p("mp.allocation")}
              </p>
              <p className="mt-1 font-semibold text-foreground">
                {p(st.auto ? "mp.by_capacity" : "mp.manual")}
              </p>
              <p className="text-xs text-foreground-muted">
                {st.auto ? (
                  p("mp.finish_together")
                ) : (
                  <button
                    type="button"
                    className="text-brand underline"
                    onClick={() => setSt({ auto: true })}
                  >
                    {p("mp.auto_btn")}
                  </button>
                )}
              </p>
            </div>
            <div className="flex flex-col items-center justify-center gap-1">
              <Button
                onClick={calculate}
                loading={pending}
                disabled={preview.rows.length === 0}
              >
                <Calculator className="size-4" aria-hidden />{" "}
                {p("mp.calculate")}
              </Button>
              <span className="text-xs text-foreground-muted">
                {preview.rows.length === 0
                  ? p("mp.select_first")
                  : result && !stale
                    ? p("mp.up_to_date")
                    : p("mp.stale")}
              </span>
            </div>
          </div>
        </Section>
      </div>

      {result && !stale ? (
        <div className="grid gap-3 lg:grid-cols-2">
          {result.fully_allocated ? (
            <div className="flex gap-2 rounded-lg border border-success/30 bg-success-subtle px-4 py-3 text-sm text-success">
              <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden />
              <div>
                <p className="font-semibold">{p("mp.fully")}</p>
                <p className="text-foreground">
                  {p("mp.fully_body", {
                    from: result.sewing_start,
                    to: result.sewing_end,
                    ship: order.ship_date,
                  })}
                </p>
              </div>
            </div>
          ) : (
            <div className="flex gap-2 rounded-lg border border-danger/30 bg-danger-subtle px-4 py-3 text-sm text-danger">
              <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
              <div>
                <p className="font-semibold">{p("mp.shortfall")}</p>
                <p className="text-foreground">
                  {p("mp.shortfall_body", {
                    qty: num(result.balance),
                    date: dmy(order.last_sewing),
                  })}
                </p>
              </div>
            </div>
          )}
          {result.late ? (
            <div className="rounded-lg border border-danger/30 bg-danger-subtle px-4 py-3 text-sm text-danger">
              {p("mp.late", {
                date: dmy(order.last_sewing),
                ship: dmy(order.ship_date),
              })}
            </div>
          ) : null}
          {result.lead_passed?.length ? (
            <div className="flex gap-2 rounded-lg border border-warning/30 bg-warning-subtle px-4 py-3 text-sm text-warning">
              <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
              <div>
                <p className="font-semibold">{p("mp.lead_passed")}</p>
                <p className="text-foreground">
                  {p("mp.lead_passed_body", {
                    list: result.lead_passed
                      .map((a) => p(`activities.${a}`))
                      .join(", "),
                  })}
                </p>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}

      <Section
        step={6}
        title={p("mp.result_title")}
        tag={p("mp.simulation_tag")}
        hint={p("mp.result_hint", {
          date: dmy(order.last_sewing),
          ship: dmy(order.ship_date),
        })}
      >
        {result && !stale ? (
          <div className="flex flex-col gap-3">
            <Tabs
              tabs={[
                { key: "schedule", label: p("mp.tab_schedule") },
                { key: "gantt", label: p("mp.tab_gantt") },
                {
                  key: "targets",
                  label: p("mp.tab_targets"),
                  count: result.days.length,
                },
              ]}
              active={tab}
              onChange={setTab}
            />
            {tab === "schedule" ? (
              <ActivityTable activities={result.activities} />
            ) : null}
            {tab === "gantt" ? <Gantt activities={result.activities} /> : null}
            {tab === "targets" ? (
              <TargetGrid
                days={result.days}
                lines={preview.rows.map((r) => ({
                  line_id: r.id,
                  code: r.code,
                }))}
              />
            ) : null}
          </div>
        ) : (
          <p className="py-6 text-center text-sm text-foreground-muted">
            {p("mp.result_empty")}
          </p>
        )}
      </Section>

      <section
        className={cn(
          card,
          "flex flex-wrap items-end justify-between gap-4 p-5",
        )}
      >
        <label className="flex min-w-72 flex-1 flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("mp.remarks")}
          <Textarea
            rows={3}
            value={st.remarks}
            onChange={(ev) =>
              setSt({ remarks: ev.target.value }, { keepResult: true })
            }
            placeholder={p("mp.remarks_ph")}
            aria-label={p("mp.remarks")}
          />
        </label>
        <div className="flex flex-col items-end gap-1">
          <div className="flex flex-wrap gap-2">
            {draft && perms.manage ? (
              <Button
                variant="outline"
                className="text-danger"
                onClick={() => setDialog("delete")}
              >
                <Trash2 className="size-4" aria-hidden /> {p("mp.delete_draft")}
              </Button>
            ) : null}
            {perms.manage ? (
              <Button
                variant="outline"
                loading={pending}
                disabled={!result || stale}
                onClick={save}
              >
                <Save className="size-4" aria-hidden />{" "}
                {p(draft ? "mp.save_draft_again" : "mp.save_draft")}
              </Button>
            ) : null}
            {perms.approve ? (
              <Button
                disabled={!draft || dirty}
                onClick={() => setDialog("confirm")}
              >
                <Lock className="size-4" aria-hidden /> {p("mp.confirm")}
              </Button>
            ) : null}
          </div>
          {!draft || dirty ? (
            <span className="text-xs text-foreground-muted">
              {p("mp.save_first")}
            </span>
          ) : null}
        </div>
      </section>

      {dialog === "lead" ? (
        <Modal
          open
          onOpenChange={(o) => !o && setDialog(null)}
          title={p("mp.lead_title")}
          description={p("mp.lead_hint")}
        >
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-foreground-muted uppercase">
                <th className="py-1.5">{p("mp.activity")}</th>
                <th className="py-1.5 text-right">{p("mp.lead_days")}</th>
                <th className="py-1.5 pl-4">{p("mp.lead_from")}</th>
              </tr>
            </thead>
            <tbody>
              {leadTimes.map((l) => (
                <tr key={l.activity} className="border-t border-border">
                  <td className="py-1.5">{p(`activities.${l.activity}`)}</td>
                  <td className="py-1.5 text-right tabular-nums">
                    {l.lead_days}
                  </td>
                  <td className="py-1.5 pl-4">
                    {p(`mp.counted.${l.counted_from}`)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Modal>
      ) : null}
      {dialog === "delete" && draft ? (
        <Modal
          open
          onOpenChange={(o) => !o && setDialog(null)}
          title={p("mp.delete_title", { no: draft.plan_no, v: draft.version })}
          description={p("mp.delete_body")}
          footer={
            <>
              <Button variant="outline" onClick={() => setDialog(null)}>
                {p("mp.cancel")}
              </Button>
              <Button variant="danger" loading={pending} onClick={remove}>
                {p("mp.delete_draft")}
              </Button>
            </>
          }
        />
      ) : null}
      {dialog === "confirm" && draft ? (
        <Modal
          open
          onOpenChange={(o) => !o && setDialog(null)}
          title={p("mp.confirm_title", { no: draft.plan_no, v: draft.version })}
          description={p("mp.confirm_body")}
          className="max-w-xl"
          footer={
            <>
              <Button variant="outline" onClick={() => setDialog(null)}>
                {p("mp.cancel")}
              </Button>
              <Button loading={pending} onClick={confirm}>
                <Lock className="size-4" aria-hidden /> {p("mp.confirm_btn")}
              </Button>
            </>
          }
        >
          <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            {[
              [p("mp.order_qty"), `${num(draft.order_qty)} ${p("mp.pcs")}`],
              [p("mp.cf_lines"), draft.lines.map((l) => l.code).join(", ")],
              [
                p("mp.manpower"),
                num(draft.lines.reduce((s, l) => s + l.manpower, 0)),
              ],
              [
                p("mp.machine"),
                num(draft.lines.reduce((s, l) => s + l.machines, 0)),
              ],
              [
                p("mp.cf_hours"),
                draft.lines.map((l) => num(l.hours)).join(" / "),
              ],
              [
                p("mp.efficiency"),
                [
                  ...new Set(draft.lines.map((l) => `${num(l.efficiency)}%`)),
                ].join(" / "),
              ],
              [
                p("mp.cf_capacity"),
                `${num(draft.daily_capacity)} ${p("mp.pcs")}`,
              ],
              [p("mp.cf_req"), num(draft.required_days)],
              [p("mp.cf_start"), dmy(draft.sewing_start)],
              [p("mp.cf_end"), dmy(draft.sewing_end)],
              [p("mp.ship_date"), dmy(draft.ship_date)],
              [
                p("mp.cf_status"),
                draft.allocated_qty >= draft.order_qty
                  ? p("mp.fully")
                  : p("mp.shortfall"),
              ],
              [
                p("mp.cf_feasibility"),
                draft.sewing_end && draft.sewing_end < draft.ship_date
                  ? p("mp.feasible")
                  : p("mp.not_feasible"),
              ],
            ].map(([k, v]) => (
              <div key={k} className="contents">
                <dt className="text-foreground-muted">{k}</dt>
                <dd className="font-semibold text-foreground">{v}</dd>
              </div>
            ))}
          </dl>
        </Modal>
      ) : null}
    </div>
  );
}

/** A locked (or revised) version: the commitment, the forecast and its tabs. */
function PlanView({ plan, perms, act, onReload, onOpenVersion }) {
  const p = usePlanT();
  const te = useT("erp");
  const toastManager = useToastManager();
  const [tab, setTab] = useState("allocation");
  const [reason, setReason] = useState(null);
  const [deciding, setDeciding] = useState(null);
  const [pending, startTransition] = useTransition();
  const f = plan.forecast;
  const locked = plan.status === "LOCKED";
  // The revision already opened as a draft, else the version an approval would open.
  const nextVersion =
    plan.versions.find((v) => v.status === "DRAFT")?.version ??
    Math.max(...plan.versions.map((v) => v.version)) + 1;
  const run = (op, args, toastKey, after) =>
    startTransition(async () => {
      const res = await act(op, ...args);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({
        title: p(`toast.${toastKey}`, { v: res.data?.revised_version ?? "" }),
        type: "success",
      });
      after?.();
      onReload();
    });

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <Lock className="size-4 text-danger" aria-hidden />
              {p(
                plan.status === "REVISED"
                  ? "mp.revised_title"
                  : "mp.locked_title",
              )}
              <span className="font-normal text-foreground-muted">
                {plan.plan_no} · V{plan.version}
              </span>
            </h2>
            {plan.confirmed_at ? (
              <p className="text-[13px] text-foreground-muted">
                {p("mp.locked_by", {
                  by: plan.confirmed_by ?? "—",
                  at: stamp(plan.confirmed_at),
                })}
              </p>
            ) : null}
          </div>
          <div className="flex items-center gap-2">
            <Pill tone={plan.status === "REVISED" ? "REVISED" : "AT_RISK"}>
              {p(`mp.badge.${plan.status}`)}
            </Pill>
            <Button
              size="sm"
              variant="outline"
              onClick={() => printPlan(plan, p, (k) => te(`print_sign.${k}`))}
            >
              <Printer className="size-4" aria-hidden /> {p("mp.print")}
            </Button>
          </div>
        </div>
        <div className="mt-4">
          <OrderInfo order={plan.order} note={plan.remarks ?? undefined} />
        </div>
        <div className="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-7">
          <Tile label={p("mp.tile_lines")} value={plan.lines.length} />
          <Tile
            label={p("mp.tile_cap")}
            value={p("mp.pcs_day", { qty: num(plan.daily_capacity) })}
          />
          <Tile
            label={p("mp.tile_req")}
            value={p("mp.n_day", { n: num(plan.required_days) })}
          />
          <Tile
            label={p("mp.tile_alloc")}
            value={`${num(plan.allocated_qty)} ${p("mp.pcs")}`}
          />
          <Tile label={p("mp.tile_start")} value={dmy(plan.sewing_start)} />
          <Tile label={p("mp.tile_original")} value={dmy(plan.sewing_end)} />
          <Tile label={p("mp.tile_ship")} value={dmy(plan.ship_date)} />
        </div>
        <p className="mt-4 flex gap-2 rounded-lg border border-danger/30 bg-danger-subtle px-4 py-3 text-sm text-danger">
          <Lock className="mt-0.5 size-4 shrink-0" aria-hidden />
          {locked
            ? p("mp.locked_banner", { next: nextVersion })
            : p("mp.revised_banner")}
        </p>
      </section>

      {f && locked ? (
        <Section
          title={
            <>
              <BarChart3 className="size-4" aria-hidden /> {p("fc.title")}
            </>
          }
          hint={p("fc.hint")}
          action={
            <Link
              href={`/capacity-planning/daily-plan?order=${encodeURIComponent(plan.order.order_no)}`}
              className={buttonVariants({ size: "sm", variant: "outline" })}
            >
              <CalendarCheck className="size-4" aria-hidden />{" "}
              {p("fc.daily_plan")}
            </Link>
          }
        >
          <ForecastTiles f={f} note />
        </Section>
      ) : null}

      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <Tabs
          tabs={[
            { key: "allocation", label: p("ptabs.allocation") },
            { key: "activity", label: p("ptabs.activity") },
            { key: "gantt", label: p("ptabs.gantt") },
            { key: "pva", label: p("ptabs.pva") },
            { key: "daily", label: p("ptabs.daily"), count: plan.days.length },
            { key: "versions", label: p("ptabs.versions") },
            {
              key: "audit",
              label: p("ptabs.audit"),
              count: plan.events.length,
            },
          ]}
          active={tab}
          onChange={setTab}
        />
        {tab === "allocation" ? (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "line",
                    "manpower",
                    "machine",
                    "hours",
                    "efficiency",
                    "hourly",
                    "daily",
                    "allocated",
                    "sewing",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-xs font-semibold uppercase",
                        ["line", "sewing"].includes(k)
                          ? "text-left"
                          : "text-right",
                      )}
                    >
                      {p(`mp.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {plan.lines.map((l) => (
                  <tr key={l.line_id} className="border-t border-border">
                    <td className="px-3 py-2.5">
                      <span className="font-semibold">{l.code}</span>
                      <span className="block text-xs text-foreground-muted">
                        {l.floor}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {l.manpower}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {l.machines}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(l.hours)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(l.efficiency)}%
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(l.hourly_capacity)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(l.daily_capacity)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(l.allocated_qty)}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {dmy(l.sewing_start)} → {dmy(l.sewing_end)}
                    </td>
                  </tr>
                ))}
                <tr className="border-t-2 border-foreground/40 font-semibold">
                  <td className="px-3 py-2.5">{p("mp.total")}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(plan.lines.reduce((s, l) => s + l.manpower, 0))}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(plan.lines.reduce((s, l) => s + l.machines, 0))}
                  </td>
                  <td colSpan={3} />
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(plan.daily_capacity)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(plan.allocated_qty)}
                  </td>
                  <td />
                </tr>
              </tbody>
            </table>
          </div>
        ) : null}
        {tab === "activity" ? (
          <ActivityTable activities={plan.activities} />
        ) : null}
        {tab === "gantt" ? <Gantt activities={plan.activities} /> : null}
        {tab === "pva" ? (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "date",
                    "line",
                    "master_target",
                    "daily_capacity",
                    "plan_target",
                    "actual",
                    "variance",
                    "balance",
                    "source",
                    "remarks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-xs font-semibold uppercase",
                        ["date", "line", "source", "remarks"].includes(k)
                          ? "text-left"
                          : "text-right",
                      )}
                    >
                      {p(`pva.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {plan.days.map((d) => (
                  <tr
                    key={`${d.day}${d.line_id}`}
                    className="border-t border-border"
                  >
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {dmy(d.day)}
                    </td>
                    <td className="px-3 py-2.5">{d.line}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(d.target)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(d.daily_capacity)}
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
                      )}
                    >
                      {num(d.variance)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(d.balance)}
                    </td>
                    <td className="px-3 py-2.5">
                      {p(`pva.sources.${d.source}`)}
                    </td>
                    <td className="px-3 py-2.5">{d.remarks ?? "—"}</td>
                  </tr>
                ))}
                <tr className="border-t-2 border-foreground/40 font-semibold">
                  <td className="px-3 py-2.5" colSpan={2}>
                    {p("mp.total")}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(plan.days.reduce((s, d) => s + d.target, 0))}
                  </td>
                  <td />
                  <td />
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(plan.days.reduce((s, d) => s + (d.actual ?? 0), 0))}
                  </td>
                  <td colSpan={4} />
                </tr>
              </tbody>
            </table>
          </div>
        ) : null}
        {tab === "daily" ? (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "date",
                    "line",
                    "manpower",
                    "machine",
                    "hours",
                    "capacity",
                    "target",
                    "status",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-xs font-semibold uppercase",
                        ["date", "line", "status"].includes(k)
                          ? "text-left"
                          : "text-right",
                      )}
                    >
                      {k === "manpower" || k === "machine" || k === "hours"
                        ? p(`mp.${k}`)
                        : p(`pva.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {plan.days.map((d) => (
                  <tr
                    key={`${d.day}${d.line_id}`}
                    className="border-t border-border"
                  >
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {dmy(d.day)}
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
                      {num(d.daily_capacity ?? d.capacity)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(d.plan_target)}
                    </td>
                    <td className="px-3 py-2.5">
                      <Pill tone={d.status}>
                        {p(`pva.statuses.${d.status}`)}
                      </Pill>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
        {tab === "versions" ? (
          <div className="flex flex-col gap-4">
            <div>
              <p className="mb-2 text-sm font-semibold text-foreground">
                {p("rev.versions")}
              </p>
              <div className="flex flex-wrap gap-2">
                {plan.versions.map((v) => (
                  <button
                    key={v.version}
                    type="button"
                    onClick={() => onOpenVersion(v.version)}
                    className={cn(
                      "flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm",
                      v.version === plan.version
                        ? "border-brand bg-brand-subtle text-brand"
                        : "border-border hover:bg-surface-muted",
                    )}
                  >
                    V{v.version}{" "}
                    <Pill tone={v.status}>{p(`status.${v.status}`)}</Pill>
                  </button>
                ))}
              </div>
            </div>
            <div>
              <div className="mb-2 flex items-center justify-between gap-2">
                <p className="text-sm font-semibold text-foreground">
                  {p("rev.revisions")}
                </p>
                {locked &&
                plan.current &&
                !plan.awaiting &&
                perms.manage &&
                reason === null ? (
                  <Button size="sm" onClick={() => setReason("")}>
                    <Undo2 className="size-4" aria-hidden /> {p("rev.request")}
                  </Button>
                ) : null}
              </div>
              {reason !== null ? (
                <div className="mb-3 rounded-lg border border-brand/30 bg-brand-subtle/40 p-4">
                  <p className="font-semibold text-foreground">
                    {p("rev.req_title")}
                  </p>
                  <p className="mb-2 text-[13px] text-foreground-muted">
                    {p("rev.req_body")}
                  </p>
                  <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                    <span>
                      {p("rev.reason")}
                      <span className="text-danger"> *</span>
                    </span>
                    <Textarea
                      rows={3}
                      value={reason}
                      onChange={(ev) => setReason(ev.target.value)}
                      placeholder={p("rev.reason_ph")}
                      aria-label={p("rev.reason")}
                    />
                  </label>
                  <div className="mt-2 flex justify-end gap-2">
                    <Button variant="outline" onClick={() => setReason(null)}>
                      {p("mp.cancel")}
                    </Button>
                    <Button
                      loading={pending}
                      disabled={!reason.trim()}
                      onClick={() =>
                        run(
                          "requestRevision",
                          [plan.plan_no, { reason }],
                          "requested",
                          () => setReason(null),
                        )
                      }
                    >
                      {p("rev.send")}
                    </Button>
                  </div>
                </div>
              ) : null}
              {plan.revisions.length === 0 ? (
                <p className="text-sm text-foreground-muted">{p("rev.none")}</p>
              ) : null}
              <ul className="flex flex-col gap-2">
                {plan.revisions.map((r) => (
                  <li
                    key={r.id}
                    className="rounded-lg border border-border p-3 text-sm"
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <span className="text-xs text-foreground-muted">
                        V{r.version} · {r.requested_by ?? "—"} ·{" "}
                        {stamp(r.requested_at)}
                      </span>
                      <Pill
                        tone={r.status === "PENDING" ? "AWAITING" : r.status}
                      >
                        {r.status === "PENDING"
                          ? p("rev.awaiting")
                          : p(`rev.statuses.${r.status}`)}
                      </Pill>
                    </div>
                    <p className="mt-1 text-foreground">{r.reason}</p>
                    {r.decision_note ? (
                      <p className="mt-1 text-xs text-foreground-muted">
                        {r.decision_note}
                      </p>
                    ) : null}
                    {r.status === "PENDING" && perms.approve ? (
                      <div className="mt-2 flex gap-2">
                        <Button
                          size="sm"
                          onClick={() =>
                            setDeciding({ id: r.id, kind: "approve", note: "" })
                          }
                        >
                          {p("rev.approve")}
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() =>
                            setDeciding({ id: r.id, kind: "reject", note: "" })
                          }
                        >
                          {p("rev.reject")}
                        </Button>
                      </div>
                    ) : null}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        ) : null}
        {tab === "audit" ? (
          <ol className="flex flex-col gap-3">
            {plan.events.map((e, i) => (
              <li key={i} className="border-l-2 border-brand pl-3">
                <p className="text-sm text-foreground">{e.summary}</p>
                <p className="text-xs text-foreground-muted">
                  {e.by ?? "—"} · {stamp(e.at)}
                </p>
              </li>
            ))}
          </ol>
        ) : null}
      </section>

      {deciding ? (
        <DecisionDialog
          deciding={deciding}
          setDeciding={setDeciding}
          pending={pending}
          onConfirm={() =>
            run(
              deciding.kind === "approve"
                ? "approveRevision"
                : "rejectRevision",
              [deciding.id, { note: deciding.note || null }],
              deciding.kind === "approve" ? "approved" : "rejected",
              () => setDeciding(null),
            )
          }
        />
      ) : null}
    </div>
  );
}

/** Approve (note optional) or Reject (note required) a revision request. */
function DecisionDialog({ deciding, setDeciding, pending, onConfirm }) {
  const p = usePlanT();
  const approve = deciding.kind === "approve";
  return (
    <Modal
      open
      onOpenChange={(o) => !o && setDeciding(null)}
      title={p(approve ? "rev.approve_title" : "rev.reject_title")}
      description={p(approve ? "rev.approve_body" : "rev.reject_body")}
      footer={
        <>
          <Button variant="outline" onClick={() => setDeciding(null)}>
            {p("mp.cancel")}
          </Button>
          <Button
            variant={approve ? "primary" : "danger"}
            loading={pending}
            disabled={!approve && !deciding.note.trim()}
            onClick={onConfirm}
          >
            {p(approve ? "rev.approve" : "rev.reject")}
          </Button>
        </>
      }
    >
      {approve ? null : (
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          <span>
            {p("rev.note")}
            <span className="text-danger"> *</span>
          </span>
          <Textarea
            rows={3}
            value={deciding.note}
            onChange={(ev) =>
              setDeciding((d) => ({ ...d, note: ev.target.value }))
            }
            aria-label={p("rev.note")}
          />
        </label>
      )}
    </Modal>
  );
}

/**
 * Master Plan.
 *
 * @param {{ initial: object, perms: object, act: Function }} props
 */
function MasterPlan({ initial, perms, act }) {
  const p = usePlanT();
  const toastManager = useToastManager();
  const [year, setYear] = useState(initial.year);
  const [month, setMonth] = useState(initial.month);
  const [orders, setOrders] = useState(initial.orders);
  const [loaded, setLoaded] = useState(initial.order);
  const [viewing, setViewing] = useState(initial.plan);
  const [list, setList] = useState(initial.list);
  const [pending, startTransition] = useTransition();

  const pickMonth = (y, m) =>
    startTransition(async () => {
      setYear(y);
      setMonth(m);
      const res = await act("orders", { year: y, month: m });
      if (res?.status === "success") setOrders(res.data);
    });
  const openOrder = (no) =>
    startTransition(async () => {
      const res = await act("order", no);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setLoaded(res.data);
      setViewing(null);
      keepUrl({ order: no });
    });
  const openVersion = (planNo, v) =>
    startTransition(async () => {
      const res = await act("show", planNo, v);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      if (res.data.status === "DRAFT")
        return openOrder(res.data.order.order_no);
      setViewing(res.data);
      keepUrl({ plan: planNo, v });
    });
  const reloadPlan = (planNo, v) => openVersion(planNo, v);
  const refreshList = async () => {
    const res = await act("list", {});
    if (res?.status === "success") setList(res.data);
  };

  const plan = viewing ?? loaded?.plan;
  const order = viewing?.order ?? loaded?.order;
  const editing =
    loaded && !viewing && (!loaded.plan || loaded.plan.status === "DRAFT");

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section
        className={cn(card, "grid gap-4 p-5 md:grid-cols-[8rem_10rem_1fr]")}
      >
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          <span className="flex items-center gap-2">
            <span className="flex size-5 items-center justify-center rounded-full bg-brand text-[11px] text-brand-foreground">
              1
            </span>
            {p("mp.year")}
          </span>
          <select
            value={year}
            onChange={(ev) => pickMonth(Number(ev.target.value), month)}
            className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
            aria-label={p("mp.year")}
          >
            {YEARS.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("mp.ship_month")}
          <select
            value={month}
            onChange={(ev) => pickMonth(year, Number(ev.target.value))}
            className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
            aria-label={p("mp.ship_month")}
          >
            {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
              <option key={m} value={m}>
                {p(`months.${m}`)}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          <span className="flex items-center gap-2">
            <span className="flex size-5 items-center justify-center rounded-full bg-brand text-[11px] text-brand-foreground">
              2
            </span>
            {p("mp.select_order")}
          </span>
          <Select
            value={order?.order_no ?? ""}
            onValueChange={(no) => no && openOrder(no)}
            options={orders}
            placeholder={p("mp.select_ph")}
            aria-label={p("mp.select_order")}
          />
          <span className="text-xs font-normal text-foreground-muted">
            {p("mp.select_hint")}
          </span>
        </label>
      </section>

      {!loaded && !viewing ? (
        <MasterPlanTable
          rows={list}
          title={p("mp.list_title")}
          hint={p("mp.list_hint")}
          chips
          onOpen={(no, v) => openVersion(no, v)}
        />
      ) : null}

      {loaded && !viewing && !loaded.order.can_plan ? (
        <section className={cn(card, "p-5")}>
          <OrderInfo order={loaded.order} />
          {loaded.order.problems.map((x) => (
            <p
              key={x}
              className="mt-4 rounded-lg border border-warning/30 bg-warning-subtle px-4 py-3 text-sm text-warning"
            >
              {p(`mp.${x}`)}
            </p>
          ))}
          <div className="mt-6 text-center">
            <p className="font-semibold text-foreground">{p("mp.cannot")}</p>
            <p className="text-sm text-foreground-muted">
              {p("mp.cannot_hint")}
            </p>
          </div>
        </section>
      ) : null}

      {editing && loaded.order.can_plan ? (
        <>
          {loaded.plan && loaded.plan.versions.length > 1 ? (
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-semibold text-foreground">
                {p("mp.view_versions")}:
              </span>
              {loaded.plan.versions.map((v) => (
                <Button
                  key={v.version}
                  size="sm"
                  variant={
                    v.version === loaded.plan.version ? "primary" : "outline"
                  }
                  onClick={() => openVersion(loaded.plan.plan_no, v.version)}
                >
                  V{v.version} · {p(`status.${v.status}`)}
                </Button>
              ))}
            </div>
          ) : null}
          <PlanEditor
            key={`${loaded.order.order_no}-${loaded.plan?.version ?? 0}-${loaded.plan?.allocated_qty ?? 0}`}
            order={loaded.order}
            lines={loaded.lines}
            leadTimes={loaded.lead_times}
            draft={loaded.plan}
            perms={perms}
            act={act}
            onSaved={(saved) => {
              setLoaded((l) => ({ ...l, plan: saved }));
              refreshList();
            }}
            onLocked={(lockedPlan) => {
              setViewing(lockedPlan);
              keepUrl({ plan: lockedPlan.plan_no, v: lockedPlan.version });
              refreshList();
            }}
            onDeleted={() => {
              openOrder(loaded.order.order_no);
              refreshList();
            }}
          />
        </>
      ) : null}

      {plan &&
      order &&
      (viewing || (loaded?.plan && loaded.plan.status !== "DRAFT")) ? (
        <PlanView
          key={`${plan.plan_no}-${plan.version}`}
          plan={plan}
          perms={perms}
          act={act}
          onReload={() => reloadPlan(plan.plan_no, plan.version)}
          onOpenVersion={(v) => openVersion(plan.plan_no, v)}
        />
      ) : null}
    </div>
  );
}

export { MasterPlan, OrderInfo, ActivityTable, DecisionDialog };
