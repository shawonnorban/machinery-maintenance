"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { Clock, Save, Search, Wallet } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import {
  Card,
  Chip,
  Files,
  Empty,
  Field,
  Footer,
  NumberCell,
  keepQuery,
  blank,
  asInput,
  num,
  th,
} from "./costing";

/*
 * Work Study → OT & Salary (AnnotechERP 14/06), the two sheets Brickland
 * edits a whole page at a time: Monthly Salary & OT Rates' Cost Particulars
 * (a year's twelve months) and the Garment Overtime Plan's OT Plan (a floor's
 * manpower groups against the hours from 6PM to 5AM). Each reads in place —
 * the url keeps the year or the floor and day — with PDF and Excel once there
 * is something on it, and Save.
 */

const RATES = ["op_salary", "aso_salary", "op_ot_rate", "aso_ot_rate"];
const BASE = "/api/work-study/ot";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;

/**
 * Cost Particulars: one company's average OP and ASO salary and OT rate for
 * every month of a year.
 *
 * @param {{ options: object, act: Function, canManage: boolean, initialYear?: string }} props
 */
function RateSheet({ options, act, canManage, initialYear }) {
  const t = useT("workstudy");
  const o = (k, p) => t(`ot.${k}`, p);
  const toastManager = useToastManager();
  const [company, setCompany] = useState(
    initialYear ? options.company.value : "",
  );
  const [year, setYear] = useState(initialYear ?? "");
  const [sheet, setSheet] = useState(null);
  const [rows, setRows] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function show(y = year) {
    if (!company || !y) return;
    setError(null);
    startTransition(async () => {
      const res = await act("rates", { year: y });
      if (res?.status === "success") {
        setSheet(res.data);
        setRows(res.data.rows);
        setDirty(false);
        keepQuery({ year: String(y) });
      } else setError(firstError(res));
    });
  }

  function save() {
    setError(null);
    startTransition(async () => {
      const res = await act("saveRates", {
        year: sheet.year,
        rows: rows.map((r) => ({
          month: r.month,
          ...Object.fromEntries(
            RATES.map((k) => [k, blank(r[k]) ? null : r[k]]),
          ),
        })),
      });
      if (res?.status === "success") {
        setSheet(res.data);
        setRows(res.data.rows);
        setDirty(false);
        toastManager.add({
          title: o("rate.saved", { year: res.data.year }),
          type: "success",
        });
      } else setError(firstError(res));
    });
  }

  // A link to the year (?year=) opens on its sheet.
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && initialYear) {
      opened.current = true;
      show(initialYear);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const costed = rows.filter((r) => RATES.some((k) => num(r[k]) > 0)).length;
  const monthName = (m) => t(`cost.months.${m}`);

  return (
    <Card
      icon={Wallet}
      title={o("rate.title")}
      hint={o("rate.hint")}
      right={
        <>
          {sheet && costed > 0 ? (
            <Files
              sheet="rates"
              query={{ year: sheet.year }}
              c={(k) => t(`cost.${k}`)}
              base={BASE}
            />
          ) : null}
          <Chip>{o("rate.count", { count: sheet ? 12 : 0 })}</Chip>
        </>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={o("rate.company")}>
          <Select
            value={company}
            onValueChange={(v) => setCompany(v ?? "")}
            options={[options.company]}
            placeholder={t("cost.sheet.select_ph")}
            aria-label={o("rate.company")}
          />
        </Field>
        <Field label={o("rate.year")}>
          <Select
            value={year}
            onValueChange={(v) => setYear(v ?? "")}
            options={options.years.map((y) => ({
              value: String(y),
              label: String(y),
            }))}
            placeholder={t("cost.sheet.select_ph")}
            aria-label={o("rate.year")}
          />
        </Field>
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => show()}
          loading={pending && !dirty}
          disabled={!company || !year}
        >
          <Search className="size-4" aria-hidden /> {o("rate.show")}
        </Button>
      </div>
      {error ? (
        <p className="mx-5 mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-sm text-danger">
          {error}
        </p>
      ) : null}
      {!sheet ? (
        <Empty title={o("rate.pick_title")} text={o("rate.pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className={th}>{o("rate.col.month")}</th>
                {RATES.map((k) => (
                  <th key={k} className={cn(th, "text-right")}>
                    {o(`rate.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.month}
                  className="border-b border-border last:border-0"
                >
                  <td className="px-3 py-1.5 font-medium">
                    {monthName(r.month)}
                  </td>
                  {RATES.map((k) => (
                    <td key={k} className="px-3 py-1.5 text-right">
                      <div className="flex justify-end">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${monthName(r.month)} ${o(`rate.col.${k}`)}`}
                          w="w-28"
                        />
                      </div>
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={
          sheet
            ? `${o("rate.summary", { year: sheet.year, costed })} — ${o("rate.footer")}`
            : o("rate.footer")
        }
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!sheet || !dirty}
          >
            <Save className="size-4" aria-hidden /> {o("rate.save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/**
 * OT Plan: every manpower group on a floor for a day, and how many operators
 * and ASOs of each stay to each hour from 6PM to 5AM.
 *
 * @param {{ options: object, act: Function, canManage: boolean, initial?: {floor?: string, date?: string} }} props
 */
function OtPlanSheet({ options, act, canManage, initial = {} }) {
  const t = useT("workstudy");
  const o = (k, p) => t(`ot.${k}`, p);
  const d = (k, p) => t(`cost.day.${k}`, p);
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    options.floors.some((f) => f.code === initial.floor) ? initial.floor : "",
  );
  const [date, setDate] = useState(
    /^\d{4}-\d{2}-\d{2}$/.test(initial.date ?? "") ? initial.date : "",
  );
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function refresh(f = floor, dt = date) {
    if (!f || !dt) return;
    setError(null);
    startTransition(async () => {
      const res = await act("plan", { floor: f, date: dt });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setShown({ floor: f, date: dt });
        setDirty(false);
        keepQuery({ floor: f, date: dt });
      } else setError(firstError(res));
    });
  }

  function save() {
    setError(null);
    const clean = rows.map((r) => ({
      planning_line_id: r.planning_line_id,
      department_id: r.department_id,
      designation_id: r.designation_id,
      mp_gen: blank(r.mp_gen) ? null : r.mp_gen,
      wh_gen: blank(r.wh_gen) ? null : r.wh_gen,
      slots: r.slots,
      remarks: r.remarks || null,
    }));
    startTransition(async () => {
      const res = await act("savePlan", { ...shown, rows: clean });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setDirty(false);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(firstError(res));
    });
  }

  // A link to the floor and day (?floor=&date=) opens on its sheet.
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && floor && date) {
      opened.current = true;
      refresh(floor, date);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const setSlot = (i, slot, who, value) => {
    setRows((all) =>
      all.map((r, j) =>
        j === i
          ? {
              ...r,
              slots: {
                ...r.slots,
                [slot]: {
                  op: 0,
                  aso: 0,
                  ...r.slots?.[slot],
                  [who]: value === "" ? 0 : Number(value),
                },
              },
            }
          : r,
      ),
    );
    setDirty(true);
  };
  const slotCount = (rows ?? []).reduce(
    (s, r) =>
      s +
      Object.values(r.slots ?? {}).filter((x) => num(x.op) + num(x.aso) > 0)
        .length,
    0,
  );
  const day = shown
    ? new Date(`${shown.date}T00:00:00`).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "";
  const groupLabel = (r) =>
    [r.department, r.line, r.designation].filter(Boolean).join(" ");

  return (
    <Card
      icon={Clock}
      title={o("plan.title")}
      hint={o("plan.hint")}
      right={
        <>
          {rows && slotCount > 0 ? (
            <Files
              sheet="ot-plan"
              query={shown}
              c={(k) => t(`cost.${k}`)}
              base={BASE}
            />
          ) : null}
          <Chip>{o("plan.count", { count: rows?.length ?? 0 })}</Chip>
        </>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={d("floor")}>
          <Select
            value={floor}
            onValueChange={(v) => setFloor(v ?? "")}
            options={options.floors.map((f) => ({
              value: f.code,
              label: f.label,
            }))}
            placeholder={d("floor_ph")}
            aria-label={d("floor")}
          />
        </Field>
        <Field label={d("date")}>
          <Input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            aria-label={d("date")}
          />
        </Field>
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => refresh()}
          loading={pending && !dirty}
          disabled={!floor || !date}
        >
          <Search className="size-4" aria-hidden /> {d("refresh")}
        </Button>
      </div>
      {error ? (
        <p className="mx-5 mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-sm text-danger">
          {error}
        </p>
      ) : null}
      {!rows ? (
        <Empty title={d("pick_title")} text={d("pick")} />
      ) : rows.length === 0 ? (
        <Empty title={o("plan.empty")} text={d("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[2200px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["department", "line", "designation"].map((k) => (
                  <th key={k} className={th}>
                    {o(`plan.col.${k}`)}
                  </th>
                ))}
                {["mp_gen", "prev_wh", "wh_gen"].map((k) => (
                  <th key={k} className={cn(th, "text-right")}>
                    {o(`plan.col.${k}`)}
                  </th>
                ))}
                {options.slots.flatMap((s) => [
                  <th key={`${s.slot}op`} className={cn(th, "text-right")}>
                    {s.label} OP
                  </th>,
                  <th key={`${s.slot}aso`} className={cn(th, "text-right")}>
                    {s.label} ASO
                  </th>,
                ])}
                <th className={th}>{o("plan.col.remarks")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={`${r.planning_line_id}|${r.department_id}|${r.designation_id}`}
                  className="border-b border-border last:border-0"
                >
                  <td className="whitespace-nowrap px-3 py-1.5">
                    {r.department ?? "—"}
                  </td>
                  <td className="whitespace-nowrap px-3 py-1.5">
                    {r.line ?? "—"}
                  </td>
                  <td className="whitespace-nowrap px-3 py-1.5">
                    {r.designation ?? "—"}
                  </td>
                  <td className="px-2 py-1.5 text-right">
                    <NumberCell
                      value={asInput(r.mp_gen)}
                      onChange={(v) => set(i, "mp_gen", v)}
                      disabled={!canManage}
                      label={`${groupLabel(r)} ${o("plan.col.mp_gen")}`}
                      w="w-20"
                    />
                  </td>
                  <td className="px-3 py-1.5 text-right tabular-nums">
                    {blank(r.prev_wh) ? "—" : Number(r.prev_wh)}
                  </td>
                  <td className="px-2 py-1.5 text-right">
                    <NumberCell
                      value={asInput(r.wh_gen)}
                      onChange={(v) => set(i, "wh_gen", v)}
                      disabled={!canManage}
                      label={`${groupLabel(r)} ${o("plan.col.wh_gen")}`}
                      w="w-20"
                    />
                  </td>
                  {options.slots.flatMap((s) =>
                    ["op", "aso"].map((who) => (
                      <td
                        key={`${s.slot}${who}`}
                        className="px-2 py-1.5 text-right"
                      >
                        <NumberCell
                          value={asInput(r.slots?.[s.slot]?.[who] ?? 0)}
                          onChange={(v) => setSlot(i, s.slot, who, v)}
                          disabled={!canManage}
                          label={`${groupLabel(r)} ${s.label} ${who.toUpperCase()}`}
                          w="w-20"
                        />
                      </td>
                    )),
                  )}
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.remarks ?? ""}
                      onChange={(e) => set(i, "remarks", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-48"
                      aria-label={`${groupLabel(r)} ${o("plan.col.remarks")}`}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={
          rows
            ? `${o("plan.summary", { date: day, groups: rows.length, slots: slotCount })} — ${o("plan.footer")}`
            : o("plan.footer")
        }
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows || rows.length === 0 || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

export { RateSheet, OtPlanSheet };
