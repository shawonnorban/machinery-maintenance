"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { CalendarRange, RefreshCw, Save, Search } from "lucide-react";
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
 * Work Study → Section Profitability (AnnotechERP 14/08), the sheets
 * Brickland edits in place: Income Target Config (every section's daily
 * target, rate and working days for a month, Target Income worked out; Update
 * brings another month across) and SecWise Profit/Loss-Trans (every
 * section's day against its share of the month, Profit/Loss worked out).
 * Each reads on Show / Refresh without a reload — the url keeps what it was
 * read for — with PDF and Excel.
 */

const BASE = "/api/work-study/section-pl";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
const money = (v) =>
  Number(v || 0).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
const fmtDay = (d) =>
  d
    ? new Date(`${d}T00:00:00`).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "—";
const isPeriod = (v) => /^\d{4}-(0[1-9]|1[0-2])$/.test(v ?? "");
const isDay = (v) => /^\d{4}-\d{2}-\d{2}$/.test(v ?? "");

function useSp() {
  const t = useT("workstudy");
  return {
    t,
    p: (k, q) => t(`sp.${k}`, q),
    files: (k) => t(`cost.${k}`),
    saved_: t("cost.day.saved"),
    monthName: (m) => t(`cost.months.${Number(m)}`),
  };
}

function ErrorLine({ error }) {
  return error ? (
    <p className="mx-5 mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-sm text-danger">
      {error}
    </p>
  ) : null;
}

function CompanyField({ options, value, onChange, p }) {
  return (
    <Field label={p("company")}>
      <Select
        value={value}
        onValueChange={(v) => onChange(v ?? "")}
        options={[options.company]}
        placeholder={p("year_ph")}
        aria-label={p("company")}
      />
    </Field>
  );
}

/* ---------------- Income Target Config ---------------- */

function TargetConfigSheet({ options, act, canManage, initialPeriod }) {
  const { p, files, monthName, saved_ } = useSp();
  const c = (k, q) => p(`cfg.${k}`, q);
  const toastManager = useToastManager();
  const label = (period) =>
    `${period.slice(0, 4)} | ${monthName(period.slice(5, 7))}`;
  const [company, setCompany] = useState(
    isPeriod(initialPeriod) ? options.company.value : "",
  );
  const [year, setYear] = useState(
    isPeriod(initialPeriod) ? initialPeriod.slice(0, 4) : "",
  );
  const [period, setPeriod] = useState(
    isPeriod(initialPeriod) ? initialPeriod : "",
  );
  const [from, setFrom] = useState("");
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [saved, setSaved] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function loaded(data) {
    setRows(data.rows);
    setSaved(data.rows);
    setDirty(false);
  }
  function show(pr = period) {
    if (!company || !pr) return;
    setError(null);
    startTransition(async () => {
      const res = await act("targets", { period: pr });
      if (res?.status === "success") {
        loaded(res.data);
        setShown(pr);
        keepQuery({ period: pr });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const clean = rows.map((r) => ({
      section_id: r.section_id,
      ...Object.fromEntries(
        [
          "sub_section",
          "daily_target",
          "daily_target_inhouse",
          "daily_target_subcon",
          "avg_rate",
          "work_days",
        ].map((k) => [k, blank(r[k]) ? null : r[k]]),
      ),
    }));
    startTransition(async () => {
      const res = await act("saveTargets", { period: shown, rows: clean });
      if (res?.status === "success") {
        loaded(res.data);
        toastManager.add({ title: saved_, type: "success" });
      } else setError(firstError(res));
    });
  }
  function update() {
    setError(null);
    startTransition(async () => {
      const res = await act("copyTargets", { period, from });
      if (res?.status === "success") {
        loaded(res.data);
        setShown(period);
        keepQuery({ period });
        toastManager.add({
          title: c("copied", { count: res.data.copied, from: label(from) }),
          type: "success",
        });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && company && period) {
      opened.current = true;
      show(period);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const income = (r) =>
    num(r.daily_target) * num(r.avg_rate) * num(r.work_days);
  const set_ = saved.filter((r) => r.id);
  const summary = rows
    ? c("summary", {
        period: label(shown),
        sections: rows.length,
        set: set_.length,
        income: money(set_.reduce((s, r) => s + num(r.target_income), 0)),
      })
    : null;
  const months = options.periods.filter((x) => x.startsWith(`${year}-`));

  return (
    <Card
      icon={CalendarRange}
      title={c("title")}
      hint={c("hint")}
      right={
        rows ? (
          <>
            {set_.length ? (
              <Files
                sheet="targets"
                query={{ period: shown }}
                c={files}
                base={BASE}
              />
            ) : null}
            <Chip>{c("count", { count: rows.length })}</Chip>
          </>
        ) : null
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <CompanyField
          options={options}
          value={company}
          onChange={setCompany}
          p={p}
        />
        <Field label={p("year")}>
          <Select
            value={year}
            onValueChange={(v) => {
              setYear(v ?? "");
              setPeriod("");
            }}
            options={options.years.map((y) => ({
              value: String(y),
              label: String(y),
            }))}
            placeholder={p("year_ph")}
            disabled={!company}
            aria-label={p("year")}
          />
        </Field>
        <Field label={p("month")}>
          <Select
            value={period}
            onValueChange={(v) => setPeriod(v ?? "")}
            options={months.map((x) => ({ value: x, label: label(x) }))}
            placeholder={p("month_ph")}
            disabled={!year}
            aria-label={p("month")}
          />
        </Field>
        <Field label={c("copy")} help={c("copy_help")}>
          <Select
            value={from}
            onValueChange={(v) => setFrom(v ?? "")}
            options={options.periods
              .filter((x) => x !== period)
              .map((x) => ({ value: x, label: label(x) }))}
            placeholder={p("month_ph")}
            disabled={!period}
            aria-label={c("copy")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => show()}
          loading={pending && !dirty}
          disabled={!company || !period}
        >
          <Search className="size-4" aria-hidden /> {c("show")}
        </Button>
        {canManage ? (
          <Button
            variant="outline"
            onClick={update}
            disabled={!period || !from || pending}
          >
            <RefreshCw className="size-4" aria-hidden /> {c("update")}
          </Button>
        ) : null}
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={c("pick_title")} text={c("pick")} />
      ) : !rows.length ? (
        <Empty title={c("empty")} text={c("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1400px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "section",
                  "sub_section",
                  "unit",
                  "daily_target",
                  "daily_target_inhouse",
                  "daily_target_subcon",
                  "avg_rate",
                  "work_days",
                  "target_income",
                  "oh_calculated",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      !["section", "sub_section", "unit"].includes(k) &&
                        "text-right",
                    )}
                  >
                    {c(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.section_id}
                  className="border-b border-border last:border-0"
                >
                  <td className="whitespace-nowrap px-3 py-1.5 text-xs font-medium">
                    {r.section}
                  </td>
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.sub_section ?? ""}
                      onChange={(e) => set(i, "sub_section", e.target.value)}
                      disabled={!canManage}
                      maxLength={120}
                      className="h-8 w-48"
                      aria-label={`${c("col.sub_section")} ${r.section}`}
                    />
                  </td>
                  <td className="px-3 py-1.5 text-xs">{r.unit}</td>
                  {[
                    "daily_target",
                    "daily_target_inhouse",
                    "daily_target_subcon",
                    "avg_rate",
                    "work_days",
                  ].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      <div className="flex justify-end">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${c(`col.${k}`)} ${r.section}`}
                          w={k === "work_days" ? "w-16" : "w-24"}
                        />
                      </div>
                    </td>
                  ))}
                  <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                    {money(income(r))}
                  </td>
                  <td className="px-3 py-1.5 text-right tabular-nums">
                    {money(r.oh_calculated)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer text={summary ? `${summary} — ${c("footer")}` : c("footer")}>
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows?.length || !dirty}
          >
            <Save className="size-4" aria-hidden /> {c("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- SecWise Profit/Loss-Trans ---------------- */

const RESULT_NUMBERS = [
  "income_target",
  "income",
  "prod_target",
  "prod_qty",
  "oh_cost",
  "mat_cost",
  "expense",
];

function ResultSheet({ options, act, canManage, initialDate }) {
  const { p, files, saved_ } = useSp();
  const r_ = (k, q) => p(`trans.${k}`, q);
  const toastManager = useToastManager();
  const [company, setCompany] = useState(
    isDay(initialDate) ? options.company.value : "",
  );
  const [date, setDate] = useState(isDay(initialDate) ? initialDate : "");
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [saved, setSaved] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function loaded(data) {
    setRows(data.rows);
    setSaved(data.rows);
    setDirty(false);
  }
  function refresh(dt = date) {
    if (!company || !dt) return;
    setError(null);
    startTransition(async () => {
      const res = await act("results", { date: dt });
      if (res?.status === "success") {
        loaded(res.data);
        setShown(dt);
        keepQuery({ date: dt });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const clean = rows.map((r) => ({
      section_id: r.section_id,
      ...Object.fromEntries(
        RESULT_NUMBERS.map((k) => [k, blank(r[k]) ? null : Number(r[k])]),
      ),
      comments: blank(r.comments) ? null : r.comments,
    }));
    startTransition(async () => {
      const res = await act("saveResults", { date: shown, rows: clean });
      if (res?.status === "success") {
        loaded(res.data);
        toastManager.add({ title: saved_, type: "success" });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && company && date) {
      opened.current = true;
      refresh(date);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const profit = (r) =>
    num(r.income) - num(r.oh_cost) - num(r.mat_cost) - num(r.expense);
  const posted = saved.filter((r) => r.id);
  const summary = rows
    ? r_("summary", {
        date: fmtDay(shown),
        sections: rows.length,
        posted: posted.length,
        profit: money(posted.reduce((s, r) => s + num(r.profit), 0)),
      })
    : null;

  return (
    <Card
      icon={CalendarRange}
      title={r_("title")}
      hint={r_("hint")}
      right={
        rows ? (
          <>
            {posted.length ? (
              <Files
                sheet="results"
                query={{ date: shown }}
                c={files}
                base={BASE}
              />
            ) : null}
            <Chip>{r_("count", { count: rows.length })}</Chip>
          </>
        ) : null
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <CompanyField
          options={options}
          value={company}
          onChange={setCompany}
          p={p}
        />
        <Field label={r_("date")}>
          <Input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            aria-label={r_("date")}
          />
        </Field>
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => refresh()}
          loading={pending && !dirty}
          disabled={!company || !date}
        >
          <Search className="size-4" aria-hidden /> {r_("refresh")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={r_("pick_title")} text={r_("pick")} />
      ) : !rows.length ? (
        <Empty title={p("cfg.empty")} text={r_("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1500px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className={th}>{r_("col.section")}</th>
                {[...RESULT_NUMBERS, "profit"].map((k) => (
                  <th key={k} className={cn(th, "text-right")}>
                    {r_(`col.${k}`)}
                  </th>
                ))}
                <th className={th}>{r_("col.comments")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.section_id}
                  className="border-b border-border last:border-0"
                >
                  <td className="whitespace-nowrap px-3 py-1.5 text-xs font-medium">
                    {r.section}
                  </td>
                  {RESULT_NUMBERS.map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      <div className="flex justify-end">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${r_(`col.${k}`)} ${r.section}`}
                        />
                      </div>
                    </td>
                  ))}
                  <td
                    className={cn(
                      "px-3 py-1.5 text-right font-semibold tabular-nums",
                      profit(r) < 0 && "text-danger",
                    )}
                  >
                    {money(profit(r))}
                  </td>
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.comments ?? ""}
                      onChange={(e) => set(i, "comments", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-48"
                      aria-label={`${r_("col.comments")} ${r.section}`}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer text={summary ? `${summary} — ${r_("footer")}` : r_("footer")}>
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows?.length || !dirty}
          >
            <Save className="size-4" aria-hidden /> {r_("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

export { TargetConfigSheet, ResultSheet };
