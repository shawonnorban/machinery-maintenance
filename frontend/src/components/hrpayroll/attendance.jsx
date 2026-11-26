"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { Check, Download, FileText, Filter, Pencil, Printer, RefreshCw, RotateCcw, Save, Search, Trash2, Upload, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { useT } from "@/lib/i18n";
import { Grid, Tiles } from "@/components/settings/devices";
import { clock12 } from "@/lib/clock";

/*
 * HR & Payroll → Attendance & Shift (AnnotechERP 24/04), as Brickland lays
 * the six screens out:
 *
 * - Daily Attendance: search, Filter (department, section, line, status), the
 *   day or range, Export; the status tiles (a click filters); the days.
 * - Biometric Sync: devices, online, punches today and the fingerprint
 *   counts; the terminals with Import file and Sync now; the day's raw punches.
 * - Missing Punch: a date range and place filters; days with IN or OUT
 *   missing, each with Correct; the corrections, approved or rejected.
 * - Attendance Correction: one employee's month on one chart — status, IN,
 *   OUT, late and OT corrected, a past day entered, a day removed.
 * - Monthly Attendance Summary: the month-end job card summary for a
 *   department or section; Excel, PDF, Print.
 * - Shift Roster: Individual, Multiple Employee, Line-wise or Weekly Rotation,
 *   with the weekly off days; the current roster.
 *
 * Nothing reloads; the days are worked out from the punches as they are looked at.
 */

const card = "rounded-xl border border-border bg-surface";
const STATUSES = ["PRESENT", "ABSENT", "LATE", "EARLY_OUT", "LEAVE", "MISSING_PUNCH", "HOLIDAY", "WEEKLY_OFF"];
const DAY_STATUSES = ["PRESENT", "ABSENT", "LEAVE", "MISSING_PUNCH", "HOLIDAY", "WEEKLY_OFF"];
const WEEK = ["SAT", "SUN", "MON", "TUE", "WED", "THU", "FRI"];
const MONTHS = Array.from({ length: 12 }, (_, i) => i + 1);
const tone = { PRESENT: "good", ABSENT: "danger", LEAVE: "info", MISSING_PUNCH: "warning", HOLIDAY: "neutral", WEEKLY_OFF: "neutral" };
const num = (v) => (v === null || v === undefined ? "—" : Number(v).toFixed(2));
const at = (iso) => new Date(/(Z|[+-]\d\d:?\d\d)$/.test(iso) ? iso : `${iso.replace(" ", "T")}+06:00`);

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`att.${k}`, p);
}

function useNow() {
  const [now, setNow] = useState(null);
  useEffect(() => setNow(Date.now()), []);
  return now;
}

function ago(w, iso, now) {
  if (!iso) return "—";
  if (!now) return at(iso).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "short", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase());
  const s = Math.max(0, Math.round((now - at(iso).getTime()) / 1000));
  if (s < 60) return w("ago.s", { n: s });
  if (s < 3600) return w("ago.m", { n: Math.round(s / 60) });
  if (s < 86400) return w("ago.h", { n: Math.round(s / 3600) });
  return w("ago.d", { n: Math.round(s / 86400) });
}

const fail = (toast, res) => toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "", type: "danger" });
const clean = (q) => Object.fromEntries(Object.entries(q).filter(([, v]) => v !== "" && v !== null && v !== undefined && v !== false));

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

/** Department, section, line (each narrowing the next) — and the employee where asked. */
function PlaceFilters({ w, options, q, set, employee = false }) {
  const sections = options.sections.filter((s) => !q.department || s.department_id === q.department);
  const lines = options.lines.filter((l) => (q.section ? l.section_id === q.section : q.department ? l.department_id === q.department : true));
  const all = { value: "", label: w("all") };
  return (
    <>
      <Field label={w("f.department")}>
        <Select value={q.department ?? ""} onValueChange={(v) => set({ department: v ?? "", section: "", line: "" })} options={[all, ...options.departments]} placeholder={w("all")} aria-label={w("f.department")} />
      </Field>
      <Field label={w("f.section")}>
        <Select value={q.section ?? ""} onValueChange={(v) => set({ section: v ?? "", line: "" })} options={[all, ...sections]} placeholder={w("all")} aria-label={w("f.section")} />
      </Field>
      <Field label={w("f.line")}>
        <Select value={q.line ?? ""} onValueChange={(v) => set({ line: v ?? "" })} options={[all, ...lines]} placeholder={w("all")} aria-label={w("f.line")} />
      </Field>
      {employee ? (
        <Field label={w("f.employee")}>
          <Select value={q.employee ?? ""} onValueChange={(v) => set({ employee: v ?? "" })} options={[all, ...options.employees]} placeholder={w("all")} aria-label={w("f.employee")} />
        </Field>
      ) : null}
    </>
  );
}

function statusCell(w, r) {
  return (
    <div className="flex flex-wrap gap-1">
      <Pill t={tone[r.status]}>{w(`status.${r.status}`)}</Pill>
      {r.is_late ? <Pill t="warning">{w("status.LATE")}</Pill> : null}
      {r.is_early_out ? <Pill t="warning">{w("status.EARLY_OUT")}</Pill> : null}
      {r.corrected ? <Pill t="info">{w("corrected")}</Pill> : null}
    </div>
  );
}

const statusText = (w, r) => [w(`status.${r.status}`), r.is_late ? w("status.LATE") : null, r.is_early_out ? w("status.EARLY_OUT") : null].filter(Boolean).join(" · ");

/* ── Daily Attendance ─────────────────────────────────────────────── */

function DailyAttendance({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [data, setData] = useState(initial);
  const [q, setQ] = useState({ range: "today", from: options.today, to: options.today, department: "", section: "", line: "", status: "" });
  const [open, setOpen] = useState(false);
  const [pending, start] = useTransition();
  const query = (x) => clean(x.range === "custom" ? { ...x, range: "" } : { ...x, from: "", to: "" });
  const load = (x = q) =>
    start(async () => {
      const res = await act("attDaily", query(x));
      if (res?.status === "success") setData(res.data);
      else fail(toast, res);
    });
  const set = (patch, reload = false) => {
    const next = { ...q, ...patch };
    setQ(next);
    if (reload) load(next);
  };
  const shown = data.rows.filter((r) => !q.status || (q.status === "LATE" ? r.is_late : q.status === "EARLY_OUT" ? r.is_early_out : r.status === q.status));
  const columns = [
    { key: "employee", label: w("col.employee"), read: (r) => `${r.name} ${r.code} ${r.section ?? ""}`, render: (r) => (
      <div>
        <div className="font-semibold">{r.name}</div>
        <div className="font-mono text-xs text-foreground-muted">
          {r.code}
          {r.section ? ` · ${r.section}` : ""}
        </div>
      </div>
    ) },
    { key: "date", label: w("col.date") },
    { key: "shift", label: w("col.shift") },
    { key: "in", label: w("col.in"), mono: true },
    { key: "out", label: w("col.out"), mono: true },
    { key: "worked", label: w("col.worked"), right: true, read: (r) => num(r.worked) },
    { key: "late", label: w("col.late"), right: true, read: (r) => (r.late ? r.late : "—") },
    { key: "early", label: w("col.early"), right: true, read: (r) => (r.early ? r.early : "—") },
    { key: "ot", label: w("col.ot"), right: true, read: (r) => num(r.ot) },
    { key: "status", label: w("col.status"), read: (r) => statusText(w, r), render: (r) => statusCell(w, r) },
  ];
  const exportUrl = (fmt) => `/api/hr-payroll/attendance-export/daily/${fmt}?${new URLSearchParams(query(q))}`;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-wrap items-end gap-3 p-4")}>
        <Field label={w("f.range")} className="w-48">
          <Select value={q.range} onValueChange={(v) => set({ range: v ?? "today" }, v !== "custom")} options={["today", "yesterday", "week", "last7", "month", "custom"].map((v) => ({ value: v, label: w(`range.${v}`) }))} aria-label={w("f.range")} />
        </Field>
        {q.range === "custom" ? (
          <>
            <Field label={w("f.from")}>
              <Input type="date" value={q.from} onChange={(e) => set({ from: e.target.value })} aria-label={w("f.from")} />
            </Field>
            <Field label={w("f.to")}>
              <Input type="date" value={q.to} onChange={(e) => set({ to: e.target.value })} aria-label={w("f.to")} />
            </Field>
            <Button onClick={() => load()} loading={pending}>
              <Search /> {w("show")}
            </Button>
          </>
        ) : null}
        <Button variant="outline" onClick={() => setOpen(!open)} aria-expanded={open}>
          <Filter /> {w("filter")}
        </Button>
        <div className="ml-auto flex gap-2">
          <a className="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted" href={exportUrl("xlsx")}>
            <Download className="size-4" /> {w("export_excel")}
          </a>
          <a className="inline-flex items-center gap-2 rounded-md bg-brand px-3 py-2 text-sm font-semibold text-brand-foreground hover:opacity-90" href={exportUrl("pdf")} target="_blank" rel="noreferrer">
            <FileText className="size-4" /> {w("export_pdf")}
          </a>
        </div>
        {open ? (
          <div className="grid w-full gap-4 border-t border-border pt-4 md:grid-cols-4">
            <PlaceFilters w={w} options={options} q={q} set={(p) => set(p)} />
            <div className="flex items-end gap-2">
              <Button onClick={() => load()} loading={pending}>
                <Search /> {w("apply")}
              </Button>
              <Button variant="outline" onClick={() => set({ department: "", section: "", line: "", status: "" }, true)}>
                <RotateCcw /> {w("reset")}
              </Button>
            </div>
          </div>
        ) : null}
      </section>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        {STATUSES.map((s) => (
          <button key={s} type="button" onClick={() => set({ status: q.status === s ? "" : s })} aria-pressed={q.status === s} className={cn(card, "px-4 py-3 text-left transition-colors hover:bg-surface-muted", q.status === s && "ring-2 ring-brand")}>
            <p className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{w(`status.${s}`)}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{data.counts[s] ?? 0}</p>
          </button>
        ))}
      </div>
      <p className="text-[13px] text-foreground-muted">{w("daily_caption", { from: data.from, to: data.to, n: shown.length })}</p>
      <Grid w={w} rows={shown} columns={columns} rowKey="id" exportName={`daily-attendance-${data.from}`} empty={w("empty.daily")} />
    </div>
  );
}

/* ── Biometric Sync ───────────────────────────────────────────────── */

function BiometricSync({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const now = useNow();
  const [data, setData] = useState(initial);
  const [q, setQ] = useState({ date: initial.date, unmapped: false });
  const [importing, setImporting] = useState(false);
  const [file, setFile] = useState({ device: options.devices[0]?.value ?? "", upload: null, error: null });
  const fileRef = useRef(null);
  const [pending, start] = useTransition();
  const load = (x = q) =>
    start(async () => {
      const res = await act("attSync", clean({ date: x.date, unmapped: x.unmapped ? 1 : "" }));
      if (res?.status === "success") setData(res.data);
      else fail(toast, res);
    });
  const t = data.tiles;
  const deviceColumns = [
    { key: "name", label: w("col.device"), read: (d) => `${d.name} ${d.code}`, render: (d) => (
      <div>
        <div className="font-semibold">{d.name}</div>
        <div className="font-mono text-xs text-foreground-muted">
          {d.code} · {w(`protocol.${d.protocol}`)}
          {d.ip_address ? ` · ${d.ip_address}` : ""}
        </div>
      </div>
    ) },
    { key: "location", label: w("col.location") },
    { key: "status", label: w("col.device_status"), read: (d) => w(d.online ? "online" : "offline"), render: (d) => <Pill t={d.online ? "good" : "danger"}>{w(d.online ? "online" : "offline")}</Pill> },
    { key: "last_seen", label: w("col.last_seen"), read: (d) => ago(w, d.last_seen, now) },
    { key: "last_sync", label: w("col.last_sync"), read: (d) => ago(w, d.last_sync, now) },
    { key: "punches", label: w("col.punches_today"), right: true },
    { key: "users", label: w("col.users"), right: true },
    { key: "fingers", label: w("col.fingers"), right: true },
  ];
  const punchColumns = [
    { key: "time", label: w("col.time"), mono: true },
    { key: "pin", label: w("col.pin"), mono: true },
    { key: "employee", label: w("col.employee"), read: (p) => p.employee ?? w("unmapped") },
    { key: "device", label: w("col.device"), mono: true },
  ];
  const upload = () =>
    start(async () => {
      if (!file.upload) return setFile((f) => ({ ...f, error: w("v.file") }));
      const body = new FormData();
      body.append("device", file.device);
      body.append("upload", file.upload);
      const res = await fetch("/api/hr-payroll/attendance-import", { method: "POST", body });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) return setFile((f) => ({ ...f, error: Object.values(json?.errors ?? {})[0]?.[0] ?? json?.message ?? w("v.file") }));
      toast.add({ title: json.data.message, type: "success" });
      setImporting(false);
      setFile({ device: file.device, upload: null, error: null });
      load();
    });
  return (
    <div className="flex flex-col gap-4">
      <Tiles
        items={[
          [w("tile.devices"), t.devices],
          [w("tile.online"), t.online, "good"],
          [w("tile.punches_today"), t.punches.toLocaleString("en-US"), "info"],
          [w("tile.registered"), t.registered, "good"],
          [w("tile.pending"), t.pending, t.pending ? "warning" : undefined],
          [w("tile.not_registered"), t.not_registered],
        ]}
      />
      <section className={cn(card, "flex flex-wrap items-start justify-between gap-3 px-5 py-4")}>
        <div>
          <h3 className="text-[15px] font-semibold">{w("terminals")}</h3>
          <p className="text-[13px] text-foreground-muted">{w("terminals_note")}</p>
        </div>
        {canManage ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setImporting(true)} disabled={!options.devices.length}>
              <Upload /> {w("import_file")}
            </Button>
            <Button
              loading={pending}
              onClick={() =>
                start(async () => {
                  const res = await act("attSyncNow");
                  if (res?.status !== "success") return fail(toast, res);
                  toast.add({ title: res.data.message, type: "success" });
                  const fresh = await act("attSync", clean({ date: q.date, unmapped: q.unmapped ? 1 : "" }));
                  if (fresh?.status === "success") setData(fresh.data);
                })
              }
            >
              <RefreshCw /> {w("sync_now")}
            </Button>
          </div>
        ) : null}
      </section>
      <Grid w={w} rows={data.devices} columns={deviceColumns} rowKey="code" exportName="attendance-terminals" empty={w("empty.devices")} />
      <section className={cn(card, "flex flex-wrap items-end gap-4 px-5 py-4")}>
        <div className="mr-auto">
          <h3 className="text-[15px] font-semibold">{w("raw_log", { date: data.date })}</h3>
          <p className="text-[13px] text-foreground-muted">{w("raw_note", { n: t.punches, u: data.unmapped })}</p>
        </div>
        <Field label={w("f.date")}>
          <Input type="date" value={q.date} max={options.today} onChange={(e) => (setQ({ ...q, date: e.target.value }), load({ ...q, date: e.target.value }))} aria-label={w("f.date")} />
        </Field>
        <label className="flex items-center gap-2 pb-2 text-sm font-semibold">
          <Checkbox checked={q.unmapped} onCheckedChange={(v) => (setQ({ ...q, unmapped: v === true }), load({ ...q, unmapped: v === true }))} aria-label={w("f.unmapped")} /> {w("f.unmapped")}
        </label>
      </section>
      <Grid w={w} rows={data.punches} columns={punchColumns} rowKey="id" exportName={`raw-punches-${data.date}`} empty={w("empty.punches")} />
      <Modal open={importing} onOpenChange={setImporting} title={w("import_file")} description={w("import_note")}>
        <div className="flex flex-col gap-4">
          <Field label={w("f.device")} required>
            <Select value={file.device} onValueChange={(v) => setFile({ ...file, device: v ?? "" })} options={options.devices} aria-label={w("f.device")} />
          </Field>
          <Field label={w("f.file")} required error={file.error}>
            <div className="flex gap-2">
              <Input readOnly value={file.upload?.name ?? ""} placeholder={w("ph.file")} aria-label={w("f.file")} />
              <input ref={fileRef} type="file" accept=".dat,.txt,.csv" className="hidden" onChange={(e) => (setFile({ ...file, upload: e.target.files?.[0] ?? null, error: null }), (e.target.value = ""))} />
              <Button variant="outline" onClick={() => fileRef.current?.click()}>
                {w("choose")}
              </Button>
            </div>
          </Field>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setImporting(false)}>
              {w("cancel")}
            </Button>
            <Button onClick={upload} loading={pending}>
              <Upload /> {w("import")}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

/* ── Missing Punch ────────────────────────────────────────────────── */

function MissingPunch({ initial, options, canManage, canApprove, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [data, setData] = useState(initial);
  const [q, setQ] = useState({ from: initial.from, to: initial.to, department: "", section: "", line: "", employee: "" });
  const [fixing, setFixing] = useState(null);
  const [pending, start] = useTransition();
  const load = (x = q) =>
    start(async () => {
      const res = await act("attMissing", clean(x));
      if (res?.status === "success") setData(res.data);
      else fail(toast, res);
    });
  const decide = (c, decision) =>
    start(async () => {
      const res = await act("attDecide", c.id, decision);
      if (res?.status !== "success") return fail(toast, res);
      toast.add({ title: res.data.message, type: "success" });
      const fresh = await act("attMissing", clean(q));
      if (fresh?.status === "success") setData(fresh.data);
    });
  const missingColumns = [
    { key: "employee", label: w("col.employee"), read: (r) => `${r.name} ${r.code}`, render: (r) => (
      <div>
        <div className="font-semibold">{r.name}</div>
        <div className="font-mono text-xs text-foreground-muted">{r.code}</div>
      </div>
    ) },
    { key: "date", label: w("col.date") },
    { key: "line", label: w("col.line") },
    { key: "shift", label: w("col.shift") },
    { key: "in", label: w("col.in"), mono: true, render: (r) => (r.in ? <span className="font-mono">{clock12(r.in)}</span> : <span className="font-semibold text-danger">{w("missing")}</span>) },
    { key: "out", label: w("col.out"), mono: true, render: (r) => (r.out ? <span className="font-mono">{clock12(r.out)}</span> : <span className="font-semibold text-danger">{w("missing")}</span>) },
    { key: "status", label: w("col.status"), read: (r) => (r.pending ? w("awaiting") : w("status.MISSING_PUNCH")), render: (r) => (r.pending ? <Pill t="info">{w("awaiting")}</Pill> : <Pill t="warning">{w("status.MISSING_PUNCH")}</Pill>) },
  ];
  const correctionColumns = [
    { key: "employee", label: w("col.employee"), read: (c) => `${c.name} ${c.code}`, render: (c) => (
      <div>
        <div className="font-semibold">{c.name}</div>
        <div className="font-mono text-xs text-foreground-muted">{c.code}</div>
      </div>
    ) },
    { key: "date", label: w("col.date") },
    { key: "old", label: w("col.old"), mono: true },
    { key: "correct_in", label: w("col.correct_in"), mono: true },
    { key: "correct_out", label: w("col.correct_out"), mono: true },
    { key: "reason", label: w("col.reason") },
    { key: "requested_by", label: w("col.requested_by") },
    { key: "approved_by", label: w("col.approved_by") },
    { key: "decided_at", label: w("col.decided_at") },
    { key: "status", label: w("col.status"), read: (c) => w(`corr.${c.status}`), render: (c) => <Pill t={c.status === "APPROVED" ? "good" : c.status === "REJECTED" ? "danger" : "warning"}>{w(`corr.${c.status}`)}</Pill> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h3 className="text-[15px] font-semibold">{w("missing_filters")}</h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("missing_note", { n: data.rows.length, p: data.pending })}</p>
        <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-7">
          <Field label={w("f.from")}>
            <Input type="date" value={q.from} onChange={(e) => setQ({ ...q, from: e.target.value })} aria-label={w("f.from")} />
          </Field>
          <Field label={w("f.to")}>
            <Input type="date" value={q.to} onChange={(e) => setQ({ ...q, to: e.target.value })} aria-label={w("f.to")} />
          </Field>
          <PlaceFilters w={w} options={options} q={q} set={(p) => setQ({ ...q, ...p })} employee />
          <div className="flex items-end">
            <Button onClick={() => load()} loading={pending}>
              <Search /> {w("show")}
            </Button>
          </div>
        </div>
      </section>
      <div>
        <h3 className="mb-2 text-[15px] font-semibold">{w("missing_title")}</h3>
        <Grid
          w={w}
          rows={data.rows}
          columns={missingColumns}
          rowKey="id"
          exportName="missing-punches"
          empty={w("empty.missing")}
          actions={canManage ? (r) => (r.pending ? null : (
            <Button size="sm" variant="outline" onClick={() => setFixing({ ...r, correct_in: r.in ?? "", correct_out: r.out ?? "", reason: "", errors: {} })} aria-label={w("correct_for", { code: r.code, date: r.date })}>
              <Pencil /> {w("correct")}
            </Button>
          )) : null}
        />
      </div>
      <div>
        <h3 className="mb-2 text-[15px] font-semibold">{w("corrections")}</h3>
        <Grid
          w={w}
          rows={data.corrections}
          columns={correctionColumns}
          rowKey="id"
          exportName="missing-punch-corrections"
          empty={w("empty.corrections")}
          actions={canApprove ? (c) => (c.status === "PENDING" ? (
            <div className="flex justify-end gap-1.5">
              <Button size="sm" variant="outline" onClick={() => decide(c, "APPROVE")} aria-label={w("approve_for", { code: c.code })}>
                <Check className="text-success" /> {w("approve")}
              </Button>
              <Button size="sm" variant="outline" onClick={() => decide(c, "REJECT")} aria-label={w("reject_for", { code: c.code })}>
                <X className="text-danger" /> {w("reject")}
              </Button>
            </div>
          ) : null) : null}
        />
      </div>
      {fixing ? (
        <Modal open onOpenChange={(o) => !o && setFixing(null)} title={w("correct_title", { code: fixing.code, date: fixing.date })} description={`${fixing.name} · ${fixing.shift ?? ""}`}>
          <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={w("col.correct_in")} error={fixing.errors.correct_in?.[0]}>
                <Input type="time" value={fixing.correct_in} onChange={(e) => setFixing({ ...fixing, correct_in: e.target.value })} aria-label={w("col.correct_in")} />
              </Field>
              <Field label={w("col.correct_out")} error={fixing.errors.correct_out?.[0]}>
                <Input type="time" value={fixing.correct_out} onChange={(e) => setFixing({ ...fixing, correct_out: e.target.value })} aria-label={w("col.correct_out")} />
              </Field>
            </div>
            <Field label={w("col.reason")} required error={fixing.errors.reason?.[0]}>
              <Textarea rows={3} value={fixing.reason} onChange={(e) => setFixing({ ...fixing, reason: e.target.value })} aria-label={w("col.reason")} />
            </Field>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setFixing(null)}>
                {w("cancel")}
              </Button>
              <Button
                loading={pending}
                onClick={() =>
                  start(async () => {
                    const res = await act("attRequest", { employee: fixing.code, date: fixing.date, correct_in: fixing.correct_in || null, correct_out: fixing.correct_out || null, reason: fixing.reason });
                    if (res?.status !== "success") return setFixing({ ...fixing, errors: res?.errors ?? { reason: [res?.message ?? ""] } });
                    toast.add({ title: res.data.message, type: "success" });
                    setFixing(null);
                    const fresh = await act("attMissing", clean(q));
                    if (fresh?.status === "success") setData(fresh.data);
                  })
                }
              >
                <Save /> {w("request_correction")}
              </Button>
            </div>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}

/* ── Attendance Correction ────────────────────────────────────────── */

function AttendanceCorrection({ options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const blank = { employee: "", month: String(options.month), year: String(options.year) };
  const [q, setQ] = useState(blank);
  const [chart, setChart] = useState(null);
  const [edits, setEdits] = useState({});
  const [asking, setAsking] = useState(null);
  const [error, setError] = useState(null);
  const [pending, start] = useTransition();
  const load = (x = q) =>
    start(async () => {
      if (!x.employee) return setError(w("v.employee"));
      const res = await act("attMonth", { employee: x.employee, month: x.month, year: x.year });
      if (res?.status !== "success") return setError(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "");
      setError(null);
      setChart(res.data);
      setEdits({});
    });
  const row = (r) => ({ status: r.status ?? "PRESENT", in: r.in ?? "", out: r.out ?? "", late: r.late ? String(r.late) : "", ot: r.ot ? String(r.ot) : "", remarks: r.remarks ?? "", ...(edits[r.date] ?? {}) });
  const edit = (date, k, v) => setEdits((e) => ({ ...e, [date]: { ...(e[date] ?? {}), [k]: v } }));
  const save = (r) =>
    start(async () => {
      const v = row(r);
      const res = await act("attSaveDay", { employee: q.employee, date: r.date, status: v.status, in: v.in || null, out: v.out || null, late: v.late === "" ? null : Number(v.late), ot: v.ot === "" ? null : Number(v.ot), remarks: v.remarks || null });
      if (res?.status !== "success") return fail(toast, res);
      toast.add({ title: res.data.message, type: "success" });
      load();
    });
  const years = Array.from({ length: 5 }, (_, i) => String(options.year - 3 + i));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "grid gap-4 p-5 md:grid-cols-[2fr_1fr_1fr_auto_auto]")}>
        <Field label={w("f.employee")} error={error}>
          <Select value={q.employee} onValueChange={(v) => (setQ({ ...q, employee: v ?? "" }), setError(null))} options={options.employees} placeholder={w("ph.employee")} aria-label={w("f.employee")} />
        </Field>
        <Field label={w("f.month")}>
          <Select value={q.month} onValueChange={(v) => setQ({ ...q, month: v ?? q.month })} options={MONTHS.map((m) => ({ value: String(m), label: w(`months.${m}`) }))} aria-label={w("f.month")} />
        </Field>
        <Field label={w("f.year")}>
          <Select value={q.year} onValueChange={(v) => setQ({ ...q, year: v ?? q.year })} options={years.map((y) => ({ value: y, label: y }))} aria-label={w("f.year")} />
        </Field>
        <div className="flex items-end">
          <Button onClick={() => load()} loading={pending}>
            <Search /> {w("search")}
          </Button>
        </div>
        <div className="flex items-end">
          <Button variant="outline" onClick={() => (setQ(blank), setChart(null), setError(null))}>
            <RotateCcw /> {w("reset")}
          </Button>
        </div>
      </section>
      {!chart ? (
        <section className={cn(card, "px-5 py-10 text-center text-sm text-foreground-muted")}>{w("choose_month")}</section>
      ) : (
        <section className={card}>
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
            <div>
              <h3 className="text-[15px] font-semibold">
                {chart.employee.name} <span className="font-mono text-xs text-foreground-muted">{chart.employee.code}</span>
              </h3>
              <p className="text-[13px] text-foreground-muted">{w("month_caption", { month: w(`months.${Number(q.month)}`), year: q.year })}</p>
            </div>
            <div className="flex flex-wrap gap-2 text-xs">
              {[
                ["present", "good"],
                ["absent", "danger"],
                ["late", "warning"],
                ["missing", "warning"],
                ["leave", "info"],
                ["off", "neutral"],
              ].map(([k, t]) => (
                <Pill key={k} t={t}>
                  {w(`tot.${k}`)}: {chart.totals[k]}
                </Pill>
              ))}
              <Pill t="info">
                {w("tot.ot")}: {num(chart.totals.ot)}
              </Pill>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1200px] text-sm">
              <thead>
                <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                  {["date", "day", "shift", "status", "in", "out", "worked", "late", "ot", "remarks"].map((k) => (
                    <th key={k} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                      {w(`mcol.${k}`)}
                    </th>
                  ))}
                  {canManage ? <th className="px-3 py-2.5 text-right font-semibold">{w("col.action")}</th> : null}
                </tr>
              </thead>
              <tbody>
                {chart.rows.map((r) => {
                  const v = row(r);
                  const off = r.future || !canManage;
                  return (
                    <tr key={r.date} className={cn("border-b border-border last:border-0", ["SAT", "FRI"].includes(r.day) && "bg-surface-muted/50", r.future && "opacity-50")}>
                      <td className="px-3 py-1.5 font-mono text-xs whitespace-nowrap">{r.date}</td>
                      <td className="px-3 py-1.5 text-xs">{w(`days.${r.day}`)}</td>
                      <td className="px-3 py-1.5 text-xs whitespace-nowrap">{r.shift ?? "—"}</td>
                      <td className="px-2 py-1.5">
                        {off ? (r.status ? statusCell(w, r) : "—") : (
                          <div className="flex items-center gap-1">
                            <Select value={v.status} onValueChange={(x) => edit(r.date, "status", x ?? "PRESENT")} options={DAY_STATUSES.map((s) => ({ value: s, label: w(`status.${s}`) }))} aria-label={w("status_for", { date: r.date })} />
                            {r.corrected ? <Pill t="info">{w("corrected")}</Pill> : null}
                          </div>
                        )}
                      </td>
                      {["in", "out"].map((k) => (
                        <td key={k} className="px-2 py-1.5">
                          {off ? <span className="font-mono">{r[k] ?? "—"}</span> : <Input type="time" className="h-8 w-28" value={v[k]} onChange={(e) => edit(r.date, k, e.target.value)} aria-label={w(`${k}_for`, { date: r.date })} />}
                        </td>
                      ))}
                      <td className="px-3 py-1.5 text-right tabular-nums">{num(r.worked)}</td>
                      <td className="px-2 py-1.5">{off ? (r.late || "—") : <Input type="number" min={0} className="h-8 w-20" value={v.late} onChange={(e) => edit(r.date, "late", e.target.value)} aria-label={w("late_for", { date: r.date })} />}</td>
                      <td className="px-2 py-1.5">{off ? num(r.ot) : <Input type="number" min={0} step="0.5" className="h-8 w-20" value={v.ot} onChange={(e) => edit(r.date, "ot", e.target.value)} aria-label={w("ot_for", { date: r.date })} />}</td>
                      <td className="px-2 py-1.5">{off ? (r.remarks ?? "") : <Input className="h-8 min-w-40" value={v.remarks} maxLength={300} onChange={(e) => edit(r.date, "remarks", e.target.value)} aria-label={w("remarks_for", { date: r.date })} />}</td>
                      {canManage ? (
                        <td className="px-2 py-1.5 text-right whitespace-nowrap">
                          {!r.future ? (
                            <>
                              <Button size="sm" variant={edits[r.date] ? "primary" : "outline"} onClick={() => save(r)} disabled={pending} aria-label={w("save_for", { date: r.date })}>
                                <Save />
                              </Button>{" "}
                              {r.exists ? (
                                <Button size="sm" variant="outline" onClick={() => setAsking(r)} disabled={pending} aria-label={w("delete_for", { date: r.date })}>
                                  <Trash2 className="text-danger" />
                                </Button>
                              ) : null}
                            </>
                          ) : null}
                        </td>
                      ) : null}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{w("correction_note")}</p>
        </section>
      )}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={w("delete_title", { date: asking?.date ?? "" })}
        description={w("delete_confirm")}
        confirmLabel={w("delete")}
        cancelLabel={w("keep")}
        destructive
        loading={pending}
        onConfirm={() =>
          start(async () => {
            const res = await act("attDeleteDay", { employee: q.employee, date: asking.date });
            setAsking(null);
            if (res?.status !== "success") return fail(toast, res);
            toast.add({ title: res.data.message, type: "success" });
            load();
          })
        }
      />
    </div>
  );
}

/* ── Monthly Attendance Summary ───────────────────────────────────── */

function MonthlySummary({ options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const blank = { month: String(options.month), year: String(options.year), department: "", section: "" };
  const [q, setQ] = useState(blank);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, start] = useTransition();
  const years = Array.from({ length: 5 }, (_, i) => String(options.year - 3 + i));
  const sections = options.sections.filter((s) => !q.department || s.department_id === q.department);
  const query = () => new URLSearchParams(clean(q)).toString();
  const generate = () =>
    start(async () => {
      if (!q.department) return setError(w("v.department"));
      const res = await act("attSummary", clean(q));
      if (res?.status !== "success") return setError(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? ""), fail(toast, res);
      setError(null);
      setData(res.data);
    });
  const print = () =>
    data &&
    printDocument({
      title: w("summary_title"),
      subtitle: `${data.label} · ${data.department}${data.section ? ` · ${data.section}` : ""}`,
      orientation: "landscape",
      sections: [
        {
          columns: [{ label: w("col.code") }, { label: w("col.employee") }, ...Array.from({ length: data.days }, (_, i) => ({ label: String(i + 1), align: "center" })), ...["present", "absent", "leave", "off", "ot"].map((k) => ({ label: w(`sum.${k}`), align: "right" }))],
          rows: data.rows.map((r) => [r.code, r.name, ...r.cells.map((c) => c.mark + (c.ot ? `\n${c.ot}` : "")), r.present, r.absent, r.leave, r.off, num(r.ot)]),
        },
      ],
      notes: [w("legend")],
      signatures: true,
    });
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 md:grid-cols-4">
          <Field label={w("f.month")}>
            <Select value={q.month} onValueChange={(v) => setQ({ ...q, month: v ?? q.month })} options={MONTHS.map((m) => ({ value: String(m), label: w(`months.${m}`) }))} aria-label={w("f.month")} />
          </Field>
          <Field label={w("f.year")}>
            <Select value={q.year} onValueChange={(v) => setQ({ ...q, year: v ?? q.year })} options={years.map((y) => ({ value: y, label: y }))} aria-label={w("f.year")} />
          </Field>
          <Field label={w("f.department")} required error={error}>
            <Select value={q.department} onValueChange={(v) => (setQ({ ...q, department: v ?? "", section: "" }), setError(null))} options={options.departments} placeholder={w("ph.department")} aria-label={w("f.department")} />
          </Field>
          <Field label={w("f.section")} hint={q.department && !sections.length ? w("no_sections") : null}>
            <Select value={q.section} onValueChange={(v) => setQ({ ...q, section: v ?? "" })} options={[{ value: "", label: w("all_sections") }, ...sections]} placeholder={w("all_sections")} aria-label={w("f.section")} />
          </Field>
        </div>
        <div className="mt-4 flex flex-wrap justify-end gap-2">
          <Button onClick={generate} loading={pending}>
            <Search /> {w("generate")}
          </Button>
          <Button variant="outline" onClick={() => (setQ(blank), setData(null), setError(null))}>
            <RotateCcw /> {w("reset")}
          </Button>
          <a className={cn("inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-semibold", data ? "hover:bg-surface-muted" : "pointer-events-none opacity-50")} href={data ? `/api/hr-payroll/attendance-export/summary/xlsx?${query()}` : undefined} aria-disabled={!data}>
            <Download className="size-4" /> {w("export_excel")}
          </a>
          <a className={cn("inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-semibold", data ? "hover:bg-surface-muted" : "pointer-events-none opacity-50")} href={data ? `/api/hr-payroll/attendance-export/summary/pdf?${query()}` : undefined} target="_blank" rel="noreferrer" aria-disabled={!data}>
            <FileText className="size-4" /> {w("export_pdf")}
          </a>
          <Button variant="outline" onClick={print} disabled={!data}>
            <Printer /> {w("print")}
          </Button>
        </div>
      </section>
      {data ? (
        <section className={card}>
          <div className="border-b border-border px-5 py-3">
            <h3 className="text-[15px] font-semibold">{w("summary_title")}</h3>
            <p className="text-[13px] text-foreground-muted">
              {data.label} · {data.department}
              {data.section ? ` · ${data.section}` : ""} · {w("n_employees", { n: data.rows.length })}
            </p>
          </div>
          {data.rows.length ? (
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-brand text-brand-foreground">
                    <th className="sticky left-0 bg-brand px-2 py-2 text-left font-semibold">{w("col.employee")}</th>
                    {Array.from({ length: data.days }, (_, i) => (
                      <th key={i} className={cn("min-w-9 px-1 py-1 text-center font-semibold", data.weekdays[i] === "FRI" && "bg-white/10")}>
                        <div>{i + 1}</div>
                        <div className="text-[10px] font-normal opacity-80">{w(`days_short.${data.weekdays[i]}`)}</div>
                      </th>
                    ))}
                    {["present", "absent", "leave", "off", "ot"].map((k) => (
                      <th key={k} className="px-2 py-2 text-right font-semibold whitespace-nowrap">
                        {w(`sum.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {data.rows.map((r) => (
                    <tr key={r.code} className="border-b border-border last:border-0">
                      <td className="sticky left-0 bg-surface px-2 py-1.5 whitespace-nowrap">
                        <div className="font-semibold">{r.name}</div>
                        <div className="font-mono text-[10px] text-foreground-muted">
                          {r.code}
                          {r.designation ? ` · ${r.designation}` : ""}
                        </div>
                      </td>
                      {r.cells.map((c, i) => (
                        <td key={i} className={cn("px-1 py-1 text-center", { A: "text-danger", MP: "text-warning", L: "text-warning", W: "text-foreground-muted", H: "text-foreground-muted" }[c.mark], data.weekdays[i] === "FRI" && "bg-surface-muted/60")}>
                          <div className="font-semibold">{c.mark}</div>
                          {c.ot ? <div className="text-[10px] text-brand">{c.ot}</div> : null}
                        </td>
                      ))}
                      <td className="px-2 py-1.5 text-right tabular-nums">{r.present}</td>
                      <td className="px-2 py-1.5 text-right tabular-nums">{r.absent}</td>
                      <td className="px-2 py-1.5 text-right tabular-nums">{r.leave}</td>
                      <td className="px-2 py-1.5 text-right tabular-nums">{r.off}</td>
                      <td className="px-2 py-1.5 text-right font-semibold tabular-nums">{num(r.ot)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="px-5 py-10 text-center text-sm text-foreground-muted">{w("empty.summary")}</p>
          )}
          <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{w("legend")}</p>
        </section>
      ) : null}
    </div>
  );
}

/* ── Shift Roster ─────────────────────────────────────────────────── */

const MODES = ["INDIVIDUAL", "MULTIPLE", "LINE", "ROTATION"];

function ShiftRoster({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial);
  const [mode, setMode] = useState("INDIVIDUAL");
  const [f, setF] = useState({ employee: "", line: "", shift: "", from: options.today, to: "", weekly_off: ["FRI"], remarks: "", weeks: "4", pattern: ["", ""] });
  const [picked, setPicked] = useState(new Set());
  const [pick, setPick] = useState({ department: "", section: "", line: "", q: "" });
  const [list, setList] = useState({ department: "", section: "", line: "", employee: "", shift: "" });
  const [errors, setErrors] = useState({});
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const set = (k, v) => (setF((x) => ({ ...x, [k]: v })), errors[k] && setErrors((e) => ({ ...e, [k]: undefined })));
  const many = mode === "MULTIPLE" || mode === "ROTATION";
  const candidates = useMemo(
    () => options.employees.filter((e) => (!pick.department || e.department_id === pick.department) && (!pick.section || e.section_id === pick.section) && (!pick.line || e.line_id === pick.line) && (!pick.q || e.label.toLowerCase().includes(pick.q.toLowerCase()))),
    [options.employees, pick],
  );
  const reload = async (x = list) => {
    const res = await act("rosterList", clean(x));
    if (res?.status === "success") setRows(res.data);
  };
  const assign = () =>
    start(async () => {
      const body = { mode, from: f.from, to: f.to || null, weekly_off: f.weekly_off, remarks: f.remarks || null };
      if (mode === "INDIVIDUAL") Object.assign(body, { employee: f.employee, shift: f.shift });
      if (mode === "MULTIPLE") Object.assign(body, { employees: [...picked], shift: f.shift });
      if (mode === "LINE") Object.assign(body, { line: f.line, shift: f.shift });
      if (mode === "ROTATION") Object.assign(body, { employees: [...picked], pattern: f.pattern, weeks: Number(f.weeks || 0), to: null });
      const res = await act("rosterAssign", body);
      if (res?.status !== "success") return setErrors(res?.errors ?? {}), fail(toast, res);
      toast.add({ title: res.data.message, type: "success" });
      setErrors({});
      setPicked(new Set());
      reload();
    });
  const shiftOptions = options.shifts;
  const columns = [
    { key: "employee", label: w("col.employee"), read: (r) => `${r.name} ${r.code}`, render: (r) => (
      <div>
        <div className="font-semibold">{r.name}</div>
        <div className="font-mono text-xs text-foreground-muted">{r.code}</div>
      </div>
    ) },
    { key: "line", label: w("col.line") },
    { key: "shift", label: w("col.shift") },
    { key: "from", label: w("col.from") },
    { key: "to", label: w("col.to"), read: (r) => r.to ?? w("open_ended") },
    { key: "weekly_off", label: w("col.weekly_off"), read: (r) => r.weekly_off.map((d) => w(`days_short.${d}`)).join(", ") },
    { key: "mode", label: w("col.mode"), read: (r) => w(`mode.${r.mode}`) },
    { key: "batch", label: w("col.batch"), mono: true },
  ];
  return (
    <div className="flex flex-col gap-4">
      {canManage ? (
        <section className={cn(card, "p-5")}>
          <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 className="text-[15px] font-semibold">{w("assign_shift")}</h3>
              <p className="max-w-2xl text-[13px] text-foreground-muted">{w("assign_note")}</p>
            </div>
            <div className="flex rounded-lg border border-border p-1" role="tablist" aria-label={w("mode_label")}>
              {MODES.map((m) => (
                <button key={m} type="button" role="tab" aria-selected={mode === m} onClick={() => (setMode(m), setErrors({}))} className={cn("rounded-md px-3 py-1.5 text-sm font-semibold", mode === m ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}>
                  {w(`mode.${m}`)}
                </button>
              ))}
            </div>
          </div>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {mode === "INDIVIDUAL" ? (
              <Field label={w("f.employee")} required error={errors.employee?.[0]}>
                <Select value={f.employee} onValueChange={(v) => set("employee", v ?? "")} options={options.employees} aria-label={w("f.employee")} />
              </Field>
            ) : null}
            {mode === "LINE" ? (
              <Field label={w("f.line")} required error={errors.line?.[0]}>
                <Select value={f.line} onValueChange={(v) => set("line", v ?? "")} options={options.lines} aria-label={w("f.line")} />
              </Field>
            ) : null}
            {mode !== "ROTATION" ? (
              <Field label={w("f.shift")} required error={errors.shift?.[0]}>
                <Select value={f.shift} onValueChange={(v) => set("shift", v ?? "")} options={shiftOptions} aria-label={w("f.shift")} />
              </Field>
            ) : null}
            <Field label={w("f.from")} required error={errors.from?.[0]}>
              <Input type="date" value={f.from} onChange={(e) => set("from", e.target.value)} aria-label={w("f.from")} />
            </Field>
            {mode === "ROTATION" ? (
              <Field label={w("f.weeks")} required hint={w("weeks_hint")} error={errors.weeks?.[0]}>
                <Input type="number" min={1} max={26} value={f.weeks} onChange={(e) => set("weeks", e.target.value)} aria-label={w("f.weeks")} />
              </Field>
            ) : (
              <Field label={w("f.to")} hint={w("to_hint")} error={errors.to?.[0]}>
                <Input type="date" value={f.to} onChange={(e) => set("to", e.target.value)} aria-label={w("f.to")} />
              </Field>
            )}
          </div>
          {mode === "ROTATION" ? (
            <div className="mt-4">
              <p className="mb-2 text-[13px] font-semibold">{w("pattern")}</p>
              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {f.pattern.map((s, i) => (
                  <Field key={i} label={w("week_n", { n: i + 1 })} required error={errors[`pattern.${i}`]?.[0]}>
                    <Select value={s} onValueChange={(v) => set("pattern", f.pattern.map((x, j) => (j === i ? (v ?? "") : x)))} options={shiftOptions} aria-label={w("week_n", { n: i + 1 })} />
                  </Field>
                ))}
              </div>
              {errors.pattern ? <p className="mt-1 text-xs text-danger">{errors.pattern[0]}</p> : null}
              <div className="mt-2 flex gap-2">
                <Button variant="outline" size="sm" onClick={() => set("pattern", [...f.pattern, ""])} disabled={f.pattern.length >= 8}>
                  {w("add_week")}
                </Button>
                <Button variant="outline" size="sm" onClick={() => set("pattern", f.pattern.slice(0, -1))} disabled={f.pattern.length <= 2}>
                  {w("remove_week")}
                </Button>
              </div>
            </div>
          ) : (
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <Field label={w("f.weekly_off")} hint={w("selected_n", { n: f.weekly_off.length })}>
                <div className="flex flex-wrap gap-x-5 gap-y-2 rounded-lg border border-border px-4 py-3">
                  {WEEK.map((d) => (
                    <label key={d} className="flex items-center gap-2 text-sm">
                      <Checkbox checked={f.weekly_off.includes(d)} onCheckedChange={(v) => set("weekly_off", v === true ? [...f.weekly_off, d] : f.weekly_off.filter((x) => x !== d))} aria-label={w(`days.${d}`)} /> {w(`days_short.${d}`)}
                    </label>
                  ))}
                </div>
              </Field>
              <Field label={w("f.remarks")}>
                <Input value={f.remarks} maxLength={300} onChange={(e) => set("remarks", e.target.value)} aria-label={w("f.remarks")} />
              </Field>
            </div>
          )}
          {many ? (
            <div className="mt-4 rounded-lg border border-border">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-2">
                <span className="text-sm font-semibold">{w("picked_n", { n: picked.size })}</span>
                <div className="flex gap-2">
                  <Button size="sm" variant="outline" onClick={() => setPicked(new Set([...picked, ...candidates.map((e) => e.value)]))}>
                    {w("select_shown", { n: candidates.length })}
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => setPicked(new Set())} disabled={!picked.size}>
                    {w("clear")}
                  </Button>
                </div>
              </div>
              <div className="grid gap-3 px-4 py-3 md:grid-cols-4">
                <PlaceFilters w={w} options={options} q={pick} set={(p) => setPick({ ...pick, ...p })} />
                <Field label={w("f.search")}>
                  <Input value={pick.q} onChange={(e) => setPick({ ...pick, q: e.target.value })} placeholder={w("ph.code_name")} aria-label={w("f.search")} />
                </Field>
              </div>
              {errors.employees ? <p className="px-4 text-xs text-danger">{errors.employees[0]}</p> : null}
              <div className="max-h-72 overflow-y-auto px-4 pb-3">
                {candidates.map((e) => (
                  <label key={e.value} className="flex items-center gap-3 border-b border-border/60 py-1.5 text-sm last:border-0">
                    <Checkbox checked={picked.has(e.value)} onCheckedChange={(v) => setPicked((s) => (v === true ? new Set([...s, e.value]) : new Set([...s].filter((x) => x !== e.value))))} aria-label={w("tick", { code: e.value })} />
                    <span className="font-mono text-xs font-semibold">{e.value}</span>
                    <span className="flex-1">{e.name}</span>
                    <span className="text-xs text-foreground-muted">
                      {e.line ?? "—"} · {e.current ?? "—"}
                    </span>
                  </label>
                ))}
              </div>
            </div>
          ) : null}
          <div className="mt-4 flex justify-end">
            <Button onClick={assign} loading={pending}>
              <Save /> {w("assign")}
            </Button>
          </div>
        </section>
      ) : null}
      <section className={cn(card, "p-5")}>
        <h3 className="text-[15px] font-semibold">{w("current_roster")}</h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("roster_note", { n: rows.length })}</p>
        <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
          <PlaceFilters w={w} options={options} q={list} set={(p) => setList({ ...list, ...p })} employee />
          <Field label={w("f.shift")}>
            <Select value={list.shift} onValueChange={(v) => setList({ ...list, shift: v ?? "" })} options={[{ value: "", label: w("all") }, ...shiftOptions]} placeholder={w("all")} aria-label={`${w("f.shift")} ${w("current_roster")}`} />
          </Field>
          <div className="flex items-end">
            <Button variant="outline" onClick={() => start(() => reload())} loading={pending}>
              <Search /> {w("show")}
            </Button>
          </div>
        </div>
      </section>
      <Grid
        w={w}
        rows={rows}
        columns={columns}
        rowKey="id"
        exportName="shift-roster"
        empty={w("empty.roster")}
        actions={canManage ? (r) => (
          <Button size="sm" variant="outline" onClick={() => setAsking(r)} aria-label={w("remove_for", { code: r.code, from: r.from })}>
            <Trash2 className="text-danger" />
          </Button>
        ) : null}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={w("remove_title", { code: asking?.code ?? "" })}
        description={w("remove_confirm")}
        confirmLabel={w("delete")}
        cancelLabel={w("keep")}
        destructive
        loading={pending}
        onConfirm={() =>
          start(async () => {
            const res = await act("rosterRemove", asking.id);
            setAsking(null);
            if (res?.status !== "success") return fail(toast, res);
            toast.add({ title: res.data.message, type: "success" });
            reload();
          })
        }
      />
    </div>
  );
}

export { DailyAttendance, BiometricSync, MissingPunch, AttendanceCorrection, MonthlySummary, ShiftRoster };
