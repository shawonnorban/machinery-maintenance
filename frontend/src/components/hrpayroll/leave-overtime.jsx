"use client";

import { useMemo, useState, useTransition } from "react";
import { CircleCheck, CircleX, Clock, Eye, History, Inbox, ListChecks, Pencil, Plus, RefreshCw, RotateCcw, Save, Search, ShieldCheck, SlidersHorizontal, SquarePen, Table2, Trash2, Undo2, Users, Wallet, ListTree } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { Grid, Tabs } from "@/components/settings/devices";
import { clock12 } from "@/lib/clock";

/*
 * HR & Payroll → Leave & Overtime (AnnotechERP 24/05), as Brickland lays the
 * five screens out:
 *
 * - Leave: Records, Leave Status (an employee's balance by leave type) and
 *   Save application (daily or early departure, keyed by HR).
 * - OT Correction: the day's OT from attendance — hours, rate and amount —
 *   corrected row by row with a reason; Reset and History.
 * - OT Eligibility: the OT limits, Grade-wise General OT, and each
 *   employee's standing with Worker OT switched on for staff.
 * - OT Request: a line's OT window and reason for all or picked employees,
 *   approved directly or sent for approval; the requests.
 * - OT Approval: my inbox, all pending, decided; approve (unticking anyone),
 *   reject or return.
 *
 * Nothing reloads; tabs and dialogs switch in place.
 */

const card = "rounded-xl border border-border bg-surface";
const money = new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const hrs = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 });
const taka = (v) => (v === null || v === undefined ? "—" : `৳${money.format(v)}`);
const hours = (v) => (v === null || v === undefined ? "—" : hrs.format(v));
const day = (d) => (d ? new Date(`${d.slice(0, 10)}T00:00:00Z`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }) : "—");
const stamp = (s) => {
  if (!s) return "—";
  const d = new Date(/(Z|[+-]\d\d:?\d\d)$/.test(s) ? s : `${s.replace(" ", "T")}Z`);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleString("en-GB", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase());
};
const fail = (toast, res) => toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "", type: "danger" });
const firstError = (res) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "";

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`lo.${k}`, p);
}

function Pill({ t = "neutral", children }) {
  const tones = { good: "bg-success-subtle text-success", warning: "bg-warning-subtle text-warning", danger: "bg-danger-subtle text-danger", info: "bg-brand-subtle text-brand", neutral: "bg-surface-muted text-foreground-muted" };
  return <span className={cn("inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap", tones[t])}>{children}</span>;
}

function Field({ label, required, hint, error, className, children }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? <span className="text-xs text-danger">{error}</span> : hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
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
    <div className={cn(card, "px-4 py-3.5")}>
      <div className="flex items-center gap-2.5">
        <span className={cn("grid size-8 place-items-center rounded-lg", tones[tone])}>
          <Icon className="size-4" aria-hidden />
        </span>
        <span className="text-[13px] font-semibold">{label}</span>
      </div>
      <p className="mt-2.5 text-2xl font-semibold tabular-nums">{value}</p>
      {caption ? <p className="mt-0.5 text-xs text-foreground-muted">{caption}</p> : null}
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

const TH = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
const TD = "px-3 py-2.5 align-top";

/* ── Leave ───────────────────────────────────────────────────────── */

const LEAVE_STATUS_TONE = { PENDING: "warning", APPROVED: "good", REJECTED: "danger", CANCELLED: "neutral" };

function blankLeave(options) {
  return {
    fiscal_year_id: options.fiscal_year ?? "", mode: "DAILY", employee: "", purpose: "", leave_type_id: "", from_date: "", to_date: "", early_date: "", early_time: "", hours: "",
    duty_by: "", joining_date: "", joining_touched: false, status: "APPROVED", confirmed: false, reason: "", reason_bn: "",
  };
}

const daysBetween = (a, b) => (a && b && b >= a ? Math.round((new Date(`${b}T00:00:00Z`) - new Date(`${a}T00:00:00Z`)) / 86400000) + 1 : null);
const dayAfter = (d) => (d ? new Date(new Date(`${d}T00:00:00Z`).getTime() + 86400000).toISOString().slice(0, 10) : "");

function LeaveApplication({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [tab, setTab] = useState("records");
  const [rows, setRows] = useState(initial);
  const [form, setForm] = useState(() => blankLeave(options));
  const [errors, setErrors] = useState({});
  const [balances, setBalances] = useState(null);
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));
  const applicant = options.employees.find((e) => e.value === form.employee);
  const early = form.mode === "EARLY_DEPARTURE";
  const balance = balances?.find((b) => b.leave_type_id === form.leave_type_id);
  const days = daysBetween(form.from_date, form.to_date);
  const short = balance && balance.balance_day !== null && days !== null && days > balance.balance_day;

  const pickApplicant = (code) => {
    set({ employee: code ?? "" });
    setBalances(null);
    if (!code) return;
    start(async () => {
      const res = await act("leaveBalances", { employee: code, ...(form.fiscal_year_id ? { fiscal_year: form.fiscal_year_id } : {}) });
      if (res?.status === "success") setBalances(res.data.rows);
    });
  };
  const save = () =>
    start(async () => {
      const body = {
        fiscal_year_id: form.fiscal_year_id, mode: form.mode, employee: form.employee, leave_type_id: form.leave_type_id, status: form.status, confirmed: form.confirmed,
        reason: form.reason || null, reason_bn: form.reason_bn || null, duty_by: form.duty_by || null,
        ...(early ? { purpose: form.purpose || null, early_date: form.early_date, early_time: form.early_time, hours: form.hours === "" ? null : Number(form.hours) } : { from_date: form.from_date, to_date: form.to_date, joining_date: form.joining_date || dayAfter(form.to_date) || null }),
      };
      const res = await act("leaveStore", body);
      if (res?.status !== "success") {
        setErrors(Object.fromEntries(Object.entries(res?.errors ?? {}).map(([k, v]) => [k, v[0]])));
        return fail(toast, res);
      }
      toast.add({ title: res.data.message, type: "success" });
      setErrors({});
      setForm(blankLeave(options));
      setBalances(null);
      const list = await act("leaveList");
      if (list?.status === "success") setRows(list.data);
      setTab("records");
    });
  const remove = () =>
    start(async () => {
      const res = await act("leaveDelete", asking.ref_no);
      setAsking(null);
      if (res?.status !== "success") return fail(toast, res);
      toast.add({ title: res.data.message, type: "success" });
      setRows((r) => r.filter((x) => x.ref_no !== asking.ref_no));
    });

  const columns = [
    { key: "ref_no", label: w("col.ref_no"), mono: true },
    { key: "applicant", label: w("col.applicant") },
    { key: "leave_type", label: w("col.leave_type") },
    { key: "mode", label: w("col.mode"), read: (r) => w(`mode.${r.mode}`) },
    { key: "from", label: w("col.from") },
    { key: "to", label: w("col.to") },
    { key: "days", label: w("col.days"), right: true, read: (r) => (r.mode === "DAILY" ? hours(r.days) : w("hours_short", { n: hours(r.hours) })) },
    { key: "status", label: w("col.status"), read: (r) => w(`leave_status.${r.status}`), render: (r) => <Pill t={LEAVE_STATUS_TONE[r.status]}>{w(`leave_status.${r.status}`)}</Pill> },
  ];

  return (
    <div className="flex flex-col gap-4">
      <Tabs
        tabs={[
          ["records", w("tab.records"), rows.length, Table2],
          ["status", w("tab.status"), undefined, ListTree],
          ...(canManage ? [["new", w("tab.new"), undefined, Plus]] : []),
        ]}
        active={tab}
        onPick={setTab}
      />
      {tab === "records" ? (
        rows.length ? (
          <Grid w={w} rows={rows} columns={columns} rowKey="ref_no" exportName="leave-applications" empty={w("empty.records")} actions={canManage ? (r) => (
            <Button size="sm" variant="ghost" className="text-danger" onClick={() => setAsking(r)} aria-label={w("delete_aria", { ref: r.ref_no })}>
              <Trash2 />
            </Button>
          ) : null} />
        ) : (
          <section className={cn(card, "px-6 py-14 text-center")}>
            <p className="text-[15px] font-semibold">{w("empty.records_title")}</p>
            <p className="mt-1 text-[13px] text-foreground-muted">{w("empty.records")}</p>
          </section>
        )
      ) : null}
      {tab === "status" ? <LeaveStatus options={options} act={act} /> : null}
      {tab === "new" && canManage ? (
        <section className={cn(card, "flex flex-col gap-5 p-5")}>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Field label={w("f.company")} required>
              <Select value="c" options={[{ value: "c", label: options.company }]} onValueChange={() => {}} aria-label={w("f.company")} />
            </Field>
            <Field label={w("f.fiscal_year")} required error={errors.fiscal_year_id}>
              <Select value={form.fiscal_year_id} onValueChange={(v) => set({ fiscal_year_id: v ?? "" })} options={options.fiscal_years} placeholder={w("select")} aria-label={w("f.fiscal_year")} />
            </Field>
            <Field label={w("f.mode")} required>
              <Select value={form.mode} onValueChange={(v) => set({ mode: v ?? "DAILY" })} options={["DAILY", "EARLY_DEPARTURE"].map((m) => ({ value: m, label: w(`mode.${m}`) }))} aria-label={w("f.mode")} />
            </Field>
            <Field label={w("f.applicant")} required error={errors.employee}>
              <Select value={form.employee} onValueChange={pickApplicant} options={options.employees} placeholder={w("select")} aria-label={w("f.applicant")} />
            </Field>
            <Field label={w("f.applicant_details")} className="md:col-span-2">
              <Input value={applicant?.summary ?? ""} readOnly aria-label={w("f.applicant_details")} />
            </Field>
            {early ? (
              <Field label={w("f.purpose")}>
                <Input value={form.purpose} onChange={(e) => set({ purpose: e.target.value })} placeholder={w("ph.purpose")} aria-label={w("f.purpose")} />
              </Field>
            ) : null}
            <Field label={w("f.leave_type")} required error={errors.leave_type_id}>
              <Select value={form.leave_type_id} onValueChange={(v) => set({ leave_type_id: v ?? "" })} options={options.leave_types.filter((t) => !applicant?.gender || t.applicable_for === "COMMON" || t.applicable_for === String(applicant.gender).toUpperCase())} placeholder={w("select")} aria-label={w("f.leave_type")} />
            </Field>
            <Field label={w("f.balance")} hint={w("hint.balance")}>
              <Input value={balance ? (balance.balance_day === null ? w("unlimited") : hours(balance.balance_day)) : ""} readOnly aria-label={w("f.balance")} />
            </Field>
            {early ? (
              <>
                <Field label={w("f.early_date")} required error={errors.early_date}>
                  <Input type="date" value={form.early_date} onChange={(e) => set({ early_date: e.target.value })} aria-label={w("f.early_date")} />
                </Field>
                <Field label={w("f.early_time")} required error={errors.early_time}>
                  <Input type="time" value={form.early_time} onChange={(e) => set({ early_time: e.target.value })} placeholder="hh:mm" aria-label={w("f.early_time")} />
                </Field>
                <Field label={w("f.hours")} required error={errors.hours}>
                  <div className="flex items-center gap-2">
                    <Input type="number" min="0" step="0.5" value={form.hours} onChange={(e) => set({ hours: e.target.value })} aria-label={w("f.hours")} />
                    <span className="text-[13px] text-foreground-muted">{w("hour")}</span>
                  </div>
                </Field>
              </>
            ) : (
              <>
                <Field label={w("f.from_date")} required error={errors.from_date}>
                  <Input type="date" value={form.from_date} onChange={(e) => set({ from_date: e.target.value })} aria-label={w("f.from_date")} />
                </Field>
                <Field label={w("f.to_date")} required error={errors.to_date}>
                  <Input type="date" value={form.to_date} onChange={(e) => set({ to_date: e.target.value, ...(form.joining_touched ? {} : { joining_date: dayAfter(e.target.value) }) })} aria-label={w("f.to_date")} />
                </Field>
                <Field label={w("f.days")} error={short ? w("warn.balance") : null}>
                  <Input value={days ?? ""} readOnly aria-label={w("f.days")} />
                </Field>
              </>
            )}
            <Field label={w("f.apply_date")} required hint={w("hint.apply_date")}>
              <Input type="date" value={options.today} readOnly aria-label={w("f.apply_date")} />
            </Field>
            <Field label={w("f.approve_date")} hint={w("hint.approve_date")}>
              <Input type="date" value={form.status === "APPROVED" ? options.today : ""} readOnly aria-label={w("f.approve_date")} />
            </Field>
            <Field label={w("f.duty_by")} error={errors.duty_by}>
              <Select value={form.duty_by} onValueChange={(v) => set({ duty_by: v ?? "" })} options={options.employees.filter((e) => e.value !== form.employee)} placeholder={w("select")} aria-label={w("f.duty_by")} />
            </Field>
            {!early ? (
              <Field label={w("f.joining_date")}>
                <Input type="date" value={form.joining_date} onChange={(e) => set({ joining_date: e.target.value, joining_touched: true })} aria-label={w("f.joining_date")} />
              </Field>
            ) : null}
            <Field label={w("f.status")} required>
              <Select value={form.status} onValueChange={(v) => set({ status: v ?? "APPROVED" })} options={options.statuses.map((s) => ({ value: s, label: w(`leave_status.${s}`) }))} aria-label={w("f.status")} />
            </Field>
            <label className="flex items-center gap-2 self-end pb-2 text-[13px] font-semibold">
              <Checkbox checked={form.confirmed} onCheckedChange={(v) => set({ confirmed: !!v })} aria-label={w("f.confirmed")} />
              {w("f.confirmed")}
            </label>
            <Field label={w("f.reason")} className="md:col-span-2 xl:col-span-3">
              <Textarea rows={3} value={form.reason} onChange={(e) => set({ reason: e.target.value })} aria-label={w("f.reason")} />
            </Field>
            <Field label={w("f.reason_bn")} className="md:col-span-2 xl:col-span-3">
              <Textarea rows={3} value={form.reason_bn} onChange={(e) => set({ reason_bn: e.target.value })} aria-label={w("f.reason_bn")} />
            </Field>
          </div>
          <p className="rounded-lg bg-surface-muted px-3 py-2 text-xs text-foreground-muted">{w("form_note")}</p>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => (setForm(blankLeave(options)), setErrors({}), setBalances(null))}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button onClick={save} loading={pending}>
              <Save /> {w("save_application")}
            </Button>
          </div>
        </section>
      ) : null}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={w("delete_title", { ref: asking?.ref_no ?? "" })}
        description={w("delete_text")}
        confirmLabel={w("delete")}
        destructive
        loading={pending}
        onConfirm={remove}
      />
    </div>
  );
}

function LeaveStatus({ options, act }) {
  const w = useWords();
  const [needle, setNeedle] = useState("");
  const [picked, setPicked] = useState(null);
  const [data, setData] = useState(null);
  const [pending, start] = useTransition();
  const list = options.employees.filter((e) => !needle || e.label.toLowerCase().includes(needle.toLowerCase()));
  const open = (e) => {
    setPicked(e.value);
    start(async () => {
      const res = await act("leaveBalances", { employee: e.value });
      setData(res?.status === "success" ? res.data : null);
    });
  };
  const cols = ["entitled_day", "entitled_hour", "taken_day", "taken_hour", "adjusted_day", "adjusted_hour", "balance_day", "balance_hour"];
  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(260px,1fr)_2.4fr]">
      <section className={cn(card, "flex flex-col")}>
        <div className="flex items-center justify-between px-5 pt-4">
          <h3 className="text-[15px] font-semibold">{w("status.employee")}</h3>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] tabular-nums">{list.length}</span>
        </div>
        <div className="relative px-5 py-3">
          <Search className="pointer-events-none absolute top-1/2 left-8 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input className="pl-9" type="search" value={needle} onChange={(e) => setNeedle(e.target.value)} placeholder={w("ph.search")} aria-label={w("status.search")} />
        </div>
        <div className="max-h-[520px] overflow-y-auto border-t border-border">
          {list.length ? (
            list.map((e) => (
              <button key={e.value} type="button" onClick={() => open(e)} className={cn("flex w-full flex-col items-start border-b border-border px-5 py-2.5 text-left last:border-0 hover:bg-surface-muted", picked === e.value && "bg-brand-subtle")}>
                <span className="text-[13px] font-semibold">{e.name}</span>
                <span className="font-mono text-xs text-foreground-muted">{e.value}{e.designation ? ` · ${e.designation}` : ""}</span>
              </button>
            ))
          ) : (
            <p className="px-5 py-6 text-center text-[13px] text-foreground-muted">{w("status.none")}</p>
          )}
        </div>
      </section>
      <section className={cn(card, "flex flex-col")}>
        <div className="px-5 py-4">
          <Head icon={ListTree} title={data ? `${w("status.balance")} — ${data.employee.name} (${data.year})` : w("status.balance")} sub={w("status.balance_sub")} />
        </div>
        {!picked ? (
          <div className="px-6 py-16 text-center">
            <p className="text-[15px] font-semibold">{w("status.pick_title")}</p>
            <p className="mt-1 text-[13px] text-foreground-muted">{w("status.pick_text")}</p>
          </div>
        ) : pending && !data ? (
          <p className="px-6 py-16 text-center text-[13px] text-foreground-muted">{w("loading")}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-sm">
              <thead>
                <tr className="bg-brand text-brand-foreground">
                  <th className={TH}>{w("col.leave_type")}</th>
                  {cols.map((c) => (
                    <th key={c} className={cn(TH, "text-right")}>
                      {w(`bal.${c}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {(data?.rows ?? []).map((r) => (
                  <tr key={r.leave_type_id} className="border-b border-border last:border-0">
                    <td className={TD}>{r.leave_type}</td>
                    {cols.map((c) => (
                      <td key={c} className={cn(TD, "text-right tabular-nums", c === "balance_day" && "font-semibold")}>
                        {hours(r[c])}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="mt-auto border-t border-border px-5 py-3 text-xs text-foreground-muted">{w("status.note")}</p>
      </section>
    </div>
  );
}

/* ── OT Correction ───────────────────────────────────────────────── */

function OtCorrection({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [q, setQ] = useState({ date: initial.date, department: "", q: "", show: "ot" });
  const [data, setData] = useState(initial);
  const [edits, setEdits] = useState({});
  const [history, setHistory] = useState(null);
  const [pending, start] = useTransition();
  const [busy, setBusy] = useState(null);
  const load = () =>
    start(async () => {
      const res = await act("otCorrection", Object.fromEntries(Object.entries(q).filter(([, v]) => v !== "")));
      if (res?.status !== "success") return toast.add({ title: w("err.load"), type: "danger" });
      setData(res.data);
      setEdits({});
    });
  const v = (r) => ({ hours: String(r.corrected_hours ?? r.original_hours), rate: String(r.corrected_rate ?? r.original_rate), reason: r.reason ?? "", ...(edits[r.key] ?? {}) });
  const edit = (r, k, val) => setEdits((e) => ({ ...e, [r.key]: { ...(e[r.key] ?? {}), [k]: val } }));
  const put = (row) => setData((d) => {
    const rows = d.rows.map((x) => (x.key === row.key ? row : x));
    const eff = (x, a, b) => x[a] ?? x[b];
    return { ...d, rows, summary: { employees: rows.filter((x) => eff(x, "corrected_hours", "original_hours") > 0).length, original_hours: rows.reduce((s, x) => s + x.original_hours, 0), effective_hours: rows.reduce((s, x) => s + eff(x, "corrected_hours", "original_hours"), 0), original_amount: rows.reduce((s, x) => s + x.original_amount, 0), effective_amount: rows.reduce((s, x) => s + eff(x, "corrected_amount", "original_amount"), 0), corrected: rows.filter((x) => x.corrected_hours !== null).length } };
  });
  const save = (r) => {
    const val = v(r);
    if (!val.reason.trim()) return toast.add({ title: w("err.reason"), type: "danger" });
    if (val.hours === "" || val.rate === "" || Number(val.hours) < 0 || Number(val.rate) < 0 || Number.isNaN(Number(val.hours)) || Number.isNaN(Number(val.rate))) return toast.add({ title: w("err.numbers"), type: "danger" });
    setBusy(r.key);
    start(async () => {
      const res = await act("otCorrect", { employee: r.code, date: r.date, hours: Number(val.hours), rate: Number(val.rate), reason: val.reason.trim() });
      setBusy(null);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      put(res.data.row);
      setEdits((e) => ({ ...e, [r.key]: undefined }));
    });
  };
  const reset = (r) => {
    const val = v(r);
    if (!val.reason.trim()) return toast.add({ title: w("err.reason"), type: "danger" });
    setBusy(r.key);
    start(async () => {
      const res = await act("otReset", { employee: r.code, date: r.date, reason: val.reason.trim() });
      setBusy(null);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      put(res.data.row);
      setEdits((e) => ({ ...e, [r.key]: undefined }));
    });
  };
  const openHistory = (r) => {
    setHistory({ row: r, rows: null });
    start(async () => {
      const res = await act("otHistory", { employee: r.code, date: r.date });
      setHistory({ row: r, rows: res?.status === "success" ? res.data : [], error: res?.status !== "success" });
    });
  };
  const s = data.summary;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Field label={w("f.date")}>
            <Input type="date" value={q.date} max={options.today} onChange={(e) => setQ({ ...q, date: e.target.value })} aria-label={w("f.date")} />
          </Field>
          <Field label={w("f.department")}>
            <Select value={q.department} onValueChange={(x) => setQ({ ...q, department: x ?? "" })} options={[{ value: "", label: w("all") }, ...options.departments]} placeholder={w("all")} aria-label={w("f.department")} />
          </Field>
          <Field label={w("f.employee")}>
            <Input value={q.q} onChange={(e) => setQ({ ...q, q: e.target.value })} onKeyDown={(e) => e.key === "Enter" && load()} placeholder={w("ph.code_name_card")} aria-label={w("f.employee")} />
          </Field>
          <Field label={w("f.show")}>
            <Select value={q.show} onValueChange={(x) => setQ({ ...q, show: x ?? "ot" })} options={[{ value: "ot", label: w("show.ot") }, { value: "all", label: w("show.all") }]} aria-label={w("f.show")} />
          </Field>
        </div>
        <Button className="mt-4" onClick={load} loading={pending && !busy}>
          <RefreshCw /> {w("show_btn")}
        </Button>
      </section>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Users} label={w("tile.ot_employees")} value={s.employees} caption={day(data.date)} />
        <Stat icon={Clock} label={w("tile.ot_hours")} value={hours(s.effective_hours)} caption={w("tile.original_h", { n: hours(s.original_hours) })} tone="warning" />
        <Stat icon={Wallet} label={w("tile.ot_salary")} value={taka(s.effective_amount)} caption={w("tile.original", { n: taka(s.original_amount) })} tone="good" />
        <Stat icon={SquarePen} label={w("tile.corrected")} value={s.corrected} caption={w("tile.corrected_caption")} tone="warning" />
      </div>
      <section className={card}>
        <div className="px-5 py-4">
          <Head title={w("ot_by_employee")} sub={w("rows_on", { n: data.rows.length, date: day(data.date) })} />
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1500px] text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["date", "employee_id", "name", "department", "grade", "shift_end", "checkout", "original_h", "corrected_h", "rate", "original_salary", "corrected_salary", "reason", "updated_by", "updated_at"].map((c) => (
                  <th key={c} className={cn(TH, ["original_h", "corrected_h", "rate", "original_salary", "corrected_salary"].includes(c) && "text-right")}>
                    {w(`cc.${c}`)}
                  </th>
                ))}
                <th className={cn(TH, "text-right")}>{w("col.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {data.rows.length ? (
                data.rows.map((r) => {
                  const val = v(r);
                  return (
                    <tr key={r.key} className="border-b border-border last:border-0">
                      <td className={cn(TD, "whitespace-nowrap")}>{day(r.date)}</td>
                      <td className={cn(TD, "font-mono text-xs font-semibold")}>{r.code}</td>
                      <td className={TD}>
                        <div className="font-semibold">{r.name}</div>
                        <div className="flex items-center gap-1.5 text-xs text-foreground-muted">
                          {r.designation}
                          {r.ot_type === "WORKER" ? <Pill t="info">{w("worker_ot")}</Pill> : null}
                        </div>
                      </td>
                      <td className={TD}>{r.department ?? "—"}</td>
                      <td className={TD}>{r.grade ?? "—"}</td>
                      <td className={TD}>{clock12(r.shift_end) ?? "—"}</td>
                      <td className={TD}>{clock12(r.out_time) ?? "—"}</td>
                      <td className={cn(TD, "text-right tabular-nums")}>{hours(r.original_hours)}</td>
                      <td className={cn(TD, "text-right")}>
                        <Input className="ml-auto h-8 w-20 text-right" type="number" min="0" step="0.5" value={val.hours} disabled={!canManage || r.locked} onChange={(e) => edit(r, "hours", e.target.value)} aria-label={w("aria.hours", { code: r.code })} />
                      </td>
                      <td className={cn(TD, "text-right")}>
                        <Input className="ml-auto h-8 w-24 text-right" type="number" min="0" step="0.01" value={val.rate} disabled={!canManage || r.locked} onChange={(e) => edit(r, "rate", e.target.value)} aria-label={w("aria.rate", { code: r.code })} />
                      </td>
                      <td className={cn(TD, "text-right tabular-nums")}>{taka(r.original_amount)}</td>
                      <td className={cn(TD, "text-right tabular-nums")}>{taka(r.corrected_amount)}</td>
                      <td className={TD}>
                        <Input className="h-8 min-w-48" value={val.reason} disabled={!canManage || r.locked} onChange={(e) => edit(r, "reason", e.target.value)} placeholder={w("ph.required")} aria-label={w("aria.reason", { code: r.code })} />
                      </td>
                      <td className={TD}>{r.updated_by || "—"}</td>
                      <td className={cn(TD, "whitespace-nowrap")}>{r.updated_at ? stamp(r.updated_at) : "—"}</td>
                      <td className={cn(TD, "text-right")}>
                        <div className="flex justify-end gap-1">
                          {canManage && !r.locked ? (
                            <Button size="sm" onClick={() => save(r)} loading={busy === r.key}>
                              <Save /> {w("save")}
                            </Button>
                          ) : null}
                          {canManage && !r.locked && r.corrected_hours !== null ? (
                            <Button size="sm" variant="outline" onClick={() => reset(r)} title={w("reset_title")} aria-label={w("reset_title")}>
                              <RotateCcw />
                            </Button>
                          ) : null}
                          {r.has_history ? (
                            <Button size="sm" variant="ghost" onClick={() => openHistory(r)} aria-label={w("history_title")}>
                              <History />
                            </Button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={16} className="px-5 py-10 text-center text-sm text-foreground-muted">
                    {w("empty.ot")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      <Modal open={history !== null} onOpenChange={(o) => !o && setHistory(null)} title={w("history_title")} description={history ? `${history.row.name} (${history.row.code}) · ${day(history.row.date)}` : ""} className="max-w-3xl">
        {history?.rows === null ? (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{w("loading")}</p>
        ) : history?.error ? (
          <p className="py-6 text-center text-[13px] text-danger">{w("err.history")}</p>
        ) : history?.rows?.length ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-foreground-muted">
                  {["when", "by", "action", "hours", "rate", "amount", "reason"].map((c) => (
                    <th key={c} className={cn(TH, ["hours", "rate", "amount"].includes(c) && "text-right")}>
                      {w(`hc.${c}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {history.rows.map((h, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    <td className={cn(TD, "whitespace-nowrap")}>{stamp(h.when)}</td>
                    <td className={TD}>{h.by ?? "—"}</td>
                    <td className={TD}>
                      <Pill t={h.action === "RESET" ? "neutral" : "info"}>{w(`hist.${h.action}`)}</Pill>
                    </td>
                    <td className={cn(TD, "text-right tabular-nums")}>{hours(h.hours)}</td>
                    <td className={cn(TD, "text-right tabular-nums")}>{h.rate === null ? "—" : money.format(h.rate)}</td>
                    <td className={cn(TD, "text-right tabular-nums")}>{taka(h.amount)}</td>
                    <td className={TD}>{h.reason ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{w("empty.history")}</p>
        )}
      </Modal>
    </div>
  );
}

/* ── OT Eligibility ──────────────────────────────────────────────── */

const LIMITS = [
  ["start_after_min", "min"],
  ["grace_min", "min"],
  ["general_rounding_min", "min"],
  ["worker_rounding_min", "min"],
  ["break_min", "min"],
  ["break_after_hours", "h"],
  ["min_minutes", "min"],
  ["max_hours_day", "h"],
];

function OtEligibility({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [data, setData] = useState(initial);
  const [limits, setLimits] = useState(() => Object.fromEntries(LIMITS.map(([k]) => [k, String(initial.limits[k])])));
  const [reason, setReason] = useState("");
  const [f, setF] = useState({ department: "", category: "", ot: "", q: "" });
  const [notes, setNotes] = useState({});
  const [pending, start] = useTransition();
  const reload = async () => {
    const res = await act("otEligibility", {});
    if (res?.status === "success") setData(res.data);
    else toast.add({ title: w("err.employees"), type: "danger" });
  };
  const saveLimits = () => {
    if (LIMITS.some(([k]) => limits[k] === "" || Number.isNaN(Number(limits[k])) || Number(limits[k]) < 0)) return toast.add({ title: w("err.limits"), type: "danger" });
    start(async () => {
      const res = await act("otLimits", Object.fromEntries(LIMITS.map(([k]) => [k, Number(limits[k])])));
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.limits_save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      await reload();
    });
  };
  const toggleGrade = (g, on) => {
    if (!reason.trim()) return toast.add({ title: w("err.grade_reason"), type: "danger" });
    start(async () => {
      const res = await act("otGrade", { grade: g.code, general_ot: on, reason: reason.trim() });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.grade"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      await reload();
    });
  };
  const saveEmployee = (r, on, note) =>
    start(async () => {
      const res = await act("otEmployee", { employee: r.code, worker_ot: on, note: note ?? r.note ?? null });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.worker_ot"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      await reload();
    });
  const rows = useMemo(
    () =>
      data.rows.filter(
        (r) =>
          (!f.department || r.department === options.departments.find((d) => d.value === f.department)?.label) &&
          (!f.category || r.category === f.category) &&
          (!f.ot || r.ot === f.ot) &&
          (!f.q || [r.code, r.name].some((x) => String(x ?? "").toLowerCase().includes(f.q.toLowerCase()))),
      ),
    [data.rows, f, options.departments],
  );
  const columns = [
    { key: "code", label: w("ec.code"), mono: true },
    { key: "name", label: w("ec.name"), render: (r) => (
      <div>
        <div className="font-semibold">{r.name}</div>
        <div className="text-xs text-foreground-muted">{r.designation ?? ""}</div>
      </div>
    ) },
    { key: "department", label: w("ec.department") },
    { key: "category", label: w("ec.category"), read: (r) => (r.category ? w(`cat.${r.category}`) : "—") },
    { key: "general_ot", label: w("ec.general_ot"), read: (r) => r.general_reason, render: (r) => (
      <div className="flex flex-col items-start gap-1">
        <Pill t={r.general_ot ? "good" : "neutral"}>{r.general_ot ? w("yes") : w("no")}</Pill>
        <span className="text-xs text-foreground-muted">{r.general_reason}</span>
      </div>
    ) },
    { key: "worker_ot", label: w("ec.worker_ot"), read: (r) => (r.worker_ot_allowed ? (r.worker_ot ? w("on") : w("off")) : w("not_applicable")), render: (r) =>
      r.worker_ot_allowed ? (
        <label className="flex items-center gap-2 text-[13px]">
          <Switch checked={r.worker_ot} disabled={!canManage || pending} onCheckedChange={(on) => saveEmployee(r, on, notes[r.code])} aria-label={w("aria.worker_ot", { code: r.code })} />
          {r.worker_ot ? w("on") : w("off")}
        </label>
      ) : (
        <span className="text-xs text-foreground-muted">{w("not_applicable")}</span>
      ) },
    { key: "worker_rule", label: w("ec.worker_rule") },
    { key: "note", label: w("ec.note"), render: (r) =>
      r.worker_ot_allowed ? (
        <Input className="h-8 min-w-40" value={notes[r.code] ?? r.note ?? ""} disabled={!canManage} onChange={(e) => setNotes({ ...notes, [r.code]: e.target.value })} onBlur={() => notes[r.code] !== undefined && notes[r.code] !== (r.note ?? "") && saveEmployee(r, r.worker_ot, notes[r.code])} placeholder={w("ph.optional")} aria-label={w("aria.note", { code: r.code })} />
      ) : (
        "—"
      ) },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <Head icon={SlidersHorizontal} title={w("limits.title")} sub={w("limits.sub")}>
          {data.limits.updated_by ? <span className="text-xs text-foreground-muted">{w("limits.changed", { by: data.limits.updated_by, at: stamp(data.limits.updated_at) })}</span> : null}
        </Head>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {LIMITS.map(([k, unit]) => (
            <Field key={k} label={w(`limits.${k}`)} hint={w(`limits.hint.${k}`)}>
              <div className="relative">
                <Input type="number" min="0" step={unit === "h" ? "0.5" : "1"} className="pr-12" value={limits[k]} disabled={!canManage} onChange={(e) => setLimits({ ...limits, [k]: e.target.value })} aria-label={w(`limits.${k}`)} />
                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">{w(`unit.${unit}`)}</span>
              </div>
            </Field>
          ))}
        </div>
        {canManage ? (
          <div>
            <Button onClick={saveLimits} loading={pending}>
              <Save /> {w("limits.save")}
            </Button>
          </div>
        ) : null}
        <p className="text-xs text-foreground-muted">{w("limits.note")}</p>
      </section>
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <Head icon={CircleCheck} title={w("grades.title")} sub={w("grades.sub")} />
        {canManage ? (
          <Field label={w("grades.reason")} hint={w("grades.reason_hint")} className="max-w-xl">
            <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder={w("ph.grade_reason")} aria-label={w("grades.reason")} />
          </Field>
        ) : null}
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                <th className={TH}>{w("grades.grade")}</th>
                <th className={TH}>{w("grades.employees")}</th>
                <th className={TH}>{w("grades.general_ot")}</th>
              </tr>
            </thead>
            <tbody>
              {data.grades.length ? (
                data.grades.map((g) => (
                  <tr key={g.code} className="border-b border-border last:border-0">
                    <td className={cn(TD, "font-semibold")}>{g.grade}</td>
                    <td className={cn(TD, "tabular-nums")}>{g.employees}</td>
                    <td className={TD}>
                      <label className="flex items-center gap-2 text-[13px]">
                        <Switch checked={g.general_ot} disabled={!canManage || pending} onCheckedChange={(on) => toggleGrade(g, on)} aria-label={w("aria.grade", { grade: g.grade })} />
                        {g.general_ot ? w("on") : w("off")}
                      </label>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={3} className="px-5 py-8 text-center text-[13px] text-foreground-muted">
                    {w("grades.none")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <p className="text-xs text-foreground-muted">{w("grades.note")}</p>
      </section>
      <section className={cn(card, "grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4")}>
        <Field label={w("f.department")}>
          <Select value={f.department} onValueChange={(x) => setF({ ...f, department: x ?? "" })} options={[{ value: "", label: w("all") }, ...options.departments]} placeholder={w("all")} aria-label={w("f.department")} />
        </Field>
        <Field label={w("f.category")}>
          <Select value={f.category} onValueChange={(x) => setF({ ...f, category: x ?? "" })} options={[{ value: "", label: w("all") }, { value: "Staff", label: w("cat.Staff") }, { value: "Worker", label: w("cat.Worker") }]} placeholder={w("all")} aria-label={w("f.category")} />
        </Field>
        <Field label={w("f.ot")}>
          <Select value={f.ot} onValueChange={(x) => setF({ ...f, ot: x ?? "" })} options={[{ value: "", label: w("all") }, { value: "GENERAL", label: w("ot.GENERAL") }, { value: "WORKER", label: w("ot.WORKER") }, { value: "NONE", label: w("ot.NONE") }]} placeholder={w("all")} aria-label={w("f.ot")} />
        </Field>
        <Field label={w("f.employee")}>
          <Input value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} placeholder={w("ph.code_name_card")} aria-label={w("f.employee")} />
        </Field>
      </section>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Users} label={w("tile.employees")} value={rows.length} caption={w("tile.employees_caption")} />
        <Stat icon={CircleCheck} label={w("ot.GENERAL")} value={rows.filter((r) => r.ot === "GENERAL").length} caption={w("tile.general_caption")} tone="good" />
        <Stat icon={CircleX} label={w("tile.staff")} value={rows.filter((r) => r.ot !== "GENERAL" && !r.worker_ot).length} caption={w("tile.staff_caption")} tone="warning" />
        <Stat icon={ShieldCheck} label={w("ot.WORKER")} value={rows.filter((r) => r.ot === "WORKER").length} caption={w("tile.worker_caption")} />
      </div>
      <div className="flex flex-col gap-2">
        <h3 className="text-[15px] font-semibold">{w("by_employee")}</h3>
        <Grid w={w} rows={rows} columns={columns} rowKey="code" exportName="ot-eligibility" empty={w("empty.employees")} />
      </div>
    </div>
  );
}

/* ── OT Request ──────────────────────────────────────────────────── */

const REQ_TONE = { PENDING: "warning", APPROVED: "good", REJECTED: "danger", RETURNED: "info" };
const minutesBetween = (a, b) => {
  if (!a || !b) return null;
  const [ah, am] = a.split(":").map(Number);
  const [bh, bm] = b.split(":").map(Number);
  let m = bh * 60 + bm - (ah * 60 + am);
  if (m <= 0) m += 1440;
  return m;
};

function blankRequest(options) {
  return { date: options.today, department_id: "", section_id: "", line_id: "", shift_id: "", ot_start: "", ot_end: "", reason: "", approval: options.can_approve ? "DIRECT" : "ROUTED", approver_id: "" };
}

function RequestDetails({ w, r }) {
  const items = [
    ["request_no", r.request_no], ["date", day(r.date)], ["status", <Pill key="s" t={REQ_TONE[r.status]}>{w(`req_status.${r.status}`)}</Pill>], ["requested_by", r.requested_by],
    ["department", r.department], ["section", r.section], ["line", r.line], ["shift", r.shift],
    ["window", `${clock12(r.ot_start)}–${clock12(r.ot_end)}`], ["employees", r.employee_count], ["requested_hours", hours(r.requested_hours)],
    ["approval", r.approval === "DIRECT" ? w("direct") : r.approver ? w("sent_to", { name: r.approver }) : "—"],
  ];
  return (
    <div className="flex flex-col gap-3">
      <dl className="grid grid-cols-2 gap-x-4 gap-y-3 md:grid-cols-4">
        {items.map(([k, v]) => (
          <div key={k} className="min-w-0">
            <dt className="text-[11px] text-foreground-muted">{w(`d.${k}`)}</dt>
            <dd className="mt-0.5 truncate text-sm">{v || "—"}</dd>
          </div>
        ))}
      </dl>
      <div>
        <dt className="text-[11px] text-foreground-muted">{w("d.reason")}</dt>
        <dd className="mt-0.5 text-sm">{r.reason}</dd>
      </div>
      {r.decision_note || r.decided_by ? (
        <div>
          <dt className="text-[11px] text-foreground-muted">{w("d.decision")}</dt>
          <dd className="mt-0.5 text-sm">{[r.decided_by ? `${r.decided_by} · ${stamp(r.decided_at)}` : null, r.decision_note].filter(Boolean).join(" — ")}</dd>
        </div>
      ) : null}
    </div>
  );
}

function RequestEmployees({ w, r, removed, onToggle }) {
  const keep = Boolean(onToggle);
  return (
    <div className="max-h-80 overflow-auto rounded-lg border border-border">
      <table className="w-full min-w-[720px] text-sm">
        <thead className="sticky top-0 bg-surface-muted">
          <tr className="text-foreground-muted">
            {keep ? <th className={TH}>{w("rc.keep")}</th> : null}
            {["code", "name", "designation"].map((c) => (
              <th key={c} className={TH}>
                {w(`rc.${c}`)}
              </th>
            ))}
            {["approved_h", "actual_h", "payable_h", "rate", "amount"].map((c) => (
              <th key={c} className={cn(TH, "text-right")}>
                {w(`rc.${c}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {r.employees.map((e) => {
            const off = e.removed || removed?.has(e.code);
            return (
              <tr key={e.code} className={cn("border-t border-border", off && "opacity-50")}>
                {keep ? (
                  <td className={TD}>
                    <Checkbox checked={!removed.has(e.code)} onCheckedChange={() => onToggle(e.code)} aria-label={w("aria.keep", { name: e.name })} />
                  </td>
                ) : null}
                <td className={cn(TD, "font-mono text-xs")}>{e.code}</td>
                <td className={TD}>{e.name}</td>
                <td className={TD}>{e.designation ?? "—"}</td>
                <td className={cn(TD, "text-right tabular-nums")}>{hours(e.approved_hours)}</td>
                <td className={cn(TD, "text-right tabular-nums")}>{hours(e.actual_hours)}</td>
                <td className={cn(TD, "text-right tabular-nums")}>{hours(e.payable_hours)}</td>
                <td className={cn(TD, "text-right tabular-nums")}>{e.rate ? money.format(e.rate) : "—"}</td>
                <td className={cn(TD, "text-right tabular-nums")}>{taka(e.amount)}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function OtRequest({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial);
  const [form, setForm] = useState(() => blankRequest(options));
  const [editing, setEditing] = useState(null);
  const [line, setLine] = useState(null);
  const [picked, setPicked] = useState(new Set());
  const [needle, setNeedle] = useState("");
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState("ALL");
  const [search, setSearch] = useState("");
  const [viewing, setViewing] = useState(null);
  const [pending, start] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));
  const sections = options.sections.filter((s) => s.department_id === form.department_id);
  // A line under the section, or one of the department's lines not filed under any section.
  const lines = options.lines.filter((l) => form.section_id && (l.section_id === form.section_id || (!l.section_id && l.department_id === form.department_id)));
  const shift = options.shifts.find((s) => s.value === form.shift_id);
  const span = minutesBetween(form.ot_start, form.ot_end);

  const loadLine = (lineId, date, keep) => {
    setLine(null);
    setPicked(new Set());
    if (!lineId) return;
    start(async () => {
      const res = await act("otLineEmployees", { line: lineId, date });
      if (res?.status !== "success") return toast.add({ title: w("err.line"), type: "danger" });
      setLine(res.data);
      const s = options.shifts.find((x) => x.value === res.data.shift_id);
      if (!keep) setForm((f) => ({ ...f, shift_id: res.data.shift_id ?? "", ot_start: f.ot_start || s?.end || "" }));
      setPicked(new Set(keep ?? res.data.employees.map((e) => e.code)));
    });
  };
  const clear = () => (setForm(blankRequest(options)), setLine(null), setPicked(new Set()), setEditing(null), setErrors({}));
  const submit = () =>
    start(async () => {
      const body = { ...form, shift_id: form.shift_id || null, approver_id: form.approval === "ROUTED" ? form.approver_id || null : null, employees: [...picked] };
      const res = editing ? await act("otRequestUpdate", editing, body) : await act("otRequestStore", body);
      if (res?.status !== "success") {
        setErrors(Object.fromEntries(Object.entries(res?.errors ?? {}).map(([k, v]) => [k, v[0]])));
        return fail(toast, res);
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows((r) => [res.data.row, ...r.filter((x) => x.id !== res.data.row.id)]);
      clear();
    });
  const edit = (r) => {
    setEditing(r.request_no);
    setForm({ date: r.date, department_id: r.department_id, section_id: r.section_id, line_id: r.line_id, shift_id: r.shift_id ?? "", ot_start: r.ot_start, ot_end: r.ot_end, reason: r.reason, approval: options.can_approve ? "DIRECT" : "ROUTED", approver_id: r.approver_id ?? "" });
    loadLine(r.line_id, r.date, r.employees.filter((e) => !e.removed).map((e) => e.code));
    window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const shown = rows.filter((r) => (status === "ALL" || r.status === status) && (!search || [r.request_no, r.line, r.section, r.date].some((x) => String(x ?? "").toLowerCase().includes(search.toLowerCase()))));
  const people = (line?.employees ?? []).filter((e) => !needle || `${e.code} ${e.name}`.toLowerCase().includes(needle.toLowerCase()));
  const allOn = line && line.employees.length > 0 && picked.size === line.employees.length;

  return (
    <div className="flex flex-col gap-4">
      {!options.can_request ? <p className="rounded-lg bg-warning-subtle px-3 py-2 text-xs text-warning">{w("cannot_request")}</p> : null}
      {options.can_request ? (
        <section className={cn(card, "flex flex-col gap-5 p-5")}>
          <Head icon={ListChecks} title={editing ? w("req.edit_title", { no: editing }) : w("req.title")} sub={w("req.sub")} />
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Field label={w("f.date")} required error={errors.date}>
              <Input type="date" value={form.date} onChange={(e) => (set({ date: e.target.value }), form.line_id && loadLine(form.line_id, e.target.value, [...picked]))} aria-label={w("f.date")} />
            </Field>
            <Field label={w("f.department")} required error={errors.department_id}>
              <Select value={form.department_id} onValueChange={(v) => (set({ department_id: v ?? "", section_id: "", line_id: "" }), setLine(null), setPicked(new Set()))} options={options.departments} placeholder={w("select")} aria-label={w("f.department")} />
            </Field>
            <Field label={w("f.section")} required error={errors.section_id}>
              <Select value={form.section_id} onValueChange={(v) => (set({ section_id: v ?? "", line_id: "" }), setLine(null), setPicked(new Set()))} options={sections} disabled={!form.department_id} placeholder={form.department_id ? w("select") : w("ph.department_first")} aria-label={w("f.section")} />
            </Field>
            <Field label={w("f.line")} required error={errors.line_id}>
              <Select value={form.line_id} onValueChange={(v) => (set({ line_id: v ?? "" }), loadLine(v, form.date))} options={lines} disabled={!form.section_id} placeholder={form.section_id ? w("select") : w("ph.section_first")} aria-label={w("f.line")} />
            </Field>
            <Field label={w("f.shift")} hint={shift ? w("shift_ends", { end: clock12(shift.end) }) + (shift.ot_allowed ? "" : w("shift_no_ot")) : w("hint.shift")}>
              <Select value={form.shift_id} onValueChange={(v) => set({ shift_id: v ?? "", ot_start: options.shifts.find((s) => s.value === v)?.end ?? form.ot_start })} options={options.shifts} placeholder={w("select")} aria-label={w("f.shift")} />
            </Field>
            <Field label={w("f.ot_start")} required hint={w("hint.ot_start")} error={errors.ot_start}>
              <Input type="time" value={form.ot_start} onChange={(e) => set({ ot_start: e.target.value })} aria-label={w("f.ot_start")} />
            </Field>
            <Field label={w("f.ot_end")} required hint={span ? w("per_employee", { n: hours(span / 60) }) : w("hint.ot_end")} error={errors.ot_end}>
              <Input type="time" value={form.ot_end} onChange={(e) => set({ ot_end: e.target.value })} aria-label={w("f.ot_end")} />
            </Field>
            <div className="hidden xl:block" />
            <Field label={w("f.reason")} required className="md:col-span-2 xl:col-span-4" error={errors.reason}>
              <Textarea rows={2} value={form.reason} onChange={(e) => set({ reason: e.target.value })} placeholder={w("ph.reason")} aria-label={w("f.reason")} />
            </Field>
          </div>
          {form.line_id ? (
            <div className="flex flex-col gap-3 rounded-lg border border-border p-4">
              <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="flex flex-wrap gap-6">
                  {[["total", line?.total], ["active", line?.employees.length], ["selected", picked.size]].map(([k, n]) => (
                    <div key={k}>
                      <p className="text-[11px] text-foreground-muted">{w(`count.${k}`)}</p>
                      <p className="text-lg font-semibold tabular-nums">{n ?? "—"}</p>
                    </div>
                  ))}
                </div>
                <Field label={w("find_employee")} className="w-full max-w-xs">
                  <Input type="search" value={needle} onChange={(e) => setNeedle(e.target.value)} placeholder={w("ph.code_name")} aria-label={w("find_employee")} />
                </Field>
              </div>
              {errors.employees ? <p className="text-xs text-danger">{errors.employees}</p> : null}
              {!line ? (
                <p className="py-6 text-center text-[13px] text-foreground-muted">{w("loading_line")}</p>
              ) : (
                <div className="max-h-80 overflow-auto rounded-lg border border-border">
                  <table className="w-full text-sm">
                    <thead className="sticky top-0 bg-surface-muted">
                      <tr className="text-foreground-muted">
                        <th className={TH}>
                          <label className="flex items-center gap-2">
                            <Checkbox checked={allOn} disabled={!line.employees.length} onCheckedChange={() => setPicked(allOn ? new Set() : new Set(line.employees.map((e) => e.code)))} aria-label={w("select_all")} />
                            {w("lc.ot")}
                          </label>
                        </th>
                        {["code", "name", "designation", "section", "shift"].map((c) => (
                          <th key={c} className={TH}>
                            {w(`lc.${c}`)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {people.length ? (
                        people.map((e) => (
                          <tr key={e.code} className="border-t border-border">
                            <td className={TD}>
                              <Checkbox checked={picked.has(e.code)} onCheckedChange={() => setPicked((p) => { const n = new Set(p); n.has(e.code) ? n.delete(e.code) : n.add(e.code); return n; })} aria-label={w("aria.ot_for", { name: e.name })} />
                            </td>
                            <td className={cn(TD, "font-mono text-xs")}>{e.code}</td>
                            <td className={TD}>{e.name}</td>
                            <td className={TD}>{e.designation ?? "—"}</td>
                            <td className={TD}>{e.section ?? "—"}</td>
                            <td className={TD}>{e.shift ?? "—"}</td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={6} className="px-5 py-6 text-center text-[13px] text-foreground-muted">
                            {w("no_match")}
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ) : null}
          <div className="flex flex-col gap-3 border-t border-border pt-4">
            <div className="flex flex-wrap items-center gap-3">
              <span className="text-[13px] font-semibold">{w("approval")}</span>
              <Segments label={w("approval_route")} value={form.approval} onChange={(v) => set({ approval: v })} items={[...(options.can_approve ? [["DIRECT", w("direct")]] : []), ["ROUTED", w("routed")]]} />
            </div>
            {form.approval === "DIRECT" ? (
              <p className="text-[13px] text-foreground-muted">{w("direct_note")}</p>
            ) : (
              <Field label={w("f.send_to")} required hint={w("hint.send_to")} error={errors.approver_id} className="max-w-sm">
                <Select value={form.approver_id} onValueChange={(v) => set({ approver_id: v ?? "" })} options={options.approvers} placeholder={w("select")} aria-label={w("f.send_to")} />
              </Field>
            )}
            {errors.approval ? <p className="text-xs text-danger">{errors.approval}</p> : null}
            <div className="flex flex-wrap gap-2">
              <Button onClick={submit} loading={pending} disabled={!picked.size || !form.reason.trim() || !form.ot_start || !form.ot_end}>
                {form.approval === "DIRECT" ? <ShieldCheck /> : <CircleCheck />}
                {form.approval === "DIRECT" ? w("approve_ot") : editing ? w("resubmit") : w("send_for_approval")}
              </Button>
              <Button variant="outline" onClick={clear}>
                {editing ? w("cancel_edit") : w("clear")}
              </Button>
            </div>
          </div>
        </section>
      ) : null}
      <section className={card}>
        <div className="flex flex-col gap-3 px-5 py-4">
          <Head title={w("requests.title")} sub={w("requests.sub")}>
            <Segments label={w("filter_status")} value={status} onChange={setStatus} items={["ALL", "PENDING", "APPROVED", "RETURNED", "REJECTED"].map((s) => [s, s === "ALL" ? w("all") : w(`req_status.${s}`)])} />
          </Head>
          <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <Input className="pl-9" type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={w("ph.requests")} aria-label={w("search_requests")} />
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["request_no", "date", "section_line", "window", "employees", "hours", "status", "approver"].map((c) => (
                  <th key={c} className={cn(TH, ["employees", "hours"].includes(c) && "text-right")}>
                    {w(`qc.${c}`)}
                  </th>
                ))}
                <th className={cn(TH, "text-right")}>{w("col.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {shown.length ? (
                shown.map((r) => (
                  <tr key={r.id} className="border-b border-border last:border-0">
                    <td className={cn(TD, "font-mono text-xs font-semibold")}>{r.request_no}</td>
                    <td className={cn(TD, "whitespace-nowrap")}>{day(r.date)}</td>
                    <td className={TD}>
                      {r.section} / {r.line}
                    </td>
                    <td className={TD}>
                      {clock12(r.ot_start)}–{clock12(r.ot_end)}
                    </td>
                    <td className={cn(TD, "text-right tabular-nums")}>{r.employee_count}</td>
                    <td className={cn(TD, "text-right tabular-nums")}>{hours(r.requested_hours)}</td>
                    <td className={TD}>
                      <Pill t={REQ_TONE[r.status]}>{w(`req_status.${r.status}`)}</Pill>
                    </td>
                    <td className={TD}>{r.approval === "DIRECT" ? <Pill>{w("direct_tag")}</Pill> : (r.approver ?? "—")}</td>
                    <td className={cn(TD, "text-right")}>
                      <div className="flex justify-end gap-1">
                        <Button size="sm" variant="ghost" onClick={() => setViewing(r)}>
                          <Eye /> {w("view")}
                        </Button>
                        {r.status === "RETURNED" && options.can_request ? (
                          <Button size="sm" variant="outline" onClick={() => edit(r)}>
                            <Pencil /> {w("edit")}
                          </Button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={9} className="px-5 py-10 text-center text-sm text-foreground-muted">
                    {w("empty.requests")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      <Modal open={viewing !== null} onOpenChange={(o) => !o && setViewing(null)} title={viewing ? w("request_title", { no: viewing.request_no }) : ""} className="max-w-4xl">
        {viewing ? (
          <div className="flex flex-col gap-4">
            <RequestDetails w={w} r={viewing} />
            <RequestEmployees w={w} r={viewing} />
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

/* ── OT Approval ─────────────────────────────────────────────────── */

function OtApproval({ initial, canApprove, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [data, setData] = useState(initial);
  const [tab, setTab] = useState(initial.inbox.length > 0 || !canApprove ? "inbox" : "pending");
  const [open, setOpen] = useState(null);
  const [note, setNote] = useState("");
  const [removed, setRemoved] = useState(new Set());
  const [error, setError] = useState("");
  const [pending, start] = useTransition();
  const review = (r) => (setOpen(r), setNote(""), setRemoved(new Set()), setError(""));
  const decide = (decision) =>
    start(async () => {
      setError("");
      const res = await act("otDecide", open.request_no, { decision, note: note.trim() || null, removed: decision === "APPROVE" ? [...removed] : [] });
      if (res?.status !== "success") return setError(firstError(res) || w("err.decision"));
      toast.add({ title: res.data.message, type: "success" });
      const fresh = await act("otApprovals");
      if (fresh?.status === "success") setData(fresh.data);
      setOpen(null);
    });
  const decidable = canApprove && open?.status === "PENDING";
  const keep = open ? open.employees.length - removed.size : 0;
  const list = { inbox: data.inbox, pending: data.pending, decided: data.decided }[tab];
  const empty = { inbox: w("empty.inbox"), pending: w("empty.pending"), decided: w("empty.decided") }[tab];
  return (
    <div className="flex flex-col gap-4">
      {!canApprove ? <p className="rounded-lg bg-warning-subtle px-3 py-2 text-xs text-warning">{w("cannot_approve")}</p> : null}
      <section className={cn(card, "p-5")}>
        <Tabs
          tabs={[
            ["inbox", w("tab.inbox"), data.inbox.length, Inbox],
            ...(canApprove ? [["pending", w("tab.pending"), data.pending.length, ListChecks]] : []),
            ["decided", w("tab.decided"), undefined, CircleCheck],
          ]}
          active={tab}
          onPick={setTab}
        />
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          <table className="w-full min-w-[1000px] text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["request_no", "date", "place", "window", "employees", "hours", tab === "decided" ? "decided" : "requested_by", "status"].map((c) => (
                  <th key={c} className={cn(TH, ["employees", "hours"].includes(c) && "text-right")}>
                    {w(`ac.${c}`)}
                  </th>
                ))}
                <th className={cn(TH, "text-right")}>{w("ac.open")}</th>
              </tr>
            </thead>
            <tbody>
              {list.length ? (
                list.map((r) => (
                  <tr key={r.id} className="border-b border-border last:border-0">
                    <td className={cn(TD, "font-mono text-xs font-semibold")}>{r.request_no}</td>
                    <td className={cn(TD, "whitespace-nowrap")}>{day(r.date)}</td>
                    <td className={TD}>
                      {r.department} / {r.section} / {r.line}
                    </td>
                    <td className={TD}>
                      {clock12(r.ot_start)}–{clock12(r.ot_end)}
                    </td>
                    <td className={cn(TD, "text-right tabular-nums")}>{r.employee_count}</td>
                    <td className={cn(TD, "text-right tabular-nums")}>{hours(r.requested_hours)}</td>
                    <td className={TD}>{tab === "decided" ? `${r.decided_by ?? "—"} · ${stamp(r.decided_at)}` : (r.requested_by ?? "—")}</td>
                    <td className={TD}>
                      <Pill t={REQ_TONE[r.status]}>{w(`req_status.${r.status}`)}</Pill>
                    </td>
                    <td className={cn(TD, "text-right")}>
                      <Button size="sm" variant={tab === "decided" ? "ghost" : "outline"} onClick={() => review(r)}>
                        <Eye /> {tab === "decided" ? w("view") : w("review")}
                      </Button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={9} className="px-5 py-10 text-center text-sm text-foreground-muted">
                    {empty}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      <Modal
        open={open !== null}
        onOpenChange={(o) => !o && !pending && setOpen(null)}
        title={open ? w("request_title", { no: open.request_no }) : ""}
        description={decidable ? w("approve_text") : undefined}
        className="max-w-4xl"
        footer={
          decidable ? (
            <>
              <Button variant="danger" onClick={() => decide("REJECT")} disabled={pending}>
                <CircleX /> {w("reject")}
              </Button>
              <Button variant="outline" onClick={() => decide("RETURN")} disabled={pending}>
                <Undo2 /> {w("return")}
              </Button>
              <Button onClick={() => decide("APPROVE")} loading={pending} disabled={keep < 1}>
                <CircleCheck /> {w("approve_n", { n: keep })}
              </Button>
            </>
          ) : null
        }
      >
        {open ? (
          <div className="flex flex-col gap-4">
            <RequestDetails w={w} r={open} />
            <RequestEmployees w={w} r={open} removed={decidable ? removed : undefined} onToggle={decidable ? (code) => setRemoved((s) => { const n = new Set(s); n.has(code) ? n.delete(code) : n.add(code); return n; }) : undefined} />
            {decidable ? (
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

export { LeaveApplication, OtCorrection, OtEligibility, OtRequest, OtApproval };
