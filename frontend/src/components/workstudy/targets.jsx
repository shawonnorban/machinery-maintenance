"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import {
  CalendarRange,
  CheckCheck,
  Copy,
  Crosshair,
  RotateCcw,
  Save,
  Scale,
  Search,
  Shuffle,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { useToastManager } from "@/components/ui/toast";
import { MultiPick } from "@/components/erp/multi-pick";
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
 * Work Study → Target & Feeding (AnnotechERP 14/03), the sheets Brickland
 * edits in place: Excess Yarn Target (a month's allowance per department),
 * the Style Changeover Dashboard (ticked rows finalised, reopened or
 * removed), and Garment Target Setting's target sheet, Production Entry,
 * Production Plans and NPT Entry. Each reads on Show / Search / Refresh
 * without a reload — the url keeps what it was read for — with PDF and Excel
 * where Brickland has them.
 */

const BASE = "/api/work-study/targets";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
const fmtDay = (d) =>
  d
    ? new Date(`${d}T00:00:00`).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "—";
const thousands = (v) => Number(v || 0).toLocaleString("en-US");

function useTf() {
  const t = useT("workstudy");
  return { t, f: (k, p) => t(`tf.${k}`, p), files: (k) => t(`cost.${k}`) };
}

function ErrorLine({ error }) {
  return error ? (
    <p className="mx-5 mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-sm text-danger">
      {error}
    </p>
  ) : null;
}

/** A floor (by code), and the lines of it (by code), as the line sheets pick them. */
function FloorLine({ options, floor, setFloor, line, setLine, f, d }) {
  const floorId = options.floors.find((x) => x.code === floor)?.value;
  return (
    <>
      <Field label={d("floor")}>
        <Select
          value={floor}
          onValueChange={(v) => {
            setFloor(v ?? "");
            setLine("");
          }}
          options={options.floors.map((x) => ({
            value: x.code,
            label: x.label,
          }))}
          placeholder={d("floor_ph")}
          aria-label={d("floor")}
        />
      </Field>
      <Field label={f("line")}>
        <Select
          value={line}
          onValueChange={(v) => setLine(v ?? "")}
          options={options.lines
            .filter((l) => l.floor_id === floorId)
            .map((l) => ({ value: l.code, label: l.code }))}
          placeholder={f("line_ph")}
          aria-label={f("line")}
        />
      </Field>
    </>
  );
}

/* ---------------- Excess Yarn Target ---------------- */

function YarnSheet({ options, act, canManage, initialPeriod }) {
  const { t, f } = useTf();
  const toastManager = useToastManager();
  const [company, setCompany] = useState(
    initialPeriod ? options.company.value : "",
  );
  const [period, setPeriod] = useState(initialPeriod ?? "");
  const [copy, setCopy] = useState("");
  const [sheet, setSheet] = useState(null);
  const [rows, setRows] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const label = (p) =>
    `${p.slice(0, 4)}|${t(`cost.months.${Number(p.slice(5, 7))}`)}`;

  function show(p = period, from = copy) {
    if (!company || !p) return;
    setError(null);
    startTransition(async () => {
      const res = await act("yarn", { period: p, copy: from || undefined });
      if (res?.status === "success") {
        setSheet(res.data);
        setRows(res.data.rows);
        setDirty(Boolean(res.data.copied_from));
        keepQuery({ period: p });
      } else setError(firstError(res));
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act("saveYarn", {
        period: sheet.period,
        rows: rows.map((r) => ({
          department_id: r.department_id,
          target_qty: blank(r.target_qty) ? null : r.target_qty,
        })),
      });
      if (res?.status === "success") {
        setSheet(res.data);
        setRows(res.data.rows);
        setDirty(false);
        setCopy("");
        toastManager.add({
          title: f("yarn.saved", { month: label(res.data.period) }),
          type: "success",
        });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && initialPeriod) {
      opened.current = true;
      show(initialPeriod, "");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, v) => {
    setRows((all) =>
      all.map((r, j) => (j === i ? { ...r, target_qty: v } : r)),
    );
    setDirty(true);
  };
  const withTarget = rows.filter((r) => !blank(r.target_qty)).length;

  return (
    <Card
      icon={Scale}
      title={f("yarn.title")}
      hint={f("yarn.hint")}
      right={
        <Chip>
          {f("yarn.summary", {
            month: sheet ? label(sheet.period) : "—",
            count: rows.length,
            set: withTarget,
          })}
        </Chip>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={f("yarn.company")}>
          <Select
            value={company}
            onValueChange={(v) => setCompany(v ?? "")}
            options={[options.company]}
            placeholder={t("cost.sheet.select_ph")}
            aria-label={f("yarn.company")}
          />
        </Field>
        <Field label={f("yarn.month")}>
          <Select
            value={period}
            onValueChange={(v) => setPeriod(v ?? "")}
            options={options.periods.map((p) => ({
              value: p,
              label: label(p),
            }))}
            placeholder={f("yarn.month_ph")}
            aria-label={f("yarn.month")}
          />
        </Field>
        <Field label={f("yarn.copy")} help={f("yarn.copy_help")}>
          <Select
            value={copy}
            onValueChange={(v) => setCopy(v ?? "")}
            options={options.periods
              .filter((p) => p !== period)
              .map((p) => ({ value: p, label: label(p) }))}
            placeholder={f("yarn.month_ph")}
            aria-label={f("yarn.copy")}
          />
        </Field>
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => show()}
          loading={pending && !sheet}
          disabled={!company || !period}
        >
          <Search className="size-4" aria-hidden /> {f("yarn.show")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!sheet ? (
        <Empty title={f("yarn.pick_title")} text={t("cost.sheet.pick")} />
      ) : (
        <>
          {sheet.copied_from ? (
            <p className="mx-5 mb-3 rounded-md bg-warning-subtle px-3 py-2 text-[13px] text-warning">
              {f("yarn.copied", { month: label(sheet.copied_from) })}
            </p>
          ) : null}
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-[520px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{f("yarn.col.department")}</th>
                  <th className={cn(th, "text-right")}>
                    {f("yarn.col.target_qty")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr
                    key={r.department_id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-3 py-1.5">{r.department}</td>
                    <td className="px-3 py-1.5">
                      <div className="flex justify-end">
                        <NumberCell
                          value={asInput(r.target_qty)}
                          onChange={(v) => set(i, v)}
                          disabled={!canManage}
                          label={`${r.department} ${f("yarn.col.target_qty")}`}
                          w="w-32"
                        />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
      <Footer text={f("yarn.footer")}>
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && Boolean(sheet)}
            disabled={!sheet || (!dirty && !sheet?.copied_from)}
          >
            <Save className="size-4" aria-hidden /> {f("yarn.save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- Style Changeover Dashboard ---------------- */

function ChangeoverBoard({ options, act, canManage }) {
  const { t, f } = useTf();
  const d = (k, p) => t(`cost.day.${k}`, p);
  const toastManager = useToastManager();
  const [q, setQ] = useState({
    floor: "",
    line: "",
    from: "",
    to: "",
    buyer: "",
    order: "",
  });
  const [rows, setRows] = useState(null);
  const [ticked, setTicked] = useState([]);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) =>
    setQ((all) => ({
      ...all,
      [k]: v ?? "",
      ...(k === "floor" ? { line: "" } : {}),
    }));

  function search() {
    setError(null);
    startTransition(async () => {
      const res = await act("changeovers", q);
      if (res?.status === "success") {
        setRows(res.data.rows);
        setTicked([]);
      } else setError(firstError(res));
    });
  }
  function run(action) {
    setError(null);
    startTransition(async () => {
      const res = await act("changeoverAction", { action, ids: ticked });
      if (res?.status === "success") {
        toastManager.add({
          title: f("sco.done", { count: res.data.count }),
          type: "success",
        });
        search();
      } else setError(firstError(res));
    });
  }
  const minutes = (rows ?? []).reduce((s, r) => s + num(r.sco_minutes), 0);
  const open = (rows ?? []).filter((r) => !r.finalized).length;
  const cols = [
    "date",
    "buyer",
    "order",
    "gmt_item",
    "line",
    "sco",
    "ft",
    "remarks",
    "feeding_start",
    "feeding_end",
    "prev_buyer",
    "prev_order",
    "prev_gmt_item",
    "output_on",
    "finalized",
  ];
  const cell = (r, k) =>
    ({
      date: fmtDay(r.changeover_date),
      buyer: r.new_buyer ?? "—",
      order: r.new_order,
      gmt_item: r.new_gmt_item ?? "—",
      line: r.line,
      sco: r.sco_minutes,
      ft: r.ft_hours === null ? "—" : Number(r.ft_hours),
      remarks: r.remarks ?? "—",
      feeding_start: r.feeding_start ? fmtDay(r.feeding_start) : "—",
      feeding_end: r.feeding_end ? fmtDay(r.feeding_end) : "—",
      prev_buyer: r.prev_buyer ?? "—",
      prev_order: r.prev_order ?? "—",
      prev_gmt_item: r.prev_gmt_item ?? "—",
      output_on: r.prev_output_on ? fmtDay(r.prev_output_on) : "—",
      finalized: r.finalized ? f("yes") : f("no"),
    })[k];
  const floorId = options.floors.find((x) => x.code === q.floor)?.value;

  return (
    <Card
      icon={Shuffle}
      title={f("sco.title")}
      hint={f("sco.hint")}
      right={
        <Chip>
          {rows
            ? f("sco.summary", {
                count: rows.length,
                minutes,
                open,
                ticked: ticked.length,
              })
            : "—"}
        </Chip>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={f("sco.floor")}>
          <Select
            value={q.floor}
            onValueChange={set("floor")}
            options={options.floors.map((x) => ({
              value: x.code,
              label: x.label,
            }))}
            placeholder={d("floor_ph")}
            aria-label={f("sco.floor")}
          />
        </Field>
        <Field label={f("sco.line")}>
          <Select
            value={q.line}
            onValueChange={set("line")}
            options={options.lines
              .filter((l) => l.floor_id === floorId)
              .map((l) => ({ value: l.code, label: l.code }))}
            placeholder={f("line_ph")}
            aria-label={f("sco.line")}
          />
        </Field>
        <Field label={f("sco.from")}>
          <Input
            type="date"
            value={q.from}
            onChange={(e) => set("from")(e.target.value)}
            aria-label={f("sco.from")}
          />
        </Field>
        <Field label={f("sco.to")}>
          <Input
            type="date"
            value={q.to}
            onChange={(e) => set("to")(e.target.value)}
            aria-label={f("sco.to")}
          />
        </Field>
        <Field label={f("sco.buyer")}>
          <Select
            value={q.buyer}
            onValueChange={set("buyer")}
            options={[{ value: "", label: f("select") }, ...options.buyers]}
            placeholder={f("select")}
            aria-label={f("sco.buyer")}
          />
        </Field>
        <Field label={f("sco.order")}>
          <Select
            value={q.order}
            onValueChange={set("order")}
            options={[
              { value: "", label: f("select") },
              ...options.orders.map((o) => ({
                value: o.value,
                label: o.label,
              })),
            ]}
            placeholder={f("select")}
            aria-label={f("sco.order")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button variant="outline" onClick={search} loading={pending && !rows}>
          <Search className="size-4" aria-hidden /> {f("sco.search")}
        </Button>
        {canManage ? (
          <>
            <Button
              variant="outline"
              onClick={() => run("finalize")}
              disabled={!ticked.length || pending}
            >
              <CheckCheck className="size-4" aria-hidden /> {f("sco.finalize")}
            </Button>
            <Button
              variant="outline"
              onClick={() => run("reopen")}
              disabled={!ticked.length || pending}
            >
              <RotateCcw className="size-4" aria-hidden /> {f("sco.reopen")}
            </Button>
            <Button
              variant="outline"
              className="text-danger"
              onClick={() => run("remove")}
              disabled={!ticked.length || pending}
            >
              <Trash2 className="size-4" aria-hidden /> {f("sco.remove")}
            </Button>
          </>
        ) : null}
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={f("sco.pick_title")} text={f("sco.pick")} />
      ) : rows.length === 0 ? (
        <Empty title={f("sco.empty")} text={f("sco.pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1700px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className={th}>
                  <Checkbox
                    checked={ticked.length === rows.length}
                    onCheckedChange={(v) =>
                      setTicked(v === true ? rows.map((r) => r.id) : [])
                    }
                    aria-label={f("sco.finalize")}
                  />
                </th>
                {cols.map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["sco", "ft"].includes(k) && "text-right",
                    )}
                  >
                    {f(`sco.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2">
                    <Checkbox
                      checked={ticked.includes(r.id)}
                      onCheckedChange={(v) =>
                        setTicked((all) =>
                          v === true
                            ? [...all, r.id]
                            : all.filter((x) => x !== r.id),
                        )
                      }
                      aria-label={`${r.new_order} ${fmtDay(r.changeover_date)}`}
                    />
                  </td>
                  {cols.map((k) => (
                    <td
                      key={k}
                      className={cn(
                        "whitespace-nowrap px-3 py-2",
                        ["sco", "ft"].includes(k) && "text-right tabular-nums",
                        k === "finalized" &&
                          r.finalized &&
                          "font-semibold text-success",
                      )}
                    >
                      {cell(r, k)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer text={f("sco.footer")} />
    </Card>
  );
}

/* ---------------- Sewing Target Setting ---------------- */

const TARGET_NUMBERS = [
  "fob",
  "smv",
  "day_run",
  "lo_effi",
  "t_effi",
  "wh",
  "wh_from",
  "wh_to",
  "used_op",
  "used_aso",
  "layout_op",
  "layout_aso",
  "wip_rfi",
  "plan",
];
const genTarget = (r) => {
  const smv = num(r.smv);
  return smv > 0
    ? Math.floor(
        ((num(r.used_op) + num(r.used_aso)) * num(r.wh) * 60 * num(r.t_effi)) /
          100 /
          smv +
          1e-9,
      )
    : 0;
};

function TargetSheet({ options, act, canManage, initial = {} }) {
  const { t, f, files } = useTf();
  const d = (k, p) => t(`cost.day.${k}`, p);
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    options.floors.some((x) => x.code === initial.floor) ? initial.floor : "",
  );
  const [date, setDate] = useState(
    /^\d{4}-\d{2}-\d{2}$/.test(initial.date ?? "") ? initial.date : "",
  );
  const [from, setFrom] = useState("");
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function refresh(fl = floor, dt = date) {
    if (!fl || !dt) return;
    setError(null);
    startTransition(async () => {
      const res = await act("targets", { floor: fl, date: dt });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setShown({ floor: fl, date: dt });
        setDirty(false);
        keepQuery({ floor: fl, date: dt });
      } else setError(firstError(res));
    });
  }
  function startCopy() {
    setError(null);
    startTransition(async () => {
      const res = await act("copyTargets", { ...shown, from });
      if (res?.status === "success") {
        setRows(res.data.rows);
        toastManager.add({
          title: f("sheet.copied", { count: res.data.copied }),
          type: "success",
        });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const keys = [
      "id",
      "planning_line_id",
      "buyer_id",
      "order_style",
      "gmt_item",
      "ship_date",
      ...TARGET_NUMBERS,
      "target_ot",
      "prod_gen",
      "prod_ot",
      "input_min",
      "prod_min",
      "ttl_value",
      "is_car",
      "is_merged",
      "is_single",
      "is_off",
    ];
    const clean = rows.map((r) =>
      Object.fromEntries(
        keys.map((k) => [
          k,
          typeof r[k] === "boolean" ? r[k] : blank(r[k]) ? null : r[k],
        ]),
      ),
    );
    startTransition(async () => {
      const res = await act("saveTargets", { ...shown, rows: clean });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setDirty(false);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(firstError(res));
    });
  }
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

  const set_ = (rows ?? []).filter((r) => !r.spare || r.order_style);
  const sum = (k) => set_.reduce((s, r) => s + num(r[k]), 0);
  const ttlTarget = (r) => genTarget(r) + num(r.target_ot);
  const ttlProd = (r) => num(r.prod_gen) + num(r.prod_ot);
  const lineCount = new Set((rows ?? []).map((r) => r.planning_line_id)).size;
  const summary = rows
    ? f("sheet.summary", {
        date: fmtDay(shown?.date),
        lines: lineCount,
        orders: set_.filter((r) => r.order_style).length,
        op: sum("used_op"),
        lop: sum("layout_op"),
        aso: sum("used_aso"),
        laso: sum("layout_aso"),
        target: thousands(set_.reduce((s, r) => s + ttlTarget(r), 0)),
        prod: thousands(set_.reduce((s, r) => s + ttlProd(r), 0)),
        imin: thousands(sum("input_min")),
        pmin: thousands(sum("prod_min")),
        value: thousands(sum("ttl_value")),
      })
    : null;
  const text = (i, r, k, w = "w-32") => (
    <Input
      value={r[k] ?? ""}
      onChange={(e) => set(i, k, e.target.value)}
      disabled={!canManage}
      className={cn("h-8", w)}
      aria-label={`${r.line} ${f(`sheet.col.${k}`)}`}
    />
  );
  const number = (i, r, k, w = "w-20") => (
    <NumberCell
      value={asInput(r[k])}
      onChange={(v) => set(i, k, v)}
      disabled={!canManage}
      label={`${r.line} ${f(`sheet.col.${k}`)}`}
      w={w}
    />
  );
  const check = (i, r, k) => (
    <Checkbox
      checked={!!r[k]}
      onCheckedChange={(v) => set(i, k, v === true)}
      disabled={!canManage}
      aria-label={`${r.line} ${f(`sheet.col.${k}`)}`}
    />
  );

  return (
    <Card
      icon={Crosshair}
      title={f("sheet.title")}
      hint={f("sheet.hint")}
      right={
        <>
          {rows && set_.length ? (
            <Files sheet="targets" query={shown} c={files} base={BASE} />
          ) : null}
          <Chip>{f("sheet.count", { count: rows ? lineCount : 0 })}</Chip>
        </>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={d("floor")}>
          <Select
            value={floor}
            onValueChange={(v) => setFloor(v ?? "")}
            options={options.floors.map((x) => ({
              value: x.code,
              label: x.label,
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
        <Field label={f("sheet.copy")} help={f("sheet.copy_help")}>
          <div className="flex gap-2">
            <Input
              type="date"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              aria-label={f("sheet.copy")}
            />
            {canManage ? (
              <Button
                variant="outline"
                onClick={startCopy}
                disabled={!shown || !from || pending}
              >
                <Copy className="size-4" aria-hidden /> {f("sheet.start_copy")}
              </Button>
            ) : null}
          </div>
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
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={d("pick_title")} text={d("pick")} />
      ) : rows.length === 0 ? (
        <Empty title={d("empty_lines")} text={d("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[3600px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "line",
                  "buyer",
                  "order_style",
                  "gmt_item",
                  "ship_date",
                  "fob",
                  "smv",
                  "is_car",
                  "day_run",
                  "lo_effi",
                  "t_effi",
                  "wh",
                  "wh_from",
                  "wh_to",
                  "used_op",
                  "used_aso",
                  "layout_op",
                  "layout_aso",
                  "wip_rfi",
                  "plan",
                  "target_gen",
                  "target_ot",
                  "ttl_target",
                  "prod_gen",
                  "prod_ot",
                  "ttl_prod",
                  "input_min",
                  "prod_min",
                  "ttl_value",
                  "is_merged",
                  "is_single",
                  "is_off",
                ].map((k) => (
                  <th key={k} className={th}>
                    {f(`sheet.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.id ?? `spare-${r.planning_line_id}-${i}`}
                  className={cn(
                    "border-b border-border last:border-0",
                    r.spare && "bg-surface-muted/40",
                  )}
                >
                  <td className="whitespace-nowrap px-3 py-1.5 font-medium">
                    {r.spare &&
                    i > 0 &&
                    rows[i - 1].planning_line_id === r.planning_line_id
                      ? "—"
                      : r.line}
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-40">
                      <Select
                        value={r.buyer_id ?? ""}
                        onValueChange={(v) => set(i, "buyer_id", v || null)}
                        options={[{ value: "", label: "—" }, ...options.buyers]}
                        disabled={!canManage}
                        aria-label={`${r.line} ${f("sheet.col.buyer")}`}
                      />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    {text(i, r, "order_style", "w-36")}
                  </td>
                  <td className="px-2 py-1.5">{text(i, r, "gmt_item")}</td>
                  <td className="px-2 py-1.5">
                    <Input
                      type="date"
                      value={r.ship_date ?? ""}
                      onChange={(e) => set(i, "ship_date", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-36"
                      aria-label={`${r.line} ${f("sheet.col.ship_date")}`}
                    />
                  </td>
                  <td className="px-2 py-1.5">{number(i, r, "fob")}</td>
                  <td className="px-2 py-1.5">{number(i, r, "smv")}</td>
                  <td className="px-3 py-1.5">{check(i, r, "is_car")}</td>
                  {[
                    "day_run",
                    "lo_effi",
                    "t_effi",
                    "wh",
                    "wh_from",
                    "wh_to",
                    "used_op",
                    "used_aso",
                    "layout_op",
                    "layout_aso",
                    "wip_rfi",
                    "plan",
                  ].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      {number(i, r, k)}
                    </td>
                  ))}
                  <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                    {genTarget(r)}
                  </td>
                  <td className="px-2 py-1.5">{number(i, r, "target_ot")}</td>
                  <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                    {ttlTarget(r)}
                  </td>
                  <td className="px-2 py-1.5">{number(i, r, "prod_gen")}</td>
                  <td className="px-2 py-1.5">{number(i, r, "prod_ot")}</td>
                  <td
                    className={cn(
                      "px-3 py-1.5 text-right font-semibold tabular-nums",
                      ttlProd(r) < ttlTarget(r) &&
                        r.order_style &&
                        "text-danger",
                    )}
                  >
                    {ttlProd(r)}
                  </td>
                  {["input_min", "prod_min", "ttl_value"].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      {number(i, r, k, "w-24")}
                    </td>
                  ))}
                  {["is_merged", "is_single", "is_off"].map((k) => (
                    <td key={k} className="px-3 py-1.5">
                      {check(i, r, k)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={summary ? `${summary} — ${f("sheet.footer")}` : f("sheet.footer")}
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- Production Entry / Production Plans ---------------- */

function AllocationSheet({ mode, options, act, canManage, initial = {} }) {
  const { t, f, files } = useTf();
  const d = (k, p) => t(`cost.day.${k}`, p);
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    options.floors.some((x) => x.code === initial.floor) ? initial.floor : "",
  );
  const [line, setLine] = useState(initial.line ?? "");
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const plans = mode === "plans";

  function refresh(fl = floor, ln = line) {
    if (!fl || !ln) return;
    setError(null);
    startTransition(async () => {
      const res = await act("allocations", { floor: fl, line: ln, view: mode });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setShown({ floor: fl, line: ln });
        setDirty(false);
        keepQuery({ floor: fl, line: ln });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const clean = plans
      ? rows.map((r) => ({
          id: r.id,
          in_target: !!r.in_target,
          status: r.status,
        }))
      : rows.map((r) =>
          Object.fromEntries(
            [
              "id",
              "buyer_id",
              "order_style",
              "style",
              "item",
              "smv",
              "input_date",
              "plan_start",
              "plan_end",
              "allocation",
              "production",
            ].map((k) => [k, blank(r[k]) ? null : r[k]]),
          ),
        );
    startTransition(async () => {
      const res = await act(plans ? "savePlans" : "saveAllocations", {
        ...shown,
        rows: clean,
      });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setDirty(false);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && floor && line) {
      opened.current = true;
      refresh(floor, line);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const real = (rows ?? []).filter((r) => r.id);
  const allocated = real.reduce((s, r) => s + num(r.allocation), 0);
  const made = real.reduce((s, r) => s + num(r.production), 0);
  const achieved = (r) =>
    r.id
      ? `${thousands(r.production)} · ${r.achieved_pct ?? 0}% [${thousands(r.remaining)}]`
      : "—";
  const planned = (r) =>
    r.plan_start || r.plan_end
      ? `${fmtDay(r.plan_start)} → ${fmtDay(r.plan_end)}`
      : "—";
  const summary = !rows
    ? null
    : plans
      ? f("plans.summary", {
          line: shown?.line,
          plans: real.length,
          on: real.filter((r) => r.in_target).length,
          alloc: thousands(allocated),
          load: thousands(allocated - made),
          unload: thousands(made),
          pct: allocated ? ((made / allocated) * 100).toFixed(1) : 0,
        })
      : f("entry.summary", {
          line: shown?.line,
          plans: real.length,
          allocated: thousands(allocated),
          made: thousands(made),
        });
  const buyerName = (id) =>
    options.buyers
      .find((b) => b.value === id)
      ?.label?.replace(/ \(.*\)$/, "") ?? "—";

  return (
    <Card
      icon={CalendarRange}
      title={f(`${mode}.title`)}
      hint={f(`${mode}.hint`)}
      right={
        rows && real.length ? (
          <Files sheet={mode} query={shown} c={files} base={BASE} />
        ) : null
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <FloorLine
          options={options}
          floor={floor}
          setFloor={setFloor}
          line={line}
          setLine={setLine}
          f={f}
          d={d}
        />
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => refresh()}
          loading={pending && !dirty}
          disabled={!floor || !line}
        >
          <Search className="size-4" aria-hidden /> {d("refresh")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={f("pick_line_title")} text={d("pick")} />
      ) : plans && rows.length === 0 ? (
        <Empty title={f("plans.empty")} text={d("pick")} />
      ) : plans ? (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1200px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "in_target",
                  "buyer",
                  "order_style",
                  "plan_start",
                  "plan_end",
                  "allocation",
                  "production",
                  "set_status",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["allocation", "production"].includes(k) && "text-right",
                    )}
                  >
                    {f(`alloc.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr key={r.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2">
                    <Checkbox
                      checked={!!r.in_target}
                      onCheckedChange={(v) => set(i, "in_target", v === true)}
                      disabled={!canManage || r.status === "CLOSED"}
                      aria-label={`${r.order_style} ${f("alloc.col.in_target")}`}
                    />
                  </td>
                  <td className="whitespace-nowrap px-3 py-2">
                    {buyerName(r.buyer_id)}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2">
                    {[r.order_style, r.style, r.item]
                      .filter(Boolean)
                      .join(" · ")}
                    {r.smv ? ` · [SMV: ${Number(r.smv)}]` : ""}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2">
                    {fmtDay(r.plan_start)}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2">
                    {fmtDay(r.plan_end)}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {thousands(r.allocation)}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                    {achieved(r)}
                  </td>
                  <td className="px-2 py-2">
                    <div className="w-36">
                      <Select
                        value={r.status}
                        onValueChange={(v) => set(i, "status", v ?? r.status)}
                        options={options.statuses.map((s) => ({
                          value: s,
                          label: f(`status.${s}`),
                        }))}
                        disabled={!canManage}
                        aria-label={`${r.order_style} ${f("alloc.col.set_status")}`}
                      />
                    </div>
                  </td>
                  <td className="px-3 py-2 font-medium">
                    {f(`status.${r.status}`)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1800px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "plan_line",
                  "buyer",
                  "order_style",
                  "style",
                  "item",
                  "smv",
                  "input_date",
                  "plan_start",
                  "plan_end",
                  "planned",
                  "allocation",
                  "production",
                  "achieved",
                ].map((k) => (
                  <th key={k} className={th}>
                    {f(`alloc.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.id ?? `spare-${i}`}
                  className={cn(
                    "border-b border-border last:border-0",
                    !r.id && "bg-surface-muted/40",
                  )}
                >
                  <td className="px-3 py-1.5 font-medium">{shown?.line}</td>
                  <td className="px-2 py-1.5">
                    <div className="w-40">
                      <Select
                        value={r.buyer_id ?? ""}
                        onValueChange={(v) => set(i, "buyer_id", v || null)}
                        options={[{ value: "", label: "—" }, ...options.buyers]}
                        disabled={!canManage}
                        aria-label={`${f("alloc.col.buyer")} ${i + 1}`}
                      />
                    </div>
                  </td>
                  {["order_style", "style", "item"].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      <Input
                        value={r[k] ?? ""}
                        onChange={(e) => set(i, k, e.target.value)}
                        disabled={!canManage}
                        className="h-8 w-36"
                        aria-label={`${f(`alloc.col.${k}`)} ${i + 1}`}
                      />
                    </td>
                  ))}
                  <td className="px-2 py-1.5">
                    <NumberCell
                      value={asInput(r.smv)}
                      onChange={(v) => set(i, "smv", v)}
                      disabled={!canManage}
                      label={`${f("alloc.col.smv")} ${i + 1}`}
                      w="w-20"
                    />
                  </td>
                  {["input_date", "plan_start", "plan_end"].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      <Input
                        type="date"
                        value={r[k] ?? ""}
                        onChange={(e) => set(i, k, e.target.value)}
                        disabled={!canManage}
                        className="h-8 w-36"
                        aria-label={`${f(`alloc.col.${k}`)} ${i + 1}`}
                      />
                    </td>
                  ))}
                  <td className="whitespace-nowrap px-3 py-1.5">
                    {planned(r)}
                  </td>
                  {["allocation", "production"].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      <NumberCell
                        value={asInput(r[k])}
                        onChange={(v) => set(i, k, v)}
                        disabled={!canManage}
                        label={`${f(`alloc.col.${k}`)} ${i + 1}`}
                        w="w-24"
                      />
                    </td>
                  ))}
                  <td className="whitespace-nowrap px-3 py-1.5 tabular-nums">
                    {achieved(r)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={
          summary ? `${summary} — ${f(`${mode}.footer`)}` : f(`${mode}.footer`)
        }
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- NPT Entry ---------------- */

function NptSheet({ options, act, canManage, initial = {} }) {
  const { t, f, files } = useTf();
  const d = (k, p) => t(`cost.day.${k}`, p);
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    options.floors.some((x) => x.code === initial.floor) ? initial.floor : "",
  );
  const [date, setDate] = useState(
    /^\d{4}-\d{2}-\d{2}$/.test(initial.date ?? "") ? initial.date : "",
  );
  const [line, setLine] = useState(initial.line ?? "");
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function refresh(fl = floor, dt = date, ln = line) {
    if (!fl || !dt || !ln) return;
    setError(null);
    startTransition(async () => {
      const res = await act("npt", { floor: fl, date: dt, line: ln });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setShown({ floor: fl, date: dt, line: ln });
        setDirty(false);
        keepQuery({ floor: fl, date: dt, line: ln });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const clean = rows.map((r) => ({
      ...Object.fromEntries(
        [
          "id",
          "order_style",
          "reason_type",
          "reason_details",
          "responsible_dept",
          "manpower",
          "minutes",
        ].map((k) => [k, blank(r[k]) ? null : r[k]]),
      ),
      hour_slots: (r.hour_slots ?? []).map(Number),
    }));
    startTransition(async () => {
      const res = await act("saveNpt", { ...shown, rows: clean });
      if (res?.status === "success") {
        setRows(res.data.rows);
        setDirty(false);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && floor && date && line) {
      opened.current = true;
      refresh(floor, date, line);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const npt = (r) => num(r.manpower) * num(r.minutes);
  const booked = (rows ?? []).filter((r) => r.id);
  const summary = rows
    ? f("npt.summary", {
        line: shown?.line,
        date: fmtDay(shown?.date),
        count: booked.length,
        minutes: thousands(booked.reduce((s, r) => s + num(r.npt), 0)),
      })
    : null;

  return (
    <Card
      icon={CalendarRange}
      title={f("npt.title")}
      hint={f("npt.hint")}
      right={
        rows && booked.length ? (
          <Files sheet="npt" query={shown} c={files} base={BASE} />
        ) : null
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <FloorLine
          options={options}
          floor={floor}
          setFloor={setFloor}
          line={line}
          setLine={setLine}
          f={f}
          d={d}
        />
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
          disabled={!floor || !date || !line}
        >
          <Search className="size-4" aria-hidden /> {d("refresh")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={f("pick_npt_title")} text={d("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1500px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "order_style",
                  "reason_type",
                  "reason_details",
                  "responsible_dept",
                  "hour_slots",
                  "manpower",
                  "minutes",
                  "npt",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["manpower", "minutes", "npt"].includes(k) &&
                        "text-right",
                    )}
                  >
                    {f(`npt.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.id ?? `blank-${i}`}
                  className="border-b border-border last:border-0"
                >
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.order_style ?? ""}
                      onChange={(e) => set(i, "order_style", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-36"
                      aria-label={`${f("npt.col.order_style")} ${i + 1}`}
                    />
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-52">
                      <Select
                        value={r.reason_type ?? ""}
                        onValueChange={(v) => set(i, "reason_type", v || null)}
                        options={[
                          { value: "", label: "—" },
                          ...options.reasons.map((x) => ({
                            value: x,
                            label: x,
                          })),
                        ]}
                        disabled={!canManage}
                        aria-label={`${f("npt.col.reason_type")} ${i + 1}`}
                      />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.reason_details ?? ""}
                      onChange={(e) => set(i, "reason_details", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-48"
                      aria-label={`${f("npt.col.reason_details")} ${i + 1}`}
                    />
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-48">
                      <Select
                        value={r.responsible_dept ?? ""}
                        onValueChange={(v) =>
                          set(i, "responsible_dept", v || null)
                        }
                        options={[
                          { value: "", label: "—" },
                          ...options.npt_depts.map((x) => ({
                            value: x,
                            label: x,
                          })),
                        ]}
                        disabled={!canManage}
                        aria-label={`${f("npt.col.responsible_dept")} ${i + 1}`}
                      />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="w-56">
                      <MultiPick
                        options={options.hour_slots.map((s) => ({
                          value: String(s),
                          label: String(s),
                        }))}
                        value={(r.hour_slots ?? []).map(String)}
                        onChange={(v) => set(i, "hour_slots", v)}
                        placeholder={f("npt.slot_ph")}
                        disabled={!canManage}
                        ariaLabel={`${f("npt.col.hour_slots")} ${i + 1}`}
                      />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex justify-end">
                      <NumberCell
                        value={asInput(r.manpower)}
                        onChange={(v) => set(i, "manpower", v)}
                        disabled={!canManage}
                        label={`${f("npt.col.manpower")} ${i + 1}`}
                        w="w-20"
                      />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex justify-end">
                      <NumberCell
                        value={asInput(r.minutes)}
                        onChange={(v) => set(i, "minutes", v)}
                        disabled={!canManage}
                        label={`${f("npt.col.minutes")} ${i + 1}`}
                        w="w-20"
                      />
                    </div>
                  </td>
                  <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                    {npt(r)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={summary ? `${summary} — ${f("npt.footer")}` : f("npt.footer")}
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

export { YarnSheet, ChangeoverBoard, TargetSheet, AllocationSheet, NptSheet };
