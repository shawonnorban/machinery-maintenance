"use client";

import { Fragment, useEffect, useMemo, useRef, useState, useTransition } from "react";
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Download, Eye, FileClock, FilePlus2, FileSpreadsheet, FileText, Mail, Plus, Printer, RotateCcw, Save, Search, Trash2, UserRound, Users } from "lucide-react";
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
import { LETTER_FIELDS, dmy, gazetteWage, letterHtml, mergeLetter, num, printLetters } from "@/lib/hr-letter";
import { Field, Head, Pill, Stat, downloadCsv, firstError } from "@/components/hrpayroll/movement";
import { Table } from "@/components/hrpayroll/salary";

/*
 * HR & Payroll → Letter Management (AnnotechERP 24/09), as Brickland lays the
 * four screens out:
 *
 * - Letter Master: letter types (add, switch on/off), the type's template
 *   with the employee fields to insert, its versions, and a live preview
 *   merged with a sample employee.
 * - Generate Letter: letter type, then one employee (by number or card) or
 *   a filtered group; the employee's details, gazette and company salary;
 *   preview, generate, print A4, PDF, e-mail.
 * - Letter History / Letter Reports: the letters issued, filtered; view,
 *   print, PDF; the register's tiles, print and Excel.
 *
 * Nothing reloads; tabs and dialogs switch in place.
 */

const card = "rounded-xl border border-border bg-surface";
const taka = (v) => (v === null || v === undefined ? "—" : `৳ ${num(v)}`);

function useWords() {
  const t = useT("hrpayroll");
  return (k, p) => t(`lt.${k}`, p);
}

/** An A4 letter scaled to the width it is given. */
function FitLetter({ letter, head, max = 1 }) {
  const box = useRef(null);
  const [zoom, setZoom] = useState(0.6);
  useEffect(() => {
    const el = box.current;
    if (!el) return undefined;
    const fit = () => setZoom(Math.min(max, Math.max(0.3, el.clientWidth / 794)));
    fit();
    const ro = new ResizeObserver(fit);
    ro.observe(el);
    return () => ro.disconnect();
  }, [max]);
  return (
    <div ref={box} className="w-full">
      <div className="overflow-hidden rounded-md border border-border bg-white shadow-sm" style={{ width: 794 * zoom }}>
        <div style={{ zoom }} dangerouslySetInnerHTML={{ __html: letterHtml(letter, head) }} />
      </div>
    </div>
  );
}

const sampleRef = (format, date) => (format || "REF/{YYYY}/{SEQ}").replaceAll("{YYYY}", date.slice(0, 4)).replaceAll("{SEQ}", "001");

/* ── Letter Master ───────────────────────────────────────────────── */

const blankTemplate = (today) => ({ version: null, title: "", subject: "", referenceFormat: "", effectiveFrom: today, gazetteId: "", salutation: "", openingMessage: "", clauses: [], showSalaryTables: false, salaryTablesAfter: 0, closingMessage: "", signatureTitle: "", signatureCompany: "{{CompanyName}}", footerNote: "" });
const formOf = (t) => ({ version: t.version, title: t.title, subject: t.subject, referenceFormat: t.referenceFormat, effectiveFrom: t.effectiveFrom ?? "", gazetteId: t.gazetteId ?? "", salutation: t.salutation, openingMessage: t.openingMessage, clauses: [...t.clauses], showSalaryTables: t.showSalaryTables, salaryTablesAfter: t.salaryTablesAfter, closingMessage: t.closingMessage, signatureTitle: t.signatureTitle, signatureCompany: t.signatureCompany, footerNote: t.footerNote });
const STATUS_TONE = { ACTIVE: "good", SUPERSEDED: "info", INACTIVE: "neutral" };

function LetterMaster({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [data, setData] = useState(initial);
  const firstCode = initial.types.find((t) => t.version)?.code ?? initial.types[0]?.code ?? "";
  const [code, setCode] = useState(firstCode);
  const versionsOf = (d, c) => d.templates.filter((t) => t.type === c);
  const startForm = (d, c) => {
    const list = versionsOf(d, c);
    const t = list.find((x) => x.status === "ACTIVE") ?? list[0];
    return t ? formOf(t) : blankTemplate(initial.today);
  };
  const [form, setForm] = useState(() => startForm(initial, firstCode));
  const [newType, setNewType] = useState("");
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const focus = useRef({ kind: "field", key: "openingMessage", el: null });
  const can = initial.can_edit;
  const type = data.types.find((t) => t.code === code);
  const versions = versionsOf(data, code);
  const editing = form.version ? versions.find((v) => v.version === form.version) : null;
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const pick = (c) => (setCode(c), setForm(startForm(data, c)));
  const after = (res, keepCode = code, version = null) => {
    if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
    toast.add({ title: res.data.message, type: "success" });
    const d = { ...data, types: res.data.types, templates: res.data.templates };
    setData(d);
    if (version) {
      const t = d.templates.find((x) => x.type === keepCode && x.version === version);
      if (t) setForm(formOf(t));
    }
  };
  const save = () =>
    start(async () => {
      const res = await act("ltSaveTemplate", { type: code, version: form.version, title: form.title, subject: form.subject, reference_format: form.referenceFormat, effective_from: form.effectiveFrom || null, gazette_id: form.gazetteId || null, salutation: form.salutation, opening_message: form.openingMessage, clauses: form.clauses, show_salary_tables: form.showSalaryTables, salary_tables_after: Number(form.salaryTablesAfter) || 0, closing_message: form.closingMessage, signature_title: form.signatureTitle, signature_company: form.signatureCompany, footer_note: form.footerNote });
      after(res, code, res?.data?.version);
    });
  const insert = (key) => {
    const token = `{{${key}}}`;
    const f = focus.current;
    const el = f.el;
    const current = f.kind === "clause" ? form.clauses[f.index] ?? "" : form[f.key] ?? "";
    const pos = el && typeof el.selectionStart === "number" ? el.selectionStart : current.length;
    const next = `${current.slice(0, pos)}${current.slice(0, pos) && !current.slice(0, pos).endsWith(" ") ? " " : ""}${token}${current.slice(pos)}`;
    if (f.kind === "clause") setForm((x) => ({ ...x, clauses: x.clauses.map((c, i) => (i === f.index ? next : c)) }));
    else set(f.key, next);
  };
  const track = (target) => ({ onFocus: (e) => (focus.current = { ...target, el: e.target }), onSelect: (e) => (focus.current = { ...target, el: e.target }) });
  const sample = data.sample ?? { employeeNumber: "EMP-001", cardNumber: "1001", name: "Md. Jamal Hossen", designation: "Supervisor (Mechanics)", department: "Mechanics", section: "Maintenance", floor: "2nd Floor", grade: "Grade-4", employmentType: "Worker", joiningDate: initial.today, companyGross: 35000, gazetteName: "RMG Minimum Wage 2023 (Non-EPZ)", gazetteYear: "2023", address: "", wage: null };
  const preview = useMemo(() => mergeLetter(form, sample, { companyName: initial.letterhead.company, letterDate: initial.today, effectiveDate: form.effectiveFrom || sample.joiningDate, probationMonths: 6 }, sampleRef(form.referenceFormat, initial.today)), [form, sample, initial]);
  const textField = (k, label, extra = {}) => (
    <Field label={label} hint={extra.hint}>
      <Input value={form[k] ?? ""} onChange={(e) => set(k, e.target.value)} aria-label={label} {...(extra.track ? track({ kind: "field", key: k }) : {})} />
    </Field>
  );
  const areaField = (k, label, rows = 3) => (
    <Field label={label}>
      <Textarea rows={rows} value={form[k] ?? ""} onChange={(e) => set(k, e.target.value)} aria-label={label} {...track({ kind: "field", key: k })} />
    </Field>
  );
  const move = (i, d) => setForm((f) => {
    const c = [...f.clauses];
    [c[i], c[i + d]] = [c[i + d], c[i]];
    return { ...f, clauses: c };
  });
  return (
    <div className="grid items-start gap-4 xl:grid-cols-[15rem_minmax(0,1fr)_minmax(0,26rem)]">
      <section className={cn(card, "flex flex-col gap-3 p-4")}>
        <Head icon={FileText} title={w("letter_types")} sub={w("n_types", { n: data.types.length })} />
        <ul className="flex flex-col gap-0.5">
          {data.types.map((t) => (
            <li key={t.code}>
              <button type="button" onClick={() => pick(t.code)} className={cn("flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-[13px] font-semibold", t.code === code ? "bg-brand text-brand-foreground" : "hover:bg-surface-muted", !t.active && t.code !== code && "text-foreground-muted line-through")}>
                <span className="truncate">{t.name}</span>
                <span className={cn("text-xs", t.code === code ? "text-brand-foreground/80" : "text-foreground-muted")}>{t.version ? `v${t.version}` : "—"}</span>
              </button>
            </li>
          ))}
        </ul>
        {can ? (
          <div className="flex flex-col gap-2 border-t border-border pt-3">
            <Input value={newType} onChange={(e) => setNewType(e.target.value)} placeholder={w("ph.new_type")} aria-label={w("new_type")} />
            <Button size="sm" variant="outline" disabled={!newType.trim() || pending} onClick={() => start(async () => {
              const res = await act("ltAddType", { name: newType });
              after(res);
              if (res?.status === "success") {
                setNewType("");
                setCode(res.data.code);
                setForm(blankTemplate(initial.today));
              }
            })}>
              <Plus /> {w("add_type")}
            </Button>
            {type ? (
              <Button size="sm" variant="ghost" disabled={pending} onClick={() => start(async () => after(await act("ltToggleType", code)))}>
                {type.active ? w("deactivate_type") : w("activate_type")}
              </Button>
            ) : null}
          </div>
        ) : null}
      </section>
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <Head icon={FilePlus2} title={w("template_of", { name: type?.name ?? "" })} sub={editing ? w("editing_v", { v: editing.version, status: w(`tpl_status.${editing.status}`).toLowerCase() }) : w("new_template")} />
            {can ? (
              <Button size="sm" variant="ghost" onClick={() => setForm(blankTemplate(initial.today))}>
                <Plus /> {w("blank")}
              </Button>
            ) : null}
          </div>
          {!can ? <p className="rounded-lg bg-surface-muted px-3 py-2 text-xs text-foreground-muted">{w("read_only")}</p> : null}
          <fieldset disabled={!can} className="flex flex-col gap-4">
            <div className="grid gap-4 md:grid-cols-2">
              {textField("title", w("f.title"), { track: true })}
              {textField("subject", w("f.subject"), { track: true })}
              {textField("referenceFormat", w("f.reference_format"), { hint: w("hint.reference_format") })}
              <Field label={w("f.effective_date")}>
                <Input type="date" value={form.effectiveFrom ?? ""} onChange={(e) => set("effectiveFrom", e.target.value)} aria-label={w("f.effective_date")} />
              </Field>
              <Field label={w("f.gazette")} hint={w("hint.gazette")}>
                <Select value={form.gazetteId ?? ""} onValueChange={(v) => set("gazetteId", v ?? "")} options={[{ value: "", label: w("own_gazette") }, ...data.gazettes]} placeholder={w("own_gazette")} aria-label={w("f.gazette")} />
              </Field>
              {textField("salutation", w("f.salutation"), { track: true })}
            </div>
            {areaField("openingMessage", w("f.main_body"), 4)}
            <div className="flex flex-col gap-2">
              <div className="flex items-center justify-between">
                <span className="text-[13px] font-semibold">{w("terms", { n: form.clauses.length })}</span>
                <Button size="sm" variant="ghost" onClick={() => set("clauses", [...form.clauses, ""])}>
                  <Plus /> {w("add_clause")}
                </Button>
              </div>
              {form.clauses.length === 0 ? <p className="text-xs text-foreground-muted">{w("no_clauses")}</p> : null}
              {form.clauses.map((c, i) => (
                <div key={i} className="flex items-start gap-2">
                  <span className="w-6 pt-2 text-right text-[13px] font-semibold">{i + 1}.</span>
                  <Textarea rows={2} className="flex-1" value={c} onChange={(e) => set("clauses", form.clauses.map((x, j) => (j === i ? e.target.value : x)))} aria-label={w("clause_n", { n: i + 1 })} {...track({ kind: "clause", index: i })} />
                  <div className="flex flex-col gap-1">
                    <Button size="sm" variant="ghost" aria-label={w("move_up")} disabled={i === 0} onClick={() => move(i, -1)}>
                      <ArrowUp />
                    </Button>
                    <Button size="sm" variant="ghost" aria-label={w("move_down")} disabled={i === form.clauses.length - 1} onClick={() => move(i, 1)}>
                      <ArrowDown />
                    </Button>
                    <Button size="sm" variant="ghost" className="text-danger" aria-label={w("remove_clause")} onClick={() => set("clauses", form.clauses.filter((_, j) => j !== i))}>
                      <Trash2 />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
            <div className="grid gap-4 rounded-lg border border-border p-3 md:grid-cols-[1fr_12rem]">
              <label className="flex items-start gap-2 text-[13px]">
                <Checkbox checked={form.showSalaryTables} onCheckedChange={(v) => set("showSalaryTables", !!v)} aria-label={w("f.salary_tables")} />
                <span>
                  <span className="font-semibold">{w("f.salary_tables")}</span>
                  <span className="block text-xs text-foreground-muted">{w("hint.salary_tables")}</span>
                </span>
              </label>
              <Field label={w("f.tables_after")} hint={w("hint.tables_after")}>
                <Input type="number" min="0" value={form.salaryTablesAfter} onChange={(e) => set("salaryTablesAfter", e.target.value)} aria-label={w("f.tables_after")} />
              </Field>
            </div>
            {areaField("closingMessage", w("f.closing"), 2)}
            <div className="grid gap-4 md:grid-cols-2">
              {textField("signatureTitle", w("f.signature_title"), { track: true })}
              {textField("signatureCompany", w("f.signature_company"), { track: true })}
            </div>
            {areaField("footerNote", w("f.footer_note"), 2)}
          </fieldset>
          {can ? (
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
              <p className="text-xs text-foreground-muted">{editing && editing.letterCount === 0 ? w("save_in_place", { v: editing.version }) : w("save_new")}</p>
              <Button onClick={save} loading={pending}>
                <Save /> {w("save_template")}
              </Button>
            </div>
          ) : null}
        </section>
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <Head title={w("employee_fields")} sub={w("employee_fields_sub")} />
          <div className="flex flex-wrap gap-1.5">
            {LETTER_FIELDS.map((f) => (
              <button key={f.key} type="button" disabled={!can} title={f.label} onMouseDown={(e) => e.preventDefault()} onClick={() => insert(f.key)} className="rounded-md border border-border px-2 py-1 font-mono text-xs hover:border-brand hover:text-brand disabled:opacity-60">
                {`{{${f.key}}}`}
              </button>
            ))}
          </div>
        </section>
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <Head icon={FileClock} title={w("versions")} sub={w("versions_sub")} />
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full min-w-[640px] text-sm">
              <thead>
                <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                  {["version", "status", "effective", "letters", "updated_by", "action"].map((c) => (
                    <th key={c} className="px-3 py-2.5 font-semibold">
                      {w(`col.${c}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {versions.length ? (
                  versions.map((v) => (
                    <tr key={v.version} className="border-b border-border last:border-0">
                      <td className="px-3 py-2 font-semibold">v{v.version}</td>
                      <td className="px-3 py-2">
                        <Pill t={STATUS_TONE[v.status]}>{w(`tpl_status.${v.status}`)}</Pill>
                      </td>
                      <td className="px-3 py-2 whitespace-nowrap">{dmy(v.effectiveFrom) || "—"}</td>
                      <td className="px-3 py-2 tabular-nums">{v.letterCount}</td>
                      <td className="px-3 py-2">{v.updatedBy}</td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap gap-1">
                          <Button size="sm" variant="outline" onClick={() => setForm(formOf(v))}>
                            {w("open")}
                          </Button>
                          {can ? (
                            <Button size="sm" variant="outline" disabled={pending} onClick={() => start(async () => after(await act("ltTemplateStatus", code, v.version, { action: v.status === "ACTIVE" ? "deactivate" : "activate" })))}>
                              {v.status === "ACTIVE" ? w("deactivate") : w("activate")}
                            </Button>
                          ) : null}
                          {can && v.letterCount === 0 ? (
                            <Button size="sm" variant="ghost" className="text-danger" onClick={() => setAsking(v)}>
                              {w("delete")}
                            </Button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-5 py-8 text-center text-foreground-muted">
                      {w("no_versions")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
      <section className={cn(card, "sticky top-4 flex flex-col gap-3 p-5")}>
        <Head title={w("live_preview")} sub={w("live_preview_sub", { code: sample.employeeNumber })} />
        <FitLetter letter={preview} head={initial.letterhead} />
      </section>
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={w("delete_version_title", { v: asking?.version ?? "" })}
        description={w("delete_version_text")}
        confirmLabel={w("delete")}
        cancelLabel={w("cancel")}
        loading={pending}
        onConfirm={() => start(async () => {
          const res = await act("ltDeleteTemplate", code, asking.version);
          setAsking(null);
          after(res);
          if (res?.status === "success") setForm(startForm({ ...data, templates: res.data.templates }, code));
        })}
      />
    </div>
  );
}

/* ── Generate Letter ─────────────────────────────────────────────── */

const blankFilters = { section: "", floor: "", department: "", designation: "", grade: "", employee_type: "", join_from: "", join_to: "", status: "ACTIVE" };

function Detail({ label, value }) {
  return (
    <div className="flex justify-between gap-3 border-b border-border py-1.5 text-[13px] last:border-0">
      <dt className="text-foreground-muted">{label}</dt>
      <dd className="text-right font-semibold">{value || "—"}</dd>
    </div>
  );
}

function EmployeeInfo({ w, e, effectiveDate, probation }) {
  const wage = e.wage ? gazetteWage(e.wage) : null;
  return (
    <>
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <Head icon={UserRound} title={w("employee_info")} sub={w("employee_info_sub")} />
        <div className="grid gap-4 md:grid-cols-[5rem_1fr_1fr]">
          <div className="grid size-20 place-items-center rounded-lg border border-border bg-surface-muted">
            <UserRound className="size-9 text-foreground-muted" />
          </div>
          <dl>
            <Detail label={w("d.number")} value={e.employeeNumber} />
            <Detail label={w("d.card")} value={e.cardNumber} />
            <Detail label={w("d.name")} value={e.name} />
            <Detail label={w("d.designation")} value={e.designation} />
            <Detail label={w("d.department")} value={e.department} />
            <Detail label={w("d.section")} value={e.section} />
            <Detail label={w("d.floor")} value={e.floor} />
            <Detail label={w("d.joining")} value={dmy(e.joiningDate)} />
          </dl>
          <dl>
            <Detail label={w("d.grade")} value={e.grade} />
            <Detail label={w("d.type")} value={e.employmentType} />
            <Detail label={w("d.probation")} value={probation > 0 ? w("months", { n: probation }) : "—"} />
            <Detail label={w("d.structure")} value={e.gazetteName} />
            <Detail label={w("d.gazette_year")} value={e.gazetteYear} />
            <Detail label={w("d.company_gross")} value={e.companyGross === null ? "—" : num(e.companyGross)} />
            <div className="flex justify-between gap-3 py-1.5 text-[13px]">
              <dt className="text-foreground-muted">{w("d.status")}</dt>
              <dd>
                <Pill t={e.status === "ACTIVE" ? "good" : "neutral"}>{w(`emp_status.${e.status}`)}</Pill>
              </dd>
            </div>
          </dl>
        </div>
      </section>
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <Head title={w("gazette_salary")} sub={e.gazetteName ? w("gazette_salary_sub", { name: e.gazetteName }) : null} />
        {wage ? (
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-surface-muted text-left text-xs text-foreground-muted">
                  <th className="px-3 py-2">{w("col.salary_head")}</th>
                  <th className="px-3 py-2 text-right">{w("col.gazette_amount")}</th>
                  <th className="px-3 py-2">{w("col.remarks")}</th>
                </tr>
              </thead>
              <tbody>
                <tr className="border-t border-border">
                  <td className="px-3 py-1.5">{w("grade_gazette")}</td>
                  <td className="px-3 py-1.5 text-right">{e.wage.grade}</td>
                  <td className="px-3 py-1.5">{e.wage.category}</td>
                </tr>
                {wage.rows.map(([k, v]) => (
                  <tr key={k} className="border-t border-border">
                    <td className="px-3 py-1.5">{k}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{taka(v)}</td>
                    <td className="px-3 py-1.5 text-foreground-muted">{w("as_per", { year: e.gazetteYear ?? "" })}</td>
                  </tr>
                ))}
                <tr className="border-t border-border bg-brand-subtle font-semibold">
                  <td className="px-3 py-1.5">{w("gross_a_as_per", { year: e.gazetteYear ?? "" })}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{taka(wage.grossA)}</td>
                  <td />
                </tr>
              </tbody>
            </table>
          </div>
        ) : (
          <p className="rounded-lg bg-warning-subtle px-3 py-2 text-xs text-warning">{w("no_wage", { grade: e.grade || w("this_grade"), gazette: e.gazetteName || w("the_gazette") })}</p>
        )}
      </section>
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <Head title={w("company_salary")} sub={w("company_salary_sub")} />
        <dl className="grid gap-x-6 md:grid-cols-2">
          <Detail label={w("offered")} value={e.companyGross === null ? "—" : taka(e.companyGross)} />
          <Detail label={w("f.effective_date")} value={dmy(effectiveDate)} />
          <Detail label={w("pay_frequency")} value={w("monthly")} />
          <Detail label={w("festival_bonus")} value={w("two_per_year")} />
        </dl>
      </section>
    </>
  );
}

function GenerateLetter({ initial, act }) {
  const w = useWords();
  const toast = useToastManager();
  const head = initial.letterhead;
  const [code, setCode] = useState(() => initial.letter_types.find((t) => t.template)?.code ?? initial.letter_types[0]?.code ?? "");
  const [mode, setMode] = useState("single");
  const [q, setQ] = useState("");
  const [one, setOne] = useState(null);
  const [filters, setFilters] = useState(blankFilters);
  const [many, setMany] = useState([]);
  const [ticked, setTicked] = useState(new Set());
  const [index, setIndex] = useState(0);
  const [letterDate, setLetterDate] = useState(initial.today);
  const [effective, setEffective] = useState("");
  const [probation, setProbation] = useState("6");
  const [previewing, setPreviewing] = useState(false);
  const [issued, setIssued] = useState(null);
  const [loading, startLoad] = useTransition();
  const [busy, startBusy] = useTransition();
  const type = initial.letter_types.find((t) => t.code === code);
  const tpl = type?.template ?? null;
  const people = mode === "single" ? (one ? [one] : []) : many.filter((e) => ticked.has(e.id));
  const current = people[Math.min(index, Math.max(0, people.length - 1))] ?? null;
  const opts = (e) => ({ companyName: head.company, letterDate, effectiveDate: effective || e.joiningDate, probationMonths: Number(probation) || 0 });
  const key = JSON.stringify([code, tpl?.version, people.map((e) => e.id).sort(), letterDate, effective, probation]);
  // Once generated, the preview carries the employee's issued reference.
  const issuedRef = issued && issued.key === key && current ? issued.letters.find((l) => l.employee?.id === current.id)?.ref : null;
  const letter = tpl && current ? mergeLetter(tpl, current, opts(current), issuedRef ?? w("issued_on_generate")) : null;
  const can = initial.can_generate;
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v }));
  const search = (value = q, typeCode = code) =>
    startLoad(async () => {
      if (!value.trim()) return toast.add({ title: w("err.enter_number"), type: "warning" });
      const res = await act("ltEmployee", { q: value.trim(), type: typeCode });
      setOne(res?.data ?? null);
      setIndex(0);
      setIssued(null);
      if (!res?.data) toast.add({ title: w("err.no_match", { q: value.trim() }), type: "warning" });
    });
  const load = (typeCode = code) =>
    startLoad(async () => {
      const res = await act("ltEmployees", { ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== "")), type: typeCode });
      if (res?.status !== "success") return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      setMany(res.data.employees);
      setTicked(new Set(res.data.employees.map((e) => e.id)));
      setIndex(0);
      setIssued(null);
      toast.add({ title: res.data.truncated ? w("truncated", { n: res.data.employees.length }) : res.data.message, type: res.data.truncated ? "warning" : "info" });
    });
  const ensure = async () => {
    if (!tpl) {
      toast.add({ title: w("err.no_template", { name: type?.name ?? "" }), type: "warning" });
      return null;
    }
    if (!people.length) {
      toast.add({ title: mode === "single" ? w("err.search_first") : w("err.tick_first"), type: "warning" });
      return null;
    }
    if (issued && issued.key === key) return issued;
    const res = await act("ltGenerate", { type: code, employees: people.map((e) => e.id), letter_date: letterDate, effective_date: effective || null, probation_months: Number(probation) || 0 });
    if (res?.status !== "success") {
      toast.add({ title: firstError(res) || w("err.save"), type: "danger" });
      return null;
    }
    toast.add({ title: res.data.message, type: "success" });
    const out = { key, refs: res.data.refs, letters: res.data.letters };
    setIssued(out);
    return out;
  };
  const merged = (letters) => letters.map((l) => mergeLetter(l.template, l.employee, { companyName: head.company, letterDate: l.letter_date, effectiveDate: l.effective_date || l.employee.joiningDate, probationMonths: l.probation_months }, l.ref));
  const print = (asPdf) => {
    const win = window.open("", "_blank");
    startBusy(async () => {
      const out = await ensure();
      if (!out) return win?.close();
      await act("ltPrinted", { refs: out.refs });
      printLetters(merged(out.letters), head, { title: out.refs.length === 1 ? out.refs[0] : w("n_letters", { n: out.refs.length }), win, autoPrint: true });
      if (asPdf) toast.add({ title: w("pdf_hint"), type: "info" });
    });
  };
  const email = () => {
    if (mode !== "single" || !one) return toast.add({ title: w("err.email_one"), type: "warning" });
    const win = window.open("", "_blank");
    startBusy(async () => {
      const out = await ensure();
      if (!out) return win?.close();
      printLetters(merged(out.letters), head, { title: out.refs[0], win, autoPrint: true });
      const subject = `${tpl?.subject || type?.name || w("letter")} — ${out.refs[0]}`;
      const body = [w("mail.dear", { name: one.name }), "", w("mail.attached", { type: type?.name ?? w("letter"), ref: out.refs[0] }), "", w("mail.regards"), w("mail.hr"), head.company].join("\n");
      window.location.href = `mailto:${encodeURIComponent(one.email ?? "")}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      toast.add({ title: one.email ? w("mail.opening") : w("mail.no_email"), type: "info" });
    });
  };
  const columns = [
    { key: "employee", label: w("col.employee"), read: (e) => `${e.employeeNumber} ${e.name} ${e.cardNumber ?? ""}`, render: (e) => (<div><div className="font-semibold">{e.name}</div><div className="text-xs text-foreground-muted">{e.employeeNumber}{e.cardNumber ? ` · ${w("card_n", { n: e.cardNumber })}` : ""}</div></div>) },
    { key: "designation", label: w("col.designation") },
    { key: "section", label: w("col.section") },
    { key: "floor", label: w("col.floor") },
    { key: "joiningDate", label: w("col.joined"), read: (e) => dmy(e.joiningDate) || "—" },
    { key: "grossA", label: w("col.gross_a"), right: true, render: (e) => (e.wage ? taka(gazetteWage(e.wage).grossA) : <span className="text-foreground-muted">{w("not_set")}</span>) },
    { key: "companyGross", label: w("col.company_gross"), right: true, render: (e) => taka(e.companyGross) },
  ];
  const opt = (k, label, list, all) => (
    <Field label={label}>
      <Select value={filters[k]} onValueChange={(v) => setF(k, v ?? "")} options={[{ value: "", label: all }, ...list]} placeholder={all} aria-label={label} />
    </Field>
  );
  return (
    <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,32rem)]">
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <Head icon={FilePlus2} title={w("generate_title")} sub={w("generate_sub")} />
          <div className="grid gap-4 md:grid-cols-2">
            <Field label={w("f.letter_type")} required hint={tpl ? w("hint.template_v", { v: tpl.version, date: dmy(tpl.effectiveFrom) }) : w("hint.no_template")}>
              <Select value={code} onValueChange={(v) => {
                setCode(v ?? "");
                setIssued(null);
                if (mode === "single" && one) search(one.employeeNumber, v);
                if (mode === "all" && many.length) load(v);
              }} options={initial.letter_types.map((t) => ({ value: t.code, label: t.template ? t.name : w("no_template_label", { name: t.name }) }))} aria-label={w("f.letter_type")} />
            </Field>
            <Field label={w("f.employee_number")}>
              <div className="flex gap-2" onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), setMode("single"), search())}>
                <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={w("ph.employee_number")} aria-label={w("f.employee_number")} />
                <Button onClick={() => (setMode("single"), search())} loading={loading && mode === "single"}>
                  <Search /> {w("search")}
                </Button>
              </div>
            </Field>
          </div>
          <div className="grid gap-4 md:grid-cols-3">
            {opt("section", w("f.section"), initial.sections, w("all_sections"))}
            {opt("floor", w("f.floor"), initial.floors, w("all_floors"))}
            <Field label={w("f.employee")}>
              <Select value={mode} onValueChange={(v) => (setMode(v === "all" ? "all" : "single"), setIndex(0))} options={[{ value: "single", label: w("single") }, { value: "all", label: w("all_employees") }]} aria-label={w("f.employee")} />
            </Field>
          </div>
          {mode === "all" ? (
            <div className="flex flex-col gap-4 rounded-lg border border-border p-3">
              <div className="grid gap-4 md:grid-cols-3">
                {opt("department", w("f.department"), initial.departments, w("all_departments"))}
                {opt("designation", w("f.designation"), initial.designations, w("all_designations"))}
                {opt("grade", w("f.grade"), initial.grades, w("all_grades"))}
                {opt("employee_type", w("f.employee_type"), initial.employee_types, w("all_types"))}
                <Field label={w("f.join_from")}>
                  <Input type="date" value={filters.join_from} onChange={(e) => setF("join_from", e.target.value)} aria-label={w("f.join_from")} />
                </Field>
                <Field label={w("f.join_to")}>
                  <Input type="date" value={filters.join_to} onChange={(e) => setF("join_to", e.target.value)} aria-label={w("f.join_to")} />
                </Field>
              </div>
              <div className="flex flex-wrap items-end gap-3">
                <Field label={w("f.employment_status")} className="w-56">
                  <Select value={filters.status} onValueChange={(v) => setF("status", v ?? "ACTIVE")} options={[{ value: "ACTIVE", label: w("active_employees") }, { value: "ALL", label: w("all_employees_status") }]} aria-label={w("f.employment_status")} />
                </Field>
                <Button onClick={() => load()} loading={loading && mode === "all"}>
                  <Users /> {w("load_employees")}
                </Button>
              </div>
            </div>
          ) : null}
          <div className="grid gap-4 border-t border-border pt-4 md:grid-cols-3">
            <Field label={w("f.letter_date")} required>
              <Input type="date" value={letterDate} onChange={(e) => setLetterDate(e.target.value)} aria-label={w("f.letter_date")} />
            </Field>
            <Field label={w("f.effective_date")} hint={w("hint.effective")}>
              <Input type="date" value={effective} onChange={(e) => setEffective(e.target.value)} aria-label={w("f.effective_date")} />
            </Field>
            <Field label={w("f.probation")}>
              <div className="flex items-center gap-2">
                <Input type="number" min="0" value={probation} onChange={(e) => setProbation(e.target.value)} aria-label={w("f.probation")} />
                <span className="text-xs text-foreground-muted">{w("months_word")}</span>
              </div>
            </Field>
          </div>
        </section>
        {mode === "single" ? (
          one ? (
            <EmployeeInfo w={w} e={one} effectiveDate={effective || one.joiningDate} probation={Number(probation) || 0} />
          ) : (
            <section className={cn(card, "p-5 text-[13px] text-foreground-muted")}>{w("search_hint")}</section>
          )
        ) : (
          <section className={cn(card, "flex flex-col gap-2 pt-5")}>
            <div className="px-5">
              <Head icon={Users} title={w("employees_selected", { a: ticked.size, b: many.length })} sub={w("run_sub")} />
            </div>
            <Table w={w} columns={columns} rows={many} rowKey={(e) => e.id} searchPh={false} empty={w("empty.run")} minWidth={900}
              selection={{ selected: ticked, onToggle: (k) => setTicked((s) => { const n = new Set(s); n.has(k) ? n.delete(k) : n.add(k); return n; }), onToggleAll: (ks) => setTicked((s) => { const n = new Set(s); const all = ks.every((k) => n.has(k)); ks.forEach((k) => (all ? n.delete(k) : n.add(k))); return n; }) }} />
          </section>
        )}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <Head title={w("preview_options")} sub={can ? w("preview_options_sub") : w("needs_generate")} />
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => setPreviewing(true)} disabled={!letter}>
              <Eye /> {mode === "all" ? w("preview_all") : w("preview_letter")}
            </Button>
            <Button variant="outline" onClick={() => startBusy(async () => void (await ensure()))} disabled={busy || !can || !letter}>
              <FilePlus2 /> {mode === "all" ? w("generate_all") : w("generate_letter")}
            </Button>
            <Button onClick={() => print(false)} disabled={busy || !can || !letter}>
              <Printer /> {mode === "all" ? w("print_all") : w("print_a4")}
            </Button>
            <Button variant="danger" onClick={() => print(true)} disabled={busy || !can || !letter}>
              <Download /> {mode === "all" ? w("export_pdf") : w("download_pdf")}
            </Button>
            {mode === "single" ? (
              <Button variant="secondary" onClick={email} disabled={busy || !can || !letter}>
                <Mail /> {w("send_email")}
              </Button>
            ) : null}
          </div>
          {issued ? <p className="text-xs text-success">{w("issued_refs", { refs: issued.refs.slice(0, 5).join(", ") + (issued.refs.length > 5 ? " …" : "") })}</p> : null}
        </section>
      </div>
      <section className={cn(card, "sticky top-4 flex flex-col gap-3 p-5")}>
        <div className="flex items-start justify-between gap-2">
          <Head title={w("letter_preview")} sub={letter ? w("a4_as_prints") : w("appears_here")} />
          {people.length > 1 ? (
            <span className="flex items-center gap-1 text-xs">
              <Button size="sm" variant="ghost" aria-label={w("previous_employee")} disabled={index <= 0} onClick={() => setIndex((i) => Math.max(0, i - 1))}>
                <ChevronLeft />
              </Button>
              <span className="tabular-nums">{Math.min(index, people.length - 1) + 1} / {people.length}</span>
              <Button size="sm" variant="ghost" aria-label={w("next_employee")} disabled={index >= people.length - 1} onClick={() => setIndex((i) => Math.min(people.length - 1, i + 1))}>
                <ChevronRight />
              </Button>
            </span>
          ) : null}
        </div>
        {letter ? <FitLetter letter={letter} head={head} /> : <div className="grid min-h-96 place-items-center rounded-lg border border-dashed border-border px-6 text-center text-[13px] text-foreground-muted">{tpl ? w("no_employee_yet") : w("no_active_template")}</div>}
      </section>
      <Modal open={previewing} onOpenChange={setPreviewing} title={w("letter_preview")} className="max-w-4xl">
        {letter ? <FitLetter letter={letter} head={head} /> : null}
      </Modal>
    </div>
  );
}

/* ── Letter History / Letter Reports ─────────────────────────────── */

const blankHistory = { query: "", type: "", department: "", section: "", floor: "", generated_by: "", from: "", to: "" };
const stampOf = (s) => {
  if (!s) return "";
  const d = new Date(/(Z|[+-]\d\d:?\d\d)$/.test(s) ? s : `${s.replace(" ", "T")}Z`);
  return Number.isNaN(d.getTime()) ? s : `${dmy(d.toISOString().slice(0, 10))} ${d.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase())}`;
};

function LetterHistory({ initial, act, mode = "history" }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial.rows);
  const [f, setF] = useState(blankHistory);
  const [ticked, setTicked] = useState(new Set());
  const [busy, start] = useTransition();
  const register = mode === "register";
  const shown = useMemo(() => {
    const q = f.query.trim().toLowerCase();
    return rows.filter((r) => (!f.type || r.type === f.type) && (!f.department || r.department === f.department) && (!f.section || r.section === f.section) && (!f.floor || r.floor === f.floor)
      && (!f.generated_by || r.generated_by === f.generated_by) && (!f.from || r.letter_date >= f.from) && (!f.to || r.letter_date <= f.to)
      && (!q || `${r.ref} ${r.code} ${r.card_no ?? ""} ${r.name}`.toLowerCase().includes(q)));
  }, [rows, f]);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const open = (refs, how) => {
    if (!refs.length) return;
    const win = window.open("", "_blank");
    start(async () => {
      const res = await act("ltPrint", { refs });
      if (res?.status !== "success") {
        win?.close();
        return toast.add({ title: firstError(res) || w("err.load"), type: "danger" });
      }
      const head = res.data.letterhead;
      const letters = res.data.letters.map((l) => mergeLetter(l.template, l.employee, { companyName: head.company, letterDate: l.letter_date, effectiveDate: l.effective_date || l.employee.joiningDate, probationMonths: l.probation_months }, l.ref));
      printLetters(letters, head, { title: refs.length === 1 ? refs[0] : w("n_letters", { n: refs.length }), win, autoPrint: how !== "view" });
      if (how !== "view" && initial.can_generate) {
        await act("ltPrinted", { refs });
        setRows((rs) => rs.map((r) => (refs.includes(r.ref) ? { ...r, status: "PRINTED", print_count: r.print_count + 1 } : r)));
        if (how === "pdf") toast.add({ title: w("pdf_hint"), type: "info" });
      }
    });
  };
  const exportCsv = () =>
    downloadCsv(`${register ? "letter-register" : "letter-history"}.csv`, ["sl", "ref_no", "employee_no", "card_no", "employee_name", "letter_type", "designation", "department", "section", "floor", "letter_date", "generated", "generated_by", "version", "status", "prints"].map((k) => w(`csv.${k}`)),
      shown.map((r, i) => [i + 1, r.ref, r.code, r.card_no, r.name, r.letter_type, r.designation, r.department, r.section, r.floor, dmy(r.letter_date), stampOf(r.generated_at), r.generated_by, `v${r.version}`, w(`letter_status.${r.status}`), r.print_count]));
  const printRegister = () =>
    printDocument({
      title: w("letter_register"),
      orientation: "landscape",
      meta: [[w("f.letter_type"), f.type ? (initial.letter_types.find((t) => t.value === f.type)?.label ?? f.type) : w("all_letter_types")], [w("f.department"), f.department || w("all")], [w("f.section"), f.section || w("all")], [w("f.floor"), f.floor || w("all")], [w("f.date_from"), dmy(f.from) || "—"], [w("f.date_to"), dmy(f.to) || "—"], [w("tile.letters"), shown.length]],
      metaColumns: 4,
      sections: [{ columns: [{ label: "#", align: "right" }, { label: w("col.letter_id") }, { label: w("col.letter_type") }, { label: w("col.employee") }, { label: w("col.department") }, { label: w("col.section") }, { label: w("col.floor") }, { label: w("col.letter_date") }, { label: w("col.generated") }, { label: w("col.version") }, { label: w("col.status") }],
        rows: shown.map((r, i) => [i + 1, r.ref, r.letter_type, `${r.code} ${r.name}`, r.department ?? "—", r.section ?? "—", r.floor ?? "—", r.letter_date, `${stampOf(r.generated_at)} · ${r.generated_by ?? ""}`, `v${r.version}`, w(`letter_status.${r.status}`)]) }],
      signatures: true,
    });
  const columns = [
    { key: "ref", label: w("col.letter_id"), render: (r) => <span className="font-mono text-xs font-semibold whitespace-nowrap">{r.ref}</span> },
    { key: "letter_type", label: w("col.letter_type") },
    { key: "employee", label: w("col.employee"), read: (r) => `${r.code} ${r.name} ${r.card_no ?? ""}`, render: (r) => (<div><div className="font-semibold">{r.name}</div><div className="text-xs whitespace-nowrap text-foreground-muted">{r.code}{r.card_no ? ` · ${w("card_n", { n: r.card_no })}` : ""}</div></div>) },
    { key: "department", label: w("col.department") },
    { key: "section", label: w("col.section") },
    { key: "floor", label: w("col.floor") },
    { key: "letter_date", label: w("col.letter_date"), read: (r) => dmy(r.letter_date), nowrap: true },
    { key: "generated", label: w("col.generated"), read: (r) => stampOf(r.generated_at), render: (r) => (<div className="text-xs"><div className="whitespace-nowrap">{stampOf(r.generated_at)}</div><div className="text-foreground-muted">{r.generated_by}</div></div>) },
    { key: "version", label: w("col.version"), read: (r) => `v${r.version}` },
    { key: "status", label: w("col.status"), read: (r) => w(`letter_status.${r.status}`), render: (r) => <Pill t={r.status === "PRINTED" ? "good" : "info"}>{r.status === "PRINTED" ? `${w("letter_status.PRINTED")}${r.print_count > 1 ? ` ×${r.print_count}` : ""}` : w("letter_status.GENERATED")}</Pill> },
    {
      key: "action",
      label: w("col.action"),
      render: (r) => (
        <div className="flex gap-1">
          <Button size="sm" variant="ghost" aria-label={w("view_aria", { ref: r.ref })} disabled={busy} onClick={() => open([r.ref], "view")}>
            <Eye />
          </Button>
          <Button size="sm" variant="ghost" aria-label={w("print_aria", { ref: r.ref })} disabled={busy} onClick={() => open([r.ref], "print")}>
            <Printer />
          </Button>
          <Button size="sm" variant="ghost" disabled={busy} onClick={() => open([r.ref], "pdf")}>
            {w("pdf")}
          </Button>
        </div>
      ),
    },
  ];
  const printed = shown.filter((r) => r.status === "PRINTED").length;
  const month = new Date().toISOString().slice(0, 7);
  const pick = (k, label, list, all) => (
    <Field label={label}>
      <Select value={f[k]} onValueChange={(v) => set(k, v ?? "")} options={[{ value: "", label: all }, ...list]} placeholder={all} aria-label={label} />
    </Field>
  );
  return (
    <div className="flex flex-col gap-4">
      {register ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Stat icon={FileText} label={w("tile.letters")} value={shown.length} />
          <Stat icon={Printer} label={w("tile.printed")} value={printed} tone="good" />
          <Stat icon={FileClock} label={w("tile.not_printed")} value={shown.length - printed} tone="warning" />
          <Stat icon={Users} label={w("tile.employees")} value={new Set(shown.map((r) => r.code)).size} />
        </div>
      ) : null}
      <section className={cn(card, "flex flex-col gap-4 pt-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3 px-5">
          <Head icon={register ? FileSpreadsheet : FileClock} title={register ? w("letter_register") : w("letter_history")} sub={initial.truncated ? w("showing_newest", { n: rows.length }) : register ? w("register_sub") : w("history_sub", { n: rows.filter((r) => r.letter_date.startsWith(month)).length })} />
          <div className="flex flex-wrap gap-2">
            {!register && ticked.size > 0 ? (
              <Button size="sm" disabled={busy} onClick={() => open([...ticked], "print")}>
                <Printer /> {w("print_selected", { n: ticked.size })}
              </Button>
            ) : null}
            {register ? (
              <Button size="sm" onClick={printRegister}>
                <Printer /> {w("print_pdf")}
              </Button>
            ) : null}
            <Button size="sm" variant="outline" onClick={exportCsv}>
              <FileSpreadsheet /> {w("excel")}
            </Button>
          </div>
        </div>
        <div className="grid gap-4 px-5 md:grid-cols-2 xl:grid-cols-4">
          <Field label={w("f.search")}>
            <Input value={f.query} onChange={(e) => set("query", e.target.value)} placeholder={w("ph.search")} aria-label={w("f.search")} />
          </Field>
          {pick("type", w("f.letter_type"), initial.letter_types, w("all_letter_types"))}
          {pick("department", w("f.department"), initial.departments, w("all_departments"))}
          {pick("section", w("f.section"), initial.sections, w("all_sections"))}
          {pick("floor", w("f.floor"), initial.floors, w("all_floors"))}
          {pick("generated_by", w("f.generated_by"), initial.generated_by, w("anyone"))}
          <Field label={w("f.date_from")}>
            <Input type="date" value={f.from} onChange={(e) => set("from", e.target.value)} aria-label={w("f.date_from")} />
          </Field>
          <Field label={w("f.date_to")}>
            <Input type="date" value={f.to} onChange={(e) => set("to", e.target.value)} aria-label={w("f.date_to")} />
          </Field>
        </div>
        <div className="px-5">
          <Button size="sm" variant="ghost" onClick={() => setF(blankHistory)}>
            <RotateCcw /> {w("clear_filters")}
          </Button>
        </div>
        <Table w={w} columns={columns} rows={shown} rowKey={(r) => r.ref} searchPh={false} minWidth={1300}
          empty={rows.length === 0 ? w("empty.none") : w("empty.no_match")}
          selection={register ? undefined : { selected: ticked, onToggle: (k) => setTicked((s) => { const n = new Set(s); n.has(k) ? n.delete(k) : n.add(k); return n; }), onToggleAll: (ks) => setTicked((s) => { const n = new Set(s); const all = ks.every((k) => n.has(k)); ks.forEach((k) => (all ? n.delete(k) : n.add(k))); return n; }) }} />
      </section>
    </div>
  );
}

function LetterReports(props) {
  return <LetterHistory {...props} mode="register" />;
}

export { LetterMaster, GenerateLetter, LetterHistory, LetterReports, FitLetter };
