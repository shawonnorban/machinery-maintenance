"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import { ArrowLeft, ArrowRight, Download, ImageOff, Paperclip, Pencil, Plus, Printer, RotateCcw, Save, Search, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { useT } from "@/lib/i18n";

/*
 * HR & Payroll → Employee → Employee Profile (AnnotechERP 24/03), as Brickland
 * lays it out:
 *
 * - Records: the Search panel (Department, Section, Status, part of a code),
 *   then the roster — a search box, Export, a filter under every column,
 *   pages of 10 / 25 / 50 / 100 — each row with Print, SB Print (the service
 *   book), Edit and remove.
 * - New employee / Edit employee: the pinned header (Employee Code, Proxi#,
 *   Company, Office and the photo) and seven tabs — Personal Information, Job
 *   Detail, Contact Detail, Document, Allowance/Deduction, Optional and Back
 *   Ground Check — filled in any order and written in one save; Back and
 *   Continue walk the tabs. Nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";
const TABS = ["personal", "job", "contact", "document", "allowance", "optional", "background"];
const PERSONAL = ["first_name", "last_name", "name", "nick_name", "father_name", "mother_name", "guardian_name", "guardian_relation", "nid", "birth_certificate_no", "date_of_birth", "nationality", "religion", "gender", "marital_status", "child_count", "blood_group", "mobile", "home_phone", "home_phone_ext", "email", "tin"];
const JOB = ["department_id", "section_id", "designation_id", "employment_type_id", "building_id", "floor_id", "line_id", "grade_id", "shift_id", "joining_date", "gross_salary", "status", "separation_date", "active"];
const CONTACT = ["present_address", "present_post_code", "permanent_address", "permanent_post_code", "emergency_name", "emergency_relation", "emergency_mobile", "emergency_address"];
const DOCUMENT = ["passport_no", "passport_issue_place", "passport_issue_date", "passport_expire_date", "driving_license_no", "driving_license_expire"];
const OPTIONAL = ["name_bn", "higher_education", "spouse_name", "spouse_relation", "spouse_employee_code", "stay_with_family", "festival_ot_eligible"];
const FIELDS_OF = { personal: PERSONAL, job: JOB, contact: CONTACT, document: DOCUMENT, optional: OPTIONAL };
const WIDE = ["present_address", "permanent_address", "emergency_address"];
const DATES = ["date_of_birth", "joining_date", "separation_date", "passport_issue_date", "passport_expire_date", "driving_license_expire"];
const CHECKS = ["active", "stay_with_family", "festival_ot_eligible"];
const REQUIRED = ["card_no", "factory_id", "name", "nationality", "religion", "gender", "employment_type_id", "status"];
const COLUMNS = ["code", "name", "section", "designation", "floor", "line", "grade", "shift", "assignment", "business_type", "status"];
const PAGES = [10, 25, 50, 100];
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const taka = (n) => Number(n || 0).toLocaleString("en-US", { maximumFractionDigits: 2 });
const dmy = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—");
const esc = (v) => String(v ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;");
const uid = () => Math.random().toString(36).slice(2);

const blankForm = (opt, extra) => ({
  card_no: "",
  factory_id: opt.factories.length === 1 ? opt.factories[0].value : "",
  ...Object.fromEntries([...PERSONAL, ...JOB, ...CONTACT, ...DOCUMENT, ...OPTIONAL].map((k) => [k, ""])),
  nationality: extra.nationalities[0]?.value ?? "",
  status: "ACTIVE",
  active: true,
  stay_with_family: false,
  festival_ot_eligible: false,
  pay_lines: [],
  checks: [],
});

const toForm = (p, opt, extra) => {
  const f = blankForm(opt, extra);
  for (const k of Object.keys(f)) if (k in p && p[k] !== null && p[k] !== undefined) f[k] = typeof p[k] === "number" ? String(p[k]) : p[k];
  f.factory_id = p.factory_id ?? "";
  f.employment_type_id = p.employment_type_id ?? "";
  for (const k of ["building_id", "floor_id", "department_id", "section_id", "line_id", "designation_id", "grade_id", "shift_id"]) f[k] = p.placement?.[k] ?? "";
  f.gross_salary = String(p.gross_salary ?? "");
  f.pay_lines = (p.pay_lines ?? []).map((l) => ({ ...l, _k: uid(), amount: String(l.amount) }));
  f.checks = (p.checks ?? []).map((c) => ({ ...c, _k: uid() }));
  return f;
};

function payload(f) {
  const out = {};
  for (const [k, v] of Object.entries(f)) if (!["pay_lines", "checks"].includes(k)) out[k] = v === "" ? null : v;
  out.child_count = f.child_count === "" ? null : Number(f.child_count);
  out.gross_salary = f.gross_salary === "" ? null : Number(f.gross_salary);
  out.pay_lines = f.pay_lines.map((l) => ({ pay_element_id: l.pay_element_id || null, type: l.type, core_salary: !!l.core_salary, amount: l.amount === "" ? 0 : Number(l.amount), active: !!l.active, effect_salary: !!l.effect_salary }));
  out.checks = f.checks.map((c) => ({ id: c.id ?? null, check_type: c.check_type, verified_by: c.verified_by || null, verified_on: c.verified_on || null, cleared: !!c.cleared, remarks: c.remarks || null }));
  return out;
}

/** The tab a field (or grid row) sits on — the first error opens it. */
function tabOf(key) {
  if (key.startsWith("pay_lines")) return "allowance";
  if (key.startsWith("checks")) return "background";
  for (const [tab, keys] of Object.entries(FIELDS_OF)) if (keys.includes(key)) return tab;
  return null;
}

function Field({ label, required, hint, error, wide, children }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1.5", wide && "md:col-span-2 xl:col-span-3")}>
      <span className="text-[13px] font-semibold text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? <span className="text-xs text-danger">{error}</span> : hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </div>
  );
}

/** Profile and service-book sheets, as Brickland prints them. */
function grid(pairs) {
  return `<table style="width:100%;border-collapse:collapse;font-size:11px">${pairs
    .reduce((rows, p, i) => (i % 2 ? rows[rows.length - 1].push(p) : rows.push([p]), rows), [])
    .map((r) => `<tr>${r.map(([k, v]) => `<td style="padding:3px 6px;color:#6b7280;width:18%">${esc(k)}</td><td style="padding:3px 6px;font-weight:600;width:32%">${esc(dash(v))}</td>`).join("")}</tr>`)
    .join("")}</table>`;
}

function printProfile(t, p) {
  const f = (k) => t(`emp.prof.f.${k}`);
  const o = (group, v) => (v ? t(`emp.prof.opt.${group}.${v}`) : null);
  // The photograph sits beside the identity grid, as on Brickland's sheet.
  const beside = (html) => (p.photo ? `<table style="width:100%;border-collapse:collapse"><tr><td style="vertical-align:top;padding:0">${html}</td><td style="vertical-align:top;width:100px;padding:0 0 0 12px"><img src="/api/files/${p.photo.id}/download" alt="" style="width:90px;height:110px;object-fit:cover;border:1px solid #d1d5db;border-radius:4px"></td></tr></table>` : html);
  printDocument({
    title: t("emp.prof.print_title"),
    subtitle: `${p.name} · ${p.code} · ${o("status", p.status)}`,
    blocks: [
      { title: t("emp.prof.pr.identity"), html: beside(grid([[f("code"), p.code], [f("card_no"), p.card_no], [f("name"), p.name], [f("name_bn"), p.name_bn], [f("father_name"), p.father_name], [f("mother_name"), p.mother_name], [f("spouse_name"), p.spouse_name], [f("date_of_birth"), dmy(p.date_of_birth)], [f("nid"), p.nid], [f("birth_certificate_no"), p.birth_certificate_no], [f("nationality"), p.nationality], [f("religion"), o("religion", p.religion)], [f("gender"), o("gender", p.gender)], [f("marital_status"), o("marital", p.marital_status)], [f("blood_group"), o("blood", p.blood_group)], [f("child_count"), p.child_count]])) },
      { title: t("emp.prof.pr.job"), html: grid([[f("company_id"), p.company], [f("factory_id"), p.office], [f("department_id"), p.department], [f("section_id"), p.section], [f("designation_id"), p.designation], [f("business_type"), p.business_type], [f("building_id"), p.building], [f("floor_id"), p.floor], [f("line_id"), p.line], [f("joining_date"), dmy(p.joining_date)], [f("gross_salary"), taka(p.gross_salary)], [f("status"), o("status", p.status)]]) },
      { title: t("emp.prof.pr.contact"), html: grid([[f("mobile"), p.mobile], [f("home_phone"), p.home_phone], [f("email"), p.email], [f("tin"), p.tin], [f("higher_education"), p.higher_education], [f("present_address"), p.present_address], [f("permanent_address"), p.permanent_address], [f("emergency_name"), [p.emergency_name, p.emergency_mobile].filter(Boolean).join(" · ")]]) },
    ],
    sections: [
      {
        title: t("emp.prof.pr.allowance"),
        columns: [{ label: t("emp.prof.col.element") }, { label: t("emp.prof.col.type") }, { label: t("emp.prof.col.core"), align: "center" }, { label: t("emp.prof.col.amount"), align: "right" }, { label: t("emp.prof.col.active"), align: "center" }, { label: t("emp.prof.col.effect"), align: "center" }],
        rows: p.pay_lines.map((l) => [l.element, t(`emp.prof.opt.line.${l.type}`), l.core_salary ? "✓" : "", taka(l.amount), l.active ? "✓" : "", l.effect_salary ? "✓" : ""]),
        empty: t("emp.prof.pr.no_lines"),
        note: t("emp.prof.pr.net", { n: taka(p.net_lines) }),
      },
    ],
    signatures: [t("emp.prof.pr.authorised")],
  });
}

function printServiceBook(t, p, history) {
  const f = (k) => t(`emp.prof.f.${k}`);
  const assignments = [...(history?.assignments ?? [])].reverse();
  const salaries = [...(history?.salaries ?? [])].reverse();
  printDocument({
    title: t("emp.prof.sb_title"),
    subtitle: `${p.name} · ${p.code} · ${t(`emp.prof.opt.status.${p.status}`)}`,
    blocks: [{ title: t("emp.prof.pr.service"), html: grid([[f("code"), p.code], [f("name"), p.name], [f("father_name"), p.father_name], [f("date_of_birth"), dmy(p.date_of_birth)], [f("nid"), p.nid], [f("joining_date"), dmy(p.joining_date)], [f("company_id"), p.company], [f("factory_id"), p.office], [f("department_id"), p.department], [f("section_id"), p.section], [t("emp.prof.pr.present_designation"), p.designation], [t("emp.prof.pr.present_gross"), taka(p.gross_salary)]]) }],
    sections: [
      {
        title: t("emp.prof.pr.postings"),
        columns: [{ label: t("emp.prof.pr.from") }, { label: t("emp.prof.pr.to") }, { label: f("department_id") }, { label: f("section_id") }, { label: f("designation_id") }, { label: f("line_id") }, { label: t("emp.prof.pr.reason") }],
        rows: assignments.map((a) => [dmy(a.from), a.to ? dmy(a.to) : t("emp.prof.pr.current"), dash(a.department), dash(a.section), dash(a.designation), dash(a.line), dash(a.reason)]),
        empty: t("emp.prof.pr.no_postings"),
      },
      {
        title: t("emp.prof.pr.increments"),
        columns: [{ label: t("emp.prof.pr.from") }, { label: t("emp.prof.pr.structure") }, { label: t("emp.prof.pr.gross"), align: "right" }, { label: t("emp.prof.pr.change"), align: "right" }],
        rows: salaries.map((s, i) => [dmy(s.from), dash(s.structure), taka(s.gross_a), i ? taka(s.gross_a - salaries[i - 1].gross_a) : "—"]),
        empty: t("emp.prof.pr.no_increments"),
      },
    ],
    signatures: [t("emp.prof.pr.employee_sign"), t("emp.prof.pr.hr_sign")],
  });
}

/** Records: the Search panel and the roster with a filter under every column. */
function Records({ t, rows, options, canManage, onSearch, searching, onEdit, onNew, onPrint, onDelete }) {
  const w = (k, p) => t(`emp.prof.${k}`, p);
  const [q, setQ] = useState({ department: "", section: "", status: "", code: "" });
  const [needle, setNeedle] = useState("");
  const [cols, setCols] = useState({});
  const [perPage, setPerPage] = useState(10);
  const [page, setPage] = useState(1);
  const shown = useMemo(
    () =>
      rows
        .map((r) => ({ ...r, assignment: w(r.assigned ? "assigned" : "pending"), status_label: w(`opt.status.${r.status}`) }))
        .filter((r) => (!needle || COLUMNS.some((c) => String(c === "status" ? r.status_label : (r[c] ?? "")).toLowerCase().includes(needle.toLowerCase()))) && COLUMNS.every((c) => !cols[c] || String(c === "status" ? r.status_label : (r[c] ?? "")).toLowerCase().includes(cols[c].toLowerCase()))),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [rows, needle, cols],
  );
  const pages = Math.max(1, Math.ceil(shown.length / perPage));
  const slice = shown.slice((Math.min(page, pages) - 1) * perPage, Math.min(page, pages) * perPage);
  const sections = options.sections.filter((s) => !q.department || s.department_id === q.department);
  const exportCsv = () => {
    const text = [COLUMNS.map((c) => w(`col.${c}`)), ...shown.map((r) => COLUMNS.map((c) => (c === "status" ? r.status_label : dash(r[c]))))].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${text}`], { type: "text/csv;charset=utf-8" }));
    link.download = "employee-profiles.csv";
    link.click();
  };
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h3 className="flex items-center gap-2 text-[15px] font-semibold">
          <Search className="size-4" /> {w("search")}
        </h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("search_note")}</p>
        <form
          className="grid gap-4 md:grid-cols-3"
          onSubmit={(e) => {
            e.preventDefault();
            setPage(1);
            onSearch(q);
          }}
        >
          <Field label={w("f.department_id")}>
            <Select value={q.department} onValueChange={(v) => setQ({ ...q, department: v ?? "", section: "" })} options={[{ value: "", label: w("all") }, ...options.departments]} placeholder={w("all")} aria-label={w("f.department_id")} />
          </Field>
          <Field label={w("f.section_id")}>
            <Select value={q.section} onValueChange={(v) => setQ({ ...q, section: v ?? "" })} options={[{ value: "", label: w("all") }, ...sections]} placeholder={w("all")} aria-label={w("f.section_id")} />
          </Field>
          <Field label={w("col.status")}>
            <Select value={q.status} onValueChange={(v) => setQ({ ...q, status: v ?? "" })} options={[{ value: "", label: w("all") }, ...options.statuses.map((s) => ({ value: s, label: w(`opt.status.${s}`) }))]} placeholder={w("all")} aria-label={w("col.status")} />
          </Field>
          <Field label={w("f.code")} hint={w("code_hint")}>
            <Input value={q.code} onChange={(e) => setQ({ ...q, code: e.target.value })} placeholder="EMP-000001" aria-label={w("f.code")} />
          </Field>
          <div className="flex items-end justify-between gap-2 md:col-span-3">
            <Button type="submit" loading={searching}>
              <Search /> {w("search")}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                const blank = { department: "", section: "", status: "", code: "" };
                setQ(blank);
                setCols({});
                setNeedle("");
                onSearch(blank);
              }}
            >
              <RotateCcw /> {w("reset")}
            </Button>
          </div>
        </form>
      </section>
      <section className={card}>
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
          <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <Input className="pl-9" type="search" value={needle} onChange={(e) => (setNeedle(e.target.value), setPage(1))} placeholder={w("search_records", { n: rows.length })} aria-label={w("search_records", { n: rows.length })} />
          </div>
          <div className="flex gap-2">
            {canManage ? (
              <Button variant="outline" onClick={onNew}>
                <Plus /> {w("new")}
              </Button>
            ) : null}
            <Button variant="outline" onClick={exportCsv} disabled={!shown.length}>
              <Download /> {w("export")}
            </Button>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1300px] text-sm">
            <thead>
              <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                {COLUMNS.map((c) => (
                  <th key={c} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                    {w(`col.${c}`)}
                  </th>
                ))}
                {/* Action stays in view at the right edge while the wide table scrolls. */}
                <th className="sticky right-0 bg-brand px-3 py-2.5 text-right font-semibold whitespace-nowrap shadow-[-6px_0_6px_-6px_rgba(0,0,0,0.25)]">{w("col.action")}</th>
              </tr>
              <tr className="border-b border-border bg-surface-muted">
                {COLUMNS.map((c) => (
                  <th key={c} className="px-2 py-1.5">
                    <Input className="h-8 text-xs" value={cols[c] ?? ""} onChange={(e) => (setCols({ ...cols, [c]: e.target.value }), setPage(1))} aria-label={w("filter_by", { col: w(`col.${c}`) })} />
                  </th>
                ))}
                <th className="sticky right-0 bg-surface-muted" />
              </tr>
            </thead>
            <tbody>
              {slice.length ? (
                slice.map((r) => (
                  <tr key={r.code} className="group border-b border-border last:border-0 hover:bg-surface-muted">
                    <td className="px-3 py-2.5 font-mono text-xs whitespace-nowrap">{r.code}</td>
                    <td className="px-3 py-2.5 font-medium">{r.name}</td>
                    {["section", "designation", "floor", "line", "grade", "shift", "assignment", "business_type"].map((c) => (
                      <td key={c} className="px-3 py-2.5 whitespace-nowrap">
                        {dash(r[c])}
                      </td>
                    ))}
                    <td className="px-3 py-2.5">
                      <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", r.status === "ACTIVE" ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted")}>{r.status_label}</span>
                    </td>
                    <td className="sticky right-0 bg-surface px-3 py-2 shadow-[-6px_0_6px_-6px_rgba(0,0,0,0.25)] group-hover:bg-surface-muted">
                      <div className="flex justify-end gap-1.5 whitespace-nowrap">
                        <Button size="sm" variant="outline" onClick={() => onPrint(r, "profile")} aria-label={`${w("print")} ${r.code}`}>
                          <Printer /> {w("print")}
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => onPrint(r, "sb")} aria-label={`${w("sb_print")} ${r.code}`}>
                          <Printer /> {w("sb_print")}
                        </Button>
                        {canManage ? (
                          <>
                            <Button size="sm" variant="outline" onClick={() => onEdit(r)} aria-label={`${w("edit")} ${r.code}`}>
                              <Pencil /> {w("edit")}
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => onDelete(r)} aria-label={`${w("delete")} ${r.code}`}>
                              <Trash2 className="text-danger" />
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={COLUMNS.length + 1} className="px-5 py-10 text-center text-sm text-foreground-muted">
                    {w("empty")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
          <span>{w("found", { n: shown.length })}</span>
          <div className="flex items-center gap-2 whitespace-nowrap">
            <span>{w("per_page")}</span>
            <Select className="w-20" value={String(perPage)} onValueChange={(v) => (setPerPage(Number(v)), setPage(1))} options={PAGES.map((n) => ({ value: String(n), label: String(n) }))} aria-label={w("per_page")} />
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>
              <ArrowLeft />
            </Button>
            <span className="tabular-nums">
              {Math.min(page, pages)} / {pages}
            </span>
            <Button size="sm" variant="outline" disabled={page >= pages} onClick={() => setPage(page + 1)}>
              <ArrowRight />
            </Button>
          </div>
        </div>
      </section>
    </div>
  );
}

/** New employee / Edit employee: the pinned header, seven tabs, one save. */
function ProfileForm({ t, options, extra, initial, act, onSaved, onClose, onNew }) {
  const w = (k, p) => t(`emp.prof.${k}`, p);
  const toastManager = useToastManager();
  const [form, setForm] = useState(() => (initial ? toForm(initial, options, extra) : blankForm(options, extra)));
  const [tab, setTab] = useState("personal");
  const [errors, setErrors] = useState({});
  const [photo, setPhoto] = useState(initial?.photo ?? null);
  const [pendingPhoto, setPendingPhoto] = useState(null);
  const [pending, startTransition] = useTransition();
  const fileRef = useRef(null);
  const editing = Boolean(initial);
  const set = (k, v) => {
    setForm((f) => {
      const next = { ...f, [k]: v };
      if (k === "factory_id") Object.assign(next, { building_id: "", floor_id: "", line_id: "" });
      if (k === "building_id") Object.assign(next, { floor_id: "", line_id: "" });
      if (k === "floor_id") next.line_id = "";
      if (k === "department_id") Object.assign(next, { section_id: "", designation_id: "", line_id: "" });
      return next;
    });
    if (errors[k]) setErrors((e) => ({ ...e, [k]: undefined }));
  };
  const err = (k) => errors[k]?.[0];
  const choices = {
    guardian_relation: extra.relations.map((v) => ({ value: v, label: w(`opt.relation.${v}`) })),
    emergency_relation: extra.relations.map((v) => ({ value: v, label: w(`opt.relation.${v}`) })),
    spouse_relation: extra.relations.map((v) => ({ value: v, label: w(`opt.relation.${v}`) })),
    religion: extra.religions.map((v) => ({ value: v, label: w(`opt.religion.${v}`) })),
    gender: extra.genders.map((v) => ({ value: v, label: w(`opt.gender.${v}`) })),
    marital_status: extra.marital.map((v) => ({ value: v, label: w(`opt.marital.${v}`) })),
    blood_group: extra.blood.map((v) => ({ value: v, label: w(`opt.blood.${v}`) })),
    status: extra.statuses.map((v) => ({ value: v, label: w(`opt.status.${v}`) })),
    nationality: extra.nationalities,
    employment_type_id: extra.employee_types,
    department_id: options.departments,
    section_id: options.sections.filter((s) => !form.department_id || s.department_id === form.department_id),
    designation_id: options.designations.filter((d) => !form.department_id || d.department_id === form.department_id),
    building_id: options.buildings.filter((b) => !form.factory_id || b.factory_id === form.factory_id),
    floor_id: options.floors.filter((x) => !form.building_id || x.building_id === form.building_id),
    line_id: options.lines.filter((l) => (form.floor_id ? l.floor_id === form.floor_id : form.section_id ? l.section_id === form.section_id : form.department_id ? l.department_id === form.department_id : true)),
    grade_id: options.grades,
    shift_id: options.shifts,
  };
  const control = (k) => {
    const label = w(`f.${k}`);
    if (CHECKS.includes(k))
      return (
        <label key={k} className="flex items-center gap-2 self-end pb-2 text-sm font-semibold">
          <Checkbox checked={Boolean(form[k])} onCheckedChange={(v) => set(k, v === true)} aria-label={label} /> {label}
        </label>
      );
    const input =
      k in choices ? (
        <Select value={form[k] ?? ""} onValueChange={(v) => set(k, v ?? "")} options={[{ value: "", label: w("select") }, ...choices[k]]} placeholder={w("select")} aria-label={label} />
      ) : WIDE.includes(k) ? (
        <Textarea rows={2} value={form[k] ?? ""} onChange={(e) => set(k, e.target.value)} aria-label={label} />
      ) : (
        <Input
          type={DATES.includes(k) ? "date" : ["child_count", "gross_salary"].includes(k) ? "number" : k === "email" ? "email" : "text"}
          step={k === "gross_salary" ? "any" : undefined}
          value={form[k] ?? ""}
          onChange={(e) => set(k, e.target.value)}
          placeholder={k === "nid" ? w("nid_hint") : undefined}
          aria-label={label}
        />
      );
    return (
      <Field key={k} label={label} required={REQUIRED.includes(k) || (k === "gross_salary" && !editing)} error={err(k)} wide={WIDE.includes(k)} hint={k === "gross_salary" ? w("gross_hint") : undefined}>
        {input}
      </Field>
    );
  };
  const setLine = (i, k, v) => setForm((f) => ({ ...f, pay_lines: f.pay_lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)) }));
  const setCheck = (i, k, v) => setForm((f) => ({ ...f, checks: f.checks.map((c, j) => (j === i ? { ...c, [k]: v } : c)) }));
  const go = (d) => setTab(TABS[Math.min(TABS.length - 1, Math.max(0, TABS.indexOf(tab) + d))]);

  const uploadPhoto = async (code, file) => {
    const body = new FormData();
    body.append("upload", file);
    const res = await fetch(`/api/hr-payroll/employee-photo?code=${encodeURIComponent(code)}`, { method: "POST", body });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json?.errors?.upload?.[0] ?? json?.message ?? w("photo_failed"));
    return json.data;
  };
  const pickPhoto = (file) => {
    if (!file) return;
    if (!editing) return setPendingPhoto(file);
    startTransition(async () => {
      try {
        const body = await uploadPhoto(initial.code, file);
        setPhoto(body.photo);
        toastManager.add({ title: w("photo_saved"), type: "success" });
      } catch (e) {
        toastManager.add({ title: e.message, type: "danger" });
      }
    });
  };
  const removePhoto = () => {
    if (!editing) return setPendingPhoto(null);
    startTransition(async () => {
      const res = await act("photoRemove", initial.code);
      if (res?.status === "success") setPhoto(null);
    });
  };

  const save = (then) =>
    startTransition(async () => {
      const res = editing ? await act("profileUpdate", initial.code, payload(form)) : await act("profileStore", payload(form));
      if (res?.status !== "success") {
        const e = res?.errors ?? { _: [res?.message ?? ""] };
        setErrors(e);
        const first = Object.keys(e).map(tabOf).find(Boolean);
        if (first) setTab(first);
        return;
      }
      setErrors({});
      let saved = res.data;
      if (pendingPhoto) {
        try {
          saved = await uploadPhoto(saved.code, pendingPhoto);
        } catch (er) {
          toastManager.add({ title: er.message, type: "danger" });
        }
      }
      toastManager.add({ title: w(editing ? "updated" : "saved", { code: saved.code }), type: "success" });
      onSaved(saved, then);
    });

  const summary = Object.entries(errors).flatMap(([, v]) => v ?? []).filter(Boolean);
  const photoSrc = pendingPhoto ? URL.createObjectURL(pendingPhoto) : photo ? `/api/files/${photo.id}/download` : null;
  return (
    <div className="flex flex-col gap-4 pb-24">
      <section className={cn(card, "p-5")}>
        <h3 className="flex items-center gap-2 text-[15px] font-semibold">
          {editing ? <Pencil className="size-4" /> : <Plus className="size-4" />} {w(editing ? "edit_title" : "new")}
        </h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{editing ? w("edit_note", { code: initial.code }) : w("new_note")}</p>
        <div className="grid gap-4 lg:grid-cols-[1fr_8rem]">
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Field label={w("f.code")} required hint={editing ? null : w("code_auto")}>
              <Input value={editing ? initial.code : extra.next_code} disabled readOnly aria-label={w("f.code")} />
            </Field>
            <Field label={w("f.card_no")} required hint={w("card_hint")} error={err("card_no")}>
              <Input value={form.card_no} onChange={(e) => set("card_no", e.target.value)} aria-label={w("f.card_no")} />
            </Field>
            <Field label={w("f.company_id")} required>
              <Select value={extra.companies[0]?.value ?? ""} disabled options={extra.companies} aria-label={w("f.company_id")} />
            </Field>
            <Field label={w("f.factory_id")} required error={err("factory_id")}>
              <Select value={form.factory_id} onValueChange={(v) => set("factory_id", v ?? "")} options={[{ value: "", label: w("select") }, ...options.factories]} placeholder={w("select")} aria-label={w("f.factory_id")} />
            </Field>
          </div>
          <div className="flex h-32 items-center justify-center overflow-hidden rounded-lg border border-border bg-surface-muted">
            {photoSrc ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={photoSrc} alt={w("f.photo")} className="size-full object-cover" />
            ) : (
              <span className="flex flex-col items-center gap-1 text-xs text-foreground-muted">
                <ImageOff className="size-5" /> {w("no_photo")}
              </span>
            )}
          </div>
        </div>
      </section>

      <div className="flex flex-wrap gap-1 border-b border-border" role="tablist">
        {TABS.map((k) => (
          <button key={k} type="button" role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={cn("-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors", tab === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
            {w(`tab.${k}`)}
          </button>
        ))}
      </div>

      {summary.length ? <Alert variant="danger">{summary.join(" · ")}</Alert> : null}

      {FIELDS_OF[tab] ? (
        <section className={cn(card, "p-5")}>
          {["job", "contact", "document"].includes(tab) ? <p className="mb-4 text-[13px] text-foreground-muted">{w(`tab_note.${tab}`)}</p> : null}
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {FIELDS_OF[tab].map(control)}
            {tab === "document" ? (
              <Field label={w("f.photo")} hint={w("photo_hint")} error={err("upload")}>
                <div className="flex gap-2">
                  <Input value={pendingPhoto?.name ?? photo?.name ?? ""} placeholder={w("photo_placeholder")} readOnly aria-label={w("f.photo")} />
                  <input ref={fileRef} type="file" accept="image/jpeg,image/png" className="hidden" onChange={(e) => (pickPhoto(e.target.files?.[0]), (e.target.value = ""))} />
                  <Button type="button" variant="outline" onClick={() => fileRef.current?.click()} loading={pending && editing}>
                    <Paperclip /> {w("attach")}
                  </Button>
                  {photo || pendingPhoto ? (
                    <Button type="button" variant="outline" onClick={removePhoto} aria-label={w("remove_photo")}>
                      <X />
                    </Button>
                  ) : null}
                </div>
              </Field>
            ) : null}
          </div>
        </section>
      ) : null}

      {tab === "allowance" ? (
        <section className={cn(card, "p-5")}>
          <h3 className="text-[15px] font-semibold">{w("ad.title")}</h3>
          <p className="mb-4 text-[13px] text-foreground-muted">{w("ad.note")}</p>
          <div className="mb-4 grid gap-4 md:grid-cols-2">
            <Field label={w("f.employee")} required>
              <Input value={editing ? `${initial.name} (${initial.code})` : form.name || w("ad.this_employee")} disabled readOnly aria-label={w("f.employee")} />
            </Field>
            {control("gross_salary")}
          </div>
          <h4 className="text-sm font-semibold">{w("ad.lines")}</h4>
          <p className="mb-2 text-xs text-foreground-muted">{w("ad.lines_note")}</p>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full min-w-[900px] text-sm">
              <thead>
                <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                  <th className="px-3 py-2 font-semibold">{w("col.element")}</th>
                  <th className="px-3 py-2 font-semibold">{w("col.type")}</th>
                  <th className="px-3 py-2 text-center font-semibold">{w("col.core")}</th>
                  <th className="px-3 py-2 text-right font-semibold">{w("col.amount")}</th>
                  <th className="px-3 py-2 text-center font-semibold">{w("col.active")}</th>
                  <th className="px-3 py-2 text-center font-semibold">{w("col.effect")}</th>
                  <th className="w-12" />
                </tr>
              </thead>
              <tbody>
                {form.pay_lines.length ? (
                  form.pay_lines.map((l, i) => (
                    <tr key={l._k} className="border-b border-border last:border-0">
                      <td className="px-2 py-1.5">
                        <Select value={l.pay_element_id ?? ""} onValueChange={(v) => setLine(i, "pay_element_id", v ?? "")} options={extra.pay_elements} placeholder={w("select")} aria-label={`${w("col.element")} ${i + 1}`} />
                        {errors[`pay_lines.${i}.pay_element_id`] ? <span className="text-xs text-danger">{errors[`pay_lines.${i}.pay_element_id`][0]}</span> : null}
                      </td>
                      <td className="px-2 py-1.5">
                        <Select value={l.type} onValueChange={(v) => setLine(i, "type", v ?? "ALLOWANCE")} options={["ALLOWANCE", "DEDUCTION"].map((v) => ({ value: v, label: w(`opt.line.${v}`) }))} aria-label={`${w("col.type")} ${i + 1}`} />
                      </td>
                      {["core_salary"].map((k) => (
                        <td key={k} className="px-2 py-1.5 text-center">
                          <Checkbox checked={!!l[k]} onCheckedChange={(v) => setLine(i, k, v === true)} aria-label={`${w("col.core")} ${i + 1}`} />
                        </td>
                      ))}
                      <td className="px-2 py-1.5">
                        <Input type="number" step="any" className="text-right" value={l.amount} onChange={(e) => setLine(i, "amount", e.target.value)} aria-label={`${w("col.amount")} ${i + 1}`} />
                      </td>
                      {[
                        ["active", "col.active"],
                        ["effect_salary", "col.effect"],
                      ].map(([k, lab]) => (
                        <td key={k} className="px-2 py-1.5 text-center">
                          <Checkbox checked={!!l[k]} onCheckedChange={(v) => setLine(i, k, v === true)} aria-label={`${w(lab)} ${i + 1}`} />
                        </td>
                      ))}
                      <td className="px-2 py-1.5 text-center">
                        <Button size="sm" variant="ghost" onClick={() => setForm((f) => ({ ...f, pay_lines: f.pay_lines.filter((_, j) => j !== i) }))} aria-label={`${w("remove_line")} ${i + 1}`}>
                          <Trash2 className="text-danger" />
                        </Button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={7} className="px-4 py-6 text-center text-[13px] text-foreground-muted">
                      {w("ad.empty")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
            <Button variant="outline" onClick={() => setForm((f) => ({ ...f, pay_lines: [...f.pay_lines, { _k: uid(), pay_element_id: "", type: "ALLOWANCE", core_salary: false, amount: "", active: true, effect_salary: true }] }))}>
              <Plus /> {w("add")}
            </Button>
            <span className="text-[13px] text-foreground-muted">
              {w("ad.net", { n: taka(form.pay_lines.filter((l) => l.active && l.effect_salary).reduce((a, l) => a + (l.type === "DEDUCTION" ? -1 : 1) * Number(l.amount || 0), 0)) })}
            </span>
          </div>
          <p className="mt-3 text-xs text-foreground-muted">{w("ad.save_note")}</p>
        </section>
      ) : null}

      {tab === "background" ? (
        <section className={cn(card, "p-5")}>
          <h3 className="text-[15px] font-semibold">{w("bg.title")}</h3>
          <p className="mb-4 text-[13px] text-foreground-muted">{w("bg.note")}</p>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full min-w-[900px] text-sm">
              <thead>
                <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                  <th className="px-3 py-2 font-semibold">
                    {w("col.check_type")} <span className="text-brand-foreground/80">*</span>
                  </th>
                  <th className="px-3 py-2 text-center font-semibold">{w("col.cleared")}</th>
                  <th className="px-3 py-2 font-semibold">{w("col.verified_by")}</th>
                  <th className="px-3 py-2 font-semibold">{w("col.verified_on")}</th>
                  <th className="px-3 py-2 font-semibold">{w("col.remarks")}</th>
                  <th className="w-12" />
                </tr>
              </thead>
              <tbody>
                {form.checks.length ? (
                  form.checks.map((c, i) => (
                    <tr key={c._k} className="border-b border-border last:border-0">
                      <td className="px-2 py-1.5">
                        <Input value={c.check_type ?? ""} maxLength={120} placeholder={w("bg.placeholder")} onChange={(e) => setCheck(i, "check_type", e.target.value)} aria-label={`${w("col.check_type")} ${i + 1}`} />
                        {errors[`checks.${i}.check_type`] ? <span className="text-xs text-danger">{errors[`checks.${i}.check_type`][0]}</span> : null}
                      </td>
                      <td className="px-2 py-1.5 text-center">
                        <Checkbox checked={!!c.cleared} onCheckedChange={(v) => setCheck(i, "cleared", v === true)} aria-label={`${w("col.cleared")} ${i + 1}`} />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input value={c.verified_by ?? ""} maxLength={160} onChange={(e) => setCheck(i, "verified_by", e.target.value)} aria-label={`${w("col.verified_by")} ${i + 1}`} />
                      </td>
                      <td className="px-3 py-1.5 whitespace-nowrap text-foreground-muted">{c.verified_on ? dmy(c.verified_on) : w("bg.on_save")}</td>
                      <td className="px-2 py-1.5">
                        <Input value={c.remarks ?? ""} maxLength={400} onChange={(e) => setCheck(i, "remarks", e.target.value)} aria-label={`${w("col.remarks")} ${i + 1}`} />
                      </td>
                      <td className="px-2 py-1.5 text-center">
                        <Button size="sm" variant="ghost" onClick={() => setForm((f) => ({ ...f, checks: f.checks.filter((_, j) => j !== i) }))} aria-label={`${w("bg.remove")} ${i + 1}`}>
                          <Trash2 className="text-danger" />
                        </Button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-4 py-6 text-center text-[13px] text-foreground-muted">
                      {w("bg.empty")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="mt-3">
            <Button variant="outline" onClick={() => setForm((f) => ({ ...f, checks: [...f.checks, { _k: uid(), check_type: "", verified_by: "", verified_on: null, cleared: false, remarks: "" }] }))}>
              <Plus /> {w("bg.add")}
            </Button>
          </div>
          <p className="mt-3 text-xs text-foreground-muted">{w("bg.footnote")}</p>
        </section>
      ) : null}

      <p className="text-xs text-foreground-muted">{w("form_note")}</p>

      <div className={cn(card, "sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-2 px-4 py-3 shadow-lg")}>
        <div className="flex gap-2">
          {tab !== TABS[0] ? (
            <Button variant="outline" onClick={() => go(-1)}>
              <ArrowLeft /> {w("back")}
            </Button>
          ) : null}
          {tab !== TABS[TABS.length - 1] ? (
            <Button variant="outline" onClick={() => go(1)}>
              {w("continue")} <ArrowRight />
            </Button>
          ) : null}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={onClose}>
            <X /> {w("cancel")}
          </Button>
          <Button onClick={() => save("list")} loading={pending}>
            <Save /> {w(editing ? "update" : "save")}
          </Button>
          <Button variant="outline" onClick={onNew}>
            <Plus /> {w("add_new")}
          </Button>
          <Button variant="outline" onClick={onClose}>
            <ArrowLeft /> {w("back_to_list")}
          </Button>
        </div>
      </div>
    </div>
  );
}

/**
 * Employee Profile.
 *
 * @param {{ rows: object[], options: object, extra: object, initial: object|null, canManage: boolean, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function EmployeeProfile({ rows: initialRows, options, extra, initial, canManage, act }) {
  const t = useT("hrpayroll");
  const w = (k, p) => t(`emp.prof.${k}`, p);
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initialRows);
  const [view, setView] = useState(initial ? "form" : "records");
  const [editing, setEditing] = useState(initial);
  const [formKey, setFormKey] = useState(initial?.code ?? "new");
  const [query, setQuery] = useState({});
  const [asking, setAsking] = useState(null);
  const [searching, startSearch] = useTransition();
  const [busy, startBusy] = useTransition();

  const reload = (q = query) =>
    startSearch(async () => {
      const res = await act("profiles", Object.fromEntries(Object.entries(q).filter(([, v]) => v)));
      if (res?.status === "success") setRows(res.data);
    });
  const open = (r) =>
    startBusy(async () => {
      const res = await act("profile", r.code);
      if (res?.status !== "success") return toastManager.add({ title: res?.message ?? "", type: "danger" });
      setEditing(res.data);
      setFormKey(`${res.data.code}-${Date.now()}`);
      setView("form");
    });
  const fresh = () => {
    setEditing(null);
    setFormKey(`new-${Date.now()}`);
    setView("form");
  };
  const print = (r, kind) =>
    startBusy(async () => {
      const res = await act("profile", r.code);
      if (res?.status !== "success") return;
      if (kind === "profile") return printProfile(t, res.data);
      const h = await act("history", r.code);
      printServiceBook(t, res.data, h?.data);
    });
  const remove = () =>
    startBusy(async () => {
      const res = await act("profileDelete", asking.code);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: w("deleted", { code: asking.code }), type: "success" });
        setRows((rs) => rs.filter((x) => x.code !== asking.code));
      } else toastManager.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "", type: "danger" });
    });

  const tabs = [
    ["records", w("records"), rows.length],
    ["form", w(editing ? "edit_title" : "new")],
  ];
  return (
    <div className={cn("flex flex-col gap-4", busy && "cursor-progress")}>
      <div className="flex flex-wrap gap-1 border-b border-border" role="tablist">
        {tabs
          .filter(([k]) => k === "records" || canManage || view === "form")
          .map(([k, label, count]) => (
            <button
              key={k}
              type="button"
              role="tab"
              aria-selected={view === k}
              onClick={() => (k === "form" && !editing && view !== "form" ? fresh() : setView(k))}
              className={cn("-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors", view === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}
            >
              {k === "form" ? editing ? <Pencil className="size-4" /> : <Plus className="size-4" /> : null}
              {label}
              {count !== undefined ? <span className="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] tabular-nums">{count}</span> : null}
            </button>
          ))}
      </div>
      <div hidden={view !== "records"}>
        <Records
          t={t}
          rows={rows}
          options={options}
          canManage={canManage}
          searching={searching}
          onSearch={(q) => (setQuery(q), reload(q))}
          onEdit={open}
          onNew={fresh}
          onPrint={print}
          onDelete={setAsking}
        />
      </div>
      {view === "form" ? (
        <ProfileForm
          key={formKey}
          t={t}
          options={options}
          extra={extra}
          initial={editing}
          act={act}
          onNew={fresh}
          onClose={() => {
            setEditing(null);
            setView("records");
          }}
          onSaved={() => {
            setEditing(null);
            setView("records");
            reload();
          }}
        />
      ) : null}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={w("delete_title", { code: asking?.code ?? "" })}
        description={w("delete_confirm")}
        confirmLabel={w("delete")}
        cancelLabel={w("keep")}
        destructive
        loading={busy}
        onConfirm={remove}
      />
    </div>
  );
}

export { EmployeeProfile };
