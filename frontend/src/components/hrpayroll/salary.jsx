"use client";

import { Fragment, useMemo, useState, useTransition } from "react";
import { ArrowLeft, ArrowRight, BadgeCheck, Banknote, Calculator, CheckCheck, CircleCheck, CircleX, ClipboardCheck, Clock, Download, Eye, FileText, Gift, Hourglass, Info, Landmark, Lock, LockOpen, PartyPopper, Printer, RefreshCw, RotateCcw, Scale, Search, ShieldAlert, TriangleAlert, Undo2, UserCheck, UserX, Users, Wallet } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Tabs } from "@/components/settings/devices";
import { ampm, clock12 } from "@/lib/clock";

/*
 * HR & Payroll → Salary Processing (AnnotechERP 24/06), as Brickland lays
 * the five screens out:
 *
 * - Daily Salary: load a day for a scope (bulk, one employee, a department,
 *   section or line); calculate, review, approve or un-approve — all rows
 *   or the ticked ones; print and CSV.
 * - OT Salary: the OT paid from attendance over a range, corrections
 *   applied; recalculate the range's attendance; print and Excel.
 * - Festival Bonus: the festivals paid with a payroll month; a festival's
 *   bonus sheet (eligible and not, with why) from preview through
 *   calculate, review, approve, finalize and paid; print and CSV.
 * - Monthly Salary: a pay period's run for a scope — calculate, review,
 *   approve, finalize, mark paid, re-open; Salary Review, Gross A & Gross B,
 *   Bank and Cash payment; payslips and the salary sheet.
 * - Payroll Adjustment: finalized slips against today's OT; raise the
 *   difference; approve or reject; paid once with the next salary.
 *
 * Nothing reloads; tabs and dialogs switch in place.
 */

const card = "rounded-xl border border-border bg-surface";
const money = new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const hrs = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 });
const taka = (v) => (v === null || v === undefined ? "—" : `৳${money.format(v)}`);
const plain = (v) => (v === null || v === undefined ? "" : Number(v).toFixed(2));
const hours = (v) => (v === null || v === undefined ? "—" : hrs.format(v));
const day = (d) => (d ? new Date(`${d.slice(0, 10)}T00:00:00Z`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }) : "—");
const stamp = (s) => {
  if (!s) return "—";
  const d = new Date(/(Z|[+-]\d\d:?\d\d)$/.test(s) ? s : `${s.replace(" ", "T")}Z`);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleString("en-GB", { day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase());
};
const monthName = (ym) => (ym ? new Date(`${ym}-01T00:00:00Z`).toLocaleString("en-GB", { month: "long", year: "numeric", timeZone: "UTC" }) : "");
const firstError = (res) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "";

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`sp.${k}`, p);
}

function downloadCsv(name, head, rows) {
  const body = [head, ...rows].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\r\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob([`﻿${body}`], { type: "text/csv;charset=utf-8" }));
  link.download = `${name}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

function Pill({ t = "neutral", children }) {
  const tones = { good: "bg-success-subtle text-success", warning: "bg-warning-subtle text-warning", danger: "bg-danger-subtle text-danger", info: "bg-brand-subtle text-brand", neutral: "bg-surface-muted text-foreground-muted" };
  return <span className={cn("inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap", tones[t])}>{children}</span>;
}

function Field({ label, required, hint, className, children }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </div>
  );
}

function Head({ icon: Icon, title, sub, children }) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div className="flex items-start gap-2.5">
        {Icon ? <Icon className="mt-0.5 size-4.5 text-foreground-muted" aria-hidden /> : null}
        <div>
          <h3 className="text-[15px] font-semibold">{title}</h3>
          {sub ? <p className="text-[13px] text-foreground-muted">{sub}</p> : null}
        </div>
      </div>
      {children}
    </div>
  );
}

function Stat({ icon: Icon, label, value, caption, tone = "brand" }) {
  const tones = { brand: "bg-brand-subtle text-brand", warning: "bg-warning-subtle text-warning", good: "bg-success-subtle text-success", danger: "bg-danger-subtle text-danger" };
  return (
    <div className={cn(card, "min-w-0 px-4 py-3.5")}>
      <div className="flex items-center gap-2.5">
        <span className={cn("grid size-8 shrink-0 place-items-center rounded-lg", tones[tone])}>
          <Icon className="size-4" aria-hidden />
        </span>
        <span className="truncate text-[13px] font-semibold">{label}</span>
      </div>
      <p className="mt-2.5 truncate text-2xl font-semibold tabular-nums">{value}</p>
      {caption ? <p className="mt-0.5 text-xs text-foreground-muted">{caption}</p> : null}
    </div>
  );
}

function Note({ tone = "info", icon: Icon = Info, children }) {
  const tones = { info: "text-foreground-muted", warning: "text-warning", danger: "text-danger", good: "text-success" };
  return (
    <p className={cn("flex items-start gap-2 text-[13px]", tones[tone])}>
      <Icon className="mt-0.5 size-3.5 shrink-0" aria-hidden />
      <span>{children}</span>
    </p>
  );
}

function Banner({ tone = "info", icon: Icon = Info, children }) {
  const tones = { info: "bg-brand-subtle text-brand", warning: "bg-warning-subtle text-warning", good: "bg-success-subtle text-success" };
  return (
    <div className={cn("flex items-start gap-2 rounded-lg px-4 py-3 text-[13px]", tones[tone])}>
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
      <span>{children}</span>
    </div>
  );
}

function Segments({ items, value, onChange, label }) {
  return (
    <div className="inline-flex flex-wrap rounded-lg border border-border bg-surface-muted p-1" role="radiogroup" aria-label={label}>
      {items.map(([k, text]) => (
        <button key={k} type="button" role="radio" aria-checked={value === k} onClick={() => onChange(k)} className={cn("rounded-md px-3 py-1.5 text-[13px] font-semibold", value === k ? "bg-surface text-brand shadow-xs" : "text-foreground-muted hover:text-foreground")}>
          {text}
        </button>
      ))}
    </div>
  );
}

/** A searchable, paged table; `selection` adds a tick column. */
function Table({ w, columns, rows, rowKey, selection, searchPh, empty, minWidth = 1100, foot }) {
  const [needle, setNeedle] = useState("");
  const [perPage, setPerPage] = useState(25);
  const [page, setPage] = useState(1);
  const text = (c, r) => String(c.read ? (c.read(r) ?? "") : (ampm(r[c.key]) ?? ""));
  const shown = useMemo(() => (needle ? rows.filter((r) => columns.some((c) => text(c, r).toLowerCase().includes(needle.toLowerCase()))) : rows), [rows, needle, columns]);
  const pages = Math.max(1, Math.ceil(shown.length / perPage));
  const cur = Math.min(page, pages);
  const slice = shown.slice((cur - 1) * perPage, cur * perPage);
  const keys = slice.map(rowKey);
  const all = selection && keys.length > 0 && keys.every((k) => selection.selected.has(k));
  return (
    <div>
      {searchPh !== false ? (
        <div className="px-5 pb-3">
          <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <Input className="pl-9" type="search" value={needle} onChange={(e) => (setNeedle(e.target.value), setPage(1))} placeholder={searchPh ?? w("grid.search")} aria-label={searchPh ?? w("grid.search")} />
          </div>
        </div>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full text-sm" style={{ minWidth }}>
          <thead>
            <tr className="bg-brand text-left text-[13px] text-brand-foreground">
              {selection ? (
                <th className="w-10 px-3 py-2.5">
                  <Checkbox checked={all} onCheckedChange={() => selection.onToggleAll(keys)} aria-label={w("grid.select_all")} />
                </th>
              ) : null}
              {columns.map((c) => (
                <th key={c.key} className={cn("px-3 py-2.5 font-semibold whitespace-nowrap", c.right && "text-right", c.center && "text-center")}>
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {slice.length ? (
              slice.map((r, i) => (
                <tr key={rowKey(r)} className="border-b border-border last:border-0 hover:bg-surface-muted">
                  {selection ? (
                    <td className="px-3 py-2.5 align-top">
                      <Checkbox checked={selection.selected.has(rowKey(r))} onCheckedChange={() => selection.onToggle(rowKey(r))} aria-label={w("grid.select_row", { n: (cur - 1) * perPage + i + 1 })} />
                    </td>
                  ) : null}
                  {columns.map((c) => (
                    <td key={c.key} className={cn("px-3 py-2.5 align-top", c.right && "text-right tabular-nums", c.center && "text-center", c.nowrap && "whitespace-nowrap")}>
                      {c.render ? c.render(r) : text(c, r) || "—"}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length + (selection ? 1 : 0)} className="px-5 py-10 text-center text-sm text-foreground-muted">
                  {needle ? w("grid.no_match") : empty}
                </td>
              </tr>
            )}
          </tbody>
          {foot && slice.length ? <tfoot>{foot}</tfoot> : null}
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
        <span>{shown.length ? w("grid.range", { a: (cur - 1) * perPage + 1, b: Math.min(shown.length, cur * perPage), n: shown.length }) : w("grid.zero")}</span>
        <div className="flex items-center gap-2">
          <span className="whitespace-nowrap">{w("grid.per_page")}</span>
          <Select value={String(perPage)} onValueChange={(v) => (setPerPage(Number(v)), setPage(1))} options={[10, 25, 50, 100].map((n) => ({ value: String(n), label: String(n) }))} aria-label={w("grid.per_page")} />
          <Button size="sm" variant="outline" disabled={cur <= 1} onClick={() => setPage(cur - 1)} aria-label={w("grid.previous")}>
            <ArrowLeft />
          </Button>
          <span className="whitespace-nowrap tabular-nums">
            {cur} / {pages}
          </span>
          <Button size="sm" variant="outline" disabled={cur >= pages} onClick={() => setPage(cur + 1)} aria-label={w("grid.next")}>
            <ArrowRight />
          </Button>
        </div>
      </div>
    </div>
  );
}

/* ── Scope ───────────────────────────────────────────────────────── */

const MODE_KEYS = { BULK: ["department", "section", "line", "employee"], INDIVIDUAL: ["employee"], DEPARTMENT: ["department"], SECTION: ["department", "section"], LINE: ["department", "section", "line"] };
const EMPTY_SCOPE = { mode: "BULK", department: "", section: "", line: "", employee: "" };
const scopeQuery = (s) => Object.fromEntries(Object.entries(s).filter(([, v]) => v !== ""));
const sameScope = (a, b) => ["mode", "department", "section", "line", "employee"].every((k) => (a?.[k] ?? "") === (b?.[k] ?? ""));

function ScopeBar({ w, options, value, onChange, leading, action }) {
  const keys = MODE_KEYS[value.mode];
  const sections = value.department ? options.sections.filter((s) => s.department_id === value.department) : options.sections;
  const lines = value.section ? options.lines.filter((l) => l.section_id === value.section || (!l.section_id && l.department_id === value.department)) : value.department ? options.lines.filter((l) => l.department_id === value.department) : options.lines;
  const set = (k, v) => onChange({ ...value, [k]: v, ...(k === "department" ? { section: "", line: "" } : k === "section" ? { line: "" } : {}) });
  const pick = (k, label, opts, all) => (
    <Field label={label}>
      <Select value={value[k]} disabled={!keys.includes(k)} onValueChange={(v) => set(k, v ?? "")} options={[{ value: "", label: all }, ...opts]} placeholder={all} aria-label={label} />
    </Field>
  );
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs font-medium text-foreground-muted">{w("mode")}</span>
        <Segments
          label={w("mode")}
          value={value.mode}
          onChange={(m) => onChange(Object.fromEntries(Object.entries({ ...value, mode: m }).map(([k, v]) => [k, k === "mode" || MODE_KEYS[m].includes(k) ? v : ""])))}
          items={Object.keys(MODE_KEYS).map((m) => [m, w(`mode_opt.${m}`)])}
        />
      </div>
      <div className="grid items-end gap-4 md:grid-cols-3 xl:grid-cols-6">
        {leading}
        {pick("department", w("f.department"), options.departments, w("all_departments"))}
        {pick("section", w("f.section"), sections, w("all_sections"))}
        {pick("line", w("f.line"), lines, w("all_lines"))}
        {pick("employee", w("f.employee"), options.employees, w("all_employees"))}
        {action}
      </div>
    </div>
  );
}

const ATT_TONE = { PRESENT: "good", ABSENT: "danger", LEAVE: "info", HOLIDAY: "neutral", WEEKLY_OFF: "neutral", MISSING_PUNCH: "warning", NO_RECORD: "warning" };
const DAILY_TONE = { NOT_CALCULATED: "neutral", CALCULATED: "info", REVIEWED: "warning", APPROVED: "good" };

/* ── Daily Salary ────────────────────────────────────────────────── */

function DailySalary({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const can = options.can;
  const [scope, setScope] = useState(EMPTY_SCOPE);
  const [date, setDate] = useState(initial.date);
  const [view, setView] = useState(initial);
  const [loaded, setLoaded] = useState({ date: initial.date, scope: EMPTY_SCOPE });
  const [selected, setSelected] = useState(new Set());
  const [confirm, setConfirm] = useState(null);
  const [pending, start] = useTransition();
  const dirty = date !== loaded.date || !sameScope(scope, loaded.scope);
  const body = () => ({ date: loaded.date, ...scopeQuery(loaded.scope), ...(selected.size ? { employees: [...selected] } : {}) });
  const load = () =>
    start(async () => {
      const res = await act("salaryDaily", { date, ...scopeQuery(scope) });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      setView(res.data);
      setLoaded({ date, scope });
      setSelected(new Set());
      toast.add({ title: w("done.loaded_daily", { n: res.data.rows.length, date: res.data.date }), type: "info" });
    });
  const run = (op, payload) =>
    start(async () => {
      const res = await act(op, payload);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setView(res.data.view);
      setSelected(new Set());
    });
  const s = view.summary;
  const rows = view.rows;
  const anyApproved = rows.some((r) => r.status === "APPROVED");
  const print = () =>
    printDocument({
      title: w("printdoc.daily"),
      subtitle: `${day(view.date)} · ${view.scope_label}`,
      orientation: "landscape",
      meta: [
        [w("f.date"), day(view.date)],
        [w("scope"), view.scope_label],
        [w("tile.employees"), `${s.employees} · ${w("tile.calc_approved", { c: s.calculated, a: s.approved })}`],
        [w("tile.net_daily"), taka(s.net)],
      ],
      sections: [
        {
          columns: [{ label: "#", align: "right" }, { label: w("col.code") }, { label: w("col.name") }, { label: w("col.department") }, { label: w("col.attendance") }, { label: w("col.daily_salary"), align: "right" }, { label: w("col.allowance"), align: "right" }, { label: w("col.ot_amount"), align: "right" }, { label: w("col.deduction"), align: "right" }, { label: w("col.net"), align: "right" }, { label: w("col.status") }],
          rows: rows.map((r, i) => [i + 1, r.code, r.name, r.department ?? "—", w(`att.${r.attendance}`), plain(r.daily_salary), plain(r.allowance), plain(r.ot_amount), plain(r.late_deduction + r.other_deduction), plain(r.net), w(`daily_status.${r.status}`)]),
          foot: [["", "", w("total"), "", "", plain(rows.reduce((a, r) => a + r.daily_salary, 0)), plain(s.allowance), plain(s.ot), plain(s.deductions), plain(s.net), ""]],
        },
      ],
      notes: [view.late_note ?? w("late_daily")],
      signatures: true,
    });
  const exportCsv = () =>
    downloadCsv(
      `daily-salary-${view.date}`,
      [w("col.code"), w("col.name"), w("col.department"), w("col.section"), w("col.line"), w("col.designation"), w("col.attendance"), w("col.in"), w("col.out"), w("col.late_min"), w("col.daily_salary"), w("col.allowance"), w("col.ot_hours"), w("col.ot_rate"), w("col.ot_amount"), w("col.late_ded"), w("col.other_ded"), w("col.net"), w("col.status"), w("col.approved_by")],
      rows.map((r) => [r.code, r.name, r.department, r.section, r.line, r.designation, w(`att.${r.attendance}`), clock12(r.in_time), clock12(r.out_time), r.late_minutes, plain(r.daily_salary), plain(r.allowance), r.ot_hours, plain(r.ot_rate), plain(r.ot_amount), plain(r.late_deduction), plain(r.other_deduction), plain(r.net), w(`daily_status.${r.status}`), r.approved_by ?? ""]),
    );
  const columns = [
    {
      key: "code",
      label: w("col.employee"),
      read: (r) => `${r.code} ${r.name}`,
      render: (r) => (
        <div className="min-w-44">
          <div className="font-semibold">{r.name}</div>
          <div className="text-xs text-foreground-muted">
            {r.code}
            {r.designation ? ` · ${r.designation}` : ""}
          </div>
          {r.flags.map((f) => (
            <div key={f} className="mt-0.5 flex items-center gap-1 text-xs text-warning">
              <TriangleAlert className="size-3" aria-hidden /> {f}
            </div>
          ))}
        </div>
      ),
    },
    { key: "place", label: w("col.place"), read: (r) => [r.department, r.section, r.line].filter(Boolean).join(" › ") },
    {
      key: "attendance",
      label: w("col.attendance"),
      read: (r) => w(`att.${r.attendance}`),
      render: (r) => (
        <div>
          <Pill t={ATT_TONE[r.attendance]}>{w(`att.${r.attendance}`)}</Pill>
          <div className="mt-0.5 text-xs whitespace-nowrap text-foreground-muted">
            {clock12(r.in_time) || "--:--"} – {clock12(r.out_time) || "--:--"}
            {r.late_minutes > 0 ? ` · ${w("late_m", { n: r.late_minutes })}` : ""}
          </div>
        </div>
      ),
    },
    { key: "daily_salary", label: w("col.daily_salary"), right: true, render: (r) => taka(r.daily_salary) },
    { key: "allowance", label: w("col.allowance"), right: true, render: (r) => taka(r.allowance) },
    {
      key: "ot_amount",
      label: w("col.ot"),
      right: true,
      render: (r) => (
        <div>
          <div>{taka(r.ot_amount)}</div>
          {r.ot_hours > 0 ? <div className="text-xs text-foreground-muted">{w("h_at", { h: hours(r.ot_hours), rate: taka(r.ot_rate) })}</div> : null}
        </div>
      ),
    },
    { key: "late_deduction", label: w("col.late_ded"), right: true, render: (r) => taka(r.late_deduction) },
    { key: "other_deduction", label: w("col.other_ded"), right: true, render: (r) => taka(r.other_deduction) },
    { key: "net", label: w("col.net"), right: true, render: (r) => <span className="font-semibold">{taka(r.net)}</span> },
    {
      key: "status",
      label: w("col.status"),
      read: (r) => w(`daily_status.${r.status}`),
      render: (r) => (
        <div>
          <Pill t={DAILY_TONE[r.status]}>{w(`daily_status.${r.status}`)}</Pill>
          {r.approved_by ? <div className="mt-0.5 text-xs whitespace-nowrap text-foreground-muted">{r.approved_by}</div> : null}
        </div>
      ),
    },
  ];
  const confirmText = confirm ? `${w(`confirm.${confirm}.text`)}${selected.size ? ` ${w("confirm.ticked", { n: selected.size })}` : ""}` : "";
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <Head icon={Clock} title={w("load_attendance")} sub={w("load_attendance_sub")} />
        <div className="mt-4">
          <ScopeBar
            w={w}
            options={options}
            value={scope}
            onChange={setScope}
            leading={
              <Field label={w("f.date")} required>
                <Input type="date" value={date} max={options.today} onChange={(e) => setDate(e.target.value)} aria-label={w("f.date")} />
              </Field>
            }
            action={
              <Button onClick={load} loading={pending && !confirm} disabled={!date}>
                <RefreshCw /> {w("load")}
              </Button>
            }
          />
        </div>
      </section>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <Stat icon={Users} label={w("tile.employees")} value={s.employees} caption={w("tile.calc_approved", { c: s.calculated, a: s.approved })} />
        <Stat icon={Wallet} label={w("tile.total_daily")} value={taka(s.daily_salary)} caption={w("tile.incl_allowance", { n: taka(s.allowance) })} tone="warning" />
        <Stat icon={Clock} label={w("tile.ot_amount")} value={taka(s.ot)} caption={w("tile.approved_ot")} tone="good" />
        <Stat icon={Undo2} label={w("tile.deductions")} value={taka(s.deductions)} caption={w("tile.late_other")} tone="warning" />
        <Stat icon={BadgeCheck} label={w("tile.net_daily")} value={taka(s.net)} caption={w("tile.flagged", { n: s.flagged })} tone="danger" />
      </div>
      <section className={card}>
        <div className="flex flex-col gap-3 px-5 py-4">
          <Head title={w("daily_title", { date: view.date })} sub={w("daily_sub", { scope: view.scope_label, n: view.days_in_month })}>
            <div className="flex flex-wrap gap-2">
              {can.calculate ? (
                <Button onClick={() => run("salaryDailyCalc", body())} loading={pending && !confirm} disabled={dirty || pending}>
                  <Calculator /> {w("calculate")}
                  {selected.size ? ` (${selected.size})` : ""}
                </Button>
              ) : null}
              {can.calculate ? (
                <Button variant="outline" onClick={() => setConfirm("review")} disabled={dirty || pending}>
                  <ClipboardCheck /> {w("review")}
                </Button>
              ) : null}
              {can.approve ? (
                <Button variant="outline" onClick={() => setConfirm("approve")} disabled={dirty || pending}>
                  <CircleCheck /> {w("approve")}
                </Button>
              ) : null}
              {can.approve && anyApproved ? (
                <Button variant="outline" onClick={() => setConfirm("unapprove")} disabled={dirty || pending}>
                  <RotateCcw /> {w("unapprove")}
                </Button>
              ) : null}
              <Button variant="outline" onClick={print} disabled={!rows.length}>
                <Printer /> {w("print")}
              </Button>
              <Button variant="outline" onClick={exportCsv} disabled={!rows.length}>
                <Download /> {w("csv")}
              </Button>
            </div>
          </Head>
          <Note>{w("late_daily")}</Note>
          {dirty ? (
            <Note tone="warning" icon={TriangleAlert}>
              {w("filters_changed")}
            </Note>
          ) : null}
        </div>
        <Table
          w={w}
          columns={columns}
          rows={rows}
          rowKey={(r) => r.code}
          searchPh={w("ph.search_daily")}
          empty={w("empty.daily")}
          selection={can.calculate || can.approve ? { selected, onToggle: (k) => setSelected((x) => { const n = new Set(x); n.has(k) ? n.delete(k) : n.add(k); return n; }), onToggleAll: (ks) => setSelected((x) => { const every = ks.every((k) => x.has(k)); const n = new Set(x); ks.forEach((k) => (every ? n.delete(k) : n.add(k))); return n; }) } : undefined}
        />
      </section>
      <ConfirmDialog
        open={!!confirm}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? w(`confirm.${confirm}.title`) : ""}
        description={confirmText}
        confirmLabel={confirm ? w(confirm) : ""}
        cancelLabel={w("cancel")}
        destructive={confirm === "unapprove"}
        loading={pending}
        onConfirm={() => {
          const action = confirm;
          setConfirm(null);
          run("salaryDailyMove", { ...body(), action });
        }}
      />
    </div>
  );
}

/* ── OT Salary ───────────────────────────────────────────────────── */

function OtSalary({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [q, setQ] = useState({ from: initial.filters.from, to: initial.filters.to, department: "", section: "", line: "", employee: "" });
  const [data, setData] = useState(initial);
  const [pending, start] = useTransition();
  const [busy, setBusy] = useState(false);
  const sections = q.department ? options.sections.filter((s) => s.department_id === q.department) : options.sections;
  const lines = q.section ? options.lines.filter((l) => l.section_id === q.section || (!l.section_id && l.department_id === q.department)) : q.department ? options.lines.filter((l) => l.department_id === q.department) : options.lines;
  const query = () => Object.fromEntries(Object.entries(q).filter(([, v]) => v !== ""));
  const load = () =>
    start(async () => {
      const res = await act("salaryOt", query());
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      setData(res.data);
    });
  const recalc = () => {
    setBusy(true);
    start(async () => {
      const res = await act("salaryOtRecalc", query());
      setBusy(false);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.recalc"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      const again = await act("salaryOt", query());
      if (again?.status === "success") setData(again.data);
    });
  };
  const range = `${day(data.filters.from)} – ${day(data.filters.to)}`;
  const label = (list, v) => (v ? (list.find((o) => o.value === v)?.label ?? v) : w("all"));
  const head = [w("col.date"), w("col.employee"), w("col.department"), w("col.section"), w("col.line"), w("col.shift"), w("col.shift_end"), w("col.out_punch"), w("col.original_ot_h"), w("col.ot_h"), w("col.ot_rate"), w("col.ot_amount"), w("col.correction")];
  const line = (r) => [day(r.date), `${r.code} ${r.name}`, r.department ?? "—", r.section ?? "—", r.line ?? "—", r.shift ?? "—", clock12(r.shift_end) ?? "—", clock12(r.out_time) ?? "—", hours(r.original_hours), hours(r.hours), plain(r.rate), plain(r.amount), r.corrected ? w("corrected") : w("auto")];
  const print = () =>
    printDocument({
      title: w("printdoc.ot"),
      subtitle: range,
      orientation: "landscape",
      meta: [
        [w("f.date"), range],
        [w("f.department"), label(options.departments, data.filters.department)],
        [w("f.section"), label(options.sections, data.filters.section)],
        [w("f.line"), label(options.lines, data.filters.line)],
        [w("f.employee"), label(options.employees, data.filters.employee)],
        [w("tile.ot_employees"), data.employees],
        [w("tile.total_ot_hours"), hours(data.total_hours)],
        [w("tile.total_ot_amount"), taka(data.total_amount)],
      ],
      metaColumns: 4,
      sections: [{ title: w("details"), columns: head.map((h, i) => ({ label: h, align: i >= 8 && i <= 11 ? "right" : undefined })), rows: data.rows.map(line), foot: [["", w("total"), "", "", "", "", "", "", hours(data.rows.reduce((a, r) => a + r.original_hours, 0)), hours(data.total_hours), "", plain(data.total_amount), ""]] }],
      signatures: true,
    });
  const columns = [
    { key: "date", label: w("col.date"), nowrap: true, read: (r) => day(r.date) },
    { key: "code", label: w("col.employee"), read: (r) => `${r.code} ${r.name}`, render: (r) => <span className="whitespace-nowrap"><span className="font-mono text-xs font-semibold">{r.code}</span> {r.name}</span> },
    { key: "department", label: w("col.department") },
    { key: "section", label: w("col.section") },
    { key: "line", label: w("col.line") },
    { key: "shift", label: w("col.shift") },
    { key: "shift_end", label: w("col.shift_end") },
    { key: "out_time", label: w("col.out_punch") },
    { key: "original_hours", label: w("col.original_ot_h"), right: true, render: (r) => hours(r.original_hours) },
    { key: "hours", label: w("col.ot_h"), right: true, render: (r) => hours(r.hours) },
    { key: "rate", label: w("col.ot_rate"), right: true, render: (r) => money.format(r.rate) },
    { key: "amount", label: w("col.ot_amount"), right: true, render: (r) => <span className="font-semibold">{money.format(r.amount)}</span> },
    { key: "corrected", label: w("col.correction"), read: (r) => (r.corrected ? w("corrected") : w("auto")), render: (r) => <Pill t={r.corrected ? "warning" : "neutral"}>{r.corrected ? w("corrected") : w("auto")}</Pill> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
          <Field label={w("f.from")}>
            <Input type="date" value={q.from} max={options.today} onChange={(e) => setQ({ ...q, from: e.target.value })} aria-label={w("f.from")} />
          </Field>
          <Field label={w("f.to")} hint={w("hint.ot_range")}>
            <Input type="date" value={q.to} max={options.today} onChange={(e) => setQ({ ...q, to: e.target.value })} aria-label={w("f.to")} />
          </Field>
          <Field label={w("f.department")}>
            <Select value={q.department} onValueChange={(v) => setQ({ ...q, department: v ?? "", section: "", line: "" })} options={[{ value: "", label: w("all") }, ...options.departments]} placeholder={w("all")} aria-label={w("f.department")} />
          </Field>
          <Field label={w("f.section")}>
            <Select value={q.section} onValueChange={(v) => setQ({ ...q, section: v ?? "", line: "" })} options={[{ value: "", label: w("all") }, ...sections]} placeholder={w("all")} aria-label={w("f.section")} />
          </Field>
          <Field label={w("f.line")}>
            <Select value={q.line} onValueChange={(v) => setQ({ ...q, line: v ?? "" })} options={[{ value: "", label: w("all") }, ...lines]} placeholder={w("all")} aria-label={w("f.line")} />
          </Field>
          <Field label={w("f.employee")}>
            <Select value={q.employee} onValueChange={(v) => setQ({ ...q, employee: v ?? "" })} options={[{ value: "", label: w("all") }, ...options.employees]} placeholder={w("all")} aria-label={w("f.employee")} />
          </Field>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          <Button onClick={load} loading={pending && !busy}>
            <RefreshCw /> {w("show")}
          </Button>
          {data.can_recalculate ? (
            <Button variant="outline" onClick={recalc} loading={busy}>
              <Calculator /> {busy ? w("recalculating") : w("recalculate")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={print} disabled={!data.rows.length}>
            <Printer /> {w("print_pdf")}
          </Button>
          <Button variant="outline" onClick={() => downloadCsv(`ot-salary-${data.filters.from}-${data.filters.to}`, head, data.rows.map(line))} disabled={!data.rows.length}>
            <Download /> {w("excel")}
          </Button>
        </div>
      </section>
      <div className="grid gap-3 md:grid-cols-3">
        <Stat icon={Users} label={data.filters.from === data.filters.to && data.filters.to === options.today ? w("tile.ot_employee_today") : w("tile.ot_employees")} value={data.employees} caption={range} />
        <Stat icon={Clock} label={w("tile.total_ot_hours")} value={hours(data.total_hours)} caption={w("tile.after_shift")} tone="warning" />
        <Stat icon={Wallet} label={w("tile.total_ot_amount")} value={taka(data.total_amount)} caption={w("tile.ot_x_rate")} tone="good" />
      </div>
      <section className={card}>
        <div className="px-5 py-4">
          <Head title={w("details_title")} sub={`${w("ot_lines", { n: data.rows.length, range })}${data.truncated ? w("truncated") : ""}${data.waiting ? ` · ${w("waiting", { n: data.waiting })}` : ""}`} />
        </div>
        <Table w={w} columns={columns} rows={data.rows} rowKey={(r) => r.key} searchPh={false} empty={w("empty.ot")} minWidth={1400} />
      </section>
    </div>
  );
}

/* ── Festival Bonus ──────────────────────────────────────────────── */

const BONUS_FLOW = ["DRAFT", "REVIEWED", "APPROVED", "FINALIZED", "PAID"];
const BONUS_TONE = { DRAFT: "info", REVIEWED: "warning", APPROVED: "good", FINALIZED: "good", PAID: "good" };

function FestivalBonus({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [month, setMonth] = useState(initial.month);
  const [data, setData] = useState(initial);
  const [tab, setTab] = useState("eligible");
  const [pending, start] = useTransition();
  const can = data.can;
  const load = (m, festival) =>
    start(async () => {
      const res = await act("salaryBonus", { month: m, ...(festival ? { festival } : {}) });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      setData(res.data);
    });
  const run = (op, payload) =>
    start(async () => {
      const res = await act(op, payload);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.again"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setData(res.data.view);
    });
  const sheet = data.sheet;
  const f = sheet?.festival;
  const status = sheet?.run?.status ?? null;
  const locked = status === "FINALIZED" || status === "PAID";
  const canCalc = can.calculate && (status === null || status === "DRAFT" || status === "REVIEWED");
  const name = f ? `${f.name} ${f.year}` : "";
  const exportCsv = () => {
    const rows = [
      [`${name} — ${status ? w(`bonus_status.${status}`) : w("preview")}`],
      [w("col.code"), w("col.name"), w("col.department"), w("col.designation"), w("col.joining_date"), w("col.service_days"), w("col.basis"), w("col.basis_amount"), w("col.pct"), w("col.fixed"), w("col.bonus"), w("col.rule")],
      ...sheet.eligible.map((e) => [e.code, e.name, e.department, e.designation, e.joining_date, e.service_days, w(`basis.${e.basis}`), plain(e.basis_amount), e.pct ?? "", plain(e.fixed), plain(e.amount), e.rule]),
      [w("total"), "", "", "", "", "", "", "", "", "", plain(sheet.total), ""],
      [],
      [w("tab.not_eligible")],
      [w("col.code"), w("col.name"), w("col.department"), w("col.designation"), w("col.joining_date"), w("col.service_days"), w("col.reason")],
      ...sheet.not_eligible.map((e) => [e.code, e.name, e.department, e.designation, e.joining_date, e.service_days ?? "", e.reason]),
    ];
    downloadCsv(`festival-bonus-${f.name.replace(/[^A-Za-z0-9]+/g, "-").toLowerCase()}-${f.year}`, rows[0], rows.slice(1));
  };
  const print = () =>
    printDocument({
      title: w("printdoc.bonus"),
      docNo: name,
      subtitle: `${status ? w(`bonus_status.${status}`) : w("preview")} · ${w("festival_on", { date: day(f.festival_date) })} · ${w("paid_with", { month: monthName(f.payment_month) })}`,
      orientation: "landscape",
      meta: [
        [w("tile.festival"), name],
        [w("col.festival_date"), day(f.festival_date)],
        [w("paid_with_label"), monthName(f.payment_month)],
        [w("bonus_type"), f.bonus_type ? w(`bonus_type_opt.${f.bonus_type}`) : w("bonus_type_unset")],
        [w("tile.eligible"), sheet.eligible.length],
        [w("tile.total_bonus"), taka(sheet.total)],
      ],
      sections: [
        {
          title: w("tab.eligible"),
          columns: [{ label: w("col.code") }, { label: w("col.name") }, { label: w("col.department") }, { label: w("col.designation") }, { label: w("col.joining") }, { label: w("col.service"), align: "right" }, { label: w("col.basis") }, { label: w("col.basis_amount"), align: "right" }, { label: "%", align: "right" }, { label: w("col.fixed"), align: "right" }, { label: w("col.bonus"), align: "right" }, { label: w("col.rule") }],
          rows: sheet.eligible.map((e) => [e.code, e.name, e.department ?? "—", e.designation ?? "—", day(e.joining_date), w("days_short", { n: e.service_days }), w(`basis.${e.basis}`), plain(e.basis_amount), e.pct ?? "—", e.fixed > 0 ? plain(e.fixed) : "—", plain(e.amount), e.rule]),
          foot: [["", w("total_bonus"), "", "", "", "", "", "", "", "", plain(sheet.total), ""]],
          empty: w("empty.eligible"),
        },
        {
          title: w("tab.not_eligible"),
          columns: [{ label: w("col.code") }, { label: w("col.name") }, { label: w("col.department") }, { label: w("col.designation") }, { label: w("col.joining") }, { label: w("col.service"), align: "right" }, { label: w("col.reason") }],
          rows: sheet.not_eligible.map((e) => [e.code, e.name, e.department ?? "—", e.designation ?? "—", day(e.joining_date), e.service_days === null ? "—" : w("days_short", { n: e.service_days }), e.reason]),
          empty: w("empty.not_eligible"),
        },
      ],
      signatures: true,
    });
  const TH = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const TD = "px-3 py-2 align-top";
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <Head icon={PartyPopper} title={w("festival_check")} sub={w("festival_check_sub")} />
        <div className="mt-4 flex flex-col gap-4">
          <Field label={w("f.payroll_month")} className="w-48">
            <Input
              type="month"
              value={month}
              onChange={(e) => {
                setMonth(e.target.value);
                if (/^\d{4}-\d{2}$/.test(e.target.value)) load(e.target.value, null);
              }}
              aria-label={w("f.payroll_month")}
            />
          </Field>
          {data.festivals.length === 0 ? (
            <Banner icon={Gift}>{w("no_festival", { month: monthName(data.month) })}</Banner>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {data.festivals.map((x) => {
                const on = f?.id === x.id;
                return (
                  <button key={x.id} type="button" aria-pressed={on} onClick={() => load(data.month, x.id)} className={cn("flex flex-col gap-1 rounded-lg border p-3 text-left transition-colors", on ? "border-brand bg-brand-subtle" : "border-border hover:bg-surface-muted")}>
                    <span className="flex items-center justify-between gap-2">
                      <span className="font-semibold">
                        {x.name} {x.year}
                      </span>
                      {x.run_status ? <Pill t={BONUS_TONE[x.run_status]}>{w(`bonus_status.${x.run_status}`)}</Pill> : <Pill t="warning">{w("not_calculated")}</Pill>}
                    </span>
                    <span className="text-xs text-foreground-muted">
                      {day(x.festival_date)} · {x.rule_set || w("no_bonus_rule")} · {x.min_service > 0 ? w("min_service_days", { n: x.min_service }) : w("no_min_service")}
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </section>
      {sheet ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Stat icon={PartyPopper} label={w("tile.festival")} value={f.name} caption={`${day(f.festival_date)} · ${w("paid_with", { month: monthName(f.payment_month) })}`} />
            <Stat icon={UserCheck} label={w("tile.eligible")} value={sheet.eligible.length} caption={status ? w("on_run", { no: sheet.run.period_no }) : w("preview_caption")} tone="good" />
            <Stat icon={UserX} label={w("tile.not_eligible")} value={sheet.not_eligible.length} caption={w("each_reason")} tone="danger" />
            <Stat icon={Banknote} label={w("tile.total_bonus")} value={taka(sheet.total)} caption={f.bonus_type ? w(`bonus_type_opt.${f.bonus_type}`) : w("bonus_type_unset")} tone="warning" />
          </div>
          <section className={cn(card, "p-5")}>
            <Head
              icon={locked ? Lock : ClipboardCheck}
              title={w("workflow")}
              sub={
                sheet.run
                  ? [w("run_line", { no: sheet.run.period_no, at: stamp(sheet.run.calculated_at) }), sheet.run.approved_by ? w("approved_by_at", { by: sheet.run.approved_by, at: stamp(sheet.run.approved_at) }) : null, sheet.run.finalized_at ? w("finalized_at", { at: stamp(sheet.run.finalized_at) }) : null].filter(Boolean).join(" · ")
                  : w("not_calculated_preview")
              }
            />
            <div className="mt-4 flex flex-col gap-3">
              <ol className="flex flex-wrap items-center gap-2" aria-label={w("bonus_workflow")}>
                {[["DRAFT", w("calculate")], ["REVIEWED", w("review")], ["APPROVED", w("approve")], ["FINALIZED", w("finalize")], ["PAID", w("payment")]].map(([k, label], i) => {
                  const reached = status !== null && BONUS_FLOW.indexOf(status) >= BONUS_FLOW.indexOf(k);
                  return (
                    <li key={k} className="flex items-center gap-2">
                      {i > 0 ? <span className="text-foreground-muted">→</span> : null}
                      <Pill t={reached ? "good" : "neutral"}>
                        {reached ? <CircleCheck className="size-3" /> : null}
                        {label}
                      </Pill>
                    </li>
                  );
                })}
              </ol>
              {sheet.blockers.map((b) => (
                <Banner key={b} tone="warning" icon={ShieldAlert}>
                  {b}
                </Banner>
              ))}
              {locked ? (
                <Banner tone="good" icon={Lock}>
                  {w(status === "PAID" ? "bonus_locked_paid" : "bonus_locked", { month: monthName(f.payment_month) })}
                </Banner>
              ) : null}
              <div className="flex flex-wrap justify-end gap-2">
                <Button variant="outline" onClick={exportCsv} disabled={pending}>
                  <Download /> {w("export_csv")}
                </Button>
                <Button variant="outline" onClick={print} disabled={pending}>
                  <Printer /> {w("print")}
                </Button>
                {can.approve && (status === "REVIEWED" || status === "APPROVED") ? (
                  <Button variant="ghost" onClick={() => run("salaryBonusMove", { festival: f.id, action: "reopen" })} disabled={pending}>
                    <RotateCcw /> {w("return_draft")}
                  </Button>
                ) : null}
                {canCalc ? (
                  <Button variant={status === null ? "primary" : "outline"} onClick={() => run("salaryBonusCalc", { festival: f.id })} loading={pending} disabled={sheet.blockers.length > 0}>
                    <Calculator /> {status === null ? w("calculate") : w("recalculate")}
                  </Button>
                ) : null}
                {can.calculate && status === "DRAFT" ? (
                  <Button onClick={() => run("salaryBonusMove", { festival: f.id, action: "review" })} disabled={pending}>
                    <ClipboardCheck /> {w("mark_reviewed")}
                  </Button>
                ) : null}
                {can.approve && status === "REVIEWED" ? (
                  <Button onClick={() => run("salaryBonusMove", { festival: f.id, action: "approve" })} disabled={pending}>
                    <BadgeCheck /> {w("approve")}
                  </Button>
                ) : null}
                {can.finalize && status === "APPROVED" ? (
                  <Button onClick={() => run("salaryBonusMove", { festival: f.id, action: "finalize" })} disabled={pending}>
                    <Lock /> {w("finalize")}
                  </Button>
                ) : null}
                {can.finalize && status === "FINALIZED" ? (
                  <Button onClick={() => run("salaryBonusMove", { festival: f.id, action: "pay" })} disabled={pending}>
                    <CheckCheck /> {w("mark_paid")}
                  </Button>
                ) : null}
              </div>
            </div>
          </section>
          <section className={card}>
            <div className="px-5 py-4">
              <Head icon={Gift} title={w("sheet_title", { name })} sub={w("sheet_sub", { status: status ? w(`bonus_status.${status}`) : w("preview"), date: day(f.festival_date), month: monthName(f.payment_month), n: sheet.eligible.length, total: taka(sheet.total) })} />
            </div>
            <div className="px-5">
              <Tabs tabs={[["eligible", w("tab.eligible"), sheet.eligible.length, UserCheck], ["not", w("tab.not_eligible"), sheet.not_eligible.length, UserX]]} active={tab} onPick={setTab} />
            </div>
            <div className="overflow-x-auto">
              {tab === "eligible" ? (
                <table className="w-full min-w-[1200px] text-sm">
                  <caption className="sr-only">{w("eligible_caption")}</caption>
                  <thead>
                    <tr className="bg-brand text-brand-foreground">
                      {["code", "name", "department", "designation", "joining", "service", "basis", "basis_amount", "pct_short", "fixed", "bonus", "rule"].map((c) => (
                        <th key={c} className={cn(TH, ["service", "basis_amount", "pct_short", "fixed", "bonus"].includes(c) && "text-right")}>
                          {w(`col.${c}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {sheet.eligible.map((e) => (
                      <tr key={e.code} className="border-b border-border last:border-0">
                        <td className={cn(TD, "font-mono text-xs font-semibold")}>{e.code}</td>
                        <td className={TD}>{e.name}</td>
                        <td className={TD}>{e.department || "—"}</td>
                        <td className={TD}>{e.designation || "—"}</td>
                        <td className={cn(TD, "whitespace-nowrap")}>{day(e.joining_date)}</td>
                        <td className={cn(TD, "text-right whitespace-nowrap tabular-nums")}>{w("days_short", { n: e.service_days })}</td>
                        <td className={TD}>{w(`basis.${e.basis}`)}</td>
                        <td className={cn(TD, "text-right tabular-nums")}>{taka(e.basis_amount)}</td>
                        <td className={cn(TD, "text-right tabular-nums")}>{e.pct ?? "—"}</td>
                        <td className={cn(TD, "text-right tabular-nums")}>{e.fixed > 0 ? taka(e.fixed) : "—"}</td>
                        <td className={cn(TD, "text-right font-semibold tabular-nums")}>{taka(e.amount)}</td>
                        <td className={cn(TD, "text-xs text-foreground-muted")}>{e.rule}</td>
                      </tr>
                    ))}
                    {sheet.eligible.length === 0 ? (
                      <tr>
                        <td colSpan={12} className="px-5 py-10 text-center text-foreground-muted">
                          {w("empty.eligible")}
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                  {sheet.eligible.length ? (
                    <tfoot>
                      <tr className="border-t border-border bg-surface-muted font-semibold">
                        <th scope="row" colSpan={10} className="px-3 py-2.5 text-right">
                          {w("total_bonus")}
                        </th>
                        <td className="px-3 py-2.5 text-right tabular-nums">{taka(sheet.total)}</td>
                        <td />
                      </tr>
                    </tfoot>
                  ) : null}
                </table>
              ) : (
                <table className="w-full min-w-[1000px] text-sm">
                  <caption className="sr-only">{w("not_eligible_caption")}</caption>
                  <thead>
                    <tr className="bg-brand text-brand-foreground">
                      {["code", "name", "department", "designation", "joining", "service", "reason"].map((c) => (
                        <th key={c} className={cn(TH, c === "service" && "text-right")}>
                          {w(`col.${c}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {sheet.not_eligible.map((e) => (
                      <tr key={e.code} className="border-b border-border last:border-0">
                        <td className={cn(TD, "font-mono text-xs font-semibold")}>{e.code}</td>
                        <td className={TD}>{e.name}</td>
                        <td className={TD}>{e.department || "—"}</td>
                        <td className={TD}>{e.designation || "—"}</td>
                        <td className={cn(TD, "whitespace-nowrap")}>{day(e.joining_date)}</td>
                        <td className={cn(TD, "text-right whitespace-nowrap tabular-nums")}>{e.service_days === null ? "—" : w("days_short", { n: e.service_days })}</td>
                        <td className={TD}>{e.reason}</td>
                      </tr>
                    ))}
                    {sheet.not_eligible.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="px-5 py-10 text-center text-foreground-muted">
                          {w("empty.not_eligible")}
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              )}
            </div>
          </section>
        </>
      ) : data.festivals.length > 1 ? (
        <Banner icon={Gift}>{w("choose_festival")}</Banner>
      ) : null}
    </div>
  );
}

/* ── Monthly Salary ──────────────────────────────────────────────── */

const RUN_STEPS = [
  ["load", ["NONE", "CALCULATED", "REVIEWED", "APPROVED", "FINALIZED", "PAID"]],
  ["calculate", ["CALCULATED", "REVIEWED", "APPROVED", "FINALIZED", "PAID"]],
  ["review", ["REVIEWED", "APPROVED", "FINALIZED", "PAID"]],
  ["approve", ["APPROVED", "FINALIZED", "PAID"]],
  ["finalize", ["FINALIZED", "PAID"]],
  ["payslip", ["FINALIZED", "PAID"]],
  ["payment", ["PAID"]],
];
const RUN_TONE = { NONE: "neutral", CALCULATED: "info", REVIEWED: "warning", APPROVED: "good", FINALIZED: "good", PAID: "good" };

function payslipHtml(w, period, r) {
  const e = (v) =>
    String(v ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;");
  const earnings = [
    [w("ps.gross_prorated"), r.earned_gross],
    [w("ps.of_basic"), r.gross_a ? Math.round((r.basic * r.earned_gross * 100) / r.gross_a) / 100 : 0],
    ...r.allowance_lines.map(([n, v]) => [n, v]),
    [w("ps.ot", { h: hours(r.ot_hours) }), r.ot_amount],
    [w("ps.festival_bonus"), r.bonus],
    ...(r.adjustment ? [[w("ps.adjustment"), r.adjustment]] : []),
  ];
  const deductions = [[w("ps.late"), r.late_deduction], [w("ps.stamp"), r.stamp_charge], ...r.deduction_lines.map(([n, v]) => [n, v]), [w("ps.loan"), r.loan_advance]];
  const totalEarn = r.earned_gross + r.allowances + r.ot_amount + r.bonus + Math.max(0, r.adjustment);
  const totalDed = r.late_deduction + r.other_deduction + r.loan_advance + Math.max(0, -r.adjustment);
  const table = (title, items, total) =>
    `<table class="grid"><thead><tr><th colspan="2">${e(title)}</th></tr></thead><tbody>${items.map(([n, v]) => `<tr><td>${e(n)}</td><td class="r">${e(taka(v))}</td></tr>`).join("")}</tbody><tfoot><tr><td>${e(total[0])}</td><td class="r">${e(taka(total[1]))}</td></tr></tfoot></table>`;
  const fact = (k, v) => `<div class="fact"><span>${e(k)}</span><strong>${e(v)}</strong></div>`;
  return `<div style="font-size:13px;font-weight:700;margin-bottom:4px">${e(r.name)} · ${e(r.code)}</div>
<div class="meta" style="grid-template-columns:repeat(4,1fr)">${fact(w("col.designation"), r.designation ?? "—")}${fact(w("col.department"), r.department ?? "—")}${fact(w("ps.section_line"), [r.section, r.line].filter(Boolean).join(" / ") || "—")}${fact(w("ps.pay_date"), period.pay_date)}${fact(w("ps.days"), `${r.payable_days} / ${r.period_days}`)}${fact(w("ps.unpaid"), r.unpaid_days)}${fact(w("ps.gross_monthly"), taka(r.gross_a))}${fact(w("ps.payment"), r.held ? w("held") : w(`method.${r.payment_method}`))}</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start">${table(w("ps.earnings"), earnings, [w("ps.total_earnings"), totalEarn])}${table(w("ps.deductions"), deductions, [w("ps.total_deductions"), totalDed])}</div>
<p style="text-align:right;font-size:14px;font-weight:700;margin:10px 0 0">${e(w("ps.net_pay"))}: ${e(taka(r.net))}</p>
<div class="sign"><div>${e(w("ps.employee_sign"))}</div><div>${e(w("ps.prepared_by"))}</div><div>${e(w("ps.authorised"))}</div></div>`;
}

function MonthlySalary({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const can = options.can;
  const [periodNo, setPeriodNo] = useState(initial?.period.no ?? "");
  const [scope, setScope] = useState(EMPTY_SCOPE);
  const [view, setView] = useState(initial);
  const [loaded, setLoaded] = useState({ period: initial?.period.no ?? "", scope: EMPTY_SCOPE });
  const [tab, setTab] = useState("sheet");
  const [confirm, setConfirm] = useState(null);
  const [pending, start] = useTransition();
  const status = view?.run?.status ?? "NONE";
  const locked = status === "FINALIZED" || status === "PAID";
  const dirty = periodNo !== loaded.period || !sameScope(scope, loaded.scope);
  const body = () => ({ period: loaded.period, ...scopeQuery(loaded.scope) });
  const load = () =>
    start(async () => {
      const res = await act("salaryMonthly", { period: periodNo, ...scopeQuery(scope) });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      setView(res.data);
      setLoaded({ period: periodNo, scope });
      toast.add({ title: w("done.loaded_monthly", { no: res.data.period.no, n: res.data.rows.length }), type: "info" });
    });
  const run = (op, payload) =>
    start(async () => {
      const res = await act(op, payload);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setView(res.data.view);
    });
  const rows = view?.rows ?? [];
  const bank = rows.filter((r) => !r.held && r.payment_method === "BANK");
  const cash = rows.filter((r) => !r.held && r.payment_method === "CASH");
  const s = view?.summary;
  const label = view ? `${view.period.no} · ${view.period.label}` : "";
  const statusLabel = (r) => (r.adjustment_required ? w("adjustment_required") : w(`slip_status.${r.slip_status}`));
  const statusPill = (r) => <Pill t={r.adjustment_required ? "warning" : r.slip_status === "DRAFT" ? "neutral" : "good"}>{statusLabel(r)}</Pill>;
  const printSlips = (list) =>
    printDocument({
      title: w("printdoc.payslip"),
      docNo: label,
      subtitle: `${view.period.start} ${w("to")} ${view.period.end}${locked ? "" : ` · ${w("draft_not_final")}`}`,
      blocks: list.map((r, i) => ({ html: `<div style="${i ? "break-before:page;" : ""}">${payslipHtml(w, view.period, r)}</div>` })),
    });
  const sheetCsv = () =>
    downloadCsv(
      `salary-sheet-${view.period.no}`,
      ["code", "name", "department", "section", "line", "designation", "grade", "salary_month", "gross_a", "gross_b", "period_days", "present_days", "absent_days", "payable_days", "leave_deduction", "earned_gross", "allowances", "ot_hours", "ot_amount", "bonus", "late_days", "late_deduction", "other_deduction", "loan_advance", "adjustment", "net_a", "gross_b_net", "gross_b_source", "payroll_status", "payment", "compliance"].map((k) => w(`sheet.${k}`)),
      rows.map((r) => [r.code, r.name, r.department, r.section, r.line, r.designation, r.grade, view.period.label, plain(r.gross_a), plain(r.gross_b), r.period_days, r.present_days, r.absent_days, r.payable_days, plain(r.leave_deduction), plain(r.earned_gross), plain(r.allowances), r.ot_hours, plain(r.ot_amount), plain(r.bonus), r.late_days, plain(r.late_deduction), plain(r.other_deduction), plain(r.loan_advance), plain(r.adjustment), plain(r.net), "", w("not_processed_complex"), statusLabel(r), r.held ? w("held").toUpperCase() : r.payment_method, r.below_minimum ? w("below_minimum") : ""]),
    );
  const btn = (action, Icon, variant = "outline") => (
    <Button size="sm" variant={variant} disabled={pending || dirty} onClick={() => setConfirm(action)}>
      <Icon /> {w(`run_btn.${action}`)}
    </Button>
  );
  const review = [
    {
      key: "code",
      label: w("col.employee"),
      read: (r) => `${r.code} ${r.name}`,
      render: (r) => (
        <div className="min-w-44">
          <div className="font-semibold">{r.name}</div>
          <div className="text-xs text-foreground-muted">{[r.code, r.designation, r.line].filter(Boolean).join(" · ")}</div>
          {r.below_minimum ? <div className="mt-0.5 text-xs text-danger">{w("below_min_line", { min: taka(r.minimum_wage), structure: r.structure ?? "—" })}</div> : null}
        </div>
      ),
    },
    { key: "days", label: w("col.days"), right: true, render: (r) => (<div><div>{r.payable_days} / {r.period_days}</div>{r.absent_days > 0 ? <div className="text-xs text-danger">{w("n_absent", { n: r.absent_days })}</div> : null}</div>) },
    { key: "gross", label: w("col.gross_a"), right: true, render: (r) => (<div><div>{taka(r.earned_gross)}</div><div className="text-xs text-foreground-muted">{w("of", { n: taka(r.gross_a) })}</div></div>) },
    { key: "allowances", label: w("col.allowance"), right: true, render: (r) => taka(r.allowances) },
    { key: "ot", label: w("col.ot"), right: true, render: (r) => (<div><div>{taka(r.ot_amount)}</div>{r.ot_hours > 0 ? <div className="text-xs text-foreground-muted">{hours(r.ot_hours)} h</div> : null}</div>) },
    { key: "bonus", label: w("col.bonus"), right: true, render: (r) => taka(r.bonus) },
    { key: "late", label: w("col.late"), right: true, render: (r) => (<div><div>{taka(r.late_deduction)}</div>{r.late_days > 0 ? <div className="text-xs text-foreground-muted">{w("n_late", { n: r.late_days })}</div> : null}</div>) },
    { key: "other", label: w("col.other_ded"), right: true, render: (r) => taka(r.other_deduction) },
    { key: "loan", label: w("col.loan"), right: true, render: (r) => taka(r.loan_advance) },
    { key: "adjustment", label: w("col.adjustment"), right: true, render: (r) => (r.adjustment ? taka(r.adjustment) : "—") },
    { key: "net", label: w("col.net"), right: true, render: (r) => (<div><div className="font-semibold">{taka(r.net)}</div>{r.held ? <Pill t="danger">{w("held")}</Pill> : null}</div>) },
    { key: "status", label: w("col.status"), center: true, read: statusLabel, render: statusPill },
    {
      key: "payslip",
      label: w("col.payslip"),
      center: true,
      render: (r) => (
        <Button size="sm" variant="ghost" onClick={() => printSlips([r])} aria-label={w("payslip_of", { code: r.code })}>
          <FileText />
        </Button>
      ),
    },
  ];
  const grossAB = [
    { key: "code", label: w("col.employee"), read: (r) => `${r.code} ${r.name}`, render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="text-xs text-foreground-muted">{r.code}</div></div>) },
    { key: "department", label: w("col.department") },
    { key: "designation", label: w("col.designation") },
    { key: "grade", label: w("col.grade") },
    { key: "month", label: w("col.salary_month"), read: () => view.period.label },
    { key: "gross_a", label: w("col.gross_a"), right: true, render: (r) => taka(r.gross_a) },
    { key: "gross_b", label: w("col.gross_b"), right: true, render: (r) => (<div><div>{taka(r.gross_b)}</div><div className="text-xs text-foreground-muted">{w("not_processed_complex")}</div></div>) },
    { key: "period_days", label: w("col.working_days"), right: true },
    { key: "present_days", label: w("col.present"), right: true },
    { key: "payable_days", label: w("col.payable"), right: true },
    { key: "leave", label: w("col.leave_ded"), right: true, render: (r) => taka(r.leave_deduction) },
    { key: "allow", label: w("col.other_allow"), right: true, render: (r) => taka(r.allowances) },
    { key: "ot_hours", label: w("col.ot_hours"), right: true, render: (r) => hours(r.ot_hours) },
    { key: "ot", label: w("col.ot_salary"), right: true, render: (r) => taka(r.ot_amount) },
    { key: "bonus", label: w("col.festival_bonus"), right: true, render: (r) => taka(r.bonus) },
    { key: "ded", label: w("col.other_ded"), right: true, render: (r) => taka(r.late_deduction + r.other_deduction + r.loan_advance) },
    { key: "net", label: w("col.net_a"), right: true, render: (r) => <span className="font-semibold">{taka(r.net)}</span> },
    { key: "net_b", label: w("col.gross_b_net"), right: true, render: () => (<div><div>—</div><div className="text-xs text-foreground-muted">{w("not_processed_complex")}</div></div>) },
    { key: "status", label: w("col.payroll_status"), center: true, read: statusLabel, render: statusPill },
  ];
  const payCols = (isBank) => [
    { key: "code", label: w("col.code"), render: (r) => <span className="font-mono text-xs font-semibold">{r.code}</span> },
    { key: "name", label: w("col.name") },
    ...(isBank
      ? [{ key: "title", label: w("col.account_title"), read: () => "" }, { key: "acc", label: w("col.account_no"), read: () => "" }, { key: "bank", label: w("col.bank_branch"), read: () => "" }]
      : [{ key: "department", label: w("col.department") }, { key: "place", label: w("col.section_line"), read: (r) => [r.section, r.line].filter(Boolean).join(" › ") }]),
    { key: "net", label: w("col.net"), right: true, render: (r) => taka(r.net) },
  ];
  const cashCsv = () => downloadCsv(`cash-salary-list-${view.period.no}`, [w("col.sl"), w("col.code"), w("col.name"), w("col.department"), w("col.section_line"), w("col.net"), w("col.signature")], cash.map((r, i) => [i + 1, r.code, r.name, r.department, [r.section, r.line].filter(Boolean).join(" › "), plain(r.net), ""]));
  const bankCsv = () => downloadCsv(`bank-salary-sheet-${view.period.no}`, [w("col.sl"), w("col.code"), w("col.name"), w("col.account_title"), w("col.account_no"), w("col.bank"), w("col.branch"), w("col.net")], bank.map((r, i) => [i + 1, r.code, r.name, "", "", "", "", plain(r.net)]));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <Head icon={Calculator} title={w("load_data")} sub={w("load_data_sub")} />
        <div className="mt-4">
          <ScopeBar
            w={w}
            options={options}
            value={scope}
            onChange={setScope}
            leading={
              <Field label={w("f.pay_period")} required className="xl:col-span-2">
                <Select value={periodNo} onValueChange={(v) => setPeriodNo(v ?? "")} options={options.periods} placeholder={w("select")} aria-label={w("f.pay_period")} />
              </Field>
            }
          />
          <Button className="mt-4" onClick={load} loading={pending && !confirm} disabled={!periodNo}>
            <RefreshCw /> {w("load")}
          </Button>
        </div>
      </section>
      {view && s ? (
        <>
          <section className={cn(card, "p-5")}>
            <Head icon={locked ? Lock : Wallet} title={label} sub={w("period_sub", { start: view.period.start, end: view.period.end, pay: view.period.pay_date, scope: view.scope_label })}>
              <Pill t={view.adjustment_required ? "warning" : RUN_TONE[status]}>{view.adjustment_required ? w("adjustment_required") : w(`run_status.${status}`)}</Pill>
            </Head>
            <div className="mt-4 flex flex-col gap-3">
              <ol className="flex flex-wrap items-center gap-2" aria-label={w("payroll_workflow")}>
                {RUN_STEPS.map(([k, at], i) => (
                  <li key={k} className="flex items-center gap-2">
                    <span className={cn("inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold", at.includes(status) ? "border-transparent bg-success-subtle text-success" : "border-border text-foreground-muted")}>
                      {at.includes(status) ? <CircleCheck className="size-3" /> : null}
                      {w(`step.${k}`)}
                    </span>
                    {i < RUN_STEPS.length - 1 ? <span className="text-foreground-muted">→</span> : null}
                  </li>
                ))}
              </ol>
              <div className="flex flex-wrap gap-2">
                {can.calculate && !locked ? (
                  <Button size="sm" disabled={pending || dirty || view.period.closed} onClick={() => run("salaryMonthlyCalc", body())} loading={pending && !confirm}>
                    <Calculator /> {status === "NONE" ? w("calculate") : w("recalculate")}
                  </Button>
                ) : null}
                {can.calculate && status === "CALCULATED" ? btn("review", ClipboardCheck) : null}
                {can.approve && status === "REVIEWED" ? btn("approve", BadgeCheck) : null}
                {can.finalize && status === "APPROVED" ? btn("finalize", Lock, "primary") : null}
                {can.finalize && status === "FINALIZED" ? btn("pay", Banknote, "primary") : null}
                {can.finalize && status === "FINALIZED" ? btn("reopen", LockOpen, "danger") : null}
                <Button size="sm" variant="outline" disabled={!rows.length} onClick={() => printSlips(rows)}>
                  <FileText /> {w("payslips")}
                </Button>
                <Button size="sm" variant="outline" disabled={!rows.length} onClick={sheetCsv}>
                  <Download /> {w("salary_sheet_csv")}
                </Button>
              </div>
              {view.run ? (
                <p className="text-xs text-foreground-muted">
                  {[w("calculated_at", { at: stamp(view.run.run_at) }), view.run.reviewed_by ? w("reviewed_by", { by: view.run.reviewed_by }) : null, view.run.approved_by ? w("approved_by_at", { by: view.run.approved_by, at: stamp(view.run.approved_at) }) : null, view.run.finalized_by ? w("finalized_by_at", { by: view.run.finalized_by, at: stamp(view.run.finalized_at) }) : null, w("run_slips", { n: view.run.slips, net: taka(view.run.total_net) })].filter(Boolean).join(" · ")}
                </p>
              ) : null}
              {view.compliance_warnings > 0 ? (
                <Note tone="danger" icon={TriangleAlert}>
                  {w("compliance_line", { n: view.compliance_warnings })}
                </Note>
              ) : null}
              {locked ? (
                <Note tone="good" icon={Lock}>
                  {status === "FINALIZED" ? w("locked_until") : w("locked")}
                </Note>
              ) : null}
              {view.adjustment_required > 0 ? (
                <Note tone="warning" icon={TriangleAlert}>
                  {w("adjustment_line", { n: view.adjustment_required })}
                </Note>
              ) : null}
              {view.period.closed ? <Note tone="warning">{w("period_closed")}</Note> : null}
              {view.not_calculated > 0 && !locked ? <Note>{w("not_calculated_line", { n: view.not_calculated })}</Note> : null}
              {dirty ? (
                <Note tone="warning" icon={TriangleAlert}>
                  {w("filters_changed")}
                </Note>
              ) : null}
              <Note>{`${w("net_formula")} ${view.late_note}`}</Note>
              <Note icon={Scale}>{w("gross_b_note")}</Note>
            </div>
          </section>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <Stat icon={Users} label={w("tile.employees")} value={s.employees} caption={s.held > 0 ? w("n_held", { n: s.held }) : view.scope_label} />
            <Stat icon={Wallet} label={w("tile.gross_allowance")} value={taka(s.gross + s.allowances)} tone="warning" />
            <Stat icon={Calculator} label={w("tile.ot")} value={taka(s.ot)} tone="good" />
            <Stat icon={Gift} label={w("tile.festival_bonus")} value={taka(s.bonus)} tone="warning" />
            <Stat icon={Undo2} label={w("tile.deductions")} value={taka(s.late + s.other + s.loan)} caption={w("late_loan", { late: taka(s.late), loan: taka(s.loan) })} tone="danger" />
            <Stat icon={BadgeCheck} label={w("tile.net_payable")} value={taka(s.net)} caption={w("bank_cash", { b: s.bank_count, c: s.cash_count })} tone="good" />
          </div>
          <section className={card}>
            <div className="px-5 pt-3">
              <Tabs
                tabs={[
                  ["sheet", w("tab.salary_review"), rows.length, FileText],
                  ["gross", w("tab.gross_ab"), rows.length, Scale],
                  ["bank", w("tab.bank"), bank.length, Landmark],
                  ["cash", w("tab.cash"), cash.length, Banknote],
                ]}
                active={tab}
                onPick={setTab}
              />
            </div>
            <div className="pt-4">
              {tab === "sheet" ? <Table w={w} columns={review} rows={rows} rowKey={(r) => r.code} searchPh={w("ph.search_monthly")} empty={status === "NONE" ? w("empty.monthly_none") : w("empty.monthly")} minWidth={1300} /> : null}
              {tab === "gross" ? (
                <>
                  <p className="px-5 pb-3 text-[13px] text-foreground-muted">{w("gross_ab_line", { a: taka(s.net), b: taka(s.gross_b) })}</p>
                  <Table w={w} columns={grossAB} rows={rows} rowKey={(r) => r.code} searchPh={w("ph.search_code_name")} empty={w("empty.monthly")} minWidth={1900} />
                </>
              ) : null}
              {tab === "bank" ? (
                <>
                  <div className="flex justify-end px-5 pb-3">
                    <Button size="sm" variant="outline" disabled={!bank.length} onClick={bankCsv}>
                      <Download /> {w("bank_csv", { n: taka(s.bank_net) })}
                    </Button>
                  </div>
                  <Table w={w} columns={payCols(true)} rows={bank} rowKey={(r) => r.code} empty={w("empty.bank")} minWidth={800} />
                </>
              ) : null}
              {tab === "cash" ? (
                <>
                  <div className="flex justify-end px-5 pb-3">
                    <Button size="sm" variant="outline" disabled={!cash.length} onClick={cashCsv}>
                      <Download /> {w("cash_csv", { n: taka(s.cash_net) })}
                    </Button>
                  </div>
                  <Table w={w} columns={payCols(false)} rows={cash} rowKey={(r) => r.code} empty={w("empty.cash")} minWidth={800} />
                </>
              ) : null}
            </div>
          </section>
        </>
      ) : null}
      <ConfirmDialog
        open={!!confirm}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? w(`run_confirm.${confirm}.title`) : ""}
        description={confirm ? w(`run_confirm.${confirm}.text`) : ""}
        confirmLabel={confirm ? w(`run_btn.${confirm}`) : ""}
        cancelLabel={w("cancel")}
        destructive={confirm === "reopen"}
        loading={pending}
        onConfirm={() => {
          const action = confirm;
          setConfirm(null);
          run("salaryMonthlyMove", { ...body(), action });
        }}
      />
    </div>
  );
}

/* ── Payroll Adjustment ──────────────────────────────────────────── */

const ADJ_TONE = { PENDING: "warning", APPROVED: "good", REJECTED: "danger", APPLIED: "info" };
const signed = (v) => `${v > 0 ? "+" : v < 0 ? "−" : ""}${taka(Math.abs(v))}`;

function PayrollAdjustment({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [q, setQ] = useState({ period: "", department: "", q: "", show: "flagged" });
  const [data, setData] = useState(initial);
  const [raise, setRaise] = useState(null);
  const [open, setOpen] = useState(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState("");
  const [pending, start] = useTransition();
  const can = data.can;
  const load = () =>
    start(async () => {
      const res = await act("salaryAdjustments", Object.fromEntries(Object.entries(q).filter(([, v]) => v !== "")));
      if (res?.status !== "success") return toast.add({ title: w("err.adj_load"), type: "danger" });
      setData(res.data);
    });
  const num = (v) => (String(v).trim() !== "" && Number.isFinite(Number(v)) ? Number(v) : NaN);
  const preview = raise
    ? raise.kind === "OT"
      ? (() => {
          const original = raise.slip.slip_ot_amount + raise.slip.adjusted_ot;
          const revised = Math.round(num(raise.hours) * num(raise.rate) * 100) / 100;
          return { original, revised, adjustment: Math.round((revised - original) * 100) / 100 };
        })()
      : { original: raise.slip.net, revised: raise.slip.net + (num(raise.amount) || 0), adjustment: num(raise.amount) }
    : null;
  const submit = () => {
    if (raise.kind === "OT" && (Number.isNaN(num(raise.hours)) || Number.isNaN(num(raise.rate)))) return setError(w("err.hours_rate"));
    if (raise.kind === "OTHER" && Number.isNaN(num(raise.amount))) return setError(w("err.amount"));
    if (!raise.reason.trim()) return setError(w("err.reason"));
    setError("");
    start(async () => {
      const res = await act("salaryAdjustmentStore", { period: data.filters.period, employee: raise.slip.code, kind: raise.kind, hours: raise.kind === "OT" ? num(raise.hours) : null, rate: raise.kind === "OT" ? num(raise.rate) : null, amount: raise.kind === "OTHER" ? num(raise.amount) : null, reason: raise.reason.trim(), department: data.filters.department, q: data.filters.q, show: data.filters.show });
      if (res?.status !== "success") return setError(firstError(res) || w("err.adj_save"));
      toast.add({ title: res.data.message, type: "success" });
      setData(res.data.view);
      setRaise(null);
    });
  };
  const decide = (decision) => {
    if (decision === "REJECT" && !note.trim()) return setError(w("err.note"));
    setError("");
    start(async () => {
      const res = await act("salaryAdjustmentDecide", open.ref, { decision, note: note.trim(), period: data.filters.period, department: data.filters.department, q: data.filters.q, show: data.filters.show });
      if (res?.status !== "success") return setError(firstError(res) || w("err.decide"));
      toast.add({ title: res.data.message, type: "success" });
      setData(res.data.view);
      setOpen(null);
    });
  };
  const s = data.summary;
  const slipCols = [
    { key: "code", label: w("col.employee_id"), render: (r) => <span className="font-mono text-xs font-semibold">{r.code}</span> },
    { key: "name", label: w("col.name") },
    { key: "department", label: w("col.department") },
    { key: "grade", label: w("col.grade") },
    { key: "status", label: w("col.payroll_status"), read: (r) => (r.flagged ? w("adjustment_required") : w(`slip_status.${r.status}`)), render: (r) => <Pill t={r.flagged ? "warning" : "good"}>{r.flagged ? w("adjustment_required") : w(`slip_status.${r.status}`)}</Pill> },
    { key: "slip_ot_hours", label: w("col.slip_ot_h"), right: true, render: (r) => hours(r.slip_ot_hours) },
    { key: "slip_ot_amount", label: w("col.slip_ot_salary"), right: true, render: (r) => taka(r.slip_ot_amount) },
    { key: "current_hours", label: w("col.current_ot_h"), right: true, render: (r) => hours(r.current_hours) },
    { key: "current_amount", label: w("col.current_ot_salary"), right: true, render: (r) => taka(r.current_amount) },
    { key: "already", label: w("col.already_adjusted"), right: true, render: (r) => (r.already ? signed(r.already) : "—") },
    { key: "why", label: w("col.why"), render: (r) => <span className="text-xs">{r.why || "—"}</span> },
    {
      key: "act",
      label: w("col.actions"),
      right: true,
      render: (r) =>
        can.raise ? (
          <Button size="sm" variant="outline" onClick={() => (setError(""), setRaise({ slip: r, kind: "OT", hours: String(r.current_hours), rate: String(r.rate || (r.current_hours ? Math.round((r.current_amount / r.current_hours) * 100) / 100 : 0)), amount: "", reason: "" }))}>
            <Scale /> {w("raise")}
          </Button>
        ) : null,
    },
  ];
  const adjCols = (withPeriod) => [
    { key: "ref", label: "#", render: (r) => <span className="font-mono text-xs font-semibold">{r.ref}</span> },
    { key: "code", label: w("col.employee"), read: (r) => `${r.code} ${r.name}`, render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="text-xs text-foreground-muted">{r.code}</div></div>) },
    ...(withPeriod ? [{ key: "period", label: w("col.period"), read: (r) => `${r.period} · ${r.period_label}` }] : []),
    { key: "kind", label: w("col.kind"), read: (r) => w(`kind.${r.kind}`), render: (r) => (<div><div>{w(`kind.${r.kind}`)}</div>{r.kind === "OT" ? <div className="text-xs text-foreground-muted">{w("h_at", { h: hours(r.hours), rate: taka(r.rate) })}</div> : null}</div>) },
    { key: "original", label: w("col.original"), right: true, render: (r) => taka(r.original) },
    { key: "revised", label: w("col.revised"), right: true, render: (r) => taka(r.revised) },
    { key: "amount", label: w("col.adjustment"), right: true, render: (r) => <span className={cn("font-semibold", r.amount < 0 ? "text-danger" : "text-success")}>{signed(r.amount)}</span> },
    { key: "status", label: w("col.status"), read: (r) => w(`adj_status.${r.status}`), render: (r) => <Pill t={ADJ_TONE[r.status]}>{w(`adj_status.${r.status}`)}</Pill> },
    { key: "reason", label: w("col.reason"), render: (r) => <span className="line-clamp-2 text-xs">{r.reason}</span> },
    { key: "requested", label: w("col.requested"), read: (r) => r.requested_by, render: (r) => (<div className="text-xs"><div>{r.requested_by ?? "—"}</div><div className="text-foreground-muted">{stamp(r.requested_at)}</div></div>) },
    { key: "decision", label: w("col.decision"), read: (r) => r.decided_by ?? "", render: (r) => (r.decided_by ? <div className="text-xs"><div>{r.decided_by}</div><div className="text-foreground-muted">{stamp(r.decided_at)}</div>{r.decision_note ? <div className="text-foreground-muted">— {r.decision_note}</div> : null}</div> : "—") },
    { key: "paid_in", label: w("col.paid_in") },
    {
      key: "act",
      label: w("col.actions"),
      right: true,
      render: (r) => (
        <div className="flex justify-end gap-1">
          {can.decide && r.status === "PENDING" ? (
            <Button size="sm" variant="outline" onClick={() => (setError(""), setNote(""), setOpen(r))}>
              <ClipboardCheck /> {w("decide")}
            </Button>
          ) : (
            <Button size="sm" variant="ghost" onClick={() => (setError(""), setNote(""), setOpen(r))} aria-label={w("details_of", { ref: r.ref })}>
              <Eye />
            </Button>
          )}
        </div>
      ),
    },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Field label={w("f.pay_period")}>
            <Select value={q.period} onValueChange={(v) => setQ({ ...q, period: v ?? "" })} options={[{ value: "", label: w("latest_finalized") }, ...data.periods]} placeholder={w("latest_finalized")} aria-label={w("f.pay_period")} />
          </Field>
          <Field label={w("f.department")}>
            <Select value={q.department} onValueChange={(v) => setQ({ ...q, department: v ?? "" })} options={[{ value: "", label: w("all") }, ...data.departments]} placeholder={w("all")} aria-label={w("f.department")} />
          </Field>
          <Field label={w("f.employee")}>
            <Input value={q.q} onChange={(e) => setQ({ ...q, q: e.target.value })} onKeyDown={(e) => e.key === "Enter" && load()} placeholder={w("ph.code_name_card")} aria-label={w("f.employee")} />
          </Field>
          <Field label={w("f.show")}>
            <Select value={q.show} onValueChange={(v) => setQ({ ...q, show: v ?? "flagged" })} options={[{ value: "flagged", label: w("adjustment_required") }, { value: "all", label: w("all_finalized") }]} aria-label={w("f.show")} />
          </Field>
        </div>
        <Button className="mt-4" onClick={load} loading={pending && !raise && !open}>
          <RefreshCw /> {w("show")}
        </Button>
      </section>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Lock} label={w("tile.finalized_slips")} value={s.finalized} caption={data.period ? `${data.period.no} · ${data.period.label}` : w("no_finalized_period")} />
        <Stat icon={TriangleAlert} label={w("adjustment_required")} value={s.flagged} caption={w("tile.flagged_caption")} tone="danger" />
        <Stat icon={Hourglass} label={w("tile.pending")} value={s.pending} caption={w("tile.pending_caption", { n: s.pending })} tone="good" />
        <Stat icon={Scale} label={w("tile.approved_adj")} value={taka(s.approved_amount)} caption={w("tile.to_apply", { a: s.approved, b: s.applied })} tone="warning" />
      </div>
      <section className={card}>
        <div className="px-5 py-4">
          <Head title={w("finalized_slips")} sub={w("finalized_sub", { n: data.slips.filter((x) => x.flagged).length })} />
          {!can.raise ? <p className="mt-1 text-xs text-foreground-muted">{w("raise_needs")}</p> : null}
        </div>
        <Table w={w} columns={slipCols} rows={data.slips} rowKey={(r) => r.code} searchPh={false} empty={data.period ? (q.show === "flagged" ? w("empty.flagged") : w("empty.finalized")) : w("empty.finalized")} minWidth={1500} />
        <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{w("current_ot_note")}</p>
      </section>
      <section className={card}>
        <div className="px-5 py-4">
          <Head title={data.period ? w("adjustments_for", { no: data.period.no }) : w("adjustments")} sub={w("adjustments_sub")} />
        </div>
        <Table w={w} columns={adjCols(false)} rows={data.adjustments} rowKey={(r) => r.ref} searchPh={false} empty={w("empty.adjustments")} minWidth={1500} />
      </section>
      <section className={card}>
        <div className="px-5 py-4">
          <Head title={w("pending_approval")} sub={w("pending_sub")} />
        </div>
        <Table w={w} columns={adjCols(true)} rows={data.pending} rowKey={(r) => r.ref} searchPh={false} empty={w("empty.pending")} minWidth={1600} />
      </section>
      <Modal
        open={!!raise}
        onOpenChange={(o) => !o && setRaise(null)}
        title={w("raise_title")}
        description={raise ? `${raise.slip.code} ${raise.slip.name} · ${data.period?.no ?? ""}` : ""}
        footer={
          <>
            <Button variant="outline" onClick={() => setRaise(null)} disabled={pending}>
              {w("cancel")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Scale /> {w("raise_for_approval")}
            </Button>
          </>
        }
      >
        {raise ? (
          <div className="flex flex-col gap-4">
            <Segments label={w("col.kind")} value={raise.kind} onChange={(k) => setRaise({ ...raise, kind: k })} items={[["OT", w("kind_long.OT")], ["OTHER", w("kind_long.OTHER")]]} />
            {raise.kind === "OT" ? (
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label={w("f.revised_hours")} required>
                  <Input type="number" min="0" step="0.5" value={raise.hours} onChange={(e) => setRaise({ ...raise, hours: e.target.value })} aria-label={w("f.revised_hours")} />
                </Field>
                <Field label={w("f.revised_rate")} required>
                  <Input type="number" min="0" step="0.01" value={raise.rate} onChange={(e) => setRaise({ ...raise, rate: e.target.value })} aria-label={w("f.revised_rate")} />
                </Field>
              </div>
            ) : (
              <Field label={w("f.amount")} required hint={w("hint.amount")}>
                <Input type="number" step="0.01" value={raise.amount} onChange={(e) => setRaise({ ...raise, amount: e.target.value })} aria-label={w("f.amount")} />
              </Field>
            )}
            <Field label={w("f.reason")} required>
              <Textarea rows={2} value={raise.reason} onChange={(e) => setRaise({ ...raise, reason: e.target.value })} aria-label={w("f.reason")} />
            </Field>
            <div className="grid grid-cols-3 gap-3 rounded-lg bg-surface-muted p-3 text-sm">
              <div>
                <div className="text-xs text-foreground-muted">{raise.kind === "OT" ? w("original_ot") : w("original_net")}</div>
                <div className="font-semibold tabular-nums">{taka(preview.original)}</div>
              </div>
              <div>
                <div className="text-xs text-foreground-muted">{w("revised")}</div>
                <div className="font-semibold tabular-nums">{Number.isNaN(preview.revised) ? "—" : taka(preview.revised)}</div>
              </div>
              <div>
                <div className="text-xs text-foreground-muted">{w("col.adjustment")}</div>
                <div className={cn("font-semibold tabular-nums", preview.adjustment < 0 ? "text-danger" : "text-success")}>{Number.isNaN(preview.adjustment) ? "—" : signed(preview.adjustment)}</div>
              </div>
            </div>
            {error ? <p role="alert" className="rounded-lg bg-danger-subtle px-3 py-2 text-xs text-danger">{error}</p> : null}
          </div>
        ) : null}
      </Modal>
      <Modal
        open={!!open}
        onOpenChange={(o) => !o && setOpen(null)}
        title={open ? w("adjustment_title", { ref: open.ref }) : ""}
        description={open ? `${open.code} ${open.name} · ${open.period} · ${open.period_label}` : ""}
        footer={
          open && can.decide && open.status === "PENDING" ? (
            <>
              <Button variant="outline" onClick={() => decide("REJECT")} disabled={pending}>
                <CircleX /> {w("reject")}
              </Button>
              <Button onClick={() => decide("APPROVE")} loading={pending}>
                <CircleCheck /> {w("approve")}
              </Button>
            </>
          ) : (
            <Button variant="outline" onClick={() => setOpen(null)}>
              {w("close")}
            </Button>
          )
        }
      >
        {open ? (
          <div className="flex flex-col gap-3 text-sm">
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
              {[
                [w("col.kind"), w(`kind_long.${open.kind}`)],
                ...(open.kind === "OT" ? [[w("f.revised_hours"), hours(open.hours)], [w("f.revised_rate"), taka(open.rate)]] : []),
                [w("col.original"), taka(open.original)],
                [w("col.revised"), taka(open.revised)],
                [w("col.adjustment"), signed(open.amount)],
                [w("col.status"), w(`adj_status.${open.status}`)],
                [w("col.requested"), `${open.requested_by ?? "—"} · ${stamp(open.requested_at)}`],
                ...(open.decided_by ? [[w("col.decision"), `${open.decided_by} · ${stamp(open.decided_at)}`]] : []),
                ...(open.paid_in ? [[w("col.paid_in"), open.paid_in]] : []),
              ].map(([k, v]) => (
                <Fragment key={k}>
                  <dt className="text-foreground-muted">{k}</dt>
                  <dd className="font-medium">{v}</dd>
                </Fragment>
              ))}
            </dl>
            <div>
              <div className="text-xs text-foreground-muted">{w("col.reason")}</div>
              <p>{open.reason}</p>
            </div>
            {open.decision_note ? (
              <div>
                <div className="text-xs text-foreground-muted">{w("decision_note")}</div>
                <p>{open.decision_note}</p>
              </div>
            ) : null}
            {can.decide && open.status === "PENDING" ? (
              <Field label={w("decision_note")} hint={w("hint.decision_note")}>
                <Textarea rows={2} value={note} onChange={(e) => setNote(e.target.value)} aria-label={w("decision_note")} />
              </Field>
            ) : null}
            {error ? <p role="alert" className="rounded-lg bg-danger-subtle px-3 py-2 text-xs text-danger">{error}</p> : null}
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

export { DailySalary, OtSalary, FestivalBonus, MonthlySalary, PayrollAdjustment, Table };
