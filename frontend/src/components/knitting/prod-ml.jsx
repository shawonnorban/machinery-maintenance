"use client";

import { useState, useTransition } from "react";
import { CalendarCheck, Eraser, FilterX, Save, Search, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  qtyFmt,
  dayFmt,
  todayIso,
  messagesOf,
  card,
  th,
  Field,
  NoLines,
  orNull,
} from "./yarn-common";
import { ScTable } from "./prod-sc";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Knit Machine Loading
 * Plan: Planned Shifts (every planned machine-shift with what the floor got)
 * and the Machine Loading Plan — SEARCH above, the grid of machines × day ×
 * shift (click a cell to plan it, or clear it to hand the shift back), and
 * Pending Knit Card below. Nothing reloads.
 */

function useMl() {
  const t = useT("knitting");
  return (k, p) => t(`ml.${k}`, p);
}

const pick = (list, ph) => [{ value: "", label: ph }, ...list];
/** "Day shift (DAY) · 08:00–20:00" → "DAY". */
const shiftCode = (label) => label?.match(/\(([^)]+)\)/)?.[1] ?? label;
const DOW = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const MON = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "May",
  "Jun",
  "Jul",
  "Aug",
  "Sep",
  "Oct",
  "Nov",
  "Dec",
];
/** "Sat 31 Oct", the same on the server and in the browser. */
const dayHead = (d) => {
  const [y, m, day] = d.split("-").map(Number);
  return `${DOW[new Date(Date.UTC(y, m - 1, day)).getUTCDay()]} ${String(day).padStart(2, "0")} ${MON[m - 1]}`;
};

function MlList({ options, initialRows }) {
  const c = useMl();
  const blank = { floor: "", machine: "", shift: "", kc: "" };
  const [f, setFilters] = useState(blank);
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const rows = initialRows.filter(
    (r) =>
      (!f.floor || r.floor_id === f.floor) &&
      (!f.machine || r.machine_id === f.machine) &&
      (!f.shift || r.shift_id === f.shift) &&
      (!f.kc ||
        [r.kc_no, r.job].join(" ").toLowerCase().includes(f.kc.toLowerCase())),
  );
  const columns = [
    {
      key: "plan_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.plan_date),
    },
    { key: "shift", label: c("col.shift") },
    { key: "machine", label: c("col.machine"), nowrap: true },
    { key: "dia_gauge", label: c("col.dia_gauge"), nowrap: true },
    { key: "floor", label: c("col.floor") },
    { key: "kc_no", label: c("col.card"), nowrap: true },
    { key: "job", label: c("col.job") },
    { key: "colour", label: c("col.colour") },
    {
      key: "plan_qty",
      label: c("col.plan"),
      num: true,
      text: (r) => (r.plan_qty === null ? "—" : qtyFmt.format(r.plan_qty)),
    },
    {
      key: "produced",
      label: c("col.produced"),
      num: true,
      text: (r) => qtyFmt.format(r.produced),
    },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={c("f.floor")}>
            <PSelect
              value={f.floor}
              onValueChange={(v) => setF("floor", v)}
              options={pick(options.floors, c("ph.all"))}
              aria-label={c("f.floor")}
            />
          </Field>
          <Field label={c("f.machine")}>
            <PSelect
              value={f.machine}
              onValueChange={(v) => setF("machine", v)}
              options={pick(options.machines, c("ph.all"))}
              aria-label={c("f.machine")}
            />
          </Field>
          <Field label={c("f.shift")}>
            <PSelect
              value={f.shift}
              onValueChange={(v) => setF("shift", v)}
              options={pick(options.shifts, c("ph.all"))}
              aria-label={c("f.shift")}
            />
          </Field>
          <Field label={c("f.card")}>
            <Input
              value={f.kc}
              onChange={(e) => setF("kc", e.target.value)}
              placeholder={c("ph.card")}
              aria-label={c("f.card")}
            />
          </Field>
        </div>
        <div className="mt-4 flex items-center justify-end gap-3 text-[13px] text-foreground-muted">
          {c("shown", { shown: rows.length, total: initialRows.length })}
          <Button variant="outline" size="sm" onClick={() => setFilters(blank)}>
            <FilterX /> {c("btn.clear")}
          </Button>
        </div>
      </section>
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c("hint.list")}
        </p>
        <ScTable
          rows={rows}
          columns={columns}
          exportName="planned-machine-shifts"
          empty={<NoLines title={c("empty.list")} hint="" />}
        />
      </section>
    </div>
  );
}

function MlPlan({ options, initialGrid, canManage, act }) {
  const c = useMl();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blank = {
    from: initialGrid.from,
    range: "1W",
    floor: "",
    dia: "",
    machine: "",
    cat: "",
    colour: "",
    kc: "",
  };
  const [f, setFilters] = useState(blank);
  const [grid, setGrid] = useState(initialGrid);
  const [cell, setCell] = useState(null);
  const [form, setForm] = useState({ kc_no: "", plan_qty: "" });
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const shifts = options.shifts;
  const cardMatch = (x) =>
    (!f.cat || x.category_code === f.cat) &&
    (!f.colour ||
      (x.colour ?? "").toLowerCase().includes(f.colour.toLowerCase())) &&
    (!f.kc ||
      [x.kc_no, x.buyer_style_order]
        .join(" ")
        .toLowerCase()
        .includes(f.kc.toLowerCase()));
  const pendingCards = grid.pending.filter(cardMatch);

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries({
          from: next.from,
          range: next.range,
          floor: next.floor,
          dia: next.dia,
          machine: next.machine,
        }).filter(([, v]) => v),
      );
      const res = await act(`machine-loads/grid?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setGrid(res.data);
      setError(null);
    });
  }
  function openCell(day, shift, machine) {
    if (!canManage) return;
    const held = grid.cells[`${day}|${shift.value}|${machine.value}`];
    setCell({ day, shift, machine, held });
    setForm({
      kc_no: held?.kc_no ?? "",
      plan_qty: held?.plan_qty ? String(held.plan_qty) : "",
    });
    setError(null);
  }
  function save(clear) {
    const key = {
      plan_date: cell.day,
      shift_id: cell.shift.value,
      machine_id: cell.machine.value,
    };
    startTransition(async () => {
      const res = clear
        ? await act("machine-loads/clear", "POST", key)
        : await act("machine-loads", "POST", {
            ...key,
            kc_no: form.kc_no || null,
            plan_qty: orNull(form.plan_qty),
          });
      if (res?.status !== "success") return setError(messagesOf(res));
      const k = `${cell.day}|${cell.shift.value}|${cell.machine.value}`;
      setGrid((g) => {
        const cells = { ...g.cells };
        if (clear) delete cells[k];
        else
          cells[k] = {
            kc_no: res.data.kc_no,
            colour: res.data.colour,
            plan_qty: res.data.plan_qty,
            produced: res.data.produced,
          };
        return { ...g, cells, pending: res.data.pending };
      });
      setCell(null);
      toastManager.add({ title: res.data.message, type: "success" });
    });
  }
  const cardOptions = grid.cards.filter(cardMatch).map((x) => ({
    value: x.kc_no,
    label: [x.kc_no, x.buyer_style_order, x.colour].filter(Boolean).join(" · "),
  }));

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("tabs.plan")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {c("hint.plan")}
        </p>
        <h3 className="mt-4 mb-3 text-sm font-semibold text-foreground">
          {c("sec.search")}
        </h3>
        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
          <Field label={c("f.from")} hint={c("hint.from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={c("f.from")}
            />
          </Field>
          <Field label={c("f.range")}>
            <PSelect
              value={f.range}
              onValueChange={(v) => setF("range", v || "1W")}
              options={options.ranges.map((r) => ({
                value: r,
                label: c(`range.${r}`),
              }))}
              aria-label={c("f.range")}
            />
          </Field>
          <Field label={c("f.company")}>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.floor")}>
            <PSelect
              value={f.floor}
              onValueChange={(v) => setF("floor", v)}
              options={pick(options.floors, c("ph.all"))}
              aria-label={c("f.floor")}
            />
          </Field>
          <Field label={c("f.dia")}>
            <PSelect
              value={f.dia}
              onValueChange={(v) => setF("dia", v)}
              options={pick(
                options.dias.map((d) => ({ value: d, label: d })),
                c("ph.all"),
              )}
              aria-label={c("f.dia")}
            />
          </Field>
          <Field label={c("f.machine")}>
            <PSelect
              value={f.machine}
              onValueChange={(v) => setF("machine", v)}
              options={pick(options.machines, c("ph.all"))}
              aria-label={c("f.machine")}
            />
          </Field>
          <Field label={c("f.cat")}>
            <PSelect
              value={f.cat}
              onValueChange={(v) => setF("cat", v)}
              options={pick(options.categories, c("ph.select"))}
              aria-label={c("f.cat")}
            />
          </Field>
          <Field label={c("f.colour")}>
            <Input
              value={f.colour}
              onChange={(e) => setF("colour", e.target.value)}
              placeholder={c("ph.colour")}
              aria-label={c("f.colour")}
            />
          </Field>
          <Field label={c("f.card")} className="lg:col-span-2">
            <Input
              value={f.kc}
              onChange={(e) => setF("kc", e.target.value)}
              placeholder={c("ph.card")}
              aria-label={c("f.card")}
            />
          </Field>
        </div>
        <div className="mt-4 flex gap-2">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {c("btn.search")}
          </Button>
          <Button
            variant="outline"
            onClick={() => {
              const next = { ...f, from: today };
              setFilters(next);
              search(next);
            }}
            disabled={pending}
          >
            <CalendarCheck /> {c("btn.today")}
          </Button>
        </div>
      </section>
      {error && !cell ? (
        <Alert variant="danger">{error.join(" · ")}</Alert>
      ) : null}
      <section className={card}>
        {grid.machines.length === 0 || shifts.length === 0 ? (
          <NoLines title={c("empty.grid")} hint={c("empty.grid_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="text-xs">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th
                    rowSpan={2}
                    className={cn(th, "sticky left-0 z-10 bg-brand")}
                  >
                    {c("col.machine")}
                  </th>
                  {grid.days.map((d) => (
                    <th
                      key={d}
                      colSpan={shifts.length}
                      className={cn(th, "border-l border-white/20 text-center")}
                    >
                      {dayHead(d)}
                    </th>
                  ))}
                </tr>
                <tr>
                  {grid.days.flatMap((d) =>
                    shifts.map((s) => (
                      <th
                        key={`${d}-${s.value}`}
                        className="border-l border-white/20 px-2 py-1 text-center font-medium"
                      >
                        {shiftCode(s.label)}
                      </th>
                    )),
                  )}
                </tr>
              </thead>
              <tbody>
                {grid.machines.map((m) => (
                  <tr key={m.value} className="border-t border-border">
                    <td className="sticky left-0 z-10 bg-surface px-2 py-2 font-semibold whitespace-nowrap">
                      {m.label}
                      <span className="block font-normal text-foreground-muted">
                        {[m.dia, m.gauge].filter(Boolean).join(" x ")}
                      </span>
                    </td>
                    {grid.days.flatMap((d) =>
                      shifts.map((s) => {
                        const held = grid.cells[`${d}|${s.value}|${m.value}`];
                        return (
                          <td
                            key={`${d}-${s.value}`}
                            className="border-l border-border p-1"
                          >
                            <button
                              type="button"
                              onClick={() => openCell(d, s, m)}
                              disabled={!canManage}
                              aria-label={`${m.label} ${d} ${shiftCode(s.label)}`}
                              className={cn(
                                "h-12 w-28 rounded-md border px-1.5 text-left leading-tight",
                                held
                                  ? "border-brand/40 bg-brand-subtle text-foreground"
                                  : "border-dashed border-border text-foreground-muted hover:border-brand/50",
                              )}
                            >
                              {held ? (
                                <>
                                  <span className="block truncate font-semibold">
                                    {held.kc_no}
                                  </span>
                                  <span className="block truncate">
                                    {held.colour ?? ""}
                                  </span>
                                  <span className="block tabular-nums">
                                    {held.plan_qty === null
                                      ? "—"
                                      : qtyFmt.format(held.plan_qty)}{" "}
                                    / {qtyFmt.format(held.produced)}
                                  </span>
                                </>
                              ) : (
                                "+"
                              )}
                            </button>
                          </td>
                        );
                      }),
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
          {c("hint.rule")}
        </p>
      </section>
      <section className={cn(card, "p-5")}>
        <h3 className="text-base font-semibold text-foreground">
          {c("sec.pending")}{" "}
          <span className="text-sm text-foreground-muted">
            ({pendingCards.length})
          </span>
        </h3>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {c("hint.pending")}
        </p>
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          {pendingCards.length === 0 ? (
            <NoLines title={c("empty.pending")} hint="" />
          ) : (
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["card"],
                    ["job"],
                    ["fabric"],
                    ["colour"],
                    ["construction"],
                    ["assigned", true],
                    ["rolled", true],
                    ["left", true],
                  ].map(([k, right]) => (
                    <th key={k} className={cn(th, right && "text-right")}>
                      {c(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {pendingCards.map((x) => (
                  <tr key={x.kc_no} className="border-t border-border">
                    <td className="px-2 py-2 font-semibold whitespace-nowrap">
                      {x.kc_no}
                    </td>
                    <td className="px-2 py-2">{x.buyer_style_order}</td>
                    <td className="px-2 py-2">{x.fabric}</td>
                    <td className="px-2 py-2">{x.colour ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {[x.machine_dia, x.gauge].filter(Boolean).join(" x ") ||
                        "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(x.assigned)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(x.rolled)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(x.left)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
      <Modal
        open={Boolean(cell)}
        onOpenChange={(o) => (o ? null : setCell(null))}
        title={
          cell
            ? `${cell.machine.label} — ${dayFmt(cell.day)} · ${shiftCode(cell.shift.label)}`
            : ""
        }
        description={
          cell
            ? c("hint.cell", {
                machine: cell.machine.label,
                day: dayFmt(cell.day),
                shift: shiftCode(cell.shift.label),
              })
            : ""
        }
        className="max-w-xl"
        footer={
          <div className="flex gap-2">
            {cell?.held ? (
              <Button
                variant="outline"
                className="text-danger"
                onClick={() => save(true)}
                disabled={pending}
              >
                <Eraser /> {c("btn.clear_cell")}
              </Button>
            ) : null}
            <Button
              variant="outline"
              onClick={() => setCell(null)}
              disabled={pending}
            >
              <X /> {c("btn.cancel")}
            </Button>
            <Button onClick={() => save(false)} loading={pending}>
              <Save /> {c("btn.plan")}
            </Button>
          </div>
        }
      >
        {error && cell ? (
          <Alert variant="danger">{error.join(" · ")}</Alert>
        ) : null}
        <div className="grid gap-4">
          <Field label={c("f.card")} required>
            <PSelect
              value={form.kc_no}
              onValueChange={(v) => setForm((x) => ({ ...x, kc_no: v ?? "" }))}
              options={pick(cardOptions, c("ph.select"))}
              aria-label={`${c("f.card")} (${c("btn.plan")})`}
            />
          </Field>
          <Field label={c("f.plan_qty")}>
            <Input
              type="number"
              min="0"
              step="any"
              value={form.plan_qty}
              onChange={(e) =>
                setForm((x) => ({ ...x, plan_qty: e.target.value }))
              }
              placeholder={c("ph.plan_default")}
              aria-label={c("f.plan_qty")}
            />
          </Field>
        </div>
      </Modal>
    </div>
  );
}

export { MlList, MlPlan };
