"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import {
  CalendarRange,
  Download,
  Printer,
  Save,
  Search,
  Users,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button, buttonVariants } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Work Study → Costing (AnnotechERP 14/05), the sheets Brickland edits a
 * whole page at a time: Overhead Cost Allocation's Monthly Cost Re-arrange,
 * and the Daily Line Cost Sheet's Order Config Data, Line Wise Prod and
 * Overall Profit Lost. Each is a card with its filters, a Show / Refresh that
 * reads the sheet in place (the url keeps the month or the floor and day, so
 * a reload comes back to it), PDF and Excel once there is something to print,
 * the grid, and a footer saying how it works with Save.
 */

const SHEET_COLUMNS = [
  "cutting",
  "sewing",
  "knitting",
  "dyeing",
  "dyeing_finishing",
  "printing",
];
const TIERS = ["aot", "eot", "2ot", "5ot"];
const n2 = (v) =>
  v === null || v === undefined || v === "" ? "" : Number(v).toFixed(2);
const blank = (v) => v === null || v === undefined || v === "";
const asInput = (v) => (blank(v) ? "" : String(Number(v)));
const num = (v) => (blank(v) ? 0 : Number(v));

/** Keep the sheet's filters in the url beside the tab, without a reload. */
function keepQuery(patch) {
  const q = new URLSearchParams(window.location.search);
  for (const [k, v] of Object.entries(patch)) v ? q.set(k, v) : q.delete(k);
  window.history.replaceState(null, "", `${window.location.pathname}?${q}`);
}

function Card({ icon: Icon = CalendarRange, title, hint, right, children }) {
  return (
    <section className="rounded-xl border border-border bg-surface">
      <header className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
        <div className="flex gap-3">
          <Icon className="mt-0.5 size-4 text-foreground-muted" aria-hidden />
          <div>
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            <p className="text-[13px] text-foreground-muted">{hint}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">{right}</div>
      </header>
      {children}
    </section>
  );
}

function Chip({ children }) {
  return (
    <span className="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold text-foreground">
      {children}
    </span>
  );
}

/** PDF and Excel for what is on screen, from the API (the PDF opens in a new tab to print). */
/** `base` is the API route the files come from (Costing by default; OT & Salary passes its own). */
function Files({ sheet, query, c, base = "/api/work-study/costing" }) {
  const qs = new URLSearchParams(query).toString();
  return (
    <>
      <a
        href={`${base}/${sheet}/pdf?${qs}`}
        target="_blank"
        rel="noreferrer"
        className={buttonVariants({ variant: "outline", size: "sm" })}
      >
        <Printer className="size-4" aria-hidden /> {c("pdf")}
      </a>
      <a
        href={`${base}/${sheet}/xlsx?${qs}`}
        className={buttonVariants({ variant: "outline", size: "sm" })}
      >
        <Download className="size-4" aria-hidden /> {c("excel")}
      </a>
    </>
  );
}

function Empty({ title, text }) {
  return (
    <div className="flex flex-col items-center gap-2 px-5 py-16 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <CalendarRange className="size-5" aria-hidden />
      </span>
      <p className="text-sm font-semibold">{title}</p>
      <p className="max-w-md text-[13px] text-foreground-muted">{text}</p>
    </div>
  );
}

function Field({ label, help, children, className }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-medium text-foreground">{label}</span>
      {children}
      {help ? (
        <span className="text-xs text-foreground-muted">{help}</span>
      ) : null}
    </label>
  );
}

function Footer({ text, children }) {
  return (
    <footer className="flex flex-col gap-3 border-t border-border bg-surface-muted/40 px-5 py-4">
      <p className="flex gap-2 text-[13px] text-foreground-muted">
        <Users className="mt-0.5 size-3.5 shrink-0" aria-hidden />
        <span>{text}</span>
      </p>
      {children ? <div>{children}</div> : null}
    </footer>
  );
}

const th = "whitespace-nowrap px-3 py-2.5 text-left text-[13px] font-semibold";
const cellInput = (w = "w-24") => cn("h-8 text-right tabular-nums", w);

/** A number typed into a grid cell. */
function NumberCell({ value, onChange, label, disabled, w }) {
  return (
    <Input
      type="number"
      min="0"
      step="any"
      value={value}
      onChange={(e) => onChange(e.target.value)}
      disabled={disabled}
      aria-label={label}
      className={cellInput(w)}
    />
  );
}

/* ---------------- Monthly Cost Re-arrange ---------------- */

/**
 * @param {{ options: object, act: Function, canManage: boolean, initialPeriod?: string }} props
 */
function CostSheet({ options, act, canManage, initialPeriod }) {
  const t = useT("workstudy");
  const c = (k, p) => t(`cost.${k}`, p);
  const toastManager = useToastManager();
  const [company, setCompany] = useState(
    initialPeriod ? options.company.value : "",
  );
  const [year, setYear] = useState(
    initialPeriod ? initialPeriod.slice(0, 4) : "",
  );
  const [month, setMonth] = useState(
    initialPeriod ? String(Number(initialPeriod.slice(5, 7))) : "",
  );
  const [copy, setCopy] = useState("");
  const [sheet, setSheet] = useState(null);
  const [rows, setRows] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const period =
    year && month ? `${year}-${String(month).padStart(2, "0")}` : "";
  const monthName = (p) =>
    `${p.slice(0, 4)}|${c(`months.${Number(p.slice(5, 7))}`)}`;

  function show(target = period, from = copy) {
    if (!company || !target) return;
    setError(null);
    startTransition(async () => {
      const res = await act("sheet", {
        period: target,
        copy: from || undefined,
      });
      if (res?.status === "success") {
        setSheet(res.data);
        setRows(res.data.rows);
        setDirty(Boolean(res.data.copied_from));
        keepQuery({ period: target });
      } else setError(res?.message);
    });
  }

  function save() {
    setError(null);
    startTransition(async () => {
      const res = await act("saveSheet", {
        period: sheet.period,
        rows: rows.map((r) =>
          Object.fromEntries(
            Object.entries(r)
              .filter(([k]) => !["section", "particular"].includes(k))
              .map(([k, v]) => [k, blank(v) ? null : v]),
          ),
        ),
      });
      if (res?.status === "success") {
        setSheet(res.data);
        setRows(res.data.rows);
        setDirty(false);
        setCopy("");
        toastManager.add({
          title: c("sheet.saved", { month: monthName(res.data.period) }),
          type: "success",
        });
      } else {
        setError(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message);
      }
    });
  }

  // A link to the month (?period=) opens on its sheet.
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && initialPeriod) {
      opened.current = true;
      show(initialPeriod, "");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const total = (key) => rows.reduce((s, r) => s + num(r[key]), 0);
  const months = Array.from({ length: 12 }, (_, i) => String(i + 1));

  return (
    <Card
      title={c("sheet.title")}
      hint={c("sheet.hint")}
      right={
        <>
          {sheet ? (
            <Files sheet="oh-sheet" query={{ period: sheet.period }} c={c} />
          ) : null}
          <Chip>
            {c("sheet.count", {
              count: sheet
                ? rows.filter((r) => !blank(r.monthly_expense)).length
                : 0,
            })}
          </Chip>
        </>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={c("sheet.company")}>
          <Select
            value={company}
            onValueChange={(v) => setCompany(v ?? "")}
            options={[options.company]}
            placeholder={c("sheet.select_ph")}
            aria-label={c("sheet.company")}
          />
        </Field>
        <Field label={c("sheet.year")}>
          <Select
            value={year}
            onValueChange={(v) => setYear(v ?? "")}
            options={options.years.map((y) => ({
              value: String(y),
              label: String(y),
            }))}
            placeholder={c("sheet.select_ph")}
            aria-label={c("sheet.year")}
          />
        </Field>
        <Field label={c("sheet.month")}>
          <Select
            value={month}
            onValueChange={(v) => setMonth(v ?? "")}
            options={
              year
                ? months.map((m) => ({
                    value: m,
                    label: `${year}|${c(`months.${m}`)}`,
                  }))
                : []
            }
            placeholder={c("sheet.month_ph")}
            aria-label={c("sheet.month")}
          />
        </Field>
        <Field label={c("sheet.copy")} help={c("sheet.copy_help")}>
          <Select
            value={copy}
            onValueChange={(v) => setCopy(v ?? "")}
            options={options.periods
              .filter((p) => p !== period)
              .map((p) => ({ value: p, label: monthName(p) }))}
            placeholder={c("sheet.month_ph")}
            aria-label={c("sheet.copy")}
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
          <Search className="size-4" aria-hidden /> {c("sheet.show")}
        </Button>
      </div>
      {error ? (
        <p className="mx-5 mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-sm text-danger">
          {error}
        </p>
      ) : null}
      {!sheet ? (
        <Empty title={c("sheet.pick_title")} text={c("sheet.pick")} />
      ) : (
        <>
          {sheet.copied_from ? (
            <p className="mx-5 mb-3 rounded-md bg-warning-subtle px-3 py-2 text-[13px] text-warning">
              {c("sheet.copied", { month: monthName(sheet.copied_from) })}
            </p>
          ) : null}
          <div className="max-h-[70vh] overflow-auto border-t border-border">
            <table className="w-full min-w-[1300px] text-sm">
              <thead className="sticky top-0 z-10 bg-brand text-brand-foreground">
                <tr>
                  {[
                    "section",
                    "particular",
                    "monthly_expense",
                    "work_days",
                    ...SHEET_COLUMNS,
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        !["section", "particular"].includes(k) && "text-right",
                      )}
                    >
                      {c(`sheet.col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr
                    key={r.head_id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="whitespace-nowrap px-3 py-1.5 text-foreground">
                      {r.section}
                    </td>
                    <td className="px-3 py-1.5 text-foreground">
                      {r.particular}
                    </td>
                    {["monthly_expense", "work_days", ...SHEET_COLUMNS].map(
                      (k) => (
                        <td key={k} className="px-2 py-1.5 text-right">
                          <NumberCell
                            value={asInput(r[k])}
                            onChange={(v) => set(i, k, v)}
                            disabled={!canManage}
                            label={`${r.particular} ${c(`sheet.col.${k}`)}`}
                            w={k === "work_days" ? "w-20" : "w-28"}
                          />
                        </td>
                      ),
                    )}
                  </tr>
                ))}
              </tbody>
              <tfoot className="sticky bottom-0 bg-surface-muted font-semibold">
                <tr>
                  <td className="px-3 py-2" colSpan={2}>
                    {c("total")}
                  </td>
                  {["monthly_expense", "work_days", ...SHEET_COLUMNS].map(
                    (k) => (
                      <td key={k} className="px-3 py-2 text-right tabular-nums">
                        {k === "work_days"
                          ? ""
                          : total(k).toLocaleString("en-US", {
                              maximumFractionDigits: 2,
                            })}
                      </td>
                    ),
                  )}
                </tr>
              </tfoot>
            </table>
          </div>
        </>
      )}
      <Footer text={c("sheet.footer")}>
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && Boolean(sheet)}
            disabled={!sheet || (!dirty && !sheet?.copied_from)}
          >
            <Save className="size-4" aria-hidden /> {c("sheet.save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- Daily Line Cost Sheet ---------------- */

const CONFIG_NUMBERS = [
  "bgt_as_cm_pct",
  "exc_rate",
  "smv",
  "fob",
  "rate_cut",
  "rate_sew",
  "rate_fin",
];
const LINE_NUMBERS = ["op", "aso", "wh", "target"];
const PROD = ["prod_g", ...TIERS.map((x) => `prod_${x}`)];
const MANOT = TIERS.map((x) => `manot_${x}`);

/**
 * One of the Daily Line Cost Sheet's three working tabs for a floor and day:
 * `config` (Order Config Data), `lines` (Line Wise Prod) or `profit` (Overall
 * Profit Lost, every figure derived and nothing saved).
 *
 * @param {{ mode: "config"|"lines"|"profit", floors: object[], buyers: object[], act: Function, canManage: boolean, initial?: {floor?: string, date?: string} }} props
 */
function DaySheet({ mode, floors, buyers, act, canManage, initial = {} }) {
  const t = useT("workstudy");
  const c = (k, p) => t(`cost.${k}`, p);
  const d = (k, p) => c(`day.${k}`, p);
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    floors.some((f) => f.code === initial.floor) ? initial.floor : "",
  );
  const [date, setDate] = useState(
    /^\d{4}-\d{2}-\d{2}$/.test(initial.date ?? "") ? initial.date : "",
  );
  const [data, setData] = useState(null);
  const [rows, setRows] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const [shown, setShown] = useState(null);
  const pickLabel = c("select");
  const buyerOptions = useMemo(
    () => [{ value: "", label: pickLabel }, ...buyers],
    [buyers, pickLabel],
  );

  function refresh(f = floor, dt = date) {
    if (!f || !dt) return;
    setError(null);
    startTransition(async () => {
      const res = await act(mode, { floor: f, date: dt });
      if (res?.status === "success") {
        setData(res.data);
        setRows(res.data.rows);
        setShown({ floor: f, date: dt });
        setDirty(false);
        keepQuery({ floor: f, date: dt });
      } else setError(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message);
    });
  }

  function save() {
    setError(null);
    const clean = rows.map((r) =>
      Object.fromEntries(
        Object.entries(r)
          .filter(([k]) => !["line", "saved", "loaded", "id"].includes(k))
          .map(([k, v]) => [k, blank(v) ? null : v]),
      ),
    );
    startTransition(async () => {
      const res = await act(mode === "config" ? "saveConfig" : "saveLines", {
        ...shown,
        rows: clean,
      });
      if (res?.status === "success") {
        setData(res.data);
        setRows(res.data.rows);
        setDirty(false);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message);
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
  const made = (r) => PROD.reduce((s, k) => s + num(r[k]), 0);
  const manot = (r) => MANOT.reduce((s, k) => s + num(r[k]), 0);
  const day = shown
    ? new Date(`${shown.date}T00:00:00`).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "";

  const title = d(`${mode}_title`);
  const hint = d(`${mode}_hint`);
  const count = !data
    ? 0
    : mode === "profit"
      ? data.rows.length
      : mode === "lines"
        ? rows.filter((r) => made(r) > 0).length
        : rows.length;
  const summary = !data
    ? null
    : mode === "config"
      ? d("config_summary", {
          date: day,
          orders: rows.length,
          costed: rows.filter((r) => r.id).length,
        })
      : mode === "lines"
        ? d("lines_summary", {
            date: day,
            lines: rows.length,
            pcs: rows.reduce((s, r) => s + made(r), 0),
          })
        : data.rows.length
          ? [
              d("profit_summary", {
                date: day,
                lines: data.rows.length,
                cm: n2(data.totals.cm_earn),
                profit: n2(data.totals.profit),
              }),
              data.no_rate ? d("no_rate") : null,
              data.no_overhead ? d("no_overhead") : null,
            ]
              .filter(Boolean)
              .join(" · ")
          : d("no_prod");
  const sheetKey = {
    config: "day-config",
    lines: "day-lines",
    profit: "day-profit",
  }[mode];

  return (
    <Card
      title={title}
      hint={hint}
      right={
        <>
          {data && count > 0 ? (
            <Files sheet={sheetKey} query={shown} c={c} />
          ) : null}
          <Chip>{d(`${mode}_count`, { count })}</Chip>
        </>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={d("floor")}>
          <Select
            value={floor}
            onValueChange={(v) => setFloor(v ?? "")}
            options={floors.map((f) => ({ value: f.code, label: f.label }))}
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
      {!data ? (
        <Empty title={d("pick_title")} text={d("pick")} />
      ) : mode === "config" ? (
        rows.length === 0 ? (
          <Empty title={d("empty_orders")} text={d("pick")} />
        ) : (
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-[1400px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "buyer",
                    "style_no",
                    "order_no",
                    "gmt_item",
                    ...CONFIG_NUMBERS,
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        CONFIG_NUMBERS.includes(k) && "text-right",
                      )}
                    >
                      {d(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr
                    key={r.order_no}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-2 py-1.5">
                      <div className="w-48">
                        <Select
                          value={r.buyer_id ?? ""}
                          onValueChange={(v) => set(i, "buyer_id", v || null)}
                          options={buyerOptions}
                          disabled={!canManage}
                          aria-label={`${r.order_no} ${d("col.buyer")}`}
                        />
                      </div>
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={r.style_no ?? ""}
                        onChange={(e) => set(i, "style_no", e.target.value)}
                        disabled={!canManage}
                        className="h-8 w-36"
                        aria-label={`${r.order_no} ${d("col.style_no")}`}
                      />
                    </td>
                    <td className="whitespace-nowrap px-3 py-1.5 font-medium">
                      {r.order_no}
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={r.gmt_item ?? ""}
                        onChange={(e) => set(i, "gmt_item", e.target.value)}
                        disabled={!canManage}
                        className="h-8 w-36"
                        aria-label={`${r.order_no} ${d("col.gmt_item")}`}
                      />
                    </td>
                    {CONFIG_NUMBERS.map((k) => (
                      <td key={k} className="px-2 py-1.5 text-right">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${r.order_no} ${d(`col.${k}`)}`}
                        />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : mode === "lines" ? (
        rows.length === 0 ? (
          <Empty title={d("empty_lines")} text={d("pick")} />
        ) : (
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-[2100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "line",
                    "buyer",
                    "order_no",
                    "item_name",
                    ...LINE_NUMBERS,
                    "prod_total",
                    ...PROD,
                    ...MANOT,
                    "manot_total",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        !["line", "buyer", "order_no", "item_name"].includes(
                          k,
                        ) && "text-right",
                      )}
                    >
                      {d(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr
                    key={r.planning_line_id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="whitespace-nowrap px-3 py-1.5 font-medium">
                      {r.line}
                    </td>
                    <td className="px-2 py-1.5">
                      <div className="w-44">
                        <Select
                          value={r.buyer_id ?? ""}
                          onValueChange={(v) => set(i, "buyer_id", v || null)}
                          options={buyerOptions}
                          disabled={!canManage}
                          aria-label={`${r.line} ${d("col.buyer")}`}
                        />
                      </div>
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={r.order_no ?? ""}
                        onChange={(e) => set(i, "order_no", e.target.value)}
                        disabled={!canManage}
                        className="h-8 w-36"
                        aria-label={`${r.line} ${d("col.order_no")}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={r.item_name ?? ""}
                        onChange={(e) => set(i, "item_name", e.target.value)}
                        disabled={!canManage}
                        className="h-8 w-32"
                        aria-label={`${r.line} ${d("col.item_name")}`}
                      />
                    </td>
                    {LINE_NUMBERS.map((k) => (
                      <td key={k} className="px-2 py-1.5 text-right">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${r.line} ${d(`col.${k}`)}`}
                          w="w-20"
                        />
                      </td>
                    ))}
                    <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                      {made(r)}
                    </td>
                    {[...PROD, ...MANOT].map((k) => (
                      <td key={k} className="px-2 py-1.5 text-right">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${r.line} ${d(`col.${k}`)}`}
                          w="w-20"
                        />
                      </td>
                    ))}
                    <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                      {n2(manot(r))}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : data.rows.length === 0 ? (
        <Empty title={d("no_prod")} text={d("pick")} />
      ) : (
        <ProfitTable data={data} d={d} c={c} />
      )}
      <Footer
        text={
          summary ? `${summary} — ${d(`${mode}_footer`)}` : d(`${mode}_footer`)
        }
      >
        {canManage && mode !== "profit" ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!data || rows.length === 0 || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/** Overall Profit Lost's grid: each line's earning against its cost, general hours and every tier, with the floor's totals. */
function ProfitTable({ data, d, c }) {
  const tierCols = (tier) =>
    tier === "g"
      ? ["sal_g", "oh_g", "cm_g", "pl_g"]
      : [`otbill_${tier}`, `oh_${tier}`, `cm_${tier}`, `pl_${tier}`];
  const cols = [
    "effi",
    "fob_earn",
    ...["g", ...TIERS].flatMap(tierCols),
    "total_cost",
    "cm_earn",
    "profit",
  ];
  const label = (k) => {
    const m = k.match(/^(sal|oh|cm|pl|otbill)_(g|aot|eot|2ot|5ot)$/);
    return m ? d(`col.${m[1]}`, { tier: m[2] }) : d(`col.${k}`);
  };
  const tone = (k, v) =>
    /^pl_|^profit$/.test(k) && Number(v) < 0 ? "text-danger" : "";
  return (
    <div className="overflow-x-auto border-t border-border">
      <table className="w-full min-w-[2600px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            {["line", "buyer", "order_no", "item_name"].map((k) => (
              <th key={k} className={th}>
                {d(`col.${k}`)}
              </th>
            ))}
            {cols.map((k) => (
              <th key={k} className={cn(th, "text-right")}>
                {label(k)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.rows.map((r) => (
            <tr key={r.line} className="border-b border-border">
              <td className="whitespace-nowrap px-3 py-2 font-medium">
                {r.line}
              </td>
              <td className="whitespace-nowrap px-3 py-2">{r.buyer ?? "—"}</td>
              <td className="whitespace-nowrap px-3 py-2">{r.order_no}</td>
              <td className="whitespace-nowrap px-3 py-2">
                {r.item_name ?? "—"}
              </td>
              {cols.map((k) => (
                <td
                  key={k}
                  className={cn(
                    "whitespace-nowrap px-3 py-2 text-right tabular-nums",
                    tone(k, r[k]),
                  )}
                >
                  {n2(r[k])}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        <tfoot className="bg-surface-muted font-semibold">
          <tr>
            <td className="px-3 py-2" colSpan={4}>
              {c("total")}
            </td>
            {cols.map((k) => (
              <td
                key={k}
                className={cn(
                  "px-3 py-2 text-right tabular-nums",
                  tone(k, data.totals[k]),
                )}
              >
                {k in data.totals ? n2(data.totals[k]) : ""}
              </td>
            ))}
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

// The card pieces OT & Salary (14/06) builds its sheets from too.
export {
  CostSheet,
  DaySheet,
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
};
