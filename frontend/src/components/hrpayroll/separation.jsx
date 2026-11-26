"use client";

import { Fragment, useMemo, useState, useTransition } from "react";
import { Calculator, CalendarClock, CircleCheck, Clock, Footprints, Pencil, Plus, Printer, RotateCcw, Save, Search, Send, ShieldAlert, Table2, Trash2, UserCheck, UserMinus, UserX, Wallet, X, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Grid, Tabs } from "@/components/settings/devices";
import { DecisionCell, DecisionDialog, Empty, EmployeePicker, Field, FilterBar, Head, Pill, Requests, Segments, Stat, day, downloadCsv, errorsOf, firstError, stamp, taka, useFilter } from "@/components/hrpayroll/movement";

/*
 * HR & Payroll → Separation (AnnotechERP 24/08), as Brickland lays the three
 * screens out:
 *
 * - Resignation / Release: four tiles, the request form beside the list;
 *   approve or reject with a note, withdraw, reopen a rejected one, start
 *   the final settlement of an approved one.
 * - Lefty Management: four tiles, Report Lefty (search, last attendance,
 *   report for review) beside the records; investigate, confirm, reject,
 *   reinstate, close — each with a note in the case history; the case
 *   detail; department- and section-wise counts.
 * - Final Settlement: Records (calculate, approve, mark paid, print, edit,
 *   delete) and New settlement, the dues worked out from the records.
 *
 * Nothing reloads; tabs and dialogs switch in place.
 */

const card = "rounded-xl border border-border bg-surface";

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`st.${k}`, p);
}

const TYPE_TONE = { RESIGNATION: "info", RELEASE: "warning", TERMINATION: "danger" };

/* ── Resignation / Release ───────────────────────────────────────── */

function Separation({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial.rows);
  const [emp, setEmp] = useState(null);
  const blank = { type: "RESIGNATION", application: initial.today, last: "", notice: "", reason: "" };
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState({});
  const [asking, setAsking] = useState(null);
  const [reopening, setReopening] = useState(null);
  const [pending, start] = useTransition();
  const [busy, startBusy] = useTransition();
  const [f, setF, shown] = useFilter(rows, (r) => ({ status: r.status, date: r.last_working_date, text: `${r.ref} ${r.code} ${r.name} ${w(`type.${r.type}`)} ${r.section ?? ""}` }));
  const gap = /^\d{4}-\d{2}-\d{2}$/.test(form.application) && /^\d{4}-\d{2}-\d{2}$/.test(form.last) ? Math.round((Date.parse(`${form.last}T00:00:00Z`) - Date.parse(`${form.application}T00:00:00Z`)) / 864e5) : null;
  const done = (res) => {
    if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
    toast.add({ title: res.data.message, type: "success" });
    setRows(res.data.rows);
  };
  const send = () =>
    start(async () => {
      const res = await act("sepStore", { employee: emp?.code ?? "", type: form.type, application_date: form.application, last_working_date: form.last, notice_days: form.notice === "" ? null : Number(form.notice), reason: form.reason });
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      done(res);
      setEmp(null);
      setForm(blank);
      setErrors({});
    });
  const decide = (note) =>
    startBusy(async () => {
      const res = await act("sepDecide", asking.ref, { decision: asking.decision, note });
      setAsking(null);
      done(res);
    });
  const count = (fn) => rows.filter(fn).length;
  const columns = [
    { key: "ref", label: w("col.ref_no"), render: (r) => <span className="font-mono text-xs font-semibold whitespace-nowrap">{r.ref}</span> },
    { key: "employee", label: w("col.employee"), render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="text-xs text-foreground-muted"><span className="font-mono whitespace-nowrap">{r.code}</span>{r.designation ? ` · ${r.designation}` : ""}{r.section ? ` · ${r.section}` : ""}</div></div>) },
    { key: "type", label: w("col.type"), render: (r) => <Pill t={TYPE_TONE[r.type]}>{w(`type.${r.type}`)}</Pill> },
    { key: "application", label: w("col.application"), render: (r) => <span className="whitespace-nowrap">{day(r.application_date)}</span> },
    { key: "last", label: w("col.last_day"), render: (r) => <span className="whitespace-nowrap">{day(r.last_working_date)}</span> },
    { key: "notice", label: w("col.notice"), right: true, render: (r) => (r.notice_days === null ? "—" : w("days_short", { n: r.notice_days })) },
    { key: "reason", label: w("col.reason"), render: (r) => <span className="line-clamp-2 text-xs">{r.reason}</span> },
    { key: "status", label: w("col.status"), render: (r) => <DecisionCell w={w} r={{ ...r, effective_date: r.last_working_date, applied_at: r.applied ? "x" : null }} appliedLabel={() => w("off_roster")} /> },
    {
      key: "action",
      label: w("col.action"),
      render: (r) => (
        <div className="flex flex-wrap gap-1">
          {r.status === "PENDING" && initial.can_approve ? (
            <>
              <Button size="sm" disabled={busy} onClick={() => setAsking({ ref: r.ref, decision: "APPROVE", title: w("approve_title", { ref: r.ref }), detail: w("approve_detail", { who: `${r.code} ${r.name}`, date: day(r.last_working_date), type: w(`type.${r.type}`).toLowerCase() }) })}>
                {w("approve")}
              </Button>
              <Button size="sm" variant="danger" disabled={busy} onClick={() => setAsking({ ref: r.ref, decision: "REJECT", title: w("reject_title", { ref: r.ref }), detail: w("reject_detail", { who: `${r.code} ${r.name}` }) })}>
                {w("reject")}
              </Button>
            </>
          ) : null}
          {r.status === "PENDING" && initial.can_request ? (
            <Button size="sm" variant="ghost" disabled={busy} onClick={() => startBusy(async () => done(await act("sepWithdraw", r.ref)))}>
              {w("withdraw")}
            </Button>
          ) : null}
          {r.status === "REJECTED" && initial.can_approve ? (
            <Button size="sm" variant="outline" disabled={busy} onClick={() => setReopening(r)}>
              {w("reopen")}
            </Button>
          ) : null}
          {r.status === "APPROVED" && !r.settlement_ref && initial.can_request ? (
            <Button size="sm" variant="outline" disabled={busy} onClick={() => startBusy(async () => done(await act("sepSettle", r.ref)))}>
              <Calculator /> {w("create_settlement")}
            </Button>
          ) : null}
          {r.settlement_ref ? (
            <a href="/hr-payroll/final-settlement" className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-brand hover:bg-brand-subtle">
              {w("settlement_link", { status: w(`fs_status.${r.settlement_status}`).toLowerCase() })}
            </a>
          ) : null}
        </div>
      ),
    },
  ];
  const exportCsv = () =>
    downloadCsv("separations.csv", ["ref_no", "code", "name", "designation", "section", "type", "application", "last_day", "notice", "reason", "status", "decided_by", "settlement"].map((k) => w(`csv.${k}`)), shown.map((r) => [r.ref, r.code, r.name, r.designation, r.section, w(`type.${r.type}`), day(r.application_date), day(r.last_working_date), r.notice_days ?? "", r.reason, w(`decision.${r.status}`), r.decided_by, r.settlement_status ? w(`fs_status.${r.settlement_status}`) : ""]));
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Clock} label={w("tile.pending")} value={count((r) => r.status === "PENDING")} tone="warning" />
        <Stat icon={CalendarClock} label={w("tile.still_working")} value={count((r) => r.status === "APPROVED" && !r.applied)} />
        <Stat icon={UserX} label={w("tile.separated")} value={count((r) => r.applied)} caption={w("tile.without_settlement", { n: count((r) => r.applied && !r.settlement_ref) })} tone="danger" />
        <Stat icon={XCircle} label={w("tile.rejected")} value={count((r) => r.status === "REJECTED")} tone="good" />
      </div>
      <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        {initial.can_request ? (
          <section className={cn(card, "flex flex-col gap-4 p-5")}>
            <Head icon={UserMinus} title={w("new_separation")} sub={w("separation_sub")} />
            <EmployeePicker w={w} act={act} op="sepSearch" employee={emp} onChange={(e) => (setEmp(e), setErrors({}))} error={errors.employee} />
            {emp ? (
              <>
                <div className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold">{w("f.type")}</span>
                  <Segments label={w("f.type")} value={form.type} onChange={(t) => setForm({ ...form, type: t })} items={["RESIGNATION", "RELEASE", "TERMINATION"].map((t) => [t, w(`type.${t}`)])} />
                </div>
                <Field label={w("f.application_date")} required error={errors.application_date}>
                  <Input type="date" value={form.application} onChange={(e) => setForm({ ...form, application: e.target.value })} aria-label={w("f.application_date")} />
                </Field>
                <Field label={w("f.last_day")} required error={errors.last_working_date}>
                  <Input type="date" value={form.last} onChange={(e) => setForm({ ...form, last: e.target.value })} aria-label={w("f.last_day")} />
                </Field>
                <Field label={w("f.notice")} error={errors.notice_days} hint={gap !== null && gap >= 0 ? w("hint.notice_n", { n: gap }) : w("hint.notice")}>
                  <Input type="number" min="0" value={form.notice} onChange={(e) => setForm({ ...form, notice: e.target.value })} aria-label={w("f.notice")} />
                </Field>
                <Field label={w("f.reason")} required error={errors.reason}>
                  <Textarea rows={3} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} aria-label={w("f.reason")} />
                </Field>
                <Button onClick={send} loading={pending} className="self-start">
                  <Send /> {w("send")}
                </Button>
              </>
            ) : (
              <p className="text-[13px] text-foreground-muted">{w("search_first")}</p>
            )}
          </section>
        ) : null}
        <section className={cn(card, "flex flex-col gap-4 p-5", !initial.can_request && "xl:col-span-2")}>
          <Head icon={UserX} title={w("separation_list")} sub={initial.can_approve ? w("approve_sub") : w("approval_for")} />
          <FilterBar w={w} f={f} onChange={setF} onExport={exportCsv} dateLabel={w("f.last_day_from")} />
          <Requests w={w} title={w("separations")} rows={shown} columns={columns} empty={w("empty.separation")} />
        </section>
      </div>
      <DecisionDialog w={w} asking={asking} busy={busy} onCancel={() => setAsking(null)} onConfirm={decide} />
      <ConfirmDialog
        open={reopening !== null}
        onOpenChange={(o) => !o && setReopening(null)}
        title={w("reopen_title", { ref: reopening?.ref ?? "" })}
        description={w("reopen_text")}
        confirmLabel={w("reopen")}
        cancelLabel={w("cancel")}
        destructive={false}
        loading={busy}
        onConfirm={() => startBusy(async () => {
          const res = await act("sepReopen", reopening.ref);
          setReopening(null);
          done(res);
        })}
      />
    </div>
  );
}

/* ── Lefty Management ────────────────────────────────────────────── */

const LEFTY_STATUSES = ["PENDING_REVIEW", "UNDER_INVESTIGATION", "CONFIRMED", "REJECTED", "REINSTATED", "CLOSED"];
const LEFTY_TONE = { PENDING_REVIEW: "warning", UNDER_INVESTIGATION: "info", CONFIRMED: "danger", REJECTED: "neutral", REINSTATED: "good", CLOSED: "neutral" };

function Lefty({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial.rows);
  const [q, setQ] = useState("");
  const [found, setFound] = useState(null);
  const [emp, setEmp] = useState(null);
  const [form, setForm] = useState({ last: "", reported: initial.today, reason: "", doc: "" });
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState("ALL");
  const [report, setReport] = useState("HIDDEN");
  const [moving, setMoving] = useState(null);
  const [note, setNote] = useState("");
  const [detail, setDetail] = useState(null);
  const [searching, startSearch] = useTransition();
  const [pending, start] = useTransition();
  const [busy, startBusy] = useTransition();
  const shown = status === "ALL" ? rows : rows.filter((r) => r.status === status);
  const n = (s) => rows.filter((r) => r.status === s).length;
  const search = () =>
    startSearch(async () => {
      const res = await act("leftySearch", { q });
      if (res?.status !== "success") return toast.add({ title: w("err.search"), type: "danger" });
      setFound(res.data);
    });
  const send = () =>
    start(async () => {
      const res = await act("leftyReport", { employee: emp?.code ?? "", last_working_date: form.last, reported_date: form.reported, reason: form.reason, document_ref: form.doc });
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      setEmp(null);
      setQ("");
      setForm({ last: "", reported: initial.today, reason: "", doc: "" });
      setErrors({});
    });
  const needsNote = moving && (moving.action === "REJECT" || moving.action === "REINSTATE");
  const move = () =>
    startBusy(async () => {
      const res = await act("leftyMove", moving.row.ref, { action: moving.action, note });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      setMoving(null);
      setNote("");
    });
  const counts = useMemo(() => {
    if (report === "HIDDEN") return [];
    const m = new Map();
    for (const r of rows) {
      const k = (report === "DEPARTMENT" ? r.department : r.section) || "—";
      const c = m.get(k) ?? Object.fromEntries(LEFTY_STATUSES.map((s) => [s, 0]));
      c[r.status] += 1;
      m.set(k, c);
    }
    return [...m.entries()].sort((a, b) => a[0].localeCompare(b[0]));
  }, [rows, report]);
  const columns = [
    { key: "ref", label: w("col.id"), render: (r) => <button type="button" className="font-mono text-xs font-semibold whitespace-nowrap text-brand hover:underline" onClick={() => setDetail(r)}>{r.ref}</button> },
    { key: "employee", label: w("col.employee"), read: (r) => `${r.code} ${r.name} ${r.card_no ?? ""}`, render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="text-xs text-foreground-muted"><span className="font-mono whitespace-nowrap">{r.code}</span>{r.card_no ? ` · ${w("card_n", { n: r.card_no })}` : ""}</div></div>) },
    { key: "department", label: w("col.dept_section"), read: (r) => `${r.department ?? ""} ${r.section ?? ""}`, render: (r) => (<div><div>{r.department || "—"}</div><div className="text-xs text-foreground-muted">{r.section}</div></div>) },
    { key: "last_attendance", label: w("col.last_attendance"), read: (r) => day(r.last_attendance_date), render: (r) => <span className="whitespace-nowrap">{day(r.last_attendance_date)}</span> },
    { key: "last_working", label: w("col.last_working"), read: (r) => day(r.last_working_date), render: (r) => <span className="whitespace-nowrap">{day(r.last_working_date)}</span> },
    { key: "status", label: w("col.status"), read: (r) => w(`lefty_status.${r.status}`), render: (r) => (<div className="flex flex-col gap-0.5"><Pill t={LEFTY_TONE[r.status]}>{w(`lefty_status.${r.status}`)}</Pill><span className="text-xs text-foreground-muted">{r.payroll_eligible ? w("on_payroll") : w("off_payroll")}</span></div>) },
  ];
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Clock} label={w("lefty_status.PENDING_REVIEW")} value={n("PENDING_REVIEW")} tone="warning" />
        <Stat icon={ShieldAlert} label={w("lefty_status.UNDER_INVESTIGATION")} value={n("UNDER_INVESTIGATION")} />
        <Stat icon={UserX} label={w("lefty_status.CONFIRMED")} value={n("CONFIRMED")} tone="danger" />
        <Stat icon={UserCheck} label={w("lefty_status.REINSTATED")} value={n("REINSTATED")} tone="good" />
      </div>
      <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        {initial.can.report ? (
          <section className={cn(card, "flex flex-col gap-4 p-5")}>
            <Head icon={Footprints} title={w("report_lefty")} sub={w("report_sub")} />
            {emp ? (
              <div className="flex flex-col gap-0.5 rounded-lg border border-border bg-surface-muted p-3 text-[13px]">
                <div className="flex items-start justify-between gap-2">
                  <span className="font-semibold">
                    {emp.name} <span className="font-mono text-xs text-foreground-muted">{emp.code}</span>
                  </span>
                  <button type="button" aria-label={w("choose_another")} onClick={() => setEmp(null)} className="text-foreground-muted hover:text-foreground">
                    <X className="size-4" />
                  </button>
                </div>
                <span>{`${w("card_n", { n: emp.card_no || "—" })} · ${emp.designation || "—"} · ${emp.grade || w("no_grade")}`}</span>
                <span>{[emp.company, emp.department, emp.section].filter(Boolean).join(" · ")}</span>
                <span className={emp.absent_days >= 10 ? "text-danger" : ""}>{emp.last_attendance_date ? w("last_seen_ago", { date: day(emp.last_attendance_date), n: emp.absent_days }) : w("never_recorded")}</span>
                {emp.open_record ? <span className="text-warning">{w("already_open", { ref: emp.open_record })}</span> : null}
              </div>
            ) : (
              <div className="flex flex-col gap-2">
                <Field label={w("f.id_or_card")} error={errors.employee}>
                  <div className="flex gap-2" onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), q.trim() && search())}>
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={w("ph.id_or_card")} aria-label={w("f.id_or_card")} />
                    <Button variant="outline" onClick={search} loading={searching} disabled={!q.trim()}>
                      <Search /> {w("search")}
                    </Button>
                  </div>
                </Field>
                {found ? (
                  found.length ? (
                    <ul className="flex max-h-64 flex-col overflow-y-auto rounded-lg border border-border">
                      {found.map((e) => (
                        <li key={e.code}>
                          <button type="button" onClick={() => (setEmp(e), setFound(null), setForm((x) => ({ ...x, last: e.last_attendance_date ?? "" })), setErrors({}))} className="flex w-full flex-col border-b border-border px-3 py-2 text-left last:border-0 hover:bg-surface-muted">
                            <span className="text-sm font-semibold">
                              {e.name} <span className="font-mono text-xs text-foreground-muted">{e.code}</span>
                            </span>
                            <span className="text-xs text-foreground-muted">{`${w("card_n", { n: e.card_no || "—" })} · ${e.section || e.department || "—"} · ${e.last_attendance_date ? w("last_seen", { date: day(e.last_attendance_date) }) : w("never_seen")}`}</span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-xs text-foreground-muted">{w("no_active_match")}</p>
                  )
                ) : null}
              </div>
            )}
            {emp ? (
              <>
                <Field label={w("f.last_day")} required error={errors.last_working_date}>
                  <Input type="date" value={form.last} onChange={(e) => setForm({ ...form, last: e.target.value })} aria-label={w("f.last_day")} />
                </Field>
                <Field label={w("f.reported_date")} required error={errors.reported_date}>
                  <Input type="date" value={form.reported} onChange={(e) => setForm({ ...form, reported: e.target.value })} aria-label={w("f.reported_date")} />
                </Field>
                <Field label={w("f.reason_remarks")} required error={errors.reason}>
                  <Textarea rows={3} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} aria-label={w("f.reason_remarks")} />
                </Field>
                <Field label={w("f.document")} hint={w("hint.document")}>
                  <Input value={form.doc} onChange={(e) => setForm({ ...form, doc: e.target.value })} placeholder={w("ph.document")} aria-label={w("f.document")} />
                </Field>
                <Button onClick={send} loading={pending} disabled={!!emp.open_record} className="self-start">
                  <Send /> {w("report_for_review")}
                </Button>
              </>
            ) : null}
          </section>
        ) : null}
        <section className={cn("flex flex-col gap-3", !initial.can.report && "xl:col-span-2")}>
          <div className={cn(card, "flex flex-wrap items-end justify-between gap-3 p-5")}>
            <Head icon={UserX} title={w("lefty_records")} sub={initial.can.confirm ? w("lefty_sub") : w("confirm_for")} />
            <Field label={w("f.status")} className="w-56">
              <Select value={status} onValueChange={(v) => setStatus(v ?? "ALL")} options={[{ value: "ALL", label: w("all_statuses") }, ...LEFTY_STATUSES.map((s) => ({ value: s, label: w(`lefty_status.${s}`) }))]} aria-label={w("f.status")} />
            </Field>
          </div>
          {rows.length ? (
            <Grid
              w={w}
              rows={shown}
              columns={columns}
              rowKey="ref"
              exportName="lefty-records"
              empty={w("empty.lefty_match")}
              actions={(r) => (
                <div className="flex flex-wrap justify-end gap-1">
                  {r.actions.map((a) => (
                    <Button key={a} size="sm" variant={a === "CONFIRM" ? "danger" : a === "REINSTATE" ? "primary" : "outline"} disabled={busy} onClick={() => (setNote(""), setMoving({ row: r, action: a }))}>
                      {w(`lefty_action.${a}`)}
                    </Button>
                  ))}
                  {r.status === "CONFIRMED" ? (
                    <a href="/hr-payroll/final-settlement" className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-brand hover:bg-brand-subtle">
                      <Calculator className="size-3.5" /> {r.settlement_status ? w("settlement_link", { status: w(`fs_status.${r.settlement_status}`).toLowerCase() }) : w("final_settlement")}
                    </a>
                  ) : null}
                </div>
              )}
            />
          ) : (
            <Empty title={w("empty.lefty")} />
          )}
        </section>
      </div>
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <Head title={w("lefty_reports")} sub={w("lefty_reports_sub")} />
          <Segments label={w("lefty_reports")} value={report} onChange={setReport} items={[["HIDDEN", w("report.hidden")], ["DEPARTMENT", w("report.department")], ["SECTION", w("report.section")]]} />
        </div>
        {report !== "HIDDEN" ? (
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full min-w-[800px] text-sm">
              <thead>
                <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                  <th className="px-3 py-2.5 font-semibold">{report === "DEPARTMENT" ? w("col.department") : w("col.section")}</th>
                  {LEFTY_STATUSES.map((s) => (
                    <th key={s} className="px-3 py-2.5 text-right font-semibold">
                      {w(`lefty_status.${s}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {counts.length ? (
                  counts.map(([k, c]) => (
                    <tr key={k} className="border-b border-border last:border-0">
                      <td className="px-3 py-2">{k}</td>
                      {LEFTY_STATUSES.map((s) => (
                        <td key={s} className="px-3 py-2 text-right tabular-nums">
                          {c[s]}
                        </td>
                      ))}
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={LEFTY_STATUSES.length + 1} className="px-5 py-8 text-center text-foreground-muted">
                      {w("empty.lefty")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
      <Modal
        open={moving !== null}
        onOpenChange={(o) => !o && setMoving(null)}
        title={moving ? `${w(`lefty_action.${moving.action}`)} — ${moving.row.ref}` : ""}
        description={moving ? `${moving.row.code} ${moving.row.name}. ${w(`lefty_effect.${moving.action}`)}` : ""}
        footer={
          <>
            <Button variant="ghost" onClick={() => setMoving(null)} disabled={busy}>
              {w("cancel")}
            </Button>
            <Button variant={moving?.action === "CONFIRM" ? "danger" : "primary"} loading={busy} disabled={needsNote && !note.trim()} onClick={move}>
              {moving ? w(`lefty_action.${moving.action}`) : ""}
            </Button>
          </>
        }
      >
        <Field label={needsNote ? w("f.reason") : w("f.remarks")} required={needsNote} hint={w("hint.history")}>
          <Textarea rows={3} value={note} onChange={(e) => setNote(e.target.value)} aria-label={needsNote ? w("f.reason") : w("f.remarks")} />
        </Field>
      </Modal>
      <Modal open={detail !== null} onOpenChange={(o) => !o && setDetail(null)} title={detail ? `${detail.ref} · ${detail.code} ${detail.name}` : ""} className="max-w-2xl">
        {detail ? (
          <div className="flex flex-col gap-4 text-sm">
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
              {[
                [w("d.card"), detail.card_no || "—"],
                [w("d.complex"), detail.company],
                [w("d.dept_section"), `${detail.department ?? "—"} / ${detail.section ?? "—"}`],
                [w("d.designation_grade"), `${detail.designation ?? "—"} / ${detail.grade ?? "—"}`],
                [w("col.last_attendance"), day(detail.last_attendance_date)],
                [w("f.last_day"), day(detail.last_working_date)],
                [w("f.reported_date"), day(detail.reported_date)],
                [w("d.reported_by"), detail.reported_by || "—"],
                [w("d.reviewed_by"), detail.reviewed_by || "—"],
                [w("d.confirmed"), detail.confirmed_by ? `${detail.confirmed_by}, ${stamp(detail.confirmed_at)}` : "—"],
                [w("f.document"), detail.document_ref || "—"],
                [w("d.payroll"), detail.payroll_eligible ? w("d.eligible") : w("d.stopped")],
              ].map(([k, v]) => (
                <Fragment key={k}>
                  <dt className="text-foreground-muted">{k}</dt>
                  <dd className="font-medium">{v}</dd>
                </Fragment>
              ))}
            </dl>
            <p>
              <span className="text-foreground-muted">{w("d.reason")} </span>
              {detail.reason}
            </p>
            <div>
              <p className="mb-1 text-[13px] font-semibold">{w("d.history")}</p>
              <ol className="flex flex-col gap-1 border-l-2 border-border pl-3 text-[13px]">
                {detail.history.map((h, i) => (
                  <li key={i} className="flex flex-wrap gap-x-2">
                    <span className="text-foreground-muted">{stamp(h.at)}</span>
                    <span>{h.by}</span>
                    <span className="font-medium">{h.from ? `${w(`lefty_status.${h.from}`)} → ` : ""}{w(`lefty_status.${h.to}`)}</span>
                    {h.note ? <span className="text-foreground-muted">— {h.note}</span> : null}
                  </li>
                ))}
              </ol>
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

/* ── Final Settlement ────────────────────────────────────────────── */

const MONEY = ["salary_due", "ot_due", "bonus_due", "leave_encashment", "deduction", "loan_recovery", "other_adjustment"];
const SETTLEMENT_TYPES = ["RESIGN_FROM_JOB", "DISABLE_FOR_LONG_ABSENTEEISM", "TERMINATION", "RETIREMENT", "DEATH"];
const FS_TONE = { DRAFT: "warning", APPROVED: "info", PAID: "good" };
const num = (v) => (String(v).trim() === "" || Number.isNaN(Number(v)) ? 0 : Number(v));
const payableOf = (f) => Math.round((num(f.salary_due) + num(f.ot_due) + num(f.bonus_due) + num(f.leave_encashment) - num(f.deduction) - num(f.loan_recovery) + num(f.other_adjustment)) * 100) / 100;
const blankSettlement = () => ({ ref: null, employee: "", separation: "", settlement_type: "", action_date: "", remarks: "", ...Object.fromEntries(MONEY.map((k) => [k, ""])) });

function printSettlement(w, r) {
  const sign = { salary_due: "+", ot_due: "+", bonus_due: "+", leave_encashment: "+", deduction: "−", loan_recovery: "−", other_adjustment: "±" };
  printDocument({
    title: w("print.title"),
    docNo: `${r.code} · ${r.name}`,
    subtitle: w(`fs_status.${r.status}`),
    meta: [
      [w("print.code"), r.code],
      [w("print.name"), r.name],
      [w("print.designation"), r.designation ?? "—"],
      [w("print.department"), r.department ?? "—"],
      [w("print.section"), r.section ?? "—"],
      [w("print.joining"), day(r.joining_date)],
      [w("print.gross"), taka(r.gross_a)],
      [w("print.company"), r.company],
      [w("print.separation_no"), r.separation ?? "—"],
      [w("print.separation_type"), r.separation_type ? w(`type.${r.separation_type}`) : "—"],
      [w("f.settlement_type"), w(`fs_type.${r.settlement_type}`)],
      [w("f.action_date"), day(r.action_date)],
      [w("col.service_length"), r.service_length],
      [w("col.status"), w(`fs_status.${r.status}`)],
    ],
    metaColumns: 2,
    sections: [
      {
        title: w("print.settlement"),
        columns: [{ label: w("print.head") }, { label: "", align: "center", width: "40px" }, { label: w("print.amount"), align: "right" }],
        rows: MONEY.map((k) => [w(`f.${k}`), sign[k], taka(r[k])]),
        foot: [[w("f.payable"), "=", taka(r.payable)]],
      },
    ],
    notes: [r.notes, r.remarks].filter(Boolean),
    signatures: [w("print.employee"), w("print.prepared"), w("print.checked"), w("print.approved")],
  });
}

function Settlement({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [tab, setTab] = useState("records");
  const [rows, setRows] = useState(initial.rows);
  const [people, setPeople] = useState(initial.employees);
  const [form, setForm] = useState(blankSettlement);
  const [errors, setErrors] = useState({});
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const [loading, startLoading] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));
  const fill = (employee, date) =>
    startLoading(async () => {
      const res = await act("fsPreview", { employee, ...(date ? { date } : {}) });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      const d = res.data;
      setForm((f) => ({ ...f, separation: d.separation, settlement_type: f.settlement_type || d.settlement_type, action_date: date || d.action_date, ...Object.fromEntries(MONEY.map((k) => [k, String(d[k])])) }));
    });
  const save = () =>
    start(async () => {
      const body = { employee: form.employee, settlement_type: form.settlement_type, action_date: form.action_date, remarks: form.remarks, ...Object.fromEntries(MONEY.map((k) => [k, num(form[k])])) };
      const res = form.ref ? await act("fsUpdate", form.ref, body) : await act("fsStore", body);
      if (res?.status !== "success") {
        setErrors(errorsOf(res));
        return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      }
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      if (res.data.employees) setPeople(res.data.employees);
      setForm(blankSettlement());
      setErrors({});
      setTab("records");
    });
  const run = () =>
    start(async () => {
      const { op, r } = asking;
      const res = op === "delete" ? await act("fsDelete", r.ref) : await act("fsMove", r.ref, { action: op });
      setAsking(null);
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      toast.add({ title: res.data.message, type: "success" });
      setRows(res.data.rows);
      if (res.data.employees) setPeople(res.data.employees);
    });
  const edit = (r) => {
    setForm({ ref: r.ref, employee: r.code, separation: r.separation ?? "", settlement_type: r.settlement_type, action_date: r.action_date, remarks: r.remarks ?? "", ...Object.fromEntries(MONEY.map((k) => [k, String(r[k])])) });
    setErrors({});
    setTab("new");
  };
  const columns = [
    { key: "code", label: w("col.employee_code"), render: (r) => <span className="font-mono text-xs whitespace-nowrap">{r.code}</span> },
    { key: "name", label: w("col.name") },
    { key: "designation", label: w("col.designation") },
    { key: "separation", label: w("col.separation"), render: (r) => <span className="font-mono text-xs whitespace-nowrap">{r.separation || "—"}</span> },
    { key: "settlement_type", label: w("f.settlement_type"), read: (r) => w(`fs_type.${r.settlement_type}`) },
    { key: "service_length", label: w("col.service_length"), render: (r) => <span className="whitespace-nowrap">{r.service_length}</span> },
    { key: "action_date", label: w("col.action_date"), read: (r) => day(r.action_date), render: (r) => <span className="whitespace-nowrap">{day(r.action_date)}</span> },
    { key: "dues", label: w("col.dues"), right: true, read: (r) => taka(r.dues) },
    { key: "recoveries", label: w("col.recoveries"), right: true, read: (r) => taka(r.recoveries) },
    { key: "payable", label: w("f.payable"), right: true, render: (r) => <span className="font-semibold">{taka(r.payable)}</span> },
    { key: "status", label: w("col.status"), read: (r) => w(`fs_status.${r.status}`), render: (r) => <Pill t={FS_TONE[r.status]}>{w(`fs_status.${r.status}`)}</Pill> },
  ];
  const can = initial.can_manage;
  const money = (k, extra = {}) => (
    <Field key={k} label={w(`f.${k}`)} error={errors[k]} hint={extra.hint}>
      <Input type="number" step="0.01" value={form[k]} onChange={(e) => set({ [k]: e.target.value })} aria-label={w(`f.${k}`)} />
    </Field>
  );
  return (
    <div className="flex flex-col gap-4">
      <Tabs tabs={[["records", w("tab.records"), rows.length, Table2], ...(can ? [["new", form.ref ? w("edit_settlement", { ref: form.ref }) : w("new_settlement"), undefined, form.ref ? Pencil : Plus]] : [])]} active={tab} onPick={setTab} />
      {tab === "records" ? (
        rows.length ? (
          <Grid
            w={w}
            rows={rows}
            columns={columns}
            rowKey="ref"
            exportName="final-settlements"
            empty={w("empty.match")}
            actions={(r) => (
              <div className="flex flex-wrap justify-end gap-1">
                {r.status === "DRAFT" && can ? (
                  <Button size="sm" variant="outline" onClick={() => setAsking({ op: "calculate", r })}>
                    <Calculator /> {w("calculate")}
                  </Button>
                ) : null}
                {r.status === "DRAFT" && initial.can_approve ? (
                  <Button size="sm" onClick={() => setAsking({ op: "approve", r })}>
                    <CircleCheck /> {w("approve")}
                  </Button>
                ) : null}
                {r.status === "APPROVED" && initial.can_approve ? (
                  <Button size="sm" onClick={() => setAsking({ op: "paid", r })}>
                    <Wallet /> {w("mark_paid")}
                  </Button>
                ) : null}
                <Button size="sm" variant="ghost" onClick={() => printSettlement(w, r)} aria-label={w("print_aria", { ref: r.ref })}>
                  <Printer />
                </Button>
                {r.status === "DRAFT" && can ? (
                  <>
                    <Button size="sm" variant="ghost" onClick={() => edit(r)} aria-label={w("edit_aria", { ref: r.ref })}>
                      <Pencil />
                    </Button>
                    <Button size="sm" variant="ghost" className="text-danger" onClick={() => setAsking({ op: "delete", r })} aria-label={w("delete_aria", { ref: r.ref })}>
                      <Trash2 />
                    </Button>
                  </>
                ) : null}
              </div>
            )}
          />
        ) : (
          <Empty title={w("empty.settlement_title")} text={can ? w("empty.settlement") : null} />
        )
      ) : null}
      {tab === "new" && can ? (
        <section className={cn(card, "flex flex-col gap-5 p-5")}>
          <Head title={form.ref ? w("edit_settlement", { ref: form.ref }) : w("new_settlement")} sub={w("settlement_sub")} />
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Field label={w("f.employee")} required error={errors.employee} hint={w("hint.employee")}>
              <Select value={form.employee} disabled={!!form.ref} onValueChange={(v) => (set({ employee: v ?? "", settlement_type: "" }), v && fill(v, null))} options={people} placeholder={w("select")} aria-label={w("f.employee")} />
            </Field>
            <Field label={w("f.separation")} className="xl:col-span-2">
              <Input value={form.separation} readOnly aria-label={w("f.separation")} />
            </Field>
            <Field label={w("f.settlement_type")} required error={errors.settlement_type}>
              <Select value={form.settlement_type} onValueChange={(v) => set({ settlement_type: v ?? "" })} options={SETTLEMENT_TYPES.map((t) => ({ value: t, label: w(`fs_type.${t}`) }))} placeholder={w("select")} aria-label={w("f.settlement_type")} />
            </Field>
            <Field label={w("f.action_date")} required error={errors.action_date} hint={loading ? w("working_out") : null}>
              <Input type="date" value={form.action_date} onChange={(e) => (set({ action_date: e.target.value }), form.employee && !form.ref && /^\d{4}-\d{2}-\d{2}$/.test(e.target.value) && fill(form.employee, e.target.value))} aria-label={w("f.action_date")} />
            </Field>
            <div className="hidden xl:block" />
            {money("salary_due")}
            {money("ot_due")}
            {money("bonus_due")}
            {money("leave_encashment")}
            {money("deduction")}
            {money("loan_recovery")}
            {money("other_adjustment", { hint: w("hint.other") })}
            <Field label={w("f.payable")} hint={w("hint.payable")}>
              <Input value={taka(payableOf(form))} readOnly aria-label={w("f.payable")} />
            </Field>
            <Field label={w("f.remarks")} className="md:col-span-2 xl:col-span-3">
              <Textarea rows={3} value={form.remarks} onChange={(e) => set({ remarks: e.target.value })} aria-label={w("f.remarks")} />
            </Field>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
            <p className="text-xs text-foreground-muted">{w("audit_note")}</p>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => (setForm(blankSettlement()), setErrors({}))}>
                <RotateCcw /> {w("reset")}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save /> {w("save_settlement")}
              </Button>
            </div>
          </div>
        </section>
      ) : null}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? w(`fs_confirm.${asking.op}.title`, { ref: asking.r.ref }) : ""}
        description={asking ? w(`fs_confirm.${asking.op}.text`) : ""}
        confirmLabel={asking ? w(`fs_confirm.${asking.op}.button`) : ""}
        cancelLabel={w("cancel")}
        destructive={asking?.op === "delete"}
        loading={pending}
        onConfirm={run}
      />
    </div>
  );
}

export { Separation, Lefty, Settlement };
