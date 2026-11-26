"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import {
  Activity,
  BadgeCheck,
  Banknote,
  Bell,
  Briefcase,
  CalendarCheck,
  CalendarClock,
  ClipboardList,
  Clock,
  Eye,
  FileDown,
  FileSpreadsheet,
  FileText,
  Fingerprint,
  Footprints,
  Hourglass,
  LayoutGrid,
  LogOut,
  Printer,
  Search,
  TrendingUp,
  UserCheck,
  UserPlus,
  UserRound,
  UserX,
  Users,
  Wallet,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { useT } from "@/lib/i18n";
import { cell, dmy, numeric, printReport } from "@/lib/hr-report-print";
import { firstError } from "@/components/hrpayroll/movement";

/*
 * HR & Payroll → Dashboard and Reports (AnnotechERP 24/02), as Brickland
 * lays them out.
 *
 * - Reports: Report Categories on the left (47 reports, numbered); on the
 *   right the report's filters with View, Print, PDF and Excel, then the
 *   tiles and the grid with its total row and a search box. Picking a
 *   report keeps the page; the url remembers it (?report=…).
 * - Dashboard: the head-count and attendance tiles, departments, the month's
 *   attendance and payroll, recent joiners, separations waiting, lefty
 *   records and notifications, then Today's Operations — for the day or the
 *   month to it.
 */

const card = "rounded-xl border border-border bg-surface";
const CAT_ICON = { employee: Users, manpower: LayoutGrid, attendance: CalendarCheck, ot: Clock, salary: Wallet, hr: Briefcase };
const SELECTS = { department: "departments", section: "sections", line: "lines", floor: "floors", designation: "designations", shift: "shifts", grade: "grades", otType: "otTypes", structure: "structures", festival: "festivals" };

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`rp.${k}`, p);
}

function tileValue(x) {
  if (x.kind === "text") return x.value ?? "—";
  if (x.kind === "money") return `৳${cell(x.value, "money")}`;
  if (x.kind === "hours") return cell(x.value, "hours");
  return cell(x.value, "int");
}

/* ── Reports ───────────────────────────────────────────────────────── */

function HrReports({ initial, act, start: startKey }) {
  const w = useWords();
  const toast = useToastManager();
  const reports = initial.reports;
  const [key, setKey] = useState(reports.some((r) => r.key === startKey) ? startKey : reports[0].key);
  const report = reports.find((r) => r.key === key);
  const blank = useMemo(() => ({ ...initial.defaults }), [initial.defaults]);
  const [f, setF] = useState(blank);
  const [result, setResult] = useState(null);
  const [query, setQuery] = useState("");
  const [busy, start] = useTransition();
  const o = initial.options;

  useEffect(() => {
    const url = new URL(window.location.href);
    url.searchParams.set("report", key);
    window.history.replaceState(null, "", url);
  }, [key]);

  const pick = (k) => {
    if (k === key) return;
    setKey(k);
    setResult(null);
    setQuery("");
  };
  const set = (k, v) =>
    setF((s) => {
      const n = { ...s, [k]: v };
      if (k === "department") (n.section = ""), (n.line = "");
      if (k === "section") n.line = "";
      return n;
    });
  const payload = () => Object.fromEntries(report.filters.filter((k) => f[k]).map((k) => [k, f[k]]));
  const missing = report.required.some((k) => !String(f[k] ?? "").trim());
  const salaryLocked = report.category === "salary" && !initial.canSalary;

  const view = () =>
    start(async () => {
      const res = await act("rpRun", report.key, payload());
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.run"), type: "danger" });
      setResult(res.data);
      setQuery("");
    });

  const optionLabel = (k, v) => {
    if (!v) return w("all");
    if (k === "period") return o.periods.find((x) => x.value === v)?.label ?? v;
    if (k === "fiscalYear") return o.fiscalYears.find((x) => x.value === v)?.label ?? v;
    if (k === "status") return report.statusChoices?.find((x) => x.value === v)?.label ?? v;
    if (k === "month") {
      const [y, m] = String(v).split("-");
      return new Date(Number(y), Number(m) - 1, 1).toLocaleString("en-GB", { month: "long", year: "numeric" });
    }
    if (["date", "from", "to"].includes(k)) return dmy(v);
    if (k === "employee") return v;
    return o[SELECTS[k]]?.find((x) => x.value === v)?.label ?? v;
  };
  const params = () => {
    const out = [];
    for (const k of report.filters) {
      if (k === "from") out.push([w("period_range"), `${dmy(f.from)} – ${dmy(f.to)}`]);
      else if (k === "to") continue;
      else out.push([k === "status" ? report.statusLabel : w(`f.${k}`), optionLabel(k, f[k])]);
    }
    return out;
  };
  const paramLine = () => params().map(([k, v]) => `${k}: ${v}`).join(" · ");

  const print = async (pdf) => {
    const win = window.open("", "_blank");
    let shown = result;
    if (!shown) {
      const res = await act("rpRun", report.key, payload());
      if (res?.status !== "success") {
        win?.close();
        return toast.add({ title: firstError(res) || w("err.run"), type: "danger" });
      }
      shown = res.data;
      setResult(shown);
    }
    const cat = initial.categories.find((c) => c.key === report.category);
    printReport({
      report: { no: report.no, name: report.name, wide: report.wide },
      result: shown,
      params: params(),
      by: initial.by,
      pdf,
      win,
      head: { ...initial.letterhead, words: { ...printWords(w), category: w("category_report", { name: cat?.name ?? "" }), remarksText: w("pr.remarks_text", { n: report.no }) } },
    });
  };

  const excel = () => {
    const qs = new URLSearchParams(payload());
    window.location.href = `/api/hr-payroll/report-export/${encodeURIComponent(report.key)}?${qs}`;
  };

  const rows = useMemo(() => {
    if (!result) return [];
    const q = query.trim().toLowerCase();
    if (!q) return result.rows;
    return result.rows.filter((r) => result.columns.some((c) => String(cell(r[c.key], c.kind)).toLowerCase().includes(q)));
  }, [result, query]);

  const Icon = CAT_ICON[report.category];

  return (
    <div className="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
      <aside className={cn(card, "self-start p-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto")}>
        <div className="flex items-start gap-2.5">
          <FileText className="mt-0.5 size-4 text-foreground-muted" aria-hidden />
          <div>
            <h2 className="text-[15px] font-semibold">{w("categories")}</h2>
            <p className="text-xs text-foreground-muted">{w("n_reports", { n: reports.length, c: initial.categories.length })}</p>
          </div>
        </div>
        {initial.categories.map((c) => {
          const CatIcon = CAT_ICON[c.key];
          return (
            <div key={c.key} className="mt-4">
              <p className="flex items-center gap-2 px-1 text-[12px] font-bold uppercase tracking-wide text-foreground">
                <CatIcon className="size-4 text-foreground-muted" aria-hidden />
                {c.name}
              </p>
              <ul className="mt-1.5 flex flex-col">
                {reports
                  .filter((r) => r.category === c.key)
                  .map((r) => (
                    <li key={r.key}>
                      <button
                        type="button"
                        aria-current={r.key === key ? "page" : undefined}
                        onClick={() => pick(r.key)}
                        className={cn("flex w-full items-baseline gap-2 rounded-lg px-2 py-1.5 text-left text-sm", r.key === key ? "bg-brand-subtle font-semibold text-brand" : "text-foreground hover:bg-surface-muted")}
                      >
                        <span className="w-6 shrink-0 text-right text-xs tabular-nums text-foreground-muted">{r.no}.</span>
                        <span className="min-w-0">{r.name}</span>
                      </button>
                    </li>
                  ))}
              </ul>
            </div>
          );
        })}
      </aside>

      <div className="flex min-w-0 flex-col gap-5">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start gap-2.5">
            <Icon className="mt-0.5 size-4 text-foreground-muted" aria-hidden />
            <div>
              <h2 className="text-[15px] font-semibold">
                {report.no}. {report.name}
              </h2>
              <p className="text-xs text-foreground-muted">{report.description}</p>
            </div>
          </div>
          {salaryLocked ? <p className="mt-3 rounded-lg bg-warning-subtle px-3 py-2 text-xs text-warning">{w("salary_locked")}</p> : null}
          <div className="mt-4 grid gap-x-3 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
            {report.filters.map((k) => (
              <Filter key={k} k={k} f={f} set={set} report={report} o={o} w={w} />
            ))}
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Button onClick={view} loading={busy} disabled={missing || salaryLocked}>
              <Eye /> {w("view")}
            </Button>
            {initial.canExport ? (
              <>
                <Button variant="outline" onClick={() => print(false)} disabled={busy || missing || salaryLocked}>
                  <Printer /> {w("print_btn")}
                </Button>
                <Button variant="outline" onClick={() => print(true)} disabled={busy || missing || salaryLocked}>
                  <FileDown /> {w("pdf")}
                </Button>
                <Button variant="outline" onClick={excel} disabled={!result || busy}>
                  <FileSpreadsheet /> {w("excel")}
                </Button>
              </>
            ) : null}
          </div>
        </section>

        {!result ? (
          <section className={cn(card, "flex flex-col items-center px-6 py-14 text-center")}>
            <span className="grid size-12 place-items-center rounded-xl bg-surface-muted">
              <Eye className="size-5 text-foreground-muted" aria-hidden />
            </span>
            <p className="mt-3 text-sm font-semibold">{w("choose_title")}</p>
            <p className="mt-1 max-w-md text-xs text-foreground-muted">{w("choose_text")}</p>
          </section>
        ) : (
          <>
            {result.tiles.length ? (
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                {result.tiles.map((x, i) => (
                  <div key={i} className={cn(card, "px-4 py-3")}>
                    <p className="text-xs text-foreground-muted">{x.label}</p>
                    <p className={cn("mt-1 font-semibold tabular-nums", x.kind === "text" ? "text-sm" : "text-xl")}>{tileValue(x)}</p>
                  </div>
                ))}
              </div>
            ) : null}
            <section className={cn(card, "min-w-0 overflow-hidden")}>
              <div className="flex flex-wrap items-start justify-between gap-3 p-5 pb-4">
                <div className="flex items-start gap-2.5">
                  <FileText className="mt-0.5 size-4 text-foreground-muted" aria-hidden />
                  <div>
                    <h3 className="text-[15px] font-semibold">{report.name}</h3>
                    <p className="text-xs text-foreground-muted">{paramLine()}</p>
                  </div>
                </div>
                <div className="relative w-full sm:w-64">
                  <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-foreground-muted" aria-hidden />
                  <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={w("search_results")} aria-label={w("search_results")} className="pl-9" />
                </div>
              </div>
              {result.rows.length ? (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-max text-[13px]">
                    <thead>
                      <tr className="bg-brand text-left text-white">
                        <th className="w-10 px-3 py-2.5 text-right font-semibold">#</th>
                        {result.columns.map((c) => (
                          <th key={c.key} className={cn("whitespace-nowrap px-3 py-2.5 font-semibold", numeric(c.kind) && "text-right")}>
                            {c.label}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r, i) => (
                        <tr key={i} className="border-b border-border last:border-0 hover:bg-surface-muted/60">
                          <td className="px-3 py-2 text-right tabular-nums text-foreground-muted">{i + 1}</td>
                          {result.columns.map((c, j) => (
                            <td key={c.key} className={cn("px-3 py-2", numeric(c.kind) ? "text-right tabular-nums" : "max-w-72", j < 2 && "font-medium")}>
                              {cell(r[c.key], c.kind)}
                            </td>
                          ))}
                        </tr>
                      ))}
                      {!rows.length ? (
                        <tr>
                          <td colSpan={result.columns.length + 1} className="px-3 py-8 text-center text-sm text-foreground-muted">
                            {w("no_search_match")}
                          </td>
                        </tr>
                      ) : null}
                    </tbody>
                    {result.foot && !query ? (
                      <tfoot>
                        <tr className="border-t-2 border-brand bg-brand-subtle font-semibold">
                          <td />
                          {result.columns.map((c) => (
                            <td key={c.key} className={cn("px-3 py-2", numeric(c.kind) && "text-right tabular-nums")}>
                              {result.foot[c.key] === undefined ? "" : cell(result.foot[c.key], c.kind)}
                            </td>
                          ))}
                        </tr>
                      </tfoot>
                    ) : null}
                  </table>
                </div>
              ) : (
                <div className="flex flex-col items-center border-t border-border px-6 py-12 text-center">
                  <p className="text-sm font-semibold">{w("nothing_matched")}</p>
                  <p className="mt-1 max-w-md text-xs text-foreground-muted">{result.empty}</p>
                </div>
              )}
              {result.rows.length ? (
                <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">
                  {w("n_rows", { n: rows.length })}
                  {result.truncated ? ` ${w("truncated", { n: result.count })}` : ""}
                  {result.note ? ` ${result.note}` : ""}
                </p>
              ) : null}
            </section>
          </>
        )}
      </div>
    </div>
  );
}

function printWords(w) {
  const k = ["corporate", "factory", "reportDate", "reportTime", "reportBy", "params", "summary", "data", "remarks", "prepared", "checked", "approved", "signature", "sl", "pdfHint", "printHint", "print", "close"];
  return Object.fromEntries(k.map((x) => [x, w(`pr.${x}`)]));
}

function Filter({ k, f, set, report, o, w }) {
  const label = k === "status" ? report.statusLabel : w(`f.${k}`);
  const required = report.required.includes(k);
  const box = (control) => (
    <label className="flex min-w-0 flex-col gap-1.5">
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {control}
    </label>
  );
  if (["date", "from", "to"].includes(k)) return box(<Input type="date" value={f[k] ?? ""} onChange={(e) => set(k, e.target.value)} aria-label={label} />);
  if (k === "month") return box(<Input type="month" value={f.month ?? ""} onChange={(e) => set("month", e.target.value)} aria-label={label} />);
  if (k === "employee") return box(<Input value={f.employee ?? ""} onChange={(e) => set("employee", e.target.value)} placeholder={w("ph.employee")} aria-label={label} />);
  if (k === "period" || k === "fiscalYear") {
    const list = k === "period" ? o.periods : o.fiscalYears;
    return box(<Select value={f[k] ?? ""} onValueChange={(v) => set(k, v ?? "")} options={list} placeholder={w("choose")} aria-label={label} />);
  }
  let list = k === "status" ? report.statusChoices : o[SELECTS[k]] ?? [];
  if (k === "section" && f.department) list = list.filter((x) => x.departmentId === f.department);
  if (k === "line" && (f.department || f.section)) list = list.filter((x) => (!f.department || x.departmentId === f.department) && (!f.section || x.sectionId === f.section));
  return box(<Select value={f[k] ?? ""} onValueChange={(v) => set(k, v ?? "")} options={[{ value: "", label: w("all") }, ...list]} placeholder={w("all")} aria-label={label} />);
}

/* ── Dashboard ─────────────────────────────────────────────────────── */

const DEPT_COLORS = ["#4f46e5", "#ea580c", "#16a34a", "#d97706", "#db2777", "#64748b"];
const ATT_COLORS = { PRESENT: "#16a34a", LATE: "#f59e0b", MISSING_PUNCH: "#f97316", LEAVE: "#3b82f6", ABSENT: "#dc2626", NOT_RECORDED: "#f87171" };
const STATUS_TONE = { ACTIVE: "text-success", PENDING: "bg-warning-subtle text-warning", CONFIRMED: "bg-danger-subtle text-danger", CLOSED: "bg-surface-muted text-foreground-muted" };

function Tile({ icon: Icon, tone, label, value, sub, delta }) {
  return (
    <div className={cn(card, "p-4")}>
      <div className="flex items-center gap-2.5">
        <span className={cn("grid size-9 place-items-center rounded-lg", tone)}>
          <Icon className="size-4" aria-hidden />
        </span>
        <span className="truncate text-[13px] font-semibold">{label}</span>
      </div>
      <p className="mt-3 text-2xl font-semibold tabular-nums">{value}</p>
      {delta}
      <p className="mt-0.5 truncate text-xs text-foreground-muted">{sub}</p>
    </div>
  );
}

function Panel({ icon: Icon, title, sub, action, children, className }) {
  return (
    <section className={cn(card, "flex min-w-0 flex-col p-5", className)}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-2.5">
          {Icon ? <Icon className="mt-0.5 size-4 shrink-0 text-foreground-muted" aria-hidden /> : null}
          <div className="min-w-0">
            <h3 className="truncate text-[15px] font-semibold">{title}</h3>
            {sub ? <p className="text-xs text-foreground-muted">{sub}</p> : null}
          </div>
        </div>
        {action}
      </div>
      <div className="mt-4 min-w-0 flex-1">{children}</div>
    </section>
  );
}

function ViewAll({ href, w }) {
  return (
    <Link href={href} className={cn(buttonVariants({ variant: "outline", size: "sm" }), "shrink-0")}>
      {w("db.view_all")} →
    </Link>
  );
}

function MiniTable({ head, rows, empty }) {
  return rows.length ? (
    <div className="overflow-x-auto">
      <table className="w-full text-[13px]">
        <thead>
          <tr className="bg-brand text-left text-white">
            {head.map((h, i) => (
              <th key={i} className={cn("whitespace-nowrap px-3 py-2 font-semibold", h.right && "text-right")}>
                {h.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i} className="border-b border-border last:border-0">
              {r.map((c, j) => (
                <td key={j} className={cn("px-3 py-2", head[j].right && "text-right tabular-nums")}>
                  {c ?? "—"}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  ) : (
    <p className="py-8 text-center text-sm text-foreground-muted">{empty}</p>
  );
}

/** "3 min ago" — only once mounted, so the server's clock never differs from the browser's. */
function ago(iso, w, now) {
  if (!now) return "";
  const m = Math.max(0, Math.round((now - new Date(iso).getTime()) / 60000));
  if (m < 60) return w("db.min_ago", { n: m });
  if (m < 60 * 24) return w("db.h_ago", { n: Math.round(m / 60) });
  return w("db.d_ago", { n: Math.round(m / 1440) });
}

function HrDashboard({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [d, setD] = useState(initial);
  const [busy, start] = useTransition();
  const [tab, setTab] = useState("leave");
  const [now, setNow] = useState(0);
  useEffect(() => setNow(Date.now()), [d]);
  const k = d.kpis;
  const n = (v) => cell(v, "int");

  const load = (patch) =>
    start(async () => {
      const q = { date: d.date, period: d.period, department: d.operations.department ?? "", ...patch };
      const res = await act("rpDashboard", Object.fromEntries(Object.entries(q).filter(([, v]) => v)));
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.run"), type: "danger" });
      setD(res.data);
    });

  const deptTotal = d.byDepartment.reduce((s, x) => s + x.value, 0);
  const segments = d.byDepartment.map((x, i) => ({ key: x.label, label: x.label, value: x.value, color: DEPT_COLORS[i % DEPT_COLORS.length] }));
  const op = d.operations;
  const attSegments = op.summary.map((s) => ({ key: s.key, label: s.label, value: s.value, color: ATT_COLORS[s.key] }));
  const pend = d.pending;
  const tabs = [
    ...(pend.ot.length ? [["ot", w("db.tab_ot"), pend.ot.length]] : []),
    ["leave", w("db.tab_leave"), pend.leave.length],
    ["attendance", w("db.tab_attendance"), pend.attendance.length],
  ];
  const opLabel = d.period === "MONTH" ? w("db.this_month") : w("db.today");

  return (
    <div className={cn("flex flex-col gap-5", busy && "opacity-70")}>
      <div className="flex flex-wrap items-center justify-end gap-3">
        <label className="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-1.5">
          <CalendarClock className="size-4 text-foreground-muted" aria-hidden />
          <Input type="date" value={d.date} onChange={(e) => e.target.value && load({ date: e.target.value })} aria-label={w("db.date")} className="h-8 border-0 px-1 shadow-none" />
        </label>
        <div className="inline-flex rounded-lg border border-border bg-surface-muted p-1" role="radiogroup" aria-label={w("db.period")}>
          {[
            ["TODAY", w("db.today")],
            ["MONTH", w("db.this_month")],
          ].map(([v, text]) => (
            <button key={v} type="button" role="radio" aria-checked={d.period === v} onClick={() => d.period !== v && load({ period: v })} className={cn("rounded-md px-3 py-1.5 text-[13px] font-semibold", d.period === v ? "bg-surface text-brand shadow-xs" : "text-foreground-muted hover:text-foreground")}>
              {text}
            </button>
          ))}
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Tile icon={Users} tone="bg-brand-subtle text-brand" label={w("db.k.total")} value={n(k.total)} sub={w("db.k.total_sub")}
          delta={k.totalDelta === null ? null : <p className={cn("mt-0.5 text-xs font-semibold", k.totalDelta >= 0 ? "text-success" : "text-danger")}>{k.totalDelta >= 0 ? "↗ +" : "↘ "}{k.totalDelta.toFixed(1)}%</p>} />
        <Tile icon={UserCheck} tone="bg-warning-subtle text-warning" label={w("db.k.active")} value={n(k.active)} sub={`${k.activePct.toFixed(1)}%`} />
        <Tile icon={UserX} tone="bg-success-subtle text-success" label={w("db.k.inactive")} value={n(k.inactive)} sub={w("db.k.inactive_sub")} />
        <Tile icon={LogOut} tone="bg-danger-subtle text-danger" label={w("db.k.resigned")} value={n(k.resigned)} sub={w("db.k.resigned_sub", { n: k.pendingSeparation })} />
        <Tile icon={Footprints} tone="bg-brand-subtle text-brand" label={w("db.k.lefty")} value={n(k.lefty)} sub={w("db.k.lefty_sub")} />
        <Tile icon={Fingerprint} tone="bg-warning-subtle text-warning" label={w("db.k.present")} value={n(k.present)} sub={w("db.k.present_sub", { p: k.presentPct.toFixed(1), n: k.late })} />
        <Tile icon={UserX} tone="bg-success-subtle text-success" label={w("db.k.absent")} value={n(k.absent)} sub={w("db.k.absent_sub", { p: k.absentPct.toFixed(1), n: k.missingPunch })} />
        <Tile icon={Clock} tone="bg-danger-subtle text-danger" label={w("db.k.pending_ot")} value={n(k.pendingOt)} sub={w("db.k.pending_ot_sub")} />
        <Tile icon={Hourglass} tone="bg-danger-subtle text-danger" label={w("db.k.approved_ot")} value={w("db.hrs", { n: cell(k.approvedOtHours, "hours") })} sub={d.monthLabel} />
        <Tile icon={Banknote} tone="bg-danger-subtle text-danger" label={w("db.k.net_payable")} value={k.netPayable === null ? "—" : `৳${cell(k.netPayable, "money")}`} sub={k.netPayable === null ? w("db.k.net_payable_none") : d.monthLabel} />
      </div>

      <div className="grid gap-5 lg:grid-cols-3">
        <Panel icon={Users} title={w("db.by_department")} sub={w("db.active_employees")}>
          <StatusDonut segments={segments} total={n(deptTotal)} totalLabel={w("db.employees")} />
          <ul className="mt-4 flex flex-col gap-1.5 text-[13px]">
            {segments.map((s) => (
              <li key={s.key} className="grid grid-cols-[1fr_auto_auto] items-center gap-3">
                <span className="flex items-center gap-2 truncate">
                  <span className="size-2.5 shrink-0 rounded-full" style={{ background: s.color }} />
                  {s.label}
                </span>
                <span className="font-semibold tabular-nums">{n(s.value)}</span>
                <span className="w-14 text-right tabular-nums text-foreground-muted">({deptTotal ? ((s.value * 100) / deptTotal).toFixed(1) : "0.0"}%)</span>
              </li>
            ))}
          </ul>
        </Panel>
        <Panel icon={CalendarCheck} title={w("db.attendance_overview")} sub={d.monthLabel}>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={d.attendanceDays.map((x) => ({ ...x, day: x.date.slice(8) }))} margin={{ top: 4, right: 4, left: -18, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                <XAxis dataKey="day" tick={{ fontSize: 11 }} interval={2} />
                <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                <Tooltip contentStyle={{ background: "var(--surface)", border: "1px solid var(--border)", fontSize: 12 }} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="present" name={w("db.att.PRESENT")} fill="#16a34a" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                <Bar dataKey="absent" name={w("db.att.ABSENT")} fill="#dc2626" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                <Bar dataKey="late" name={w("db.att.LATE")} fill="#f59e0b" radius={[3, 3, 0, 0]} isAnimationActive={false} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panel>
        <Panel icon={Wallet} title={w("db.payroll_summary")} sub={d.monthLabel}>
          {d.payroll ? (
            <dl className="flex flex-col divide-y divide-border text-[13px]">
              {[
                ["db.p.period", d.payroll.period],
                ["db.p.status", d.payroll.status],
                ["db.p.employees", n(d.payroll.employees)],
                ["db.p.gross", `৳${cell(d.payroll.gross, "money")}`],
                ["db.p.ot", `৳${cell(d.payroll.ot, "money")}`],
                ["db.p.bonus", `৳${cell(d.payroll.bonus, "money")}`],
                ["db.p.deductions", `৳${cell(d.payroll.deductions, "money")}`],
                ["db.p.net", `৳${cell(d.payroll.net, "money")}`],
              ].map(([key, v]) => (
                <div key={key} className="flex justify-between gap-3 py-2">
                  <dt className="text-foreground-muted">{w(key)}</dt>
                  <dd className="font-semibold tabular-nums">{v}</dd>
                </div>
              ))}
            </dl>
          ) : (
            <p className="text-[13px] text-foreground-muted">
              {w("db.payroll_none")}{" "}
              <Link href="/hr-payroll/monthly-salary" className="text-brand underline">
                {w("db.open_monthly_salary")}
              </Link>
            </p>
          )}
        </Panel>
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Panel icon={UserPlus} title={w("db.recent_employees")} action={d.can.employee ? <ViewAll href="/hr-payroll/employee-register" w={w} /> : null}>
          <MiniTable
            head={[{ label: w("db.c.id") }, { label: w("db.c.name") }, { label: w("db.c.card") }, { label: w("db.c.department") }, { label: w("db.c.designation") }, { label: w("db.c.joining") }, { label: w("db.c.status") }]}
            rows={d.recentEmployees.map((e) => [e.code, e.name, e.card, e.department, e.designation, dmy(e.joining), <span key="s" className={STATUS_TONE[e.status] ?? ""}>{w(`s.${e.status}`)}</span>])}
            empty={w("db.none_yet")}
          />
        </Panel>
        <Panel icon={ClipboardList} title={w("db.pending_approvals")} action={d.can.separation ? <ViewAll href="/hr-payroll/employee-separation" w={w} /> : null}>
          <MiniTable
            head={[{ label: w("db.c.type") }, { label: w("db.c.reference") }, { label: w("db.c.employee") }, { label: w("db.c.date") }, { label: w("db.c.status") }, { label: w("db.c.action") }]}
            rows={d.pendingSeparations.map((s) => [
              w(`s.${s.type}`),
              <span key="r" className="font-mono text-xs">{s.ref}</span>,
              s.employee,
              dmy(s.date),
              <span key="s" className="rounded-full bg-warning-subtle px-2 py-0.5 text-xs font-semibold text-warning">{w(`s.${s.status}`)}</span>,
              <Link key="a" href="/hr-payroll/employee-separation" className="text-xs font-semibold text-brand hover:underline">{w("db.review")}</Link>,
            ])}
            empty={w("db.no_pending_separation")}
          />
        </Panel>
        <Panel icon={Footprints} title={w("db.recent_lefty")} action={d.can.separation ? <ViewAll href="/hr-payroll/lefty-management" w={w} /> : null}>
          <MiniTable
            head={[{ label: w("db.c.id") }, { label: w("db.c.name") }, { label: w("db.c.card") }, { label: w("db.c.department") }, { label: w("db.c.last_attendance") }, { label: w("db.c.status") }]}
            rows={d.recentLefty.map((l) => [<span key="r" className="font-mono text-xs">{l.ref}</span>, l.name, l.card, l.department, dmy(l.last), <span key="s" className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", STATUS_TONE[l.status] ?? "bg-surface-muted")}>{w(`s.${l.status}`)}</span>])}
            empty={w("db.no_lefty")}
          />
        </Panel>
        <Panel icon={Bell} title={w("db.notifications")} action={<ViewAll href="/notifications" w={w} />}>
          <MiniTable
            head={[{ label: w("db.c.message") }, { label: w("db.c.date") }]}
            rows={d.notifications.map((x) => [
              <span key="m" className="flex items-start gap-2"><span className={cn("mt-1.5 size-2 shrink-0 rounded-full", x.unread ? "bg-brand" : "bg-success")} />{x.message}</span>,
              <span key="d" className="whitespace-nowrap">{now ? new Date(x.at).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : dmy(x.at)}</span>,
            ])}
            empty={w("db.no_notifications")}
          />
        </Panel>
      </div>

      <h2 className="mt-1 text-[17px] font-semibold">
        {w("db.operations")} <span className="text-sm font-normal text-foreground-muted">· {opLabel}</span>
      </h2>
      <div className="grid gap-5 lg:grid-cols-3">
        <Panel icon={BadgeCheck} title={w("db.designation_manpower")}>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={op.designation} margin={{ top: 4, right: 4, left: -18, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                <XAxis dataKey="category" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                <Tooltip contentStyle={{ background: "var(--surface)", border: "1px solid var(--border)", fontSize: 12 }} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="total" name={w("db.total")} fill="#4f46e5" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                <Bar dataKey="present" name={w("db.att.PRESENT")} fill="#ea580c" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                <Bar dataKey="absent" name={w("db.att.ABSENT")} fill="#16a34a" radius={[3, 3, 0, 0]} isAnimationActive={false} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panel>
        <Panel
          icon={TrendingUp}
          title={w("db.line_manpower", { dept: op.departments.find((x) => x.value === op.department)?.label ?? w("all") })}
        >
          <div className="mb-3">
            <Select value={op.department ?? ""} onValueChange={(v) => load({ department: v ?? "" })} options={op.departments} placeholder={w("f.department")} aria-label={w("f.department")} />
          </div>
          <MiniTable
            head={[{ label: "#" }, { label: w("db.c.line") }, { label: w("db.total"), right: true }, { label: w("db.att.PRESENT"), right: true }, { label: w("db.att.ABSENT"), right: true }, { label: w("db.c.ot_emp"), right: true }, { label: w("db.c.ot_hours"), right: true }]}
            rows={op.lines.map((l, i) => [i + 1, <span key="l" className="font-semibold">{l.line}</span>, n(l.total), n(l.present), <span key="a" className="text-danger">{n(l.absent)}</span>, n(l.otEmployees), cell(l.otHours, "hours")])}
            empty={w("db.no_lines")}
          />
        </Panel>
        <Panel icon={Activity} title={w("db.attendance_summary", { when: opLabel })}>
          <StatusDonut segments={attSegments} total={n(op.total)} totalLabel={d.period === "MONTH" ? w("db.employee_days") : w("db.employees")} />
          <ul className="mt-4 flex flex-col gap-1.5 text-[13px]">
            {attSegments.map((s) => (
              <li key={s.key} className="grid grid-cols-[1fr_auto_auto] items-center gap-3">
                <span className="flex items-center gap-2">
                  <span className="size-2.5 shrink-0 rounded-full" style={{ background: s.color }} />
                  {s.label}
                </span>
                <span className="font-semibold tabular-nums">{n(s.value)}</span>
                <span className="w-14 text-right tabular-nums text-foreground-muted">{op.total ? ((s.value * 100) / op.total).toFixed(1) : "0.0"}%</span>
              </li>
            ))}
          </ul>
        </Panel>
      </div>
      <div className="grid gap-5 lg:grid-cols-[1fr_1.4fr_1fr]">
        <Panel icon={Hourglass} title={w("db.top_ot", { when: opLabel })} action={d.can.ot ? <ViewAll href="/hr-payroll/ot-approval" w={w} /> : null}>
          <MiniTable
            head={[{ label: w("db.c.line") }, { label: w("db.c.employees"), right: true }, { label: w("db.c.ot_hours"), right: true }]}
            rows={op.otLines.map((x) => [x.line, n(x.employees), cell(x.hours, "hours")])}
            empty={d.period === "MONTH" ? w("db.no_ot_month") : w("db.no_ot_today")}
          />
        </Panel>
        <Panel icon={ClipboardList} title={w("db.pending_approvals")}>
          <div className="flex gap-1 border-b border-border" role="tablist">
            {tabs.map(([key, label, count]) => (
              <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => setTab(key)} className={cn("-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-[13px] font-semibold", tab === key ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
                {label}
                <span className="rounded-full bg-surface-muted px-1.5 text-[11px] tabular-nums text-foreground">{count}</span>
              </button>
            ))}
          </div>
          <div className="mt-3">
            {tab === "ot" ? (
              <PendingList items={pend.ot.map((x) => ({ key: x.ref, title: x.ref, detail: `${dmy(x.date)} · ${x.detail}`, note: x.reason, href: "/hr-payroll/ot-approval" }))} empty={w("db.no_ot_waiting")} w={w} />
            ) : tab === "leave" ? (
              <PendingList items={pend.leave.map((x) => ({ key: x.ref, title: `${x.employee} · ${x.type ?? ""}`, detail: `${dmy(x.from)} – ${dmy(x.to)} · ${w("db.days", { n: x.days })}`, note: x.ref, href: "/hr-payroll/leave-application" }))} empty={w("db.no_leave_waiting")} w={w} />
            ) : (
              <PendingList items={pend.attendance.map((x, i) => ({ key: `${x.code}-${i}`, title: `${x.employee} (${x.code})`, detail: `${dmy(x.date)} · ${x.change}`, note: [x.reason, x.requestedBy].filter(Boolean).join(" · "), href: "/hr-payroll/attendance-correction" }))} empty={w("db.no_attendance_waiting")} w={w} />
            )}
          </div>
        </Panel>
        <Panel icon={Clock} title={w("db.recent_activities")}>
          {d.activities.length ? (
            <ul className="flex flex-col gap-3">
              {d.activities.map((a, i) => {
                const AIcon = a.kind === "DEVICE" ? Fingerprint : UserRound;
                return (
                  <li key={i} className="flex items-start gap-3">
                    <span className={cn("grid size-8 shrink-0 place-items-center rounded-lg", a.kind === "DEVICE" ? "bg-warning-subtle text-warning" : "bg-brand-subtle text-brand")}>
                      <AIcon className="size-4" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="text-[13px] font-semibold">{a.title}</p>
                      <p className="truncate text-xs text-foreground-muted">{a.detail}</p>
                    </div>
                    <span className="shrink-0 text-xs text-foreground-muted">{ago(a.at, w, now)}</span>
                  </li>
                );
              })}
            </ul>
          ) : (
            <p className="py-8 text-center text-sm text-foreground-muted">{w("db.no_activity")}</p>
          )}
        </Panel>
      </div>
    </div>
  );
}

function PendingList({ items, empty, w }) {
  if (!items.length) return <p className="py-8 text-center text-sm text-foreground-muted">{empty}</p>;
  return (
    <ul className="flex flex-col divide-y divide-border">
      {items.map((x) => (
        <li key={x.key} className="flex items-start justify-between gap-3 py-2.5">
          <div className="min-w-0">
            <p className="truncate text-[13px] font-semibold">{x.title}</p>
            <p className="truncate text-xs text-foreground-muted">{x.detail}</p>
            {x.note ? <p className="truncate text-xs text-foreground-muted">{x.note}</p> : null}
          </div>
          <Link href={x.href} className="shrink-0 text-xs font-semibold text-brand hover:underline">
            {w("db.review")}
          </Link>
        </li>
      ))}
    </ul>
  );
}

export { HrReports, HrDashboard };
