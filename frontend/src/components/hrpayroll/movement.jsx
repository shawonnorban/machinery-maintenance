"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import { ArrowRight, BadgeCheck, BadgeDollarSign, CalendarClock, Check, Clock, Download, FileText, Paperclip, Pencil, Plus, RotateCcw, Save, Search, Send, Table2, Trash2, TrendingUp, UserRound, X, XCircle, CircleCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { Grid, Tabs } from "@/components/settings/devices";

/*
 * HR & Payroll → Employee Movement (AnnotechERP 24/07), as Brickland lays
 * the four screens out:
 *
 * - Transfer: Records and New transfer — the employee's old posting filled
 *   in, the new department, section and line (floor follows the line), date,
 *   reason and status; a draft is finalized from its row.
 * - Promotion / Increment: four tiles, the request form beside the list
 *   (status, search, effective range, Excel); approve or reject with a note,
 *   withdraw while pending.
 * - Re-Joining: Records and New re-join — a former employee back from a date,
 *   with the pay period, the re-join letter and remarks.
 *
 * Nothing reloads; tabs and dialogs switch in place.
 */

const card = "rounded-xl border border-border bg-surface";
const money = new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const taka = (v) => (v === null || v === undefined ? "—" : `৳${money.format(v)}`);
const day = (d) => (d ? new Date(`${d.slice(0, 10)}T00:00:00Z`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }) : "—");
const stamp = (s) => {
  if (!s) return "";
  const d = new Date(/(Z|[+-]\d\d:?\d\d)$/.test(s) ? s : `${s.replace(" ", "T")}Z`);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase());
};
const firstError = (res) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "";
const errorsOf = (res) => Object.fromEntries(Object.entries(res?.errors ?? {}).map(([k, v]) => [k, v?.[0]]));

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`mv.${k}`, p);
}

function downloadCsv(name, head, rows) {
  const body = [head, ...rows].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\r\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob([`﻿${body}`], { type: "text/csv;charset=utf-8" }));
  link.download = name;
  link.click();
  URL.revokeObjectURL(link.href);
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

function Head({ icon: Icon, title, sub }) {
  return (
    <div className="flex items-start gap-2.5">
      {Icon ? <Icon className="mt-0.5 size-4.5 text-foreground-muted" aria-hidden /> : null}
      <div>
        <h3 className="text-[15px] font-semibold">{title}</h3>
        {sub ? <p className="text-[13px] text-foreground-muted">{sub}</p> : null}
      </div>
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

function Empty({ title, text }) {
  return (
    <section className={cn(card, "px-6 py-14 text-center")}>
      <p className="text-[15px] font-semibold">{title}</p>
      {text ? <p className="mt-1 text-[13px] text-foreground-muted">{text}</p> : null}
    </section>
  );
}

/* ── Transfer ────────────────────────────────────────────────────── */

const blankTransfer = (today) => ({ ref: null, employee: "", new_department_id: "", new_section_id: "", new_line_id: "", effective_date: today, reason: "", status: "" });

function Transfer({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const can = options.can_manage;
  const [tab, setTab] = useState("records");
  const [rows, setRows] = useState(initial);
  const [people, setPeople] = useState(options.employees);
  const [form, setForm] = useState(() => blankTransfer(options.today));
  const [errors, setErrors] = useState({});
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));
  const old = people.find((p) => p.value === form.employee);
  const sections = form.new_department_id ? options.sections.filter((s) => s.department_id === form.new_department_id) : [];
  const lines = form.new_section_id ? options.lines.filter((l) => l.section_id === form.new_section_id || (!l.section_id && l.department_id === form.new_department_id)) : form.new_department_id ? options.lines.filter((l) => l.department_id === form.new_department_id) : [];
  const line = options.lines.find((l) => l.value === form.new_line_id);
  const save = () =>
    start(async () => {
      const body = { employee: form.employee, new_department_id: form.new_department_id, new_section_id: form.new_section_id || null, new_line_id: form.new_line_id || null, effective_date: form.effective_date, reason: form.reason, status: form.status };
      const res = form.ref ? await act("trUpdate", form.ref, body) : await act("trStore", body);
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      if (res.data.employees) setPeople(res.data.employees);
      setForm(blankTransfer(options.today));
      setErrors({});
      setTab("records");
    });
  const run = (op, ref) =>
    start(async () => {
      const res = await act(op, ref);
      setAsking(null);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      if (res.data.employees) setPeople(res.data.employees);
    });
  const edit = (r) => {
    setForm({ ref: r.ref, employee: r.code, new_department_id: r.new_department_id ?? "", new_section_id: r.new_section_id ?? "", new_line_id: r.new_line_id ?? "", effective_date: r.effective_date, reason: r.reason ?? "", status: r.status });
    setErrors({});
    setTab("new");
  };
  const columns = [
    { key: "ref", label: w("col.ref_no"), render: (r) => <span className="font-mono text-xs font-semibold whitespace-nowrap">{r.ref}</span> },
    { key: "effective_date", label: w("col.eff_date"), read: (r) => day(r.effective_date), render: (r) => <span className="whitespace-nowrap">{day(r.effective_date)}</span> },
    { key: "code", label: w("col.emp_code"), render: (r) => <span className="font-mono text-xs whitespace-nowrap">{r.code}</span> },
    { key: "name", label: w("col.name"), render: (r) => (<div><div className="font-semibold">{r.name}</div>{r.designation ? <div className="text-xs text-foreground-muted">{r.designation}</div> : null}</div>) },
    { key: "old_department", label: w("col.old_department") },
    { key: "old_section", label: w("col.old_section") },
    { key: "old_line", label: w("col.old_line") },
    { key: "new_department", label: w("col.new_department") },
    { key: "new_section", label: w("col.new_section") },
    { key: "new_line", label: w("col.new_line") },
    { key: "status", label: w("col.status"), read: (r) => w(`tr_status.${r.status}`), render: (r) => (<div><Pill t={r.status === "FINALIZED" ? "good" : "warning"}>{w(`tr_status.${r.status}`)}</Pill>{r.finalized_by ? <div className="mt-0.5 text-xs whitespace-nowrap text-foreground-muted">{r.finalized_by}</div> : null}</div>) },
  ];
  return (
    <div className="flex flex-col gap-4">
      <Tabs tabs={[["records", w("tab.records"), rows.length, Table2], ...(can ? [["new", form.ref ? w("edit_transfer", { ref: form.ref }) : w("new_transfer"), undefined, form.ref ? Pencil : Plus]] : [])]} active={tab} onPick={setTab} />
      {tab === "records" ? (
        rows.length ? (
          <Grid
            w={w}
            rows={rows}
            columns={columns}
            rowKey="ref"
            exportName="transfers"
            empty={w("empty.match")}
            actions={can ? (r) => (r.status === "DRAFT" ? (
              <div className="flex justify-end gap-1">
                <Button size="sm" onClick={() => setAsking({ op: "trFinalize", r })}>
                  <CircleCheck /> {w("finalize")}
                </Button>
                <Button size="sm" variant="ghost" onClick={() => edit(r)} aria-label={w("edit_aria", { ref: r.ref })}>
                  <Pencil />
                </Button>
                <Button size="sm" variant="ghost" className="text-danger" onClick={() => setAsking({ op: "trDelete", r })} aria-label={w("delete_aria", { ref: r.ref })}>
                  <Trash2 />
                </Button>
              </div>
            ) : null) : null}
          />
        ) : (
          <Empty title={w("empty.transfer_title")} text={can ? w("empty.transfer") : null} />
        )
      ) : null}
      {tab === "new" && can ? (
        <section className={cn(card, "flex flex-col gap-5 p-5")}>
          <Head title={form.ref ? w("edit_transfer", { ref: form.ref }) : w("new_transfer")} sub={w("transfer_sub")} />
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Field label={w("f.employee")} required error={errors.employee}>
              <Select value={form.employee} disabled={!!form.ref} onValueChange={(v) => set({ employee: v ?? "" })} options={form.ref && !people.some((p) => p.value === form.employee) ? [{ value: form.employee, label: form.employee }] : people} placeholder={w("select")} aria-label={w("f.employee")} />
            </Field>
            <Field label={w("f.name_designation")} hint={w("hint.filled")}>
              <Input value={old?.name ?? ""} readOnly aria-label={w("f.name_designation")} />
            </Field>
            <Field label={w("f.old_department")} hint={w("hint.filled")}>
              <Input value={old?.department ?? ""} readOnly aria-label={w("f.old_department")} />
            </Field>
            <Field label={w("f.old_section")} hint={w("hint.filled")}>
              <Input value={old?.section ?? ""} readOnly aria-label={w("f.old_section")} />
            </Field>
            <Field label={w("f.old_line")} hint={w("hint.filled")}>
              <Input value={old?.line ?? ""} readOnly aria-label={w("f.old_line")} />
            </Field>
            <Field label={w("f.old_floor")} hint={w("hint.filled")}>
              <Input value={old?.floor ?? ""} readOnly aria-label={w("f.old_floor")} />
            </Field>
            <Field label={w("f.new_department")} required error={errors.new_department_id}>
              <Select value={form.new_department_id} onValueChange={(v) => set({ new_department_id: v ?? "", new_section_id: "", new_line_id: "" })} options={options.departments} placeholder={w("select")} aria-label={w("f.new_department")} />
            </Field>
            <Field label={w("f.new_section")} error={errors.new_section_id} hint={form.new_department_id && !sections.length ? w("hint.no_records") : null}>
              <Select value={form.new_section_id} onValueChange={(v) => set({ new_section_id: v ?? "", new_line_id: "" })} options={[{ value: "", label: w("select") }, ...sections]} placeholder={w("select")} aria-label={w("f.new_section")} />
            </Field>
            <Field label={w("f.new_line")} error={errors.new_line_id}>
              <Select value={form.new_line_id} onValueChange={(v) => set({ new_line_id: v ?? "" })} options={[{ value: "", label: w("select") }, ...lines]} placeholder={w("select")} aria-label={w("f.new_line")} />
            </Field>
            <Field label={w("f.new_floor")} hint={w("hint.floor")}>
              <Input value={line?.floor ?? ""} readOnly aria-label={w("f.new_floor")} />
            </Field>
            <Field label={w("f.effective_date")} required error={errors.effective_date}>
              <Input type="date" value={form.effective_date} onChange={(e) => set({ effective_date: e.target.value })} aria-label={w("f.effective_date")} />
            </Field>
            <Field label={w("f.reason")} className="md:col-span-2 xl:col-span-3">
              <Textarea rows={3} value={form.reason} onChange={(e) => set({ reason: e.target.value })} aria-label={w("f.reason")} />
            </Field>
            <Field label={w("f.status")} required error={errors.status} hint={w("hint.status")}>
              <Select value={form.status} onValueChange={(v) => set({ status: v ?? "" })} options={[{ value: "DRAFT", label: w("tr_status.DRAFT") }, { value: "FINALIZED", label: w("tr_status.FINALIZED") }]} placeholder={w("select")} aria-label={w("f.status")} />
            </Field>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
            <p className="text-xs text-foreground-muted">{w("audit_note")}</p>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => (setForm(blankTransfer(options.today)), setErrors({}))}>
                <RotateCcw /> {w("reset")}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save /> {w("save_transfer")}
              </Button>
            </div>
          </div>
        </section>
      ) : null}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? w(asking.op === "trFinalize" ? "finalize_title" : "delete_title", { ref: asking.r.ref }) : ""}
        description={asking ? w(asking.op === "trFinalize" ? "finalize_text" : "delete_text") : ""}
        confirmLabel={asking?.op === "trFinalize" ? w("finalize") : w("delete")}
        cancelLabel={w("cancel")}
        destructive={asking?.op === "trDelete"}
        loading={pending}
        onConfirm={() => run(asking.op, asking.r.ref)}
      />
    </div>
  );
}

/* ── Promotion and Increment ─────────────────────────────────────── */

const DECISION_TONE = { PENDING: "warning", APPROVED: "good", REJECTED: "danger" };

function EmployeeCard({ w, e, action }) {
  const facts = [
    [w("card.designation"), e.designation || "—"],
    [w("card.grade"), e.grade || "—"],
    [w("card.department"), e.department || "—"],
    [w("card.section"), e.section || "—"],
    [w("card.line"), e.line || "—"],
    [w("card.joined"), day(e.joining_date)],
    [w("card.gross_a"), taka(e.gross_a)],
    [w("card.structure"), e.structure || "—"],
  ];
  return (
    <div className="rounded-lg border border-border bg-surface-muted p-3">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="font-semibold">{e.name}</p>
          <p className="font-mono text-xs text-foreground-muted">
            {e.code}
            {e.card_no ? ` · ${w("card.card", { n: e.card_no })}` : ""}
          </p>
        </div>
        {action}
      </div>
      <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-[13px]">
        {facts.map(([k, v]) => (
          <div key={k} className="flex gap-2">
            <dt className="text-foreground-muted">{k}</dt>
            <dd className="font-medium">{v}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

function EmployeePicker({ w, act, employee, onChange, error, op = "mvSearch" }) {
  const [q, setQ] = useState("");
  const [found, setFound] = useState(null);
  const [failed, setFailed] = useState("");
  const [pending, start] = useTransition();
  const search = () =>
    start(async () => {
      setFailed("");
      const res = await act(op, { q });
      if (res?.status !== "success") return setFailed(w("err.search"));
      setFound(res.data);
    });
  if (employee)
    return (
      <Field label={w("f.employee")} required>
        <EmployeeCard w={w} e={employee} action={<Button size="sm" variant="ghost" onClick={() => onChange(null)}>{w("change")}</Button>} />
      </Field>
    );
  return (
    <div className="flex flex-col gap-2">
      <Field label={w("f.employee")} required error={error || failed}>
        <div className="flex gap-2" onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), search())}>
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={w("ph.employee")} aria-label={w("f.employee")} />
          <Button onClick={search} loading={pending} aria-label={w("search_employees")}>
            <Search />
          </Button>
        </div>
      </Field>
      {found ? (
        found.length ? (
          <ul className="flex max-h-64 flex-col overflow-y-auto rounded-lg border border-border">
            {found.map((e) => (
              <li key={e.code}>
                <button type="button" onClick={() => (onChange(e), setFound(null))} className="flex w-full items-center gap-2 border-b border-border px-3 py-2 text-left last:border-0 hover:bg-surface-muted">
                  <UserRound className="size-4 text-foreground-muted" aria-hidden />
                  <span className="flex flex-col">
                    <span className="text-sm font-semibold">{e.name}</span>
                    <span className="text-xs text-foreground-muted">
                      {e.code}
                      {e.designation ? ` · ${e.designation}` : ""}
                    </span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-xs text-foreground-muted">{w("no_employee")}</p>
        )
      ) : null}
    </div>
  );
}

function useFilter(rows, read) {
  const [f, setF] = useState({ status: "ALL", query: "", from: "", to: "" });
  const shown = useMemo(() => {
    const q = f.query.trim().toLowerCase();
    return rows.filter((r) => {
      const x = read(r);
      return (f.status === "ALL" || x.status === f.status) && (!f.from || x.date >= f.from) && (!f.to || x.date <= f.to) && (!q || x.text.toLowerCase().includes(q));
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows, f]);
  return [f, setF, shown];
}

function FilterBar({ w, f, onChange, onExport, dateLabel }) {
  return (
    <div className="flex flex-wrap items-end gap-3">
      <Segments label={w("f.status")} value={f.status} onChange={(s) => onChange({ ...f, status: s })} items={["ALL", "PENDING", "APPROVED", "REJECTED"].map((s) => [s, w(`decision.${s}`)])} />
      <Field label={w("f.search")} className="w-56">
        <Input value={f.query} onChange={(e) => onChange({ ...f, query: e.target.value })} placeholder={w("ph.search")} aria-label={w("f.search")} />
      </Field>
      <Field label={dateLabel ?? w("f.effective_from")}>
        <Input type="date" value={f.from} onChange={(e) => onChange({ ...f, from: e.target.value })} aria-label={dateLabel ?? w("f.effective_from")} />
      </Field>
      <Field label={w("f.to")}>
        <Input type="date" value={f.to} onChange={(e) => onChange({ ...f, to: e.target.value })} aria-label={w("f.to")} />
      </Field>
      <Button variant="outline" onClick={onExport}>
        <Download /> {w("excel_csv")}
      </Button>
    </div>
  );
}

function Requests({ w, title, rows, columns, empty, exportName, head, line }) {
  const [perPage, setPerPage] = useState(10);
  const [page, setPage] = useState(1);
  const pages = Math.max(1, Math.ceil(rows.length / perPage));
  const cur = Math.min(page, pages);
  const slice = rows.slice((cur - 1) * perPage, cur * perPage);
  return (
    <div className="flex flex-col gap-3">
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full min-w-[900px] text-sm">
          <caption className="sr-only">{title}</caption>
          <thead>
            <tr className="bg-brand text-left text-[13px] text-brand-foreground">
              {columns.map((c) => (
                <th key={c.key} className={cn("px-3 py-2.5 font-semibold whitespace-nowrap", c.right && "text-right")}>
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {slice.length ? (
              slice.map((r) => (
                <tr key={r.ref} className="border-b border-border last:border-0 hover:bg-surface-muted">
                  {columns.map((c) => (
                    <td key={c.key} className={cn("px-3 py-2.5 align-top", c.right && "text-right tabular-nums")}>
                      {c.render(r)}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length} className="px-5 py-10 text-center text-foreground-muted">
                  {empty}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 text-[13px] text-foreground-muted">
        <span>{w("count", { n: rows.length })}</span>
        <div className="flex items-center gap-2">
          <span className="whitespace-nowrap">{w("per_page")}</span>
          <Select value={String(perPage)} onValueChange={(v) => (setPerPage(Number(v)), setPage(1))} options={[10, 25, 50, 100].map((n) => ({ value: String(n), label: String(n) }))} aria-label={w("per_page")} />
          <Button size="sm" variant="outline" disabled={cur <= 1} onClick={() => setPage(cur - 1)} aria-label={w("previous")}>
            ‹
          </Button>
          <span className="whitespace-nowrap tabular-nums">
            {cur} / {pages}
          </span>
          <Button size="sm" variant="outline" disabled={cur >= pages} onClick={() => setPage(cur + 1)} aria-label={w("next")}>
            ›
          </Button>
        </div>
      </div>
    </div>
  );
}

function FromTo({ from, to }) {
  return from === to ? (
    <span>{to}</span>
  ) : (
    <span className="inline-flex flex-wrap items-center gap-1">
      <span className="text-foreground-muted">{from}</span>
      <ArrowRight className="size-3 text-foreground-muted" aria-hidden />
      <Pill t="info">{to}</Pill>
    </span>
  );
}

function DecisionCell({ w, r, appliedLabel }) {
  return (
    <div className="flex flex-col gap-0.5">
      <Pill t={DECISION_TONE[r.status]}>{w(`decision.${r.status}`)}</Pill>
      {r.status === "APPROVED" ? <span className="text-xs text-foreground-muted">{r.applied_at ? appliedLabel(r) : w("from_date", { date: day(r.effective_date) })}</span> : null}
      {r.decided_by ? <span className="text-xs whitespace-nowrap text-foreground-muted">{`${r.decided_by}, ${stamp(r.decided_at)}`}</span> : null}
      {r.decision_note ? <span className="text-xs text-foreground-muted">— {r.decision_note}</span> : null}
    </div>
  );
}

function DecisionDialog({ w, asking, busy, onCancel, onConfirm }) {
  const [note, setNote] = useState("");
  const approve = asking?.decision === "APPROVE";
  return (
    <Modal
      open={asking !== null}
      onOpenChange={(o) => !o && (setNote(""), onCancel())}
      title={asking?.title ?? ""}
      description={asking?.detail}
      footer={
        <>
          <Button variant="ghost" onClick={onCancel} disabled={busy}>
            {w("cancel")}
          </Button>
          <Button variant={approve ? "primary" : "danger"} loading={busy} onClick={() => (onConfirm(note), setNote(""))}>
            {approve ? <Check /> : <X />} {approve ? w("approve") : w("reject")}
          </Button>
        </>
      }
    >
      <Field label={w("f.note")}>
        <Textarea rows={3} value={note} onChange={(e) => setNote(e.target.value)} placeholder={approve ? w("ph.note_approve") : w("ph.note_reject")} aria-label={w("f.note")} />
      </Field>
    </Modal>
  );
}

function useDecisions(kind, act, setRows) {
  const w = useWords();
  const toast = useToastManager();
  const [asking, setAsking] = useState(null);
  const [busy, start] = useTransition();
  const decide = (note) =>
    start(async () => {
      const res = await act("mvDecide", kind, asking.ref, { decision: asking.decision, note });
      setAsking(null);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.decide"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
    });
  const withdraw = (ref) =>
    start(async () => {
      const res = await act("mvWithdraw", kind, ref);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
    });
  return { asking, setAsking, busy, decide, withdraw };
}

function ActionCell({ w, r, data, d, approveDetail, rejectDetail }) {
  if (r.status !== "PENDING") return null;
  return (
    <div className="flex flex-wrap gap-1">
      {data.can_approve ? (
        <>
          <Button size="sm" disabled={d.busy} onClick={() => d.setAsking({ ref: r.ref, decision: "APPROVE", title: w("approve_title", { ref: r.ref }), detail: approveDetail(r) })}>
            {w("approve")}
          </Button>
          <Button size="sm" variant="danger" disabled={d.busy} onClick={() => d.setAsking({ ref: r.ref, decision: "REJECT", title: w("reject_title", { ref: r.ref }), detail: rejectDetail(r) })}>
            {w("reject")}
          </Button>
        </>
      ) : null}
      {data.can_request ? (
        <Button size="sm" variant="ghost" disabled={d.busy} onClick={() => d.withdraw(r.ref)}>
          {w("withdraw")}
        </Button>
      ) : null}
    </div>
  );
}

function Promotion({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial.rows);
  const [emp, setEmp] = useState(null);
  const [form, setForm] = useState({ designation: "", grade: "", gross: "", date: initial.today, reason: "" });
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const d = useDecisions("promotions", act, setRows);
  const [f, setF, shown] = useFilter(rows, (r) => ({ status: r.status, date: r.effective_date, text: `${r.ref} ${r.code} ${r.name} ${r.old_designation ?? ""} ${r.new_designation ?? ""}` }));
  const pick = (e) => (setEmp(e), setForm((x) => ({ ...x, designation: e?.designation_id ?? "", grade: e?.grade_id ?? "", gross: "" })), setErrors({}));
  const send = () =>
    start(async () => {
      const res = await act("promoStore", { employee: emp?.code ?? "", new_designation_id: form.designation, new_grade_id: form.grade || null, new_gross: form.gross === "" ? null : Number(form.gross), effective_date: form.date, reason: form.reason });
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      setEmp(null);
      setForm({ designation: "", grade: "", gross: "", date: initial.today, reason: "" });
      setErrors({});
    });
  const count = (fn) => rows.filter(fn).length;
  const columns = [
    { key: "ref", label: w("col.ref_no"), render: (r) => <span className="font-mono text-xs font-semibold whitespace-nowrap">{r.ref}</span> },
    { key: "employee", label: w("col.employee"), render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="font-mono text-xs whitespace-nowrap text-foreground-muted">{r.code}</div></div>) },
    { key: "designation", label: w("col.designation"), render: (r) => <FromTo from={r.old_designation || "—"} to={r.new_designation} /> },
    { key: "grade", label: w("col.grade"), render: (r) => <FromTo from={r.old_grade || "—"} to={r.new_grade || "—"} /> },
    { key: "gross", label: w("col.gross_a"), right: true, render: (r) => (r.new_gross === null ? <span className="text-foreground-muted">{w("unchanged")}</span> : <FromTo from={taka(r.old_gross)} to={taka(r.new_gross)} />) },
    { key: "effective", label: w("col.effective"), render: (r) => <span className="whitespace-nowrap">{day(r.effective_date)}</span> },
    { key: "status", label: w("col.status"), render: (r) => <DecisionCell w={w} r={r} appliedLabel={() => w("in_force")} /> },
    { key: "action", label: w("col.action"), render: (r) => <ActionCell w={w} r={r} data={initial} d={d} approveDetail={(x) => w("promo_approve_detail", { who: `${x.code} ${x.name}`, to: x.new_designation, date: day(x.effective_date) })} rejectDetail={(x) => w("promo_reject_detail", { who: `${x.code} ${x.name}`, as: x.old_designation || w("as_they_are") })} /> },
  ];
  const exportCsv = () =>
    downloadCsv("promotions.csv", ["ref_no", "code", "name", "old_designation", "new_designation", "old_grade", "new_grade", "old_gross", "new_gross", "effective", "reason", "status", "decided_by"].map((k) => w(`csv.${k}`)), shown.map((r) => [r.ref, r.code, r.name, r.old_designation, r.new_designation, r.old_grade, r.new_grade, r.old_gross, r.new_gross ?? "", day(r.effective_date), r.reason, w(`decision.${r.status}`), r.decided_by]));
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Clock} label={w("tile.pending")} value={count((r) => r.status === "PENDING")} tone="warning" />
        <Stat icon={CalendarClock} label={w("tile.waiting")} value={count((r) => r.status === "APPROVED" && !r.applied_at)} />
        <Stat icon={BadgeCheck} label={w("tile.in_force")} value={count((r) => !!r.applied_at)} tone="good" />
        <Stat icon={XCircle} label={w("tile.rejected")} value={count((r) => r.status === "REJECTED")} tone="danger" />
      </div>
      <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        {initial.can_request ? (
          <section className={cn(card, "flex flex-col gap-4 p-5")}>
            <Head icon={TrendingUp} title={w("new_promotion")} sub={w("promotion_sub")} />
            <EmployeePicker w={w} act={act} employee={emp} onChange={pick} error={errors.employee} />
            {emp ? (
              <>
                <Field label={w("f.new_designation")} required error={errors.new_designation_id} hint={w("now", { v: emp.designation || w("none") })}>
                  <Select value={form.designation} onValueChange={(v) => setForm({ ...form, designation: v ?? "" })} options={initial.designations} placeholder={w("select")} aria-label={w("f.new_designation")} />
                </Field>
                <Field label={w("f.new_grade")} error={errors.new_grade_id} hint={w("now", { v: emp.grade || w("none") })}>
                  <Select value={form.grade} onValueChange={(v) => setForm({ ...form, grade: v ?? "" })} options={initial.grades} placeholder={w("select")} aria-label={w("f.new_grade")} />
                </Field>
                <Field label={w("f.new_gross")} error={errors.new_gross} hint={w("gross_hint", { now: taka(emp.gross_a), structure: emp.structure || w("the_structure") })}>
                  <div className="flex items-center gap-2">
                    <Input type="number" min="0" value={form.gross} onChange={(e) => setForm({ ...form, gross: e.target.value })} aria-label={w("f.new_gross")} />
                    <span className="text-xs text-foreground-muted">BDT</span>
                  </div>
                </Field>
                <Field label={w("f.effective_date")} required error={errors.effective_date}>
                  <Input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} aria-label={w("f.effective_date")} />
                </Field>
                <Field label={w("f.reason")} required error={errors.reason}>
                  <Textarea rows={3} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} aria-label={w("f.reason")} />
                </Field>
                <Button onClick={send} loading={pending} className="self-start">
                  <Send /> {w("send")}
                </Button>
              </>
            ) : (
              <p className="text-[13px] text-foreground-muted">{w("search_first_promotion")}</p>
            )}
          </section>
        ) : null}
        <section className={cn(card, "flex flex-col gap-4 p-5", !initial.can_request && "xl:col-span-2")}>
          <Head icon={BadgeCheck} title={w("promotion_requests")} sub={initial.can_approve ? w("approve_sub") : w("approval_for")} />
          <FilterBar w={w} f={f} onChange={setF} onExport={exportCsv} />
          <Requests w={w} title={w("promotion_requests")} rows={shown} columns={columns} empty={w("empty.promotion")} />
        </section>
      </div>
      <DecisionDialog w={w} asking={d.asking} busy={d.busy} onCancel={() => d.setAsking(null)} onConfirm={d.decide} />
    </div>
  );
}

function Increment({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial.rows);
  const [emp, setEmp] = useState(null);
  const [form, setForm] = useState({ mode: "NEW", value: "", date: initial.today, reason: "" });
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const d = useDecisions("increments", act, setRows);
  const [f, setF, shown] = useFilter(rows, (r) => ({ status: r.status, date: r.effective_date, text: `${r.ref} ${r.code} ${r.name} ${r.designation ?? ""}` }));
  const old = emp?.gross_a ?? 0;
  const v = Number(String(form.value).replace(/,/g, ""));
  const next = String(form.value).trim() && Number.isFinite(v) && v > 0 ? (form.mode === "NEW" ? Math.round(v * 100) / 100 : form.mode === "AMOUNT" ? Math.round((old + v) * 100) / 100 : Math.round(old * (1 + v / 100))) : null;
  const send = () =>
    start(async () => {
      const res = await act("incStore", { employee: emp?.code ?? "", mode: form.mode, value: form.value === "" ? null : Number(form.value), effective_date: form.date, reason: form.reason });
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      setEmp(null);
      setForm({ mode: "NEW", value: "", date: initial.today, reason: "" });
      setErrors({});
    });
  const applied = rows.filter((r) => !!r.applied_at);
  const columns = [
    { key: "ref", label: w("col.ref_no"), render: (r) => <span className="font-mono text-xs font-semibold whitespace-nowrap">{r.ref}</span> },
    { key: "employee", label: w("col.employee"), render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="text-xs text-foreground-muted"><span className="font-mono whitespace-nowrap">{r.code}</span>{r.designation ? ` · ${r.designation}` : ""}</div></div>) },
    { key: "old", label: w("col.old_salary"), right: true, render: (r) => taka(r.old_gross) },
    { key: "new", label: w("col.new_salary"), right: true, render: (r) => <span className="font-semibold">{taka(r.new_gross)}</span> },
    { key: "inc", label: w("col.increment"), right: true, render: (r) => <span className="whitespace-nowrap text-success">{`+${taka(r.increment)} (${r.percent}%)`}</span> },
    { key: "effective", label: w("col.effective"), render: (r) => <span className="whitespace-nowrap">{day(r.effective_date)}</span> },
    { key: "reason", label: w("col.reason"), render: (r) => <span className="line-clamp-2 text-xs">{r.reason}</span> },
    { key: "status", label: w("col.approval"), render: (r) => <DecisionCell w={w} r={r} appliedLabel={(x) => w("applied_at", { at: stamp(x.applied_at) })} /> },
    { key: "action", label: w("col.action"), render: (r) => <ActionCell w={w} r={r} data={initial} d={d} approveDetail={(x) => w("inc_approve_detail", { who: `${x.code} ${x.name}`, old: taka(x.old_gross), new: taka(x.new_gross), date: day(x.effective_date) })} rejectDetail={(x) => w("inc_reject_detail", { who: `${x.code} ${x.name}`, old: taka(x.old_gross) })} /> },
  ];
  const exportCsv = () =>
    downloadCsv("increments.csv", ["ref_no", "code", "name", "designation", "old_salary", "new_salary", "increment", "pct", "effective", "reason", "status", "decided_by", "applied"].map((k) => w(`csv.${k}`)), shown.map((r) => [r.ref, r.code, r.name, r.designation, r.old_gross, r.new_gross, r.increment, r.percent, day(r.effective_date), r.reason, w(`decision.${r.status}`), r.decided_by, stamp(r.applied_at)]));
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Clock} label={w("tile.pending")} value={rows.filter((r) => r.status === "PENDING").length} tone="warning" />
        <Stat icon={CalendarClock} label={w("tile.waiting")} value={rows.filter((r) => r.status === "APPROVED" && !r.applied_at).length} />
        <Stat icon={BadgeCheck} label={w("tile.applied")} value={applied.length} caption={w("a_month", { n: taka(applied.reduce((a, r) => a + r.increment, 0)) })} tone="good" />
        <Stat icon={XCircle} label={w("tile.rejected")} value={rows.filter((r) => r.status === "REJECTED").length} tone="danger" />
      </div>
      <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        {initial.can_request ? (
          <section className={cn(card, "flex flex-col gap-4 p-5")}>
            <Head icon={BadgeDollarSign} title={w("new_increment")} sub={w("increment_sub")} />
            <EmployeePicker w={w} act={act} employee={emp} onChange={(e) => (setEmp(e), setForm((x) => ({ ...x, value: "" })), setErrors({}))} error={errors.employee} />
            {emp ? (
              <>
                <div className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold">{w("give_as")}</span>
                  <Segments label={w("give_as")} value={form.mode} onChange={(m) => setForm({ ...form, mode: m })} items={["NEW", "AMOUNT", "PERCENT"].map((m) => [m, w(`mode.${m}`)])} />
                </div>
                <Field label={w(`value.${form.mode}`)} required error={errors.value}>
                  <div className="flex items-center gap-2">
                    <Input type="number" min="0" value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} aria-label={w(`value.${form.mode}`)} />
                    <span className="text-xs text-foreground-muted">{form.mode === "PERCENT" ? "%" : "BDT"}</span>
                  </div>
                </Field>
                <div className="flex items-center gap-3 rounded-lg bg-surface-muted px-3 py-2 text-sm">
                  <span className="flex flex-col">
                    <span className="text-xs text-foreground-muted">{w("old_salary")}</span>
                    <span className="font-semibold tabular-nums">{taka(old)}</span>
                  </span>
                  <ArrowRight className="size-4 text-foreground-muted" aria-hidden />
                  <span className="flex flex-col">
                    <span className="text-xs text-foreground-muted">{w("new_salary")}</span>
                    <span className="font-semibold tabular-nums">{next === null ? "—" : taka(next)}</span>
                  </span>
                </div>
                {next !== null && old > 0 ? <p className={cn("text-xs", next > old ? "text-success" : "text-danger")}>{next > old ? `+${taka(next - old)} (${Math.round(((next - old) / old) * 10000) / 100}%)` : w("above_old")}</p> : null}
                <Field label={w("f.effective_date")} required error={errors.effective_date}>
                  <Input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} aria-label={w("f.effective_date")} />
                </Field>
                <Field label={w("f.reason")} required error={errors.reason}>
                  <Textarea rows={3} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} aria-label={w("f.reason")} />
                </Field>
                <Button onClick={send} loading={pending} className="self-start">
                  <Send /> {w("send")}
                </Button>
              </>
            ) : (
              <p className="text-[13px] text-foreground-muted">{w("search_first_increment")}</p>
            )}
          </section>
        ) : null}
        <section className={cn(card, "flex flex-col gap-4 p-5", !initial.can_request && "xl:col-span-2")}>
          <Head icon={BadgeCheck} title={w("increment_list")} sub={initial.can_approve ? w("approve_sub") : w("approval_for")} />
          <FilterBar w={w} f={f} onChange={setF} onExport={exportCsv} />
          <Requests w={w} title={w("increment_requests")} rows={shown} columns={columns} empty={w("empty.increment")} />
        </section>
      </div>
      <DecisionDialog w={w} asking={d.asking} busy={d.busy} onCancel={() => d.setAsking(null)} onConfirm={d.decide} />
    </div>
  );
}

/* ── Re-Joining ──────────────────────────────────────────────────── */

const blankRejoin = () => ({ ref: null, period: "", employee: "", rejoin_date: "", doc_path: "", remarks: "", remarks_bn: "" });

function Rejoin({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const can = initial.can_manage;
  const [tab, setTab] = useState("records");
  const [rows, setRows] = useState(initial.rows);
  const [people, setPeople] = useState(initial.employees);
  const [form, setForm] = useState(blankRejoin);
  const [file, setFile] = useState(null);
  const [errors, setErrors] = useState({});
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const picker = useRef(null);
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));
  const editing = rows.find((r) => r.ref === form.ref);
  const summary = editing ? editing.summary : (people.find((p) => p.value === form.employee)?.summary ?? "");
  const upload = async (ref) => {
    const body = new FormData();
    body.append("upload", file);
    const res = await fetch(`/api/hr-payroll/rejoin-doc?ref=${encodeURIComponent(ref)}`, { method: "POST", body });
    const json = await res.json().catch(() => null);
    if (!res.ok) throw new Error(json?.errors?.upload?.[0] ?? json?.message ?? w("err.doc"));
    return json.data;
  };
  const save = () =>
    start(async () => {
      const body = { period: form.period || null, employee: form.employee, rejoin_date: form.rejoin_date, doc_path: file ? null : form.doc_path, remarks: form.remarks, remarks_bn: form.remarks_bn };
      const res = form.ref ? await act("rjUpdate", form.ref, body) : await act("rjStore", body);
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      let data = res.data;
      if (file) {
        try {
          data = { ...data, rows: (await upload(res.data.ref)).rows };
        } catch (e) {
          toast.add({ title: e.message, type: "danger" });
        }
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows(data.rows);
      if (data.employees) setPeople(data.employees);
      setForm(blankRejoin());
      setFile(null);
      setErrors({});
      setTab("records");
    });
  const remove = () =>
    start(async () => {
      const res = await act("rjDelete", asking.ref);
      setAsking(null);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      setPeople(res.data.employees);
    });
  const edit = (r) => {
    setForm({ ref: r.ref, period: r.period ?? "", employee: r.code, rejoin_date: r.rejoin_date, doc_path: r.doc_path ?? "", remarks: r.remarks ?? "", remarks_bn: r.remarks_bn ?? "" });
    setFile(null);
    setErrors({});
    setTab("new");
  };
  const columns = [
    { key: "code", label: w("col.code"), render: (r) => <span className="font-mono text-xs whitespace-nowrap">{r.code}</span> },
    { key: "name", label: w("col.name") },
    { key: "designation", label: w("col.designation") },
    { key: "rejoin_date", label: w("col.rejoin_date"), read: (r) => day(r.rejoin_date) },
    { key: "doc", label: w("col.doc"), read: (r) => r.doc_path ?? "", render: (r) => (r.doc_file_id ? <a className="inline-flex items-center gap-1 text-brand hover:underline" href={`/api/files/${r.doc_file_id}`} target="_blank" rel="noreferrer"><FileText className="size-3.5" />{r.doc_path || w("open_doc")}</a> : r.doc_path || "—") },
    { key: "remarks", label: w("col.remarks"), render: (r) => <span className="line-clamp-2 text-xs">{r.remarks || "—"}</span> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <Tabs tabs={[["records", w("tab.records"), rows.length, Table2], ...(can ? [["new", form.ref ? w("edit_rejoin", { code: form.employee }) : w("new_rejoin"), undefined, form.ref ? Pencil : Plus]] : [])]} active={tab} onPick={setTab} />
      {tab === "records" ? (
        rows.length ? (
          <Grid
            w={w}
            rows={rows}
            columns={columns}
            rowKey="ref"
            exportName="re-joins"
            empty={w("empty.match")}
            actions={can ? (r) => (
              <div className="flex justify-end gap-1">
                <Button size="sm" variant="ghost" onClick={() => edit(r)} aria-label={w("edit_aria", { ref: r.code })}>
                  <Pencil />
                </Button>
                <Button size="sm" variant="ghost" className="text-danger" onClick={() => setAsking(r)} aria-label={w("delete_aria", { ref: r.code })}>
                  <Trash2 />
                </Button>
              </div>
            ) : null}
          />
        ) : (
          <Empty title={w("empty.rejoin_title")} text={can ? w("empty.rejoin") : null} />
        )
      ) : null}
      {tab === "new" && can ? (
        <section className={cn(card, "flex flex-col gap-5 p-5")}>
          <Head title={form.ref ? w("edit_rejoin", { code: form.employee }) : w("new_rejoin")} sub={w("rejoin_sub")} />
          <div className="grid gap-4 md:grid-cols-2">
            <Field label={w("f.period")} error={errors.period} hint={w("hint.period")}>
              <Select value={form.period} onValueChange={(v) => set({ period: v ?? "" })} options={[{ value: "", label: w("select") }, ...initial.periods]} placeholder={w("select")} aria-label={w("f.period")} />
            </Field>
            <Field label={w("f.employee_code")} required error={errors.employee}>
              <Select value={form.employee} disabled={!!form.ref} onValueChange={(v) => set({ employee: v ?? "" })} options={form.ref ? [{ value: form.employee, label: form.employee }] : people} placeholder={w("select")} aria-label={w("f.employee_code")} />
            </Field>
            <Field label={w("f.name_position")} hint={w("hint.fetched")} className="md:col-span-2">
              <Input value={summary} readOnly aria-label={w("f.name_position")} />
            </Field>
            <Field label={w("f.rejoin_date")} required error={errors.rejoin_date}>
              <Input type="date" value={form.rejoin_date} onChange={(e) => set({ rejoin_date: e.target.value })} aria-label={w("f.rejoin_date")} />
            </Field>
            <Field label={w("f.doc")} hint={w("hint.doc")} error={errors.doc_path}>
              <div className="flex gap-2">
                <Input value={file ? file.name : form.doc_path} onChange={(e) => (setFile(null), set({ doc_path: e.target.value }))} placeholder={w("ph.doc")} aria-label={w("f.doc")} />
                <input ref={picker} type="file" accept=".pdf,.jpg,.jpeg,.png" className="hidden" onChange={(e) => setFile(e.target.files?.[0] ?? null)} aria-label={w("attach")} />
                <Button variant="outline" onClick={() => picker.current?.click()}>
                  <Paperclip /> {w("attach")}
                </Button>
              </div>
            </Field>
            <Field label={w("f.remarks")} className="md:col-span-2">
              <Textarea rows={3} value={form.remarks} onChange={(e) => set({ remarks: e.target.value })} aria-label={w("f.remarks")} />
            </Field>
            <Field label={w("f.remarks_bn")} className="md:col-span-2">
              <Textarea rows={3} value={form.remarks_bn} onChange={(e) => set({ remarks_bn: e.target.value })} aria-label={w("f.remarks_bn")} />
            </Field>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
            <p className="text-xs text-foreground-muted">{w("audit_note")}</p>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => (setForm(blankRejoin()), setFile(null), setErrors({}))}>
                <RotateCcw /> {w("reset")}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save /> {w("save_rejoin")}
              </Button>
            </div>
          </div>
        </section>
      ) : null}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={w("rejoin_delete_title", { code: asking?.code ?? "" })}
        description={w("rejoin_delete_text")}
        confirmLabel={w("delete")}
        cancelLabel={w("cancel")}
        destructive
        loading={pending}
        onConfirm={remove}
      />
    </div>
  );
}

export { Transfer, Promotion, Increment, Rejoin, Field, Head, Stat, Pill, Segments, Empty, EmployeePicker, EmployeeCard, useFilter, FilterBar, Requests, DecisionDialog, DecisionCell, downloadCsv, taka, day, stamp, firstError, errorsOf };
